<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // بخش‌های پیش‌فرض حافظه‌ی برند — همان فهرست درخواست‌شده، هفت گروه. اسلاگ‌های این ردیف‌ها
    // (is_system=true) در کد جای خاصی رزرو نشده‌اند (بر خلاف WorkflowStage::STAGE_*) — همه‌شان
    // یکسان توسط App\Services\BrandMemory\BrandMemoryService::buildContext() خوانده می‌شوند؛
    // is_system فقط یعنی «قابل‌حذف نیست»، نه اینکه رفتار خاصی در کد دارد.
    public function up(): void
    {
        $now = now();

        $sections = [
            'Identity' => [
                ['brand_name', 'Brand Name', 'The name of the business/brand, and how it should be referred to.'],
                ['business_description', 'Business Description', 'A clear description of what the business does and who it serves.'],
                ['mission', 'Mission', 'Why the business exists — its purpose.'],
                ['vision', 'Vision', 'Where the business is headed — its long-term goal.'],
                ['languages', 'Languages', 'Which languages/markets the brand communicates in.'],
            ],
            'Voice & Audience' => [
                ['writing_tone', 'Writing Tone', 'How the brand should sound — e.g. confident, warm, direct, technical.'],
                ['target_audience', 'Target Audience', 'Who the content is written for — their level, needs, and interests.'],
                ['formatting_rules', 'Formatting Rules', 'Structural preferences — heading style, paragraph length, list usage, etc.'],
            ],
            'Content Rules' => [
                ['writing_rules', 'Writing Rules', 'General rules every piece of content should follow.'],
                ['seo_rules', 'SEO Rules', 'Standing SEO conventions to always apply.'],
                ['internal_linking_rules', 'Internal Linking Rules', 'How and when content should link to other content on the site.'],
                ['eeat_guidelines', 'EEAT Guidelines', 'Experience/Expertise/Authoritativeness/Trustworthiness signals to reinforce.'],
                ['cta_rules', 'Call To Action Rules', 'How calls-to-action should be written and when to include one.'],
                ['faq_rules', 'FAQ Rules', 'How FAQ entries should be written — question style, answer length.'],
                ['schema_rules', 'Schema Rules', 'Standing preferences for structured-data suggestions.'],
            ],
            'Vocabulary' => [
                ['forbidden_words', 'Forbidden Words', 'Words or phrases that must never be used.'],
                ['preferred_words', 'Preferred Words', 'Preferred terminology to use instead of common alternatives.'],
            ],
            'Business Info' => [
                ['products', 'Products', 'Products the business offers.'],
                ['services', 'Services', 'Services the business offers.'],
                ['locations', 'Locations', 'Where the business operates — cities, gyms, addresses.'],
            ],
            'Credentials' => [
                ['biography', 'Biography', "The founder/instructor's background, in the brand's own words."],
                ['certificates', 'Certificates', 'Certifications, belts, ranks, or accreditations to reference.'],
                ['experience', 'Experience', 'Years active, notable achievements, competition record.'],
            ],
            'Channel Tone' => [
                ['newsletter_tone', 'Newsletter Tone', 'Tone for email/newsletter content specifically.'],
                ['social_media_tone', 'Social Media Tone', 'Tone for social media content specifically.'],
            ],
        ];

        $rows = [];
        $sortOrder = 1;

        foreach ($sections as $group => $items) {
            foreach ($items as [$key, $label, $description]) {
                $rows[] = [
                    'key' => $key,
                    'label' => $label,
                    'group' => $group,
                    'description' => $description,
                    'is_enabled' => true,
                    'is_system' => true,
                    'sort_order' => $sortOrder++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('brand_memory_sections')->insert($rows);
    }

    public function down(): void
    {
        DB::table('brand_memory_sections')->where('is_system', true)->delete();
    }
};
