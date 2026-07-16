<?php

namespace App\Services\AiAssistant;

use App\Models\Article;
use App\Models\Page;
use App\Services\AiAssistant\Contracts\AiProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * ساخت پرامپت برای یک فیلد/حالت مشخص، فراخوانی ارائه‌دهنده‌ی هوش مصنوعی، و تبدیل پاسخ خام به
 * شکل موردانتظار (متن ساده / لیست / جفت پرسش‌وپاسخ). هرگز روی رکورد نمی‌نویسد — فقط نتیجه را
 * برمی‌گرداند؛ نوشتن روی رکورد فقط با کلیک صریح ادمین روی «Apply» در صفحه‌ی دستیار انجام می‌شود.
 */
class ContentAssistantService
{
    private const MAX_BODY_CHARS = 6000;

    private const MAX_BODY_HTML_CHARS = 12000;

    public function __construct(
        private readonly AiProvider $provider,
        private readonly ContentReviewService $reviewService,
    ) {}

    /**
     * @return array{result: mixed, warnings: string[]}
     */
    public function generate(Model $record, string $field, string $mode, array $options = []): array
    {
        $definition = ActionRegistry::for($field);
        $modelType = $record instanceof Article ? 'Article' : 'Page';

        if (! in_array($modelType, $definition['applicable_to'], true)) {
            throw new \InvalidArgumentException("Field '{$field}' does not apply to {$modelType}.");
        }

        if (! in_array($mode, $definition['modes'], true)) {
            throw new \InvalidArgumentException("Mode '{$mode}' is not supported for the '{$field}' field.");
        }

        $raw = $this->provider->respond(
            $this->buildSystemPrompt($definition, $mode),
            $this->buildUserPrompt($record, $field, $definition),
            $this->imagesFor($record, $field),
            array_merge(['max_tokens' => $definition['max_tokens'] ?? 2048], $options),
        );

        return $this->parseResponse($raw, $definition['response_shape']);
    }

    /**
     * پیام آزادِ چت را به یک عمل مشخص طبقه‌بندی می‌کند — برای App\Jobs\ProcessAiChatMessage.
     * هرگز خودش چیزی تولید/اعمال نمی‌کند؛ فقط تشخیص می‌دهد که پیام باید کدام فیلد/حالتِ همین
     * ActionRegistry را صف کند (intent=action)، یک ترجمه‌ی کامل بخواهد (intent=translate)، یا صرفاً
     * یک پاسخ گفتگویی است (intent=chat) — سپس همان مسیرهای موجودِ generateField/ترجمه فراخوانی
     * می‌شوند، نه یک مسیر تولید جدید.
     *
     * @return array{intent: 'action'|'translate'|'chat', field: ?string, mode: ?string, target_locale: ?string, reply: string}
     */
    public function classifyIntent(Model $record, string $message): array
    {
        $modelType = $record instanceof Article ? 'Article' : 'Page';

        $raw = $this->provider->respond(
            $this->buildIntentSystemPrompt($modelType),
            $this->buildIntentUserPrompt($record, $message),
            [],
            ['max_tokens' => 400],
        );

        return $this->parseIntentResponse($raw, $modelType);
    }

    private function buildIntentSystemPrompt(string $modelType): string
    {
        $fieldList = collect(ActionRegistry::applicableTo($modelType))
            ->reject(fn (array $definition, string $key) => $key === 'content_review_summary')
            ->map(fn (array $definition, string $key) => "- {$key} (\"{$definition['label']}\") — modes: ".implode(', ', $definition['modes']))
            ->implode("\n");

        return <<<PROMPT
            You are an AI writing assistant embedded in the editor for a Brazilian Jiu-Jitsu / self-defense training website (bilingual: English and Turkish). The user just sent a chat message about the content they are currently editing. Classify their intent and respond with ONLY a JSON object (no markdown fences, no other text) with these keys:
            - "intent": one of "action", "translate", "chat"
            - "field": if intent is "action", the exact field key from the list below (else null)
            - "mode": if intent is "action", one of that field's allowed modes exactly as listed (else null)
            - "target_locale": if intent is "translate", "en" or "tr" (else null)
            - "reply": a short, friendly 1-2 sentence reply — acknowledge what you're about to do for "action"/"translate", or directly answer the question for "chat"

            Available fields and their modes:
            {$fieldList}

            Examples: "Generate 5 FAQs" -> field=faq, mode=generate. "Improve the introduction" -> field=body, mode=improve. "Rewrite the conclusion" -> field=body, mode=rewrite. "Make it shorter" -> field=body, mode=shorten. "Improve readability" -> field=body, mode=improve. "Make it more persuasive" -> field=body, mode=improve. "Write a better CTA" -> field=cta, mode=improve. "Generate SEO title" -> field=seo_title, mode=generate. "Translate to Turkish" -> intent=translate, target_locale=tr. "Translate to English" -> intent=translate, target_locale=en. If the message doesn't map to a specific field/action (a general question, a compliment, an unclear request, or something this assistant can't do), use intent="chat" and answer directly in "reply".
            PROMPT;
    }

    private function buildIntentUserPrompt(Model $record, string $message): string
    {
        $lines = [
            'Locale: '.($record->locale ?? 'en'),
            'Title: '.($record->title ?? ''),
        ];

        $body = trim(strip_tags((string) $record->body));

        if ($body !== '') {
            $lines[] = 'Content (excerpt): '.mb_substr($body, 0, 2000);
        }

        $lines[] = 'User message: '.$message;

        return implode("\n\n", $lines);
    }

    /**
     * @return array{intent: 'action'|'translate'|'chat', field: ?string, mode: ?string, target_locale: ?string, reply: string}
     */
    private function parseIntentResponse(string $raw, string $modelType): array
    {
        $stripped = preg_replace('/^```(json)?|```$/m', '', trim($raw));
        $decoded = json_decode(trim($stripped), true);

        if (! is_array($decoded) || ! isset($decoded['intent'])) {
            return [
                'intent' => 'chat',
                'field' => null,
                'mode' => null,
                'target_locale' => null,
                'reply' => trim($raw) ?: "Sorry, I couldn't understand that — could you rephrase?",
            ];
        }

        $intent = in_array($decoded['intent'], ['action', 'translate', 'chat'], true) ? $decoded['intent'] : 'chat';
        $field = $decoded['field'] ?? null;
        $mode = $decoded['mode'] ?? null;

        if ($intent === 'action') {
            $isValidAction = is_string($field) && ActionRegistry::exists($field)
                && in_array($modelType, ActionRegistry::for($field)['applicable_to'], true)
                && in_array($mode, ActionRegistry::for($field)['modes'], true);

            if (! $isValidAction) {
                $intent = 'chat';
            }
        }

        $targetLocale = in_array($decoded['target_locale'] ?? null, ['en', 'tr'], true) ? $decoded['target_locale'] : null;

        if ($intent === 'translate' && ! $targetLocale) {
            $intent = 'chat';
        }

        $reply = $decoded['reply'] ?? null;

        return [
            'intent' => $intent,
            'field' => $intent === 'action' ? $field : null,
            'mode' => $intent === 'action' ? $mode : null,
            'target_locale' => $intent === 'translate' ? $targetLocale : null,
            'reply' => is_string($reply) && trim($reply) !== '' ? trim($reply) : 'Got it.',
        ];
    }

    private function buildSystemPrompt(array $definition, string $mode): string
    {
        $modeInstruction = match ($mode) {
            'generate' => 'Generate this from scratch based on the content provided.',
            'improve' => 'Improve the current value below — keep what works, fix what doesn\'t.',
            'rewrite' => 'Rewrite the current value below with a fresh approach, same underlying meaning.',
            'expand' => 'Expand the current value below with more useful detail.',
            'shorten' => 'Shorten the current value below while keeping the essential meaning.',
            'simplify' => 'Simplify the current value below into clearer, plainer language.',
        };

        return "You are an SEO and content assistant for a Brazilian Jiu-Jitsu / self-defense training website (bilingual: English and Turkish — always respond in the same language as the content shown to you). {$modeInstruction}\n\n{$definition['instruction']}";
    }

    /**
     * تصاویری که برای فیلدهای بینایی‌محور (ALT/caption) به ارائه‌دهنده فرستاده می‌شوند — بقیه‌ی
     * فیلدها آرایه‌ی خالی می‌گیرند و ارائه‌دهنده آن را نادیده می‌گیرد
     *
     * @return string[]
     */
    private function imagesFor(Model $record, string $field): array
    {
        if (! in_array($field, ['alt_text', 'caption'], true) || blank($record->image_path)) {
            return [];
        }

        return [asset('storage/'.$record->image_path)];
    }

    private function buildUserPrompt(Model $record, string $field, array $definition): string
    {
        if ($field === 'content_review_summary') {
            $findings = $this->reviewService->review($record);

            if ($findings === []) {
                return 'Content review findings: none — everything checked out clean.';
            }

            return "Content review findings:\n".collect($findings)
                ->map(fn (array $f) => '- ('.$f['severity'].') '.$f['message'])
                ->implode("\n");
        }

        if ($field === 'internal_links') {
            return $this->buildInternalLinkCandidatesPrompt($record);
        }

        $lines = [
            'Locale: '.($record->locale ?? 'en'),
            'Title: '.($record->title ?? ''),
        ];

        if ($record instanceof Article && $record->category) {
            $lines[] = 'Category: '.$record->category;
        }

        if ($record instanceof Article && $record->excerpt) {
            $lines[] = 'Excerpt: '.$record->excerpt;
        }

        $keywords = $record->keywords()->pluck('keyword')->all();

        if ($keywords !== []) {
            $lines[] = 'Target keywords: '.implode(', ', $keywords);
        }

        // برای فیلد body خودِ HTML خام نشان داده می‌شود (نه نسخه‌ی strip_tags شده) تا هوش مصنوعی
        // ساختار موجود (heading ها، لیست‌ها) را ببیند و در بازنویسی حفظ کند؛ چون این همان محتوایی
        // است که زیر «Current value» هم می‌آمد، آن خط برای body عمداً حذف می‌شود تا دوبار فرستاده نشود
        if ($field === 'body') {
            $rawBody = trim((string) $record->body);

            if ($rawBody !== '') {
                $lines[] = 'Content (HTML):'."\n".mb_substr($rawBody, 0, self::MAX_BODY_HTML_CHARS);
            }

            return implode("\n\n", $lines);
        }

        $body = trim(strip_tags((string) $record->body));

        if ($body !== '') {
            $lines[] = 'Content:'."\n".mb_substr($body, 0, self::MAX_BODY_CHARS);
        }

        $current = $record->getAttribute($field);

        if (! blank($current)) {
            $lines[] = 'Current value of "'.$definition['label'].'": '.(is_array($current) ? json_encode($current) : $current);
        }

        return implode("\n\n", $lines);
    }

    // فهرست دیگر مقالات/صفحات هم‌زبان (به‌جز خود رکورد) برای اینکه هوش مصنوعی از میان آن‌ها
    // لینک پیشنهاد بدهد — بدون این فهرست، هوش مصنوعی نمی‌داند این سایت واقعاً چه محتوایی دارد
    private function buildInternalLinkCandidatesPrompt(Model $record): string
    {
        $locale = $record->locale ?? 'en';

        $candidates = collect();

        foreach (['Article' => Article::class, 'Page' => Page::class] as $type => $class) {
            $isArticle = $class === Article::class;

            $class::query()
                ->where('locale', $locale)
                ->where('id', '!=', $record instanceof $class ? $record->id : 0)
                ->when($isArticle, fn ($q) => $q->where('status', 'published'))
                ->limit(40)
                ->get($isArticle ? ['id', 'title', 'category'] : ['id', 'title'])
                ->each(function ($item) use (&$candidates, $type, $isArticle) {
                    $candidates->push('id: '.$item->id.', type: '.$type.', title: "'.$item->title.'"'
                        .($isArticle && $item->category ? ', category: '.$item->category : ''));
                });
        }

        if ($candidates->isEmpty()) {
            return 'There is no other published content on this site yet to link to.';
        }

        $body = trim(strip_tags((string) $record->body));

        return "This content's title: {$record->title}\n\n"
            .'This content (excerpt): '.mb_substr($body, 0, 2000)."\n\n"
            ."Other content on this site:\n".$candidates->implode("\n");
    }

    /**
     * @return array{result: mixed, warnings: string[]}
     */
    private function parseResponse(string $raw, string $shape): array
    {
        $raw = trim($raw);

        return match ($shape) {
            'text' => ['result' => trim($raw, "\"' \t\n\r\0\x0B"), 'warnings' => []],
            'html' => ['result' => $this->cleanHtml($raw), 'warnings' => []],
            'list' => $this->parseJsonArray($raw, isAssoc: false),
            'qa_pairs' => $this->parseJsonArray($raw, isAssoc: true),
            'internal_link_suggestions' => $this->parseStructuredArray($raw, ['id', 'type', 'anchor_text', 'reason']),
            'external_link_suggestions' => $this->parseStructuredArray($raw, ['url', 'anchor_text', 'reason']),
        };
    }

    private function cleanHtml(string $raw): string
    {
        return trim(preg_replace('/^```(html)?|```$/m', '', $raw));
    }

    /**
     * @param  string[]  $requiredKeys
     * @return array{result: mixed, warnings: string[]}
     */
    private function parseStructuredArray(string $raw, array $requiredKeys): array
    {
        $stripped = preg_replace('/^```(json)?|```$/m', '', $raw);
        $decoded = json_decode(trim($stripped), true);

        if (! is_array($decoded)) {
            return ['result' => null, 'warnings' => ['The AI response was not valid JSON — raw response: '.mb_substr($raw, 0, 300)]];
        }

        $valid = array_values(array_filter(
            $decoded,
            fn ($item) => is_array($item) && collect($requiredKeys)->every(fn ($key) => array_key_exists($key, $item))
        ));

        if ($valid === []) {
            return ['result' => null, 'warnings' => ['The AI response did not contain any valid suggestions.']];
        }

        return ['result' => $valid, 'warnings' => []];
    }

    /**
     * @return array{result: mixed, warnings: string[]}
     */
    private function parseJsonArray(string $raw, bool $isAssoc): array
    {
        $stripped = preg_replace('/^```(json)?|```$/m', '', $raw);
        $decoded = json_decode(trim($stripped), true);

        if (! is_array($decoded)) {
            return ['result' => null, 'warnings' => ['The AI response was not valid JSON — raw response: '.mb_substr($raw, 0, 300)]];
        }

        if ($isAssoc) {
            $valid = array_values(array_filter($decoded, fn ($item) => is_array($item) && isset($item['question'], $item['answer'])));

            if ($valid === []) {
                return ['result' => null, 'warnings' => ['The AI response did not contain any valid question/answer pairs.']];
            }

            return ['result' => $valid, 'warnings' => []];
        }

        $valid = array_values(array_filter($decoded, fn ($item) => is_string($item) && trim($item) !== ''));

        if ($valid === []) {
            return ['result' => null, 'warnings' => ['The AI response did not contain any usable items.']];
        }

        return ['result' => $valid, 'warnings' => []];
    }
}
