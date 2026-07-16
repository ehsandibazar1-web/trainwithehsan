<?php

namespace App\Jobs;

use App\Models\AiGeneration;
use App\Services\AiAssistant\ContentAssistantService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

// یک تماس API هزینه‌بر است — به‌جای retry خودکار روی خطا، همان یک‌بار تلاش می‌کند و شکست را
// روی خود رکورد (status=failed) ثبت می‌کند تا ادمین دوباره از پنل دستیار درخواست بدهد
class RunAiContentGeneration implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(private readonly int $generationId) {}

    public function handle(ContentAssistantService $service): void
    {
        $generation = AiGeneration::find($this->generationId);

        if (! $generation) {
            return;
        }

        $generation->update(['status' => 'processing']);

        try {
            $outcome = $service->generate($generation->content, $generation->field, $generation->mode);

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
            $generation->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
