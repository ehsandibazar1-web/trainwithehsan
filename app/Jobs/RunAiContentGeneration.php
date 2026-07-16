<?php

namespace App\Jobs;

use App\Models\AiGeneration;
use App\Services\AiAssistant\ContentAssistantService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

// یک تماس API هزینه‌بر است — به‌جای retry خودکار روی خطا، همان یک‌بار تلاش می‌کند و شکست را
// روی خود رکورد (status=failed) ثبت می‌کند تا ادمین دوباره از پنل دستیار درخواست بدهد.
//
// دو نقطه‌ی بازبینیِ کنسل‌شدن دارد — سقفِ واقعیِ چیزی است که روی صف database ممکن است (نمی‌شود
// یک تماس HTTP در حال اجرا را واقعاً کشت): قبل از شروع (اگر بین dispatch و برداشتن توسط
// worker کنسل شده باشد، اصلاً اجرا نمی‌شود) و درست قبل از نوشتن نتیجه‌ی نهایی (یک fresh() از
// دیتابیس، نه وضعیت حافظه‌ای، تا کنسلی که حین تماس API رخ داده هم دیده شود).
class RunAiContentGeneration implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(private readonly int $generationId) {}

    public function handle(ContentAssistantService $service): void
    {
        $generation = AiGeneration::find($this->generationId);

        if (! $generation || $generation->status === 'cancelled') {
            return;
        }

        $generation->update(['status' => 'processing']);

        try {
            $outcome = $service->generate($generation->content, $generation->field, $generation->mode);

            if ($generation->fresh()->status === 'cancelled') {
                return;
            }

            if ($outcome['result'] === null) {
                $generation->update([
                    'status' => 'failed',
                    'error' => implode(' ', $outcome['warnings']) ?: 'The AI did not return a usable result.',
                ]);

                return;
            }

            $generation->update([
                'status' => 'completed',
                'result' => $outcome['result'],
            ]);
        } catch (\Throwable $e) {
            if ($generation->fresh()->status === 'cancelled') {
                return;
            }

            $generation->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
