<?php

namespace App\Services\AiAssistant;

/**
 * لیست فیلدهای قابل‌تولید/بهبود توسط دستیار هوش مصنوعی — هر ردیف شامل برچسب، اینکه روی
 * Article/Page/هردو اعمال می‌شود، حالت‌های مجاز (generate/improve/...) و شکل پاسخ موردانتظار
 * (متن ساده، لیست، یا جفت‌های پرسش‌وپاسخ). افزودن فیلد/حالت تازه = یک ردیف اینجا، همان روحیه‌ی
 * ALIASES در App\Services\ArticleImport\ArticleImportService.
 */
class ActionRegistry
{
    public const MODES = ['generate', 'improve', 'rewrite', 'expand', 'shorten', 'simplify'];

    // این سه فیلد روی خودِ Article/Page ذخیره نمی‌شوند — روی رکورد Media متناظر با image_path
    // (کتابخانه‌ی رسانه)، دقیقاً هم‌روحِ alt_text از قبل. GenerationApplier و AiAssistantPanel هر
    // دو از همین ثابت استفاده می‌کنند تا این استثنا فقط یک‌جا تعریف شده باشد.
    public const MEDIA_BACKED_FIELDS = ['alt_text', 'caption', 'description'];

    private const FIELDS = [
        'seo_title' => [
            'label' => 'SEO Title',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve', 'shorten'],
            'response_shape' => 'text',
            'instruction' => 'Write a compelling, click-worthy SEO title (max 60 characters) that accurately represents this content and naturally includes its primary topic/keyword. Return ONLY the title text — no quotes, no explanation, no markdown.',
        ],
        'meta_description' => [
            'label' => 'Meta Description',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve', 'shorten', 'expand'],
            'response_shape' => 'text',
            'instruction' => 'Write a compelling meta description (max 155 characters) that summarizes this content and encourages clicks from search results. Return ONLY the description text — no quotes, no explanation.',
        ],
        'og_title' => [
            'label' => 'Social Share Title',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve', 'shorten'],
            'response_shape' => 'text',
            'instruction' => 'Write an engaging title for social media shares (Open Graph, max 60 characters) — can be punchier/more casual than the SEO title. Return ONLY the title text — no quotes, no explanation.',
        ],
        'og_description' => [
            'label' => 'Social Share Description',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve', 'shorten', 'expand'],
            'response_shape' => 'text',
            'instruction' => 'Write an engaging description for social media shares (max 155 characters). Return ONLY the description text — no quotes, no explanation.',
        ],
        'excerpt' => [
            'label' => 'Excerpt',
            'applicable_to' => ['Article'],
            'modes' => ['generate', 'improve', 'shorten', 'expand', 'simplify'],
            'response_shape' => 'text',
            'instruction' => 'Write a standalone, quotable 1-2 sentence summary of this article, suitable for a blog listing card and an RSS feed description. Return ONLY the excerpt text — no quotes, no explanation.',
        ],
        // بدنه‌ی مقاله/صفحه — تنها فیلدی که پاسخش HTML خام است، نه متن ساده/لیست؛ به‌جای «generate»
        // فقط حالت‌های ویرایشیِ روی محتوای موجود دارد (این دستیار زمینه‌ی کافی برای نوشتن یک مقاله‌ی
        // کامل از صفر ندارد) — همین چهار/پنج حالت است که «بهبود مقدمه»، «بازنویسی نتیجه‌گیری»،
        // «کوتاه‌ترش کن» و مثال‌های مشابه در چت هوش مصنوعی را ممکن می‌کند
        'body' => [
            'label' => 'Article Body',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['improve', 'rewrite', 'expand', 'shorten', 'simplify'],
            'response_shape' => 'html',
            'max_tokens' => 4096,
            'instruction' => 'Return the full replacement body content as clean, semantic HTML only — real <h2>/<h3> headings, <p> paragraphs, <ul>/<ol> lists where appropriate, no inline styles, no <script> tags. Preserve the overall structure and topic, just apply the requested change. Return ONLY the HTML — no markdown, no code fences, no explanation outside it.',
        ],
        'faq' => [
            'label' => 'FAQ',
            'applicable_to' => ['Article'],
            'modes' => ['generate', 'improve', 'expand'],
            'response_shape' => 'qa_pairs',
            'instruction' => 'Write 3-5 frequently-asked-question entries that genuinely help a reader of this article, based only on what the article actually covers. Return ONLY a JSON array of objects, each with exactly two keys "question" and "answer" — no other text, no markdown fences.',
        ],
        'outline' => [
            'label' => 'Article Outline',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve', 'expand', 'shorten'],
            'response_shape' => 'list',
            'instruction' => 'Write a section-by-section outline (headings only, 4-8 items) for this content. Return ONLY a JSON array of short heading strings — no other text, no markdown fences.',
        ],
        'cta' => [
            'label' => 'Call To Action',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve', 'rewrite', 'simplify'],
            'response_shape' => 'text',
            'instruction' => 'Write one short, compelling call-to-action sentence encouraging the reader to take the next step (e.g. book a session, get in touch). Return ONLY the sentence — no quotes, no explanation.',
        ],
        'tags' => [
            'label' => 'Tags',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve'],
            'response_shape' => 'list',
            'instruction' => 'Suggest 4-8 relevant SEO keyword tags for this content (short phrases, not single generic words). Return ONLY a JSON array of strings — no other text, no markdown fences.',
        ],
        'slug' => [
            'label' => 'Slug',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve'],
            'response_shape' => 'text',
            'instruction' => 'Suggest a short, readable, SEO-friendly URL slug (lowercase, words separated by hyphens, no stop words unless needed for clarity). Return ONLY the slug — no quotes, no explanation, no leading/trailing slashes.',
        ],
        'category' => [
            'label' => 'Category',
            'applicable_to' => ['Article'],
            'modes' => ['generate', 'improve'],
            'response_shape' => 'text',
            'instruction' => 'Suggest one short, single category name (1-3 words) that best classifies this article. Return ONLY the category name — no quotes, no explanation.',
        ],
        // خلاصه‌ی روایی گزارش Content Review — بر خلاف بقیه، به هیچ ستونی روی رکورد اعمال
        // نمی‌شود (خروجی فقط برای خواندن است)، برای همین appliable=false و در تب Generate
        // نشان داده نمی‌شود؛ AiContentAssistant::getFieldsProperty() این را فیلتر می‌کند
        'content_review_summary' => [
            'label' => 'Content Review Summary',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate'],
            'response_shape' => 'text',
            'appliable' => false,
            'instruction' => 'Summarize the content-review findings listed below in 2-4 plain-language sentences for a non-technical site owner, prioritizing the most impactful issues first and suggesting what to fix. Return ONLY the summary text — no quotes, no explanation, no markdown.',
        ],
        // پیشنهاد لینک داخلی — هرگز مستقیماً روی body نمی‌نویسد؛ به‌جایش به‌عنوان ردیف‌های pending
        // با origin=ai در internal_link_suggestions ذخیره می‌شود و از همان چرخه‌ی
        // approve/dismiss/insert موجود در Internal Linking Center عبور می‌کند (بدون تغییر آن منطق)
        'internal_links' => [
            'label' => 'Internal Link Suggestions',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate'],
            'response_shape' => 'internal_link_suggestions',
            'appliable' => false,
            'instruction' => 'From the list of other content provided below, pick up to 5 items this content should link to. Return ONLY a JSON array of objects, each with exactly four keys "id" and "type" (copied exactly from the list entry), "anchor_text" (a short natural phrase from this content to use as the link text), and "reason" (one short sentence explaining why) — no other text, no markdown fences.',
        ],
        // پیشنهاد لینک خارجی — فقط پیشنهاد است، هرگز خودکار درج نمی‌شود (زیرساختی برای تایید
        // امن محتوای خارجی وجود ندارد)؛ هر URL پیشنهادی قبل از نمایش با همان الگوی
        // SeoAuditService::checkExternalLinks() بررسی می‌شود که خراب نباشد
        'external_links' => [
            'label' => 'External Link Suggestions',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate'],
            'response_shape' => 'external_link_suggestions',
            'appliable' => false,
            'instruction' => 'Suggest up to 3 real, well-known, authoritative external websites (not social media) this content could link to for credibility (e.g. a recognized federation, a reputable publication). Return ONLY a JSON array of objects, each with exactly three keys "url" (a real, specific URL, not a placeholder), "anchor_text", and "reason" — no other text, no markdown fences.',
        ],
        // پیش‌نمایش فقط-خواندنی — هیچ الگو/schema‌ای در قالب‌ها تغییر نمی‌کند (طبق SeoAuditService،
        // «Missing Schema» یک شکاف سطح‌قالب است و نیاز به تایید صریح دارد، نه اینجا)
        'schema' => [
            'label' => 'Schema Suggestions',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate'],
            'response_shape' => 'text',
            'appliable' => false,
            'instruction' => 'Suggest additional structured-data (JSON-LD schema.org) content this page could benefit from — e.g. extra FAQ entries, or fields for its existing Article/Person schema. Return the suggestion as a readable, formatted JSON snippet the site owner can hand to a developer. Return ONLY the JSON snippet as text — no explanation outside it.',
        ],
        // چهار prompt جداگانه برای AI Image Pipeline — روی خودِ Article/Page ذخیره می‌شوند (ستون
        // واقعی، نه Media، چون قبل از تولید هیچ تصویری هنوز وجود ندارد). امروز فقط
        // hero_image_prompt واقعاً به App\Jobs\GenerateHeroImage می‌رسد (تصمیم تأییدشده‌ی کاربر:
        // «فقط یک تصویر با کیفیت بالا»)؛ سه‌تای دیگر همین حالا قابل‌تولید/ویرایش‌اند تا نسخه‌های
        // بعدی بتوانند تولید تصویرِ جداگانه برای هرکدام را بدون تغییر schema اضافه کنند.
        'hero_image_prompt' => [
            'label' => 'Hero Image Prompt',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve', 'rewrite', 'expand', 'shorten'],
            'response_shape' => 'text',
            'instruction' => 'Write a detailed, vivid image-generation prompt (1-3 sentences) for this content\'s main hero/featured image — describe subject, setting, mood, and style (photorealistic, professional, editorial). Return ONLY the prompt text — no quotes, no explanation.',
        ],
        'thumbnail_image_prompt' => [
            'label' => 'Thumbnail Image Prompt',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve', 'rewrite', 'expand', 'shorten'],
            'response_shape' => 'text',
            'instruction' => 'Write a detailed image-generation prompt for a simplified, clearly-legible thumbnail version of this content\'s image — bold subject, minimal background clutter, works well at a small size (blog listing card). Return ONLY the prompt text — no quotes, no explanation.',
        ],
        'og_image_prompt' => [
            'label' => 'Open Graph Image Prompt',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve', 'rewrite', 'expand', 'shorten'],
            'response_shape' => 'text',
            'instruction' => 'Write a detailed image-generation prompt for a social-share preview image (Open Graph, 1200x630-ish) — leave clear negative space for a title overlay, avoid small text baked into the image itself. Return ONLY the prompt text — no quotes, no explanation.',
        ],
        'social_image_prompt' => [
            'label' => 'Social Image Prompt',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve', 'rewrite', 'expand', 'shorten'],
            'response_shape' => 'text',
            'instruction' => 'Write a detailed image-generation prompt for a square social-media share image (Instagram/Twitter) — eye-catching, on-brand, works well cropped to a square. Return ONLY the prompt text — no quotes, no explanation.',
        ],
        // این سه فیلد روی خود مقاله/صفحه ذخیره نمی‌شوند — روی رکورد Media متناظر با image_path
        // (کتابخانه‌ی رسانه)، نگاه کنید به MEDIA_BACKED_FIELDS بالا و
        // App\Services\AiAssistant\GenerationApplier
        'alt_text' => [
            'label' => 'Image ALT Text',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve'],
            'response_shape' => 'text',
            'instruction' => 'Write concise, descriptive ALT text (max 125 characters) for this content\'s featured image, based on what the content is about. Return ONLY the ALT text — no quotes, no explanation.',
        ],
        'caption' => [
            'label' => 'Image Caption',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve'],
            'response_shape' => 'text',
            'instruction' => 'Write a short, engaging caption (max 20 words) for this content\'s featured image, suitable to show underneath it. Return ONLY the caption — no quotes, no explanation.',
        ],
        'description' => [
            'label' => 'Image Description',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['generate', 'improve', 'expand'],
            'response_shape' => 'text',
            'instruction' => 'Write a longer, plain-language description (2-3 sentences) of this content\'s featured image, describing what is visible and its relevance to the content — more detail than ALT text, for internal/SEO reference. Return ONLY the description text — no quotes, no explanation.',
        ],
        // ترجمه‌ی کامل — بر خلاف بقیه‌ی فیلدها، مسیر تولیدش ContentAssistantService::generate()
        // نیست (که یک مقدار متنی/لیستی برمی‌گرداند)، بلکه ::buildTranslationPayload() +
        // App\Jobs\TranslateArticleDraft است که یک ردیف Article/Page کاملاً تازه می‌سازد؛
        // 'modes' اینجا در واقع زبان مقصد است (en/tr)، نه یک حالت ویرایشی معمول. appliable=false
        // چون «Apply» به این معنا وجود ندارد — نتیجه از قبل یک رکورد ذخیره‌شده‌ی مستقل است، نه
        // چیزی که باید روی این رکورد نوشته شود؛ AiAssistantPanel::translate() این را صف می‌کند.
        'translate' => [
            'label' => 'Translate',
            'applicable_to' => ['Article', 'Page'],
            'modes' => ['en', 'tr'],
            'response_shape' => 'text',
            'appliable' => false,
            'instruction' => 'Translate this content, preserving meaning, tone, and HTML structure.',
        ],
    ];

    public static function all(): array
    {
        return self::FIELDS;
    }

    public static function exists(string $field): bool
    {
        return isset(self::FIELDS[$field]);
    }

    public static function for(string $field): array
    {
        return self::FIELDS[$field]
            ?? throw new \InvalidArgumentException("Unknown AI Assistant field: {$field}");
    }

    /** @return array<string, array<string, mixed>> */
    public static function applicableTo(string $modelType): array
    {
        return array_filter(self::FIELDS, fn (array $field) => in_array($modelType, $field['applicable_to'], true));
    }
}
