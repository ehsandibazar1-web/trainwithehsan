<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\MediaPickerInput;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class HomepageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Homepage Settings';

    protected static ?string $title = 'Homepage Settings';

    protected string $view = 'filament.pages.homepage-settings';

    public ?array $data = [];

    // کلیدهای متنی ساده — برای هر دو زبان یکسان تعریف می‌شوند
    private const TEXT_KEYS = [
        'hero1_title', 'hero1_sub',
        'hero2_title', 'hero2_sub',
        'hero3_title', 'hero3_sub',
        'video1_caption', 'video1_embed',
        'video2_caption', 'video2_embed',
        'video3_caption', 'video3_embed',
        'app_title', 'app_subtitle', 'app_text', 'app_button_label',
        'courses_title', 'courses_subtitle',
        'course1_label', 'course2_label', 'course3_label',
        'course1_link', 'course2_link', 'course3_link',
        'members_title', 'members_subtitle', 'members_button_label',
        // insta_url = لینک پروفایل اینستاگرام؛ هنوز به‌عنوان fallback دکمهٔ Follow هر دو ردیف
        // استفاده می‌شود، پس نگه داشته شده و داخل بخش «Instagram Showcase» ردیف اول نمایش داده
        // می‌شود (بخش قدیمی «Instagram» و ۴ فیلد عکسِ بلااستفادهٔ آن به‌درخواست کاربر حذف شدند)
        'insta_url',
        // ویترین اینستاگرام (Instagram Showcase) — ردیف اول. توجه: کلید آدرس embed به‌صورت
        // تاریخی insta_embed_url است (بدون بخش showcase_) و باید در فرم دقیقاً همین کلید پاس شود
        'insta_showcase_enabled', 'insta_embed_url',
        'insta_showcase_title', 'insta_showcase_subtitle',
        'insta_showcase_button_text', 'insta_showcase_button_url',
        // ردیف دوم ویترین اینستاگرام — برای دو صفحه/پروفایل جدا (مثل سایت مرجع)، کاملاً
        // اختیاری و پیش‌فرض غیرفعال؛ فقط اگر ادمین صریحاً فعالش کند نمایش داده می‌شود
        'insta_showcase2_enabled', 'insta_showcase2_embed_url',
        'insta_showcase2_title', 'insta_showcase2_subtitle',
        'insta_showcase2_button_text', 'insta_showcase2_button_url',
    ];

    // کلیدهای فایل (عکس/ویدیو) — مقدارشان مسیر فایل روی دیسک public است
    private const FILE_KEYS = [
        'hero1_image', 'hero2_image', 'hero3_image',
        'video1_file', 'video2_file', 'video3_file',
        'video1_thumb', 'video2_thumb', 'video3_thumb',
        'app_image',
        'course1_image', 'course2_image', 'course3_image',
        // فیلدهای عکسِ قدیمیِ اینستاگرام (insta1_image/insta2_image/...) به‌درخواست کاربر حذف
        // شدند — دیگر رندر نمی‌شدند و به هیچ‌جا وابسته نبودند؛ مقدارهای قدیمی در DB بی‌ضررند
        'insta_showcase_fallback_image',
        'insta_showcase2_fallback_image',
    ];

    public function mount(): void
    {
        $state = [];

        foreach (['en', 'tr'] as $locale) {
            foreach (self::TEXT_KEYS as $key) {
                $state[$locale][$key] = SiteSetting::get("home.$locale.$key");
            }
            foreach (self::FILE_KEYS as $key) {
                $state[$locale][$key] = SiteSetting::get("home.$locale.$key");
            }
            $state[$locale]['members'] = SiteSetting::getJson("home.$locale.members");
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('English — Hero Slider')
                    ->schema(self::heroFields('en')),
                Section::make('English — Video Row')
                    ->schema(self::videoFields('en'))
                    ->collapsed(),
                Section::make('English — App Section')
                    ->schema(self::appFields('en'))
                    ->collapsed(),
                Section::make('English — Courses Section')
                    ->schema(self::coursesFields('en'))
                    ->collapsed(),
                Section::make('English — Member Results')
                    ->schema(self::membersFields('en'))
                    ->collapsed(),
                Section::make('English — Instagram Showcase')
                    ->description('The embedded Instagram post/reel shown next to the text on the homepage.')
                    ->schema(self::instaShowcaseFields('en', 'insta_showcase', 'insta_embed_url', includeProfileUrl: true))
                    ->collapsed(),
                Section::make('English — Instagram Showcase — Row 2 (optional)')
                    ->description('An optional second Instagram row below the first — for a different post, Reel, or account. Add an Embed URL or Fallback Image below to make it appear; nothing changes on the homepage until you do.')
                    ->schema(self::instaShowcaseFields('en', 'insta_showcase2', alwaysVisible: false))
                    ->collapsed(),

                Section::make('Türkçe — Hero Slider')
                    ->schema(self::heroFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — Video Row')
                    ->schema(self::videoFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — App Section')
                    ->schema(self::appFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — Courses Section')
                    ->schema(self::coursesFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — Member Results')
                    ->schema(self::membersFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — Instagram Showcase')
                    ->description('The embedded Instagram post/reel shown next to the text on the homepage.')
                    ->schema(self::instaShowcaseFields('tr', 'insta_showcase', 'insta_embed_url', includeProfileUrl: true))
                    ->collapsed(),
                Section::make('Türkçe — Instagram Showcase — Row 2 (optional)')
                    ->description('An optional second Instagram row below the first — for a different post, Reel, or account. Add an Embed URL or Fallback Image below to make it appear; nothing changes on the homepage until you do.')
                    ->schema(self::instaShowcaseFields('tr', 'insta_showcase2', alwaysVisible: false))
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    private static function heroFields(string $l): array
    {
        $fields = [];
        foreach ([1, 2, 3] as $i) {
            $fields[] = TextInput::make("$l.hero{$i}_title")->label("Slide $i — Title");
            $fields[] = Textarea::make("$l.hero{$i}_sub")->label("Slide $i — Subtitle")->rows(2);
            $fields[] = MediaPickerInput::make("$l.hero{$i}_image")
                ->label("Slide $i — Background image")
                ->onlyImages()
                ->uploadDirectory('homepage/hero')
                ->nullable();
        }

        return $fields;
    }

    private static function videoFields(string $l): array
    {
        $fields = [];
        foreach ([1, 2, 3] as $i) {
            $fields[] = TextInput::make("$l.video{$i}_caption")->label("Video $i — Caption");
            $fields[] = MediaPickerInput::make("$l.video{$i}_thumb")
                ->label("Video $i — Thumbnail photo")
                ->onlyImages()
                ->uploadDirectory('homepage/videos')
                ->nullable();
            $fields[] = TextInput::make("$l.video{$i}_embed")
                ->label("Video $i — Embed URL (YouTube/Aparat)")
                ->helperText('Paste an embed link here, OR upload a file below — not both.');
            $fields[] = MediaPickerInput::make("$l.video{$i}_file")
                ->label("Video $i — File (mp4)")
                ->helperText('Pick or upload a video in the Media Library. Up to 128 MB.')
                ->initialType('video')
                ->uploadDirectory('homepage/videos')
                ->nullable();
        }

        return $fields;
    }

    private static function appFields(string $l): array
    {
        return [
            TextInput::make("$l.app_title")->label('Title'),
            TextInput::make("$l.app_subtitle")->label('Subtitle'),
            Textarea::make("$l.app_text")->label('Text')->rows(4),
            TextInput::make("$l.app_button_label")->label('Button label'),
            MediaPickerInput::make("$l.app_image")
                ->label('Section image')
                ->onlyImages()
                ->uploadDirectory('homepage')
                ->nullable(),
        ];
    }

    private static function coursesFields(string $l): array
    {
        $fields = [
            TextInput::make("$l.courses_title")->label('Section title'),
            Textarea::make("$l.courses_subtitle")->label('Section subtitle')->rows(2),
        ];
        $blogPath = $l === 'tr' ? '/tr/blog' : '/blog';
        foreach ([1, 2, 3] as $i) {
            $fields[] = TextInput::make("$l.course{$i}_label")->label("Course $i — Label");
            $fields[] = TextInput::make("$l.course{$i}_link")
                ->label("Course $i — Link")
                ->nullable()
                ->helperText("Where this card goes when clicked — a relative path (e.g. /blog/some-article) or a full https:// URL. Leave blank to link to the blog ($blogPath) as a placeholder until you have a dedicated page.");
            $fields[] = MediaPickerInput::make("$l.course{$i}_image")
                ->label("Course $i — Image")
                ->onlyImages()
                ->uploadDirectory('homepage/courses')
                ->nullable();
        }

        return $fields;
    }

    private static function membersFields(string $l): array
    {
        return [
            TextInput::make("$l.members_title")->label('Section title'),
            Textarea::make("$l.members_subtitle")->label('Section subtitle')->rows(2),
            TextInput::make("$l.members_button_label")->label('Button label'),
            Repeater::make("$l.members")
                ->label('Members')
                ->schema([
                    TextInput::make('name')->label('Name'),
                    MediaPickerInput::make('photo')
                        ->label('Photo')
                        ->onlyImages()
                        ->uploadDirectory('homepage/members')
                        ->nullable(),
                    TextInput::make('video_embed')
                        ->label('Video embed URL (YouTube)')
                        ->helperText('Paste a YouTube link here, OR pick a video file below — not both. Clicking this member\'s photo will open the video, just like the videos at the top of the homepage.')
                        ->nullable(),
                    MediaPickerInput::make('video_file')
                        ->label('Video file (mp4)')
                        ->helperText('Pick or upload a video in the Media Library. Up to 128 MB.')
                        ->initialType('video')
                        ->uploadDirectory('homepage/members')
                        ->nullable(),
                ])
                ->defaultItems(0)
                ->addActionLabel('Add member'),
        ];
    }

    // یک ردیف کامل ویترین اینستاگرام — با پیشوند $prefix برای ردیف اول (insta_showcase، کلیدهای
    // بدون تغییر نسبت به نسخهٔ اول) یا ردیف دوم (insta_showcase2) قابل استفادهٔ مجدد است. کلید
    // آدرس embed ردیف اول به‌صورت تاریخی insta_embed_url است (بدون بخش showcase_) — باید صریحاً
    // به‌عنوان $embedKey پاس شود؛ ردیف دوم از الگوی یکدست‌تر {prefix}_embed_url استفاده می‌کند.
    // $includeProfileUrl فقط برای ردیف اول true است تا فیلد مشترک insta_url (لینک پروفایل،
    // fallback دکمهٔ Follow هر دو ردیف) یک‌بار همین‌جا نمایش داده شود — جایگزین بخش قدیمیِ حذف‌شده.
    private static function instaShowcaseFields(string $l, string $prefix = 'insta_showcase', ?string $embedKey = null, bool $alwaysVisible = true, bool $includeProfileUrl = false): array
    {
        $embedKey ??= "{$prefix}_embed_url";

        $fields = [];

        if ($includeProfileUrl) {
            $fields[] = TextInput::make("$l.insta_url")
                ->label('Instagram profile link')
                ->helperText('Your Instagram profile, e.g. https://instagram.com/ehsandibazar — used for the "Follow" buttons whenever a row has no Button URL of its own. (This is the shared link for both rows.)');
        }

        // توجه: نمایش امبد زنده فقط به وجود Embed URL بستگی دارد (نه به این توگل) — و همین‌طور
        // نمایش Fallback Image فقط به آپلود آن عکس بستگی دارد. این هماهنگی عمدی است تا رفتار
        // «آپلود عکس» و «چسباندن لینک» بین ردیف اول و دوم دقیقاً یکسان باشد (به‌درخواست کاربر).
        $fields[] = Toggle::make("$l.{$prefix}_enabled")
            ->label('Enable Instagram Showcase')
            ->helperText($alwaysVisible
                ? 'Not required — this row always appears. Add an Embed URL below to show a live post/reel, or a Fallback Image for a static photo; either one appears automatically without needing this toggle.'
                : 'Not required if you add an Embed URL or Fallback Image below — this row then appears automatically. Turn this on only if you want the row to show with just the default Instagram icon and no photo/embed yet.');
        $fields[] = TextInput::make("$l.$embedKey")
            ->label('Instagram Embed URL')
            ->url()
            ->helperText('Paste any public Instagram Post or Reel URL, e.g. https://www.instagram.com/p/ABC123/ or https://www.instagram.com/reel/ABC123/ — shows automatically once saved, the correct embed is detected automatically.');

        return array_merge($fields, [
            TextInput::make("$l.{$prefix}_title")->label('Section Title'),
            TextInput::make("$l.{$prefix}_subtitle")->label('Section Subtitle'),
            TextInput::make("$l.{$prefix}_button_text")
                ->label('Button Text')
                ->helperText('Leave blank to keep the default "Follow us on Instagram".'),
            TextInput::make("$l.{$prefix}_button_url")
                ->label('Button URL')
                ->url()
                ->helperText('Leave blank to use the Instagram URL set above.'),
            MediaPickerInput::make("$l.{$prefix}_fallback_image")
                ->label('Optional Fallback Image')
                ->helperText('Shown if the Instagram embed is disabled, has no URL, or fails to load.')
                ->onlyImages()
                ->uploadDirectory('homepage')
                ->nullable(),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (['en', 'tr'] as $locale) {
            foreach (array_merge(self::TEXT_KEYS, self::FILE_KEYS) as $key) {
                $value = $state[$locale][$key] ?? null;

                // FileUpload گاهی مقدار را به‌صورت آرایه برمی‌گرداند —
                // اولین مسیر را برمی‌داریم تا در ستون متنی ذخیره‌شدنی باشد
                if (is_array($value)) {
                    $value = array_values(array_filter($value))[0] ?? null;
                }

                SiteSetting::set("home.$locale.$key", $value, 'homepage');
            }

            SiteSetting::set(
                "home.$locale.members",
                json_encode($state[$locale]['members'] ?? [], JSON_UNESCAPED_UNICODE),
                'homepage'
            );
        }

        Notification::make()
            ->success()
            ->title('Homepage settings saved')
            ->send();
    }
}
