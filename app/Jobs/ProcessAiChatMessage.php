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
// پیام کاربر را طبقه‌بندی می‌کند (ContentAssistantService::classifyIntent) و بر اساس نتیجه یا یک
// AiGeneration معمولی صف می‌کند (همان RunAiContentGeneration موجود، نه یک مسیر تولید جدید)، یا صرفاً
// یک پاسخ گفتگویی assistant می‌نویسد. Translate هنوز اینجا سیم‌کشی نشده — تا تکمیل آن (بخش Translate)
// فقط با یک پاسخ متنی جواب می‌دهد، بدون صف کردن چیزی.
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

        // intent=translate تا سیم‌کشی کامل (بخش Translate) فقط پاسخ می‌دهد، چیزی صف نمی‌کند
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
