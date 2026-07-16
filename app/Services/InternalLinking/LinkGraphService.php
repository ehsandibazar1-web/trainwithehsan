<?php

namespace App\Services\InternalLinking;

use App\Services\Seo\HtmlContentScanner;
use App\Services\Seo\InternalLinkResolver;
use App\Services\Seo\SeoAuditService;
use Illuminate\Support\Collection;

/**
 * گراف کامل لینک‌های داخلی سایت (مقاله‌ها + صفحه‌ها) — پایه‌ی داشبورد Internal Linking Center.
 *
 * عمداً منطق تشخیص/استخراج لینک را دوباره پیاده‌سازی نمی‌کند: از همان
 * SeoAuditService::collectContentItems() برای فهرست محتوا و همان HtmlContentScanner +
 * InternalLinkResolver برای استخراج/تشخیص لینک‌ها استفاده می‌کند (نگاه کنید به CLAUDE.md،
 * بخش «Internal Linking Center»). فقط قابلیت‌های تازه‌ای که SeoAuditService نمی‌دهد را اضافه
 * می‌کند: شمارش ورودی/خروجی هر صفحه و خودِ گراف (گره‌ها/یال‌ها) برای پیشنهاد لینک و نمایش بصری.
 *
 * برخلاف InternalLinkResolver::internalPathExists() (که برای هر لینک یک کوئری دیتابیس می‌زند)،
 * اینجا یک نقشه‌ی lookup از پیش در حافظه ساخته می‌شود تا ساخت کل گراف فقط دو کوئری بزند
 * (Article::all + Page::all، از طریق collectContentItems)، نه یکی به‌ازای هر لینک.
 */
class LinkGraphService
{
    // کمتر از این تعداد لینک ورودی یعنی «لینک‌دهی ضعیف» (اما orphan هم نیست، حداقل ۱ لینک دارد)
    private const WEAK_INBOUND_THRESHOLD = 2;

    // بیشتر از این تعداد لینک خروجی در یک صفحه، طبق راهنمای رایج سئو، یعنی «لینک بیش‌ازحد»
    private const EXCESSIVE_OUTBOUND_THRESHOLD = 100;

    public function __construct(
        private readonly SeoAuditService $seoAudit,
        private readonly HtmlContentScanner $scanner,
        private readonly InternalLinkResolver $resolver,
    ) {}

    /**
     * @return array{nodes: Collection<string, array<string, mixed>>, edges: Collection<int, array<string, mixed>>}
     */
    public function build(): array
    {
        $items = $this->seoAudit->collectContentItems();

        $lookup = [];
        foreach ($items as $item) {
            $lookup[$item['model']][$item['locale']][$item['slug']] = $item;
        }

        // عمدا آرایه‌ی خام PHP است، نه Collection — چون در حلقه‌ی پایین با ++ جهش‌پذیر تغییر می‌کند؛
        // تغییر مستقیم عنصر تودرتوی یک Collection (مثل $nodes[$key]['x']++) بی‌صدا بی‌اثر است
        $nodes = [];
        foreach ($items as $item) {
            $nodes[$this->nodeKey($item['model'], $item['id'])] = array_merge($item, [
                'inbound' => 0,
                'outbound' => 0,
                'inbound_from' => [],
            ]);
        }

        $edges = collect();

        foreach ($items as $item) {
            $sourceKey = $this->nodeKey($item['model'], $item['id']);
            $seenTargets = []; // چند لینک به همون مقصد را یک‌بار بشمار (خروجی/ورودی یکتا)

            foreach ($this->scanner->links($item['body']) as $link) {
                if ($this->resolver->isSkippable($link['href']) || $this->resolver->isExternal($link['href'])) {
                    continue;
                }

                $parsed = $this->resolver->parseInternalPath($link['href']);
                if (! $parsed) {
                    continue; // یا لینک ثابت (/,/blog,...) است، یا خراب — خرابی‌ها را SeoAuditService گزارش می‌دهد
                }

                $target = $lookup[$parsed['type']][$parsed['locale']][$parsed['slug']] ?? null;
                if (! $target) {
                    continue;
                }

                $targetKey = $this->nodeKey($target['model'], $target['id']);
                if ($targetKey === $sourceKey || isset($seenTargets[$targetKey])) {
                    continue;
                }
                $seenTargets[$targetKey] = true;

                $nodes[$sourceKey]['outbound']++;
                $nodes[$targetKey]['inbound']++;
                $nodes[$targetKey]['inbound_from'][] = $sourceKey;

                $edges->push(['from' => $sourceKey, 'to' => $targetKey, 'anchor' => $link['text']]);
            }
        }

        return ['nodes' => collect($nodes), 'edges' => $edges];
    }

    public function nodeKey(string $model, int $id): string
    {
        return $model.':'.$id;
    }

    /**
     * هر محتوایی با صفر لینک ورودی — با هر وضعیتی (draft هم)، چون هدف اینجا برنامه‌ریزی لینک‌سازی
     * است، نه فقط گزارش مشکلات سایت زنده (برخلاف SeoAuditService::orphanPages که عمدا فقط
     * published را چک می‌کند).
     */
    public function noInboundLinks(Collection $nodes): array
    {
        return $nodes
            ->filter(fn ($node) => $node['inbound'] === 0)
            ->map(fn ($node) => $this->finding($node, 'no_inbound_links', 'No other article or page links to this yet.'))
            ->values()->all();
    }

    public function noOutboundLinks(Collection $nodes): array
    {
        return $nodes
            ->filter(fn ($node) => $node['outbound'] === 0)
            ->map(fn ($node) => $this->finding($node, 'no_outbound_links', "This content doesn't link out to any other article or page."))
            ->values()->all();
    }

    /**
     * لینک‌دهی ضعیف = حداقل یک لینک ورودی دارد (پس orphan نیست) ولی هنوز کمتر از آستانه است.
     */
    public function weakInternalLinking(Collection $nodes): array
    {
        return $nodes
            ->filter(fn ($node) => $node['status'] === 'published')
            ->filter(fn ($node) => $node['inbound'] > 0 && $node['inbound'] < self::WEAK_INBOUND_THRESHOLD)
            ->map(fn ($node) => $this->finding(
                $node,
                'weak_internal_linking',
                "Only {$node['inbound']} internal link points here — aim for at least ".self::WEAK_INBOUND_THRESHOLD.' for healthy internal linking.'
            ))
            ->values()->all();
    }

    public function excessiveInternalLinks(Collection $nodes): array
    {
        return $nodes
            ->filter(fn ($node) => $node['outbound'] > self::EXCESSIVE_OUTBOUND_THRESHOLD)
            ->map(fn ($node) => $this->finding(
                $node,
                'excessive_internal_links',
                "Links out to {$node['outbound']} other pages — more than the ".self::EXCESSIVE_OUTBOUND_THRESHOLD.' recommended per page, which dilutes link value and can look spammy.'
            ))
            ->values()->all();
    }

    /**
     * هیچ سیستم ریدایرکتی (جدول/میدلور) در این اپ وجود ندارد — پس زنجیره‌ی ریدایرکت اصلا
     * نمی‌تواند رخ دهد. اگر اسلاگی عوض شود، لینک‌های قدیمی به‌جای ریدایرکت، «لینک داخلی خراب»
     * می‌شوند — همان دسته‌ای که از SeoAuditService بازاستفاده می‌شود.
     */
    public function redirectChains(): array
    {
        return [];
    }

    private function finding(array $node, string $category, string $detail): array
    {
        return [
            'category' => $category,
            'type' => $node['model'],
            'locale' => $node['locale'],
            'title' => $node['title'].' ('.strtoupper($node['locale']).')',
            'detail' => $detail,
            'edit_url' => $node['edit_url'],
        ];
    }
}
