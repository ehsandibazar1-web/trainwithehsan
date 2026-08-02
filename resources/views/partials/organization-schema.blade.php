{{--
    گره‌ی واحدِ Organization برای همه‌ی صفحات — تنها منبعِ حقیقت برای name/url/logo/sameAs/founder.
    هدف: رفعِ تکه‌تکه‌شدنِ knowledge graph (هر صفحه قبلاً یک نسخه‌ی جدا و ناقص از Organization
    داشت). @id/url هرگز دامنه را هاردکد نمی‌کنند (طبق قانونِ خودِ پروژه در CLAUDE.md).

    مهم: این فایل عمداً فقط از فرمِ تک‌خطیِ دایرکتیوِ php استفاده می‌کند، نه فرمِ بلوکی — کامپایلرِ
    Blade اولین رخدادِ این دایرکتیو را (حتی به‌شکلِ تک‌خطی) با اولین پایان‌دهنده‌ی فرمِ بلوکی در
    کلِ فایل جفت می‌کند (بدون توجه به تودرتو بودن یا این‌که در کامنت ذکر شده باشد)، پس استفاده از
    فرمِ بلوکی این‌جا کل محتوای فایلِ میزبان (home/about/blog-post/...) را که این partial در آن
    include می‌شود خراب می‌کرد — یک باگِ واقعی که در توسعه‌ی همین تغییر کشف شد.
--}}
@php($__orgId = url('/').'/#organization')
@php($__personId = url('/').'/#person')
@php($__orgLogo = asset('storage/homepage/logo.header.png'))
@php($__orgSameAs = \App\Models\SiteSetting::socialLinks())
{{-- LocalBusiness/SportsActivityLocation فقط وقتی که یک آدرسِ واقعی در Footer Settings تنظیم شده
     باشد اضافه می‌شود — همان "null بر حدس ارجح است"ِ این کدبیس؛ بدونِ آدرس، ادعای یک مکانِ فیزیکی
     دروغِ ساختاری در JSON-LD می‌شد. تلفن/ایمیل هم از همان منبع، اختیاری. --}}
@php($__contactSettings = \App\Models\SiteSetting::byPrefix('footer.en'))
@php($__contactAddress = $__contactSettings['footer.en.contact_address'] ?? null)
@php($__contactPhone = $__contactSettings['footer.en.contact_phone'] ?? null)
@php($__contactEmail = $__contactSettings['footer.en.contact_email'] ?? null)
@php($__isLocalBusiness = filled($__contactAddress))
@php($__orgTypes = $__isLocalBusiness ? ['Organization', 'SportsActivityLocation'] : 'Organization')
{
  "@@type": @json($__orgTypes),
  "@@id": @json($__orgId),
  "name": "Train with Ehsan",
  "url": @json(url('/')),
  "logo": {"@@type": "ImageObject", "url": @json($__orgLogo)},
  "founder": {"@@id": @json($__personId)},
  "areaServed": "Istanbul, Türkiye"
  @if(!empty($__orgSameAs))
  ,"sameAs": @json($__orgSameAs)
  @endif
  @if($__isLocalBusiness)
  ,"address": @json($__contactAddress)
  @endif
  @if(filled($__contactPhone))
  ,"telephone": @json($__contactPhone)
  @endif
  @if(filled($__contactEmail))
  ,"email": @json($__contactEmail)
  @endif
}
