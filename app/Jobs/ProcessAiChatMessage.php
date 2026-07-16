<?php

namespace App\Jobs;

use App\Models\AiChatMessage;
use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Services\AiAssistant\ContentAssistantService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

// یک تماس API هزینه‌بر است — مثل RunAiContentGeneration فقط یک‌بار تلاش می‌کند، نه retry خودکار.
// پیام کاربر را طبقه‌بندی می‌کند (ContentAssistantService::classifyIntent) و بر اساس نتیجه یکی از
// سه کار را می‌کند: یک AiGeneration معمولی صف می‌کند (همان RunAiContentGeneration موجود)، یک
// ترجمه‌ی کامل صف می‌کند (همان TranslateArticleDraft که AiAssistantPanel::translate() هم استفاده
// می‌کند)، یا صرفاً یک پاسخ گفتگویی assistant می‌نویسد — در هر سه حالت هیچ مسیر تولید جدیدی
// ساخته نمی‌شود، فقط همان‌هایی که از قبل موجودند صدا زده می‌شوند.
class ProcessAiChatMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        private readonly string $contentType,
        private readonly int $contentId,
        private readonly int $userMessageId,
    ) {}

    public function handle(ContentAssistantService $service): void
    {
        $userMessage = AiChatMessage::find($this->userMessageId);

        if (! $userMessage) {
            return;
        }

        $record = $this->contentType === 'Article' ? Article::find($this->contentId) : Page::find($this->contentId);

        if (! $record) {
            return;
        }

        try {
            $classification = $service->classifyIntent($record, $userMessage->message);
        } catch (\Throwable $e) {
            $this->reply("Sorry, something went wrong: {$e->getMessage()}");

            return;
        }

        if ($classification['intent'] === 'action') {
            $inputSnapshot = $classification['field'] === 'alt_text'
                ? Media::forRecord($record)?->alt_text
                : $record->getAttribute($classification['field']);

            $generation = AiGeneration::create([
                'content_type' => $this->contentType,
                'content_id' => $this->contentId,
                'field' => $classification['field'],
                'mode' => $classification['mode'],
                'provider' => config('services.anthropic.driver', 'anthropic'),
                'status' => 'queued',
                'input_snapshot' => $inputSnapshot,
            ]);

            RunAiContentGeneration::dispatch($generation->id);

            $this->reply($classification['reply'], $generation->id);

            return;
        }

        if ($classification['intent'] === 'translate') {
            $generation = AiGeneration::create([
                'content_type' => $this->contentType,
                'content_id' => $this->contentId,
                'field' => 'translate',
                'mode' => $classification['target_locale'],
                'provider' => config('services.anthropic.driver', 'anthropic'),
                'status' => 'queued',
            ]);

            TranslateArticleDraft::dispatch($this->contentType, $this->contentId, $classification['target_locale'], $generation->id);

            $this->reply($classification['reply'], $generation->id);

            return;
        }

        $this->reply($classification['reply']);
    }

    private function reply(string $message, ?int $relatedGenerationId = null): void
    {
        AiChatMessage::create([
            'content_type' => $this->contentType,
            'content_id' => $this->contentId,
            'role' => 'assistant',
            'message' => $message,
            'related_generation_id' => $relatedGenerationId,
        ]);
    }
}
