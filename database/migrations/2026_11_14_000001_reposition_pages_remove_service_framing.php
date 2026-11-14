<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // بازموضع‌سازی صفحات Contact/FAQ/Terms/Privacy/Disclaimer از «ارائه‌دهنده‌ی خدماتِ آموزشی
    // در استانبول» به «برندِ شخصی» — به‌خواستِ صریحِ صاحبِ سایت (نگرانیِ پرونده‌ی مهاجرت: هیچ
    // ادعای آموزش/خدمت/مکانِ کسب‌وکار در استانبول، و هیچ آموزشِ آنلاین/اپ، نباید جایی بماند).
    //
    // این صفحات ردیف‌های زنده‌ی جدولِ pages هستند که مهاجرتِ seed (2026_07_17_000011) قبلاً
    // ساخته است — ویرایشِ آن فایل روی نصبِ موجود اثری ندارد، پس این مهاجرتِ داده‌ایْ همان
    // ردیف‌ها را به‌روز می‌کند (اجرا از طریقِ دکمه‌ی «run pending migrations» در System
    // Maintenance، چون این هاست SSH ندارد — همان مسیرِ استانداردِ پروژه).
    //
    // گاردِ idempotent: هر ردیف فقط وقتی عوض می‌شود که هنوز نشانِ متنِ seed را داشته باشد
    // (برای full-replace: وجودِ «Istanbul»/«İstanbul» در body؛ برای patchِ جراحی: فقط اگر
    // str_replace چیزی را واقعاً تغییر داد). پس اجرای دوباره no-op است و اگر ادمین قبلاً خودش
    // این صفحه را ویرایش کرده باشد، دست‌نخورده می‌ماند. Cookie Policy عمداً اینجا نیست — آن
    // صفحه هیچ اشاره‌ای به استانبول/خدمات ندارد.
    public function up(): void
    {
        $this->replaceContact();
        $this->replaceFaq();
        $this->replaceTerms();
        $this->patchPrivacy();
        $this->patchDisclaimer();
    }

    // بازگردانی عمداً انجام نمی‌شود: معکوسِ این مهاجرت یعنی برگرداندنِ همان متنِ «آموزش در
    // استانبول» که کلِ هدفِ این تغییر حذفِ آن بود. محتوا از این پس از طریقِ پنل مدیریت می‌شود.
    public function down(): void
    {
        // no-op (نگاه کنید به توضیح بالا)
    }

    private function replaceRow(string $slug, string $locale, string $fingerprint, array $fields): void
    {
        $row = DB::table('pages')->where('slug', $slug)->where('locale', $locale)->first();
        if (! $row) {
            return;
        }
        // فقط وقتی هنوز متنِ seed است (نشانِ fingerprint را دارد) — وگرنه ادمین خودش عوضش کرده.
        if ($fingerprint !== '' && ! str_contains((string) $row->body, $fingerprint)) {
            return;
        }
        $fields['updated_at'] = now();
        DB::table('pages')->where('id', $row->id)->update($fields);
    }

    private function patchRow(string $slug, string $locale, array $replacements, array $extraFields = []): void
    {
        $row = DB::table('pages')->where('slug', $slug)->where('locale', $locale)->first();
        if (! $row) {
            return;
        }
        $body = (string) $row->body;
        foreach ($replacements as $old => $new) {
            $body = str_replace($old, $new, $body);
        }
        $update = [];
        if ($body !== (string) $row->body) {
            $update['body'] = $body;
        }
        foreach ($extraFields as $k => $v) {
            if (($row->$k ?? null) !== $v) {
                $update[$k] = $v;
            }
        }
        if ($update) {
            $update['updated_at'] = now();
            DB::table('pages')->where('id', $row->id)->update($update);
        }
    }

    private function replaceContact(): void
    {
        $enBody = <<<'HTML'
<p>Have a question for Ehsan Dibazar, or want to get in touch about his martial-arts background, the Martial Intelligence approach, or an article on this site? Send a message — he reads every one.</p>
<p>Fill in the form below with your name, email and message and he'll get back to you as soon as possible.</p>
HTML;
        $trBody = <<<'HTML'
<p>Ehsan Dibazar'a bir sorunuz mu var ya da dövüş sanatları geçmişi, Martial Intelligence yaklaşımı veya bu sitedeki bir yazı hakkında iletişime mi geçmek istiyorsunuz? Bir mesaj gönderin — her birini okur.</p>
<p>Aşağıdaki formu adınız, e-postanız ve mesajınızla doldurun; en kısa sürede size dönüş yapar.</p>
HTML;

        $this->replaceRow('contact', 'en', 'Istanbul', [
            'body' => $enBody,
            'seo_title' => 'Contact — Ehsan Dibazar',
            'meta_description' => 'Get in touch with Ehsan Dibazar — martial artist, coach and sport scientist, and creator of the Martial Intelligence approach.',
            'meta_keywords' => 'contact, Ehsan Dibazar, Martial Intelligence, martial artist',
            'og_title' => 'Contact Ehsan Dibazar',
            'og_description' => 'Have a question? Send a message and Ehsan will get back to you.',
        ]);

        $this->replaceRow('contact', 'tr', 'İstanbul', [
            'body' => $trBody,
            'seo_title' => 'İletişim — Ehsan Dibazar',
            'meta_description' => 'Dövüş sanatçısı, koç ve spor bilimci Ehsan Dibazar ile iletişime geçin.',
            'meta_keywords' => 'iletişim, Ehsan Dibazar, Martial Intelligence, dövüş sanatçısı',
            'og_title' => 'Ehsan Dibazar ile İletişime Geçin',
            'og_description' => 'Bir sorunuz mu var? Bir mesaj gönderin, Ehsan size dönüş yapsın.',
        ]);
    }

    private function replaceFaq(): void
    {
        $en = [
            ['question' => 'Who is Ehsan Dibazar?', 'answer' => 'Ehsan Dibazar is a martial artist, coach and sport scientist with an MSc in Sport Science and a background across Muay Thai, Brazilian Jiu-Jitsu and self-defense. He is the creator of the Martial Intelligence approach.'],
            ['question' => 'What is the Martial Intelligence approach?', 'answer' => 'Martial Intelligence is Ehsan\'s way of thinking about self-defense. It treats decision-making under pressure — reading a situation, staying calm and choosing the right response — as the core skill, rather than physical technique alone.'],
            ['question' => 'What is Brazilian Jiu-Jitsu (BJJ)?', 'answer' => 'Brazilian Jiu-Jitsu is a grappling-based martial art built around leverage and technique rather than raw strength, which is why it can be effective for a smaller person against a larger opponent.'],
            ['question' => 'What is Muay Thai?', 'answer' => 'Muay Thai is a striking martial art from Thailand, known as the art of eight limbs, using punches, kicks, elbows and knees.'],
            ['question' => 'What does self-defense mean in the Martial Intelligence approach?', 'answer' => 'It focuses on real-world situations — recognizing danger early, de-escalation and decisive, simple responses under pressure — rather than a large technical curriculum.'],
            ['question' => 'What does Ehsan write about on this site?', 'answer' => 'The blog shares articles on self-defense, martial arts, training ideas and the Martial Intelligence mindset.'],
            ['question' => 'What is Ehsan\'s background and education?', 'answer' => 'Ehsan holds an MSc in Sport Science and has trained and competed across several martial arts over more than a decade.'],
            ['question' => 'How can I follow Ehsan\'s work?', 'answer' => 'You can read the latest articles on the blog and follow along through the social links in the site footer.'],
            ['question' => 'How can I get in touch?', 'answer' => 'The best way is through the Contact page — send a message and Ehsan will get back to you.'],
        ];

        $tr = [
            ['question' => 'Ehsan Dibazar kimdir?', 'answer' => 'Ehsan Dibazar; Spor Bilimi alanında yüksek lisansa (MSc) sahip, Muay Thai, Brezilya Jiu-Jitsu ve kendini savunma alanlarında geçmişi olan bir dövüş sanatçısı, koç ve spor bilimcidir. Martial Intelligence yaklaşımının yaratıcısıdır.'],
            ['question' => 'Martial Intelligence yaklaşımı nedir?', 'answer' => 'Martial Intelligence, Ehsan\'ın kendini savunmaya bakış biçimidir. Temel beceri olarak yalnızca fiziksel tekniği değil, baskı altında karar vermeyi — durumu okumak, sakin kalmak ve doğru tepkiyi seçmek — görür.'],
            ['question' => 'Brezilya Jiu-Jitsu (BJJ) nedir?', 'answer' => 'Brezilya Jiu-Jitsu, ham güç yerine kaldıraç ve teknik üzerine kurulu bir güreş temelli dövüş sanatıdır; bu yüzden daha küçük bir kişi için daha büyük bir rakibe karşı etkili olabilir.'],
            ['question' => 'Muay Thai nedir?', 'answer' => 'Muay Thai, sekiz uzvun sanatı olarak bilinen, yumruk, tekme, dirsek ve dizleri kullanan Tayland kökenli bir vuruş sanatıdır.'],
            ['question' => 'Martial Intelligence yaklaşımında kendini savunma ne anlama gelir?', 'answer' => 'Geniş bir teknik müfredat yerine gerçek dünya durumlarına — tehlikeyi erken fark etmek, gerginliği azaltmak ve baskı altında kararlı, basit tepkilere — odaklanır.'],
            ['question' => 'Ehsan bu sitede ne hakkında yazıyor?', 'answer' => 'Blog; kendini savunma, dövüş sanatları, antrenman fikirleri ve Martial Intelligence zihniyeti üzerine yazılar paylaşır.'],
            ['question' => 'Ehsan\'ın geçmişi ve eğitimi nedir?', 'answer' => 'Ehsan, Spor Bilimi alanında yüksek lisansa sahiptir ve on yıldan fazla süredir çeşitli dövüş sanatlarında antrenman yapmış ve yarışmıştır.'],
            ['question' => 'Ehsan\'ın çalışmalarını nasıl takip edebilirim?', 'answer' => 'En yeni yazıları blogda okuyabilir ve sitenin alt kısmındaki sosyal medya bağlantıları üzerinden takip edebilirsiniz.'],
            ['question' => 'Nasıl iletişime geçebilirim?', 'answer' => 'En iyi yol İletişim sayfasıdır — bir mesaj gönderin, Ehsan size dönüş yapsın.'],
        ];

        $enBody = '<p>Answers to common questions about Ehsan Dibazar, the Martial Intelligence approach and the martial arts he writes about. Can\'t find what you\'re looking for? <a href="'.url('/contact').'">Contact Ehsan</a> directly.</p>';
        $trBody = '<p>Ehsan Dibazar, Martial Intelligence yaklaşımı ve hakkında yazdığı dövüş sanatları ile ilgili sık sorulan soruların yanıtları. Aradığınızı bulamadınız mı? Doğrudan <a href="'.url('/tr/contact').'">Ehsan ile iletişime geçin</a>.</p>';

        $this->replaceRow('faq', 'en', 'Istanbul', [
            'body' => $enBody,
            'faqs' => json_encode($en),
            'seo_title' => 'FAQ — Ehsan Dibazar & the Martial Intelligence Approach',
            'meta_description' => 'Common questions about Ehsan Dibazar — martial artist, coach and sport scientist — and the Martial Intelligence approach to self-defense.',
            'meta_keywords' => 'FAQ, Ehsan Dibazar, Martial Intelligence, self-defense, Muay Thai, Brazilian Jiu-Jitsu',
            'og_title' => 'Frequently Asked Questions — Ehsan Dibazar',
            'og_description' => 'Learn about Ehsan Dibazar and the Martial Intelligence approach.',
        ]);

        $this->replaceRow('faq', 'tr', 'İstanbul', [
            'body' => $trBody,
            'faqs' => json_encode($tr),
            'seo_title' => 'SSS — Ehsan Dibazar ve Martial Intelligence Yaklaşımı',
            'meta_description' => 'Dövüş sanatçısı, koç ve spor bilimci Ehsan Dibazar ve kendini savunmaya yönelik Martial Intelligence yaklaşımı hakkında sık sorulan sorular.',
            'meta_keywords' => 'SSS, Ehsan Dibazar, Martial Intelligence, kendini savunma, Muay Thai, Brezilya Jiu-Jitsu',
            'og_title' => 'Sıkça Sorulan Sorular — Ehsan Dibazar',
            'og_description' => 'Ehsan Dibazar ve Martial Intelligence yaklaşımı hakkında bilgi edinin.',
        ]);
    }

    private function replaceTerms(): void
    {
        $enBody = <<<'HTML'
<p>These Terms and Conditions govern your use of trainwithehsan.com, the personal website of Ehsan Dibazar ("we", "us", "our"). By using this site, you agree to these terms.</p>

<h2>About This Site</h2>
<p>trainwithehsan.com is a personal website where Ehsan Dibazar shares his martial-arts background, the Martial Intelligence approach, articles and related content. The site does not sell products or take bookings; it is published for general information.</p>

<h2>Use of Content</h2>
<p>Articles, videos and other material on this site are provided for general informational and educational purposes. Any physical technique described is general information, not personal instruction — see our <a href="/disclaimer">Disclaimer</a> for important detail on physical risk.</p>

<h2>Intellectual Property</h2>
<p>All content on this website — including text, images and videos — is the property of Ehsan Dibazar unless otherwise stated, and may not be copied, redistributed or used commercially without prior written permission.</p>

<h2>Limitation of Liability</h2>
<p>To the fullest extent permitted by law, Ehsan Dibazar is not liable for any indirect, incidental or consequential damages arising from your use of this website, except where such liability cannot be excluded by law.</p>

<h2>Governing Law</h2>
<p>These Terms are governed by the laws of the Republic of Türkiye.</p>

<h2>Changes to These Terms</h2>
<p>We may update these Terms and Conditions from time to time. The "Last updated" date at the top of this page reflects the most recent revision. Continued use of the site after a change means you accept the updated terms.</p>

<h2>Contact</h2>
<p>Questions about these Terms? <a href="/contact">Contact us</a>.</p>
HTML;

        $trBody = <<<'HTML'
<p>Bu Şartlar ve Koşullar, Ehsan Dibazar'ın ("biz", "bize", "bizim") kişisel web sitesi trainwithehsan.com'u kullanımınızı düzenler. Bu siteyi kullanarak bu şartları kabul etmiş olursunuz.</p>

<h2>Bu Site Hakkında</h2>
<p>trainwithehsan.com, Ehsan Dibazar'ın dövüş sanatları geçmişini, Martial Intelligence yaklaşımını, yazılarını ve ilgili içeriği paylaştığı kişisel bir web sitesidir. Site ürün satmaz veya rezervasyon almaz; genel bilgilendirme amacıyla yayınlanır.</p>

<h2>İçeriğin Kullanımı</h2>
<p>Bu sitedeki yazılar, videolar ve diğer materyaller genel bilgilendirme ve eğitim amaçlıdır. Açıklanan herhangi bir fiziksel teknik kişisel eğitim değil genel bilgidir — fiziksel riskle ilgili önemli ayrıntılar için <a href="/tr/disclaimer">Sorumluluk Reddi</a> sayfamıza bakın.</p>

<h2>Fikri Mülkiyet</h2>
<p>Bu web sitesindeki tüm içerik — metinler, görseller ve videolar dahil — aksi belirtilmedikçe Ehsan Dibazar'ın mülkiyetindedir ve önceden yazılı izin olmadan kopyalanamaz, yeniden dağıtılamaz veya ticari amaçla kullanılamaz.</p>

<h2>Sorumluluk Sınırlaması</h2>
<p>Yasaların izin verdiği en geniş ölçüde, Ehsan Dibazar, kanunen hariç tutulamayan durumlar dışında, bu web sitesini kullanmanızdan kaynaklanan dolaylı, arızi veya sonuç niteliğindeki zararlardan sorumlu değildir.</p>

<h2>Geçerli Hukuk</h2>
<p>Bu Şartlar, Türkiye Cumhuriyeti yasalarına tabidir.</p>

<h2>Bu Şartlardaki Değişiklikler</h2>
<p>Bu Şartlar ve Koşulları zaman zaman güncelleyebiliriz. Bu sayfanın üstündeki "Son güncelleme" tarihi en son revizyonu yansıtır. Bir değişiklikten sonra siteyi kullanmaya devam etmeniz, güncellenmiş şartları kabul ettiğiniz anlamına gelir.</p>

<h2>İletişim</h2>
<p>Bu Şartlar hakkında sorularınız mı var? <a href="/tr/contact">Bizimle iletişime geçin</a>.</p>
HTML;

        $this->replaceRow('terms-and-conditions', 'en', 'Istanbul', [
            'body' => $enBody,
            'seo_title' => 'Terms and Conditions — Ehsan Dibazar',
            'meta_description' => 'The terms for using trainwithehsan.com, the personal website of Ehsan Dibazar.',
            'meta_keywords' => 'terms and conditions, terms of use, Ehsan Dibazar',
            'og_title' => 'Terms and Conditions — Ehsan Dibazar',
            'og_description' => 'The terms governing use of this website.',
        ]);

        $this->replaceRow('terms-and-conditions', 'tr', 'İstanbul', [
            'body' => $trBody,
            'seo_title' => 'Şartlar ve Koşullar — Ehsan Dibazar',
            'meta_description' => 'Ehsan Dibazar\'ın kişisel web sitesi trainwithehsan.com\'u kullanmaya ilişkin şartlar.',
            'meta_keywords' => 'şartlar ve koşullar, kullanım koşulları, Ehsan Dibazar',
            'og_title' => 'Şartlar ve Koşullar — Ehsan Dibazar',
            'og_description' => 'Bu web sitesinin kullanımını düzenleyen şartlar.',
        ]);
    }

    private function patchPrivacy(): void
    {
        $this->patchRow('privacy-policy', 'en', [
            'Ehsan Dibazar ("we", "us", "our"), based in Istanbul, Türkiye, collects, uses and protects information when you visit trainwithehsan.com or use our Personal Training, Muay Thai, Brazilian Jiu-Jitsu, Self-Defense and Fitness services.' => 'Ehsan Dibazar ("we", "us", "our") collects, uses and protects information when you visit trainwithehsan.com, subscribe to the newsletter or send a message through the contact form.',
            'understand aggregate site usage and improve our content and services, using the analytics tools described above.' => 'understand aggregate site usage and improve our content, using the analytics tools described above.',
        ]);

        $this->patchRow('privacy-policy', 'tr', [
            'İstanbul, Türkiye merkezli Ehsan Dibazar\'ın ("biz", "bize", "bizim") trainwithehsan.com\'u ziyaret ettiğinizde veya Kişisel Antrenman, Muay Thai, Brezilya Jiu-Jitsu, Kendini Savunma ve Fitness hizmetlerimizi kullandığınızda bilgilerinizi nasıl topladığını, kullandığını ve koruduğunu açıklar.' => 'Ehsan Dibazar\'ın ("biz", "bize", "bizim") trainwithehsan.com\'u ziyaret ettiğinizde, bültene abone olduğunuzda veya iletişim formu aracılığıyla mesaj gönderdiğinizde bilgilerinizi nasıl topladığını, kullandığını ve koruduğunu açıklar.',
            'Yukarıda açıklanan analitik araçları kullanarak toplam site kullanımını anlamak ve içeriğimizi ve hizmetlerimizi geliştirmek için.' => 'Yukarıda açıklanan analitik araçları kullanarak toplam site kullanımını anlamak ve içeriğimizi geliştirmek için.',
        ]);
    }

    private function patchDisclaimer(): void
    {
        $enRiskOld = <<<'HTML'
<h2>Physical Risk in Martial Arts Training</h2>
<p>Muay Thai, Brazilian Jiu-Jitsu, Self-Defense and general fitness training involve physical exertion, contact and an inherent risk of injury. By participating in any session, in person or remote, you acknowledge and accept these risks. Instruction is designed to introduce technique progressively and safely, but no training method can eliminate risk entirely.</p>
HTML;
        $enRiskNew = <<<'HTML'
<h2>Physical Activity and Risk</h2>
<p>Martial arts and fitness activity involve physical exertion and an inherent risk of injury. Information on this site is general and educational; practising any physical technique is done at your own risk, and you should seek qualified, in-person instruction and medical clearance before attempting it.</p>
HTML;

        $this->patchRow('disclaimer', 'en', [
            'The following disclaimer applies to trainwithehsan.com and the Personal Training, Muay Thai, Brazilian Jiu-Jitsu, Self-Defense and Fitness services offered by Ehsan Dibazar, based in Istanbul, Türkiye.' => 'The following disclaimer applies to trainwithehsan.com, the personal website of Ehsan Dibazar.',
            $enRiskOld => $enRiskNew,
            'While we aim to provide effective, high-quality coaching, we do not guarantee specific outcomes' => 'While the content on this site aims to be accurate and useful, we do not guarantee specific outcomes',
        ], [
            'meta_description' => 'Important information about physical risk, medical advice and the limits of any guarantees for the content on trainwithehsan.com.',
        ]);

        $trRiskOld = <<<'HTML'
<h2>Dövüş Sanatları Antrenmanında Fiziksel Risk</h2>
<p>Muay Thai, Brezilya Jiu-Jitsu, Kendini Savunma ve genel fitness antrenmanı fiziksel efor, temas ve doğal bir yaralanma riski içerir. Yüz yüze veya uzaktan herhangi bir seansa katılarak bu riskleri kabul etmiş ve onaylamış olursunuz. Eğitim, tekniği kademeli ve güvenli bir şekilde tanıtacak şekilde tasarlanmıştır, ancak hiçbir antrenman yöntemi riski tamamen ortadan kaldıramaz.</p>
HTML;
        $trRiskNew = <<<'HTML'
<h2>Fiziksel Aktivite ve Risk</h2>
<p>Dövüş sanatları ve fitness aktiviteleri fiziksel efor ve doğal bir yaralanma riski içerir. Bu sitedeki bilgiler genel ve eğitim amaçlıdır; herhangi bir fiziksel tekniği uygulamak kendi sorumluluğunuzdadır ve denemeden önce kalifiye, yüz yüze eğitim ve doktor onayı almalısınız.</p>
HTML;

        $this->patchRow('disclaimer', 'tr', [
            'Aşağıdaki sorumluluk reddi beyanı, İstanbul, Türkiye merkezli Ehsan Dibazar\'ın trainwithehsan.com\'u ve sunduğu Kişisel Antrenman, Muay Thai, Brezilya Jiu-Jitsu, Kendini Savunma ve Fitness hizmetleri için geçerlidir.' => 'Aşağıdaki sorumluluk reddi beyanı, Ehsan Dibazar\'ın kişisel web sitesi trainwithehsan.com için geçerlidir.',
            $trRiskOld => $trRiskNew,
            'Etkili, yüksek kaliteli koçluk sağlamayı amaçlasak da, belirli sonuçları' => 'Bu sitedeki içeriğin doğru ve faydalı olmasını amaçlasak da, belirli sonuçları',
        ], [
            'meta_description' => 'trainwithehsan.com\'daki içerikle ilgili fiziksel risk, tıbbi tavsiye ve garanti sınırları hakkında önemli bilgiler.',
        ]);
    }
};
