<?php

namespace App\Http\Controllers\Api;

use App\Filament\Resources\Articles\ArticleResource;
use App\Http\Controllers\Controller;
use App\Jobs\ImportAiArticle;
use App\Services\ArticleImport\ArticleImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * API امن ایمپورت هوش مصنوعی — همان قالب JSON صفحه‌ی AI Import.
 *
 * سیاست API: هرگز مستقیم منتشر نمی‌کند. هر ایمپورت به‌اجبار «پیش‌نویس» ذخیره
 * می‌شود و مسیر انتشار همان گردش‌کار موجود است:
 * اعتبارسنجی → پیش‌نویس (Draft Queue) → پیش‌نمایش امضاشده → تأیید مدیر → انتشار.
 * تمام منطق در ArticleImportService است — این کنترلر فقط سیاست و شکل پاسخ.
 */
class AiImportController extends Controller
{
    public function __construct(private readonly ArticleImportService $service) {}

    // اعتبارسنجی بدون هیچ ذخیره‌سازی — گام اول جریان
    public function validatePayload(Request $request): JsonResponse
    {
        $analysis = $this->service->analyze($this->forceDraft($request->getContent()), 'json');

        return response()->json([
            'ok' => true,
            'valid' => $analysis['errors'] === [],
            'errors' => $analysis['errors'],
            'warnings' => $analysis['warnings'],
            'mapping' => $analysis['mapping'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $raw = $this->forceDraft($request->getContent());

        $context = [
            'source' => 'api',
            'api_token_id' => $request->attributes->get('ai_api_token')?->id,
        ];

        // پشتیبانی صف: ?queue=1 (یا "queue": true در بدنه) — همان سرویس، غیرهمزمان
        if ($this->wantsQueue($request)) {
            ImportAiArticle::dispatch($raw, 'json', $context);

            return response()->json([
                'ok' => true,
                'queued' => true,
                'message' => 'Import queued. The result will appear in the Import History; the article is saved as a draft for review in the Draft Queue.',
            ], 202);
        }

        $result = $this->service->import($raw, 'json', $context);

        if ($result['article'] === null) {
            return response()->json([
                'ok' => false,
                'errors' => $result['errors'],
                'warnings' => $result['warnings'],
                'import_log_id' => $result['log']->id,
            ], 422);
        }

        $article = $result['article'];

        return response()->json([
            'ok' => true,
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'locale' => $article->locale,
                'status' => $article->status,
            ],
            // پیش‌نمایش امضاشده — همان سیستم پیش‌نمایش موجود برای پیش‌نویس‌ها
            'preview_url' => URL::temporarySignedRoute('articles.preview', now()->addDay(), ['article' => $article->id]),
            'edit_url' => ArticleResource::getUrl('edit', ['record' => $article]),
            'warnings' => $result['warnings'],
            'import_log_id' => $result['log']->id,
            'note' => 'Saved as a draft. Review it in the admin panel (AI Studio → Draft Queue) and publish through the normal workflow.',
        ], 201);
    }

    /**
     * سیاست اجباری API: وضعیت انتشار همیشه «پیش‌نویس» می‌شود؛ تاریخ انتشارِ
     * پیشنهادی حفظ می‌شود تا مدیر هنگام تأیید ببیند. بدنه‌ی غیر JSON دست‌نخورده
     * رد می‌شود تا سرویس همان خطای استاندارد Invalid JSON را برگرداند و لاگ کند.
     */
    private function forceDraft(string $raw): string
    {
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return $raw;
        }

        unset($decoded['status'], $decoded['publish_status'], $decoded['queue']);
        $decoded['publish_status'] = 'draft';

        return json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }

    private function wantsQueue(Request $request): bool
    {
        if ($request->boolean('queue')) {
            return true;
        }

        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) && (bool) ($decoded['queue'] ?? false);
    }
}
