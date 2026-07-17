<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // صفحات پیش‌فرض «Contact / FAQ / Privacy Policy / Terms & Conditions / Cookie Policy /
    // Disclaimer» — همه از طریق سیستم موجود Pages (جدول pages، همان مدل Page، همان مسیرهای
    // /{slug} و /tr/{slug}) ساخته می‌شوند، نه یک ماژول CMS جدید. اسلاگ هر صفحه در هر دو زبان
    // یکسان است (privacy-policy/en و privacy-policy/tr) — دقیقاً همان قراردادِ unique(slug,
    // locale) که از قبل روی این جدول هست. ردیف انگلیسی اول درج می‌شود، سپس ردیف ترکی با
    // translation_of اشاره به آن — کافی است چون Page::renderShow() هر دو جهت را از طریق
    // belongsTo/hasMany پیدا می‌کند (نگاه کنید به Page::translation()/translations()).
    public function up(): void
    {
        $now = now();

        foreach ($this->pages() as $page) {
            $enId = DB::table('pages')->insertGetId([
                'locale' => 'en',
                'translation_of' => null,
                'title' => $page['en']['title'],
                'slug' => $page['slug'],
                'body' => $page['en']['body'],
                'faqs' => isset($page['en']['faqs']) ? json_encode($page['en']['faqs']) : null,
                'seo_title' => $page['en']['seo_title'],
                'meta_description' => $page['en']['meta_description'],
                'meta_keywords' => $page['en']['meta_keywords'] ?? null,
                'canonical_url' => null,
                'robots' => null,
                'og_title' => $page['en']['og_title'],
                'og_description' => $page['en']['og_description'],
                'image_path' => null,
                'status' => 'published',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('pages')->insert([
                'locale' => 'tr',
                'translation_of' => $enId,
                'title' => $page['tr']['title'],
                'slug' => $page['slug'],
                'body' => $page['tr']['body'],
                'faqs' => isset($page['tr']['faqs']) ? json_encode($page['tr']['faqs']) : null,
                'seo_title' => $page['tr']['seo_title'],
                'meta_description' => $page['tr']['meta_description'],
                'meta_keywords' => $page['tr']['meta_keywords'] ?? null,
                'canonical_url' => null,
                'robots' => null,
                'og_title' => $page['tr']['og_title'],
                'og_description' => $page['tr']['og_description'],
                'image_path' => null,
                'status' => 'published',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('pages')->whereIn('slug', array_column($this->pages(), 'slug'))->delete();
    }

    private function pages(): array
    {
        return [
            $this->contactPage(),
            $this->faqPage(),
            $this->privacyPolicyPage(),
            $this->termsPage(),
            $this->cookiePolicyPage(),
            $this->disclaimerPage(),
        ];
    }

    private function contactPage(): array
    {
        return [
            'slug' => 'contact',
            'en' => [
                'title' => 'Contact',
                'body' => <<<'HTML'
<p>Have a question about Personal Training, Muay Thai, Brazilian Jiu-Jitsu, Self-Defense or Fitness coaching in Istanbul? Reach out — we're happy to help you find the right course or answer any question before you get started.</p>
<p>Fill in the form below with your name, email and message and we'll get back to you as soon as possible.</p>
HTML,
                'seo_title' => 'Contact Ehsan Dibazar — Personal Training & Self-Defense in Istanbul',
                'meta_description' => 'Get in touch with Ehsan Dibazar about Personal Training, Muay Thai, Brazilian Jiu-Jitsu, Self-Defense or Fitness coaching in Istanbul. We reply to every message.',
                'meta_keywords' => 'contact, Ehsan Dibazar, Istanbul, personal training, self-defense, Muay Thai, Brazilian Jiu-Jitsu',
                'og_title' => 'Contact Ehsan Dibazar',
                'og_description' => 'Questions about training in Istanbul? Send us a message and we\'ll get back to you.',
            ],
            'tr' => [
                'title' => 'İletişim',
                'body' => <<<'HTML'
<p>İstanbul'da Kişisel Antrenman, Muay Thai, Brezilya Jiu-Jitsu, Kendini Savunma veya Fitness koçluğu hakkında bir sorunuz mu var? Bize ulaşın — başlamadan önce size doğru dersi bulmanızda veya sorularınızı yanıtlamada memnuniyetle yardımcı oluruz.</p>
<p>Aşağıdaki formu adınız, e-postanız ve mesajınızla doldurun; en kısa sürede size dönüş yapacağız.</p>
HTML,
                'seo_title' => 'Ehsan Dibazar ile İletişime Geçin — İstanbul Kişisel Antrenman ve Kendini Savunma',
                'meta_description' => 'İstanbul\'da Kişisel Antrenman, Muay Thai, Brezilya Jiu-Jitsu, Kendini Savunma veya Fitness koçluğu hakkında Ehsan Dibazar ile iletişime geçin.',
                'meta_keywords' => 'iletişim, Ehsan Dibazar, İstanbul, kişisel antrenman, kendini savunma, Muay Thai, Brezilya Jiu-Jitsu',
                'og_title' => 'Ehsan Dibazar ile İletişime Geçin',
                'og_description' => 'İstanbul\'da antrenman hakkında sorularınız mı var? Bize mesaj gönderin, size dönelim.',
            ],
        ];
    }

    private function faqPage(): array
    {
        $en = [
            ['question' => 'Do I need any prior experience to start?', 'answer' => 'No prior experience is required. Every course is built to welcome complete beginners — no athletic background, no previous martial arts training, and no age limit. Sessions are structured so you can start at your own pace and build up gradually.'],
            ['question' => 'What is included in a Personal Training session?', 'answer' => 'A Personal Training session is a one-on-one program built entirely around your goals, current fitness level and schedule. It typically combines strength and conditioning work with technique from self-defense, Muay Thai or Brazilian Jiu-Jitsu, depending on what you want to focus on.'],
            ['question' => 'What does Muay Thai training involve?', 'answer' => 'Muay Thai training covers striking technique (punches, kicks, elbows and knees), pad work, conditioning and controlled sparring as your skill develops. Classes are structured for both fitness-focused students and those who want to progress further into the sport.'],
            ['question' => 'What is Brazilian Jiu-Jitsu (BJJ) and who is it for?', 'answer' => "Brazilian Jiu-Jitsu is a grappling-based martial art built around leverage and technique rather than raw strength — which is exactly why it's effective for smaller or less physically strong practitioners against a larger opponent. It's suitable for anyone, regardless of size, strength or prior experience."],
            ['question' => 'How is Self-Defense training different from a typical martial arts class?', 'answer' => 'Self-Defense training focuses specifically on real-world situations — recognizing danger early, de-escalation, and decisive, simple techniques that work under pressure rather than a large technical curriculum. This is the core of the Martial Intelligence approach: decision-making under pressure, not just physical technique.'],
            ['question' => 'Can I train just for fitness, without wanting to fight or compete?', 'answer' => 'Absolutely. Many students train purely for the fitness, discipline and confidence that martial arts training builds, with no interest in sparring or competition. Sessions can be adapted entirely around a fitness and conditioning focus.'],
            ['question' => 'Are private, one-on-one sessions available?', 'answer' => "Yes. Private sessions are available for anyone who wants individual attention, a schedule tailored to them, or faster progress on a specific goal, whether that's self-defense, Muay Thai, BJJ or general fitness."],
            ['question' => 'How do I book a session or course?', 'answer' => "The easiest way to book is to send a message through the Contact page with what you're interested in (Personal Training, Muay Thai, BJJ, Self-Defense or Fitness) and your availability, and we'll follow up to arrange the details."],
            ['question' => 'Can I cancel or reschedule a booked session?', 'answer' => 'Yes — just reach out as early as possible through the Contact page so we can find a new time that works. We ask for as much notice as you can give so the time slot can be offered to someone else if needed.'],
            ['question' => 'Where do the classes take place?', 'answer' => "Classes are held in Istanbul, Türkiye. Some training is also available remotely through structured video courses for students who want to train on their own schedule or aren't based in Istanbul."],
            ['question' => 'Is there an age limit, or is training suitable for both women and men?', 'answer' => 'Training is open to both women and men, with no upper age limit — the pace and intensity are adapted to the individual. Complete beginners of any age and background are genuinely welcome.'],
            ['question' => 'What equipment do I need to bring?', 'answer' => "For your first session, comfortable athletic clothing is all you need. Specific equipment (gloves, wraps, a gi for BJJ, etc.) is only needed as you progress into a specific discipline, and recommendations are given once you've chosen your path."],
            ['question' => 'Is training safe? What about the risk of injury?', 'answer' => "Safety is a priority in every session — technique is taught progressively, contact is controlled, and intensity is matched to each student's level. As with any physical activity, some risk of injury exists, which is why beginners are never placed into high-intensity sparring before they're ready. See our Disclaimer page for more on this."],
            ['question' => 'How much do sessions and courses cost?', 'answer' => "Pricing depends on the course, format (group, private, or remote) and the number of sessions. Reach out through the Contact page with what you're interested in and we'll give you a clear, personalized quote."],
        ];

        $tr = [
            ['question' => 'Başlamak için önceden deneyimim olması gerekir mi?', 'answer' => 'Hayır, önceden hiçbir deneyime ihtiyaç yoktur. Her ders tam başlangıç seviyesindeki kişileri karşılayacak şekilde tasarlanmıştır — sporcu geçmişi, önceki dövüş sanatları deneyimi veya yaş sınırı gerekmez. Dersler kendi hızınızda başlayıp kademeli olarak ilerleyebileceğiniz şekilde yapılandırılmıştır.'],
            ['question' => 'Kişisel Antrenman seansına neler dahildir?', 'answer' => 'Kişisel Antrenman, tamamen sizin hedeflerinize, mevcut fitness seviyenize ve programınıza göre kurgulanan birebir bir programdır. Genellikle kuvvet ve kondisyon çalışmasını, odaklanmak istediğiniz alana göre kendini savunma, Muay Thai veya Brezilya Jiu-Jitsu tekniğiyle birleştirir.'],
            ['question' => 'Muay Thai antrenmanı neleri kapsar?', 'answer' => 'Muay Thai antrenmanı vuruş tekniğini (yumruklar, tekmeler, dirsekler ve dizler), pad çalışmasını, kondisyonu ve becerileriniz geliştikçe kontrollü sparingi kapsar. Dersler hem fitness odaklı öğrenciler hem de sporda ilerlemek isteyenler için yapılandırılmıştır.'],
            ['question' => 'Brezilya Jiu-Jitsu (BJJ) nedir ve kimler için uygundur?', 'answer' => 'Brezilya Jiu-Jitsu, ham güç yerine kaldıraç ve teknik üzerine kurulu bir güreş temelli dövüş sanatıdır — bu yüzden daha küçük veya fiziksel olarak daha az güçlü kişiler için daha büyük bir rakibe karşı etkilidir. Boyut, güç veya önceki deneyimden bağımsız olarak herkes için uygundur.'],
            ['question' => 'Kendini Savunma antrenmanı tipik bir dövüş sanatları dersinden nasıl farklıdır?', 'answer' => 'Kendini Savunma antrenmanı özellikle gerçek dünya durumlarına odaklanır — tehlikeyi erkenden fark etmek, gerginliği azaltmak ve geniş bir teknik müfredat yerine baskı altında işe yarayan basit, kararlı tekniklere odaklanmak. Bu, Martial Intelligence yaklaşımının özüdür: sadece fiziksel teknik değil, baskı altında doğru karar verme becerisi.'],
            ['question' => 'Dövüşmek veya yarışmak istemeden, sadece fitness için antrenman yapabilir miyim?', 'answer' => 'Kesinlikle. Birçok öğrenci sparing veya yarışmayla hiç ilgilenmeden, sadece dövüş sanatları antrenmanının getirdiği fitness, disiplin ve özgüven için antrenman yapıyor. Seanslar tamamen fitness ve kondisyon odaklı olacak şekilde uyarlanabilir.'],
            ['question' => 'Birebir özel seanslar mevcut mu?', 'answer' => 'Evet. Bireysel ilgi, kendine özel bir program veya belirli bir hedefte (kendini savunma, Muay Thai, BJJ veya genel fitness) daha hızlı ilerleme isteyen herkes için özel seanslar mevcuttur.'],
            ['question' => 'Bir seans veya kursu nasıl rezerve edebilirim?', 'answer' => 'En kolay yol, İletişim sayfasından ilgilendiğiniz alanı (Kişisel Antrenman, Muay Thai, BJJ, Kendini Savunma veya Fitness) ve müsait olduğunuz zamanları belirten bir mesaj göndermek. Detayları ayarlamak için size dönüş yapacağız.'],
            ['question' => 'Rezerve ettiğim bir seansı iptal edebilir veya erteleyebilir miyim?', 'answer' => 'Evet — sadece mümkün olduğunca erken İletişim sayfası üzerinden bize ulaşın, size uygun yeni bir zaman bulalım. Gerekirse o zaman dilimini başka birine sunabilmemiz için mümkün olduğunca önceden haber vermenizi rica ediyoruz.'],
            ['question' => 'Dersler nerede yapılıyor?', 'answer' => "Dersler İstanbul, Türkiye'de yapılmaktadır. Kendi programında antrenman yapmak isteyen veya İstanbul'da bulunmayan öğrenciler için yapılandırılmış video kurslar aracılığıyla uzaktan antrenman da mevcuttur."],
            ['question' => 'Yaş sınırı var mı, hem kadınlar hem erkekler için uygun mu?', 'answer' => 'Antrenmanlar hem kadınlara hem erkeklere açıktır ve üst yaş sınırı yoktur — tempo ve yoğunluk kişiye göre uyarlanır. Her yaştan ve geçmişten tam başlangıç seviyesindeki kişiler gerçekten memnuniyetle karşılanır.'],
            ['question' => 'Hangi ekipmanı getirmem gerekiyor?', 'answer' => 'İlk seansınız için rahat spor kıyafeti yeterlidir. Belirli ekipmanlar (eldiven, bandaj, BJJ için gi vb.) yalnızca belirli bir disipline ilerledikçe gerekir ve yolunuzu seçtikten sonra öneriler verilir.'],
            ['question' => 'Antrenman güvenli mi? Yaralanma riski nedir?', 'answer' => 'Güvenlik her seansta önceliktir — teknik kademeli olarak öğretilir, temas kontrollüdür ve yoğunluk her öğrencinin seviyesine göre ayarlanır. Her fiziksel aktivitede olduğu gibi bir miktar yaralanma riski vardır; bu yüzden başlangıç seviyesindeki kişiler hazır olmadan yoğun sparinge sokulmaz. Daha fazla bilgi için Sorumluluk Reddi sayfamıza bakın.'],
            ['question' => 'Seans ve kursların ücreti ne kadar?', 'answer' => 'Fiyatlandırma kursa, formata (grup, özel veya uzaktan) ve seans sayısına göre değişir. İletişim sayfasından ilgilendiğiniz konuyu belirterek bize ulaşın, size net ve kişiselleştirilmiş bir teklif verelim.'],
        ];

        return [
            'slug' => 'faq',
            'en' => [
                'title' => 'Frequently Asked Questions',
                'body' => '<p>Answers to the most common questions about Personal Training, Muay Thai, Brazilian Jiu-Jitsu, Self-Defense and Fitness coaching with Ehsan Dibazar in Istanbul. Can\'t find what you\'re looking for? <a href="'.url('/contact').'">Contact us</a> directly.</p>',
                'faqs' => $en,
                'seo_title' => 'FAQ — Personal Training, Muay Thai, BJJ & Self-Defense in Istanbul',
                'meta_description' => 'Answers to common questions about Personal Training, Muay Thai, Brazilian Jiu-Jitsu, Self-Defense and Fitness coaching with Ehsan Dibazar in Istanbul.',
                'meta_keywords' => 'FAQ, frequently asked questions, personal training, self-defense, Muay Thai, Brazilian Jiu-Jitsu, Istanbul',
                'og_title' => 'Frequently Asked Questions — Ehsan Dibazar',
                'og_description' => 'Everything you need to know before starting Personal Training, Muay Thai, BJJ, Self-Defense or Fitness coaching in Istanbul.',
            ],
            'tr' => [
                'title' => 'Sıkça Sorulan Sorular',
                'body' => '<p>İstanbul\'da Ehsan Dibazar ile Kişisel Antrenman, Muay Thai, Brezilya Jiu-Jitsu, Kendini Savunma ve Fitness koçluğu hakkında en sık sorulan soruların yanıtları. Aradığınızı bulamadınız mı? Doğrudan <a href="'.url('/tr/contact').'">bizimle iletişime geçin</a>.</p>',
                'faqs' => $tr,
                'seo_title' => 'SSS — İstanbul Kişisel Antrenman, Muay Thai, BJJ ve Kendini Savunma',
                'meta_description' => 'İstanbul\'da Ehsan Dibazar ile Kişisel Antrenman, Muay Thai, Brezilya Jiu-Jitsu, Kendini Savunma ve Fitness koçluğu hakkında sık sorulan soruların yanıtları.',
                'meta_keywords' => 'SSS, sıkça sorulan sorular, kişisel antrenman, kendini savunma, Muay Thai, Brezilya Jiu-Jitsu, İstanbul',
                'og_title' => 'Sıkça Sorulan Sorular — Ehsan Dibazar',
                'og_description' => 'İstanbul\'da Kişisel Antrenman, Muay Thai, BJJ, Kendini Savunma veya Fitness koçluğuna başlamadan önce bilmeniz gereken her şey.',
            ],
        ];
    }

    private function privacyPolicyPage(): array
    {
        return [
            'slug' => 'privacy-policy',
            'en' => [
                'title' => 'Privacy Policy',
                'body' => <<<'HTML'
<p>This Privacy Policy explains how Ehsan Dibazar ("we", "us", "our"), based in Istanbul, Türkiye, collects, uses and protects information when you visit trainwithehsan.com or use our Personal Training, Muay Thai, Brazilian Jiu-Jitsu, Self-Defense and Fitness services. We aim to comply with both Türkiye's Personal Data Protection Law (KVKK) and, for visitors from the European Economic Area, the General Data Protection Regulation (GDPR).</p>

<h2>Information We Collect</h2>
<p>We collect information you provide directly to us, such as your name and email address when you subscribe to our newsletter, or your name, email address and message when you use the Contact form. We do not require you to create an account or provide payment information through this website.</p>

<h2>Cookies and Analytics</h2>
<p>This site uses cookies for essential functionality and, only after you give consent through our cookie banner, for analytics. We use Google Tag Manager (which may host a Google Analytics 4 configuration) and Microsoft Clarity to understand how visitors use the site — such as which pages are viewed and how visitors navigate — so we can improve the experience. No analytics or tracking script runs until you click "Accept" on the cookie banner; if you click "Decline", none of these tools load. See our <a href="/cookie-policy">Cookie Policy</a> for full details, including how to change your choice.</p>

<h2>Google Search Console</h2>
<p>We use Google Search Console to monitor how our site appears in Google Search results. This tool does not collect any personal data about visitors to our site — it only reports on Google's own search index data.</p>

<h2>Advertising</h2>
<p>If this site displays advertising through Google AdSense in the future, Google may use cookies to serve ads based on your visits to this and other websites. You can opt out of personalized advertising through Google's Ads Settings.</p>

<h2>How We Use Your Information</h2>
<ul>
<li>To respond to messages sent through the Contact form.</li>
<li>To send newsletter emails to subscribers who have confirmed their subscription (double opt-in) — you can unsubscribe at any time using the link in every email.</li>
<li>To understand aggregate site usage and improve our content and services, using the analytics tools described above.</li>
</ul>
<p>We do not sell, rent or trade your personal information to third parties.</p>

<h2>Data Retention</h2>
<p>Contact form messages are retained only as long as needed to respond to your inquiry. Newsletter subscriber data is retained until you unsubscribe. Analytics data is retained according to the retention settings of the respective third-party tool (Google Tag Manager / Analytics, Microsoft Clarity).</p>

<h2>Your Rights</h2>
<p>Under KVKK and GDPR, you have the right to request access to, correction of, or deletion of your personal data, and to object to certain processing. To exercise any of these rights, please contact us through our <a href="/contact">Contact page</a>.</p>

<h2>Changes to This Policy</h2>
<p>We may update this Privacy Policy from time to time. The "Last updated" date at the top of this page reflects the most recent revision.</p>

<h2>Contact</h2>
<p>If you have any questions about this Privacy Policy, please <a href="/contact">contact us</a>.</p>
HTML,
                'seo_title' => 'Privacy Policy — Ehsan Dibazar',
                'meta_description' => 'How Ehsan Dibazar collects, uses and protects your data on trainwithehsan.com, including cookies, analytics and your KVKK/GDPR rights.',
                'meta_keywords' => 'privacy policy, KVKK, GDPR, data protection, Ehsan Dibazar',
                'og_title' => 'Privacy Policy — Ehsan Dibazar',
                'og_description' => 'How we collect, use and protect your data, including cookies and analytics.',
            ],
            'tr' => [
                'title' => 'Gizlilik Politikası',
                'body' => <<<'HTML'
<p>Bu Gizlilik Politikası, İstanbul, Türkiye merkezli Ehsan Dibazar'ın ("biz", "bize", "bizim") trainwithehsan.com'u ziyaret ettiğinizde veya Kişisel Antrenman, Muay Thai, Brezilya Jiu-Jitsu, Kendini Savunma ve Fitness hizmetlerimizi kullandığınızda bilgilerinizi nasıl topladığını, kullandığını ve koruduğunu açıklar. Hem Türkiye'nin Kişisel Verilerin Korunması Kanunu'na (KVKK) hem de Avrupa Ekonomik Alanı'ndan ziyaretçiler için Genel Veri Koruma Yönetmeliği'ne (GDPR) uyum sağlamayı amaçlıyoruz.</p>

<h2>Topladığımız Bilgiler</h2>
<p>Bültenimize abone olduğunuzda adınız ve e-posta adresiniz, İletişim formunu kullandığınızda ise adınız, e-posta adresiniz ve mesajınız gibi doğrudan bize sağladığınız bilgileri topluyoruz. Bu web sitesi üzerinden bir hesap oluşturmanızı veya ödeme bilgisi vermenizi istemiyoruz.</p>

<h2>Çerezler ve Analitik</h2>
<p>Bu site temel işlevsellik için ve yalnızca çerez banner'ımız üzerinden onay verdikten sonra analitik amaçlarla çerezler kullanır. Ziyaretçilerin siteyi nasıl kullandığını (hangi sayfaların görüntülendiği, ziyaretçilerin sitede nasıl gezindiği gibi) anlamak ve deneyimi iyileştirmek için Google Tag Manager (bir Google Analytics 4 yapılandırması barındırabilir) ve Microsoft Clarity kullanıyoruz. Çerez banner'ında "Kabul Et"e tıklamadan hiçbir analitik veya izleme betiği çalışmaz; "Reddet"e tıklarsanız bu araçların hiçbiri yüklenmez. Seçiminizi nasıl değiştireceğiniz de dahil tüm detaylar için <a href="/tr/cookie-policy">Çerez Politikamıza</a> bakın.</p>

<h2>Google Search Console</h2>
<p>Sitemizin Google Arama sonuçlarında nasıl göründüğünü izlemek için Google Search Console kullanıyoruz. Bu araç, sitemizin ziyaretçileri hakkında herhangi bir kişisel veri toplamaz — yalnızca Google'ın kendi arama indeksi verileri hakkında rapor verir.</p>

<h2>Reklamcılık</h2>
<p>Bu site ileride Google AdSense aracılığıyla reklam gösterirse, Google bu ve diğer web sitelerini ziyaretlerinize dayalı reklamlar sunmak için çerezler kullanabilir. Google'ın Reklam Ayarları üzerinden kişiselleştirilmiş reklamlardan çıkabilirsiniz.</p>

<h2>Bilgilerinizi Nasıl Kullanıyoruz</h2>
<ul>
<li>İletişim formu aracılığıyla gönderilen mesajlara yanıt vermek için.</li>
<li>Aboneliğini onaylamış (çift onaylı) bültene kayıtlı kişilere e-posta göndermek için — her e-postadaki bağlantıyı kullanarak istediğiniz zaman abonelikten çıkabilirsiniz.</li>
<li>Yukarıda açıklanan analitik araçları kullanarak toplam site kullanımını anlamak ve içeriğimizi ve hizmetlerimizi geliştirmek için.</li>
</ul>
<p>Kişisel bilgilerinizi üçüncü taraflara satmıyor, kiralamıyor veya takas etmiyoruz.</p>

<h2>Veri Saklama</h2>
<p>İletişim formu mesajları yalnızca talebinize yanıt vermek için gereken süre boyunca saklanır. Bülten abonesi verileri abonelikten çıkana kadar saklanır. Analitik veriler, ilgili üçüncü taraf aracın (Google Tag Manager / Analytics, Microsoft Clarity) saklama ayarlarına göre saklanır.</p>

<h2>Haklarınız</h2>
<p>KVKK ve GDPR kapsamında, kişisel verilerinize erişim talep etme, düzeltme veya silme ve belirli işlemlere itiraz etme hakkına sahipsiniz. Bu haklardan herhangi birini kullanmak için lütfen <a href="/tr/contact">İletişim sayfamız</a> üzerinden bize ulaşın.</p>

<h2>Bu Politikadaki Değişiklikler</h2>
<p>Bu Gizlilik Politikasını zaman zaman güncelleyebiliriz. Bu sayfanın üstündeki "Son güncelleme" tarihi en son revizyonu yansıtır.</p>

<h2>İletişim</h2>
<p>Bu Gizlilik Politikası hakkında sorularınız varsa lütfen <a href="/tr/contact">bizimle iletişime geçin</a>.</p>
HTML,
                'seo_title' => 'Gizlilik Politikası — Ehsan Dibazar',
                'meta_description' => 'Ehsan Dibazar\'ın trainwithehsan.com üzerinde verilerinizi nasıl topladığı, kullandığı ve koruduğu; çerezler, analitik ve KVKK/GDPR haklarınız dahil.',
                'meta_keywords' => 'gizlilik politikası, KVKK, GDPR, veri koruma, Ehsan Dibazar',
                'og_title' => 'Gizlilik Politikası — Ehsan Dibazar',
                'og_description' => 'Verilerinizi nasıl topladığımız, kullandığımız ve koruduğumuz; çerezler ve analitik dahil.',
            ],
        ];
    }

    private function termsPage(): array
    {
        return [
            'slug' => 'terms-and-conditions',
            'en' => [
                'title' => 'Terms and Conditions',
                'body' => <<<'HTML'
<p>These Terms and Conditions govern your use of trainwithehsan.com and the Personal Training, Muay Thai, Brazilian Jiu-Jitsu, Self-Defense and Fitness services offered by Ehsan Dibazar ("we", "us", "our"), based in Istanbul, Türkiye. By using this site or booking a session, you agree to these terms.</p>

<h2>Our Services</h2>
<p>We provide in-person and remote coaching in Personal Training, Muay Thai, Brazilian Jiu-Jitsu, Self-Defense and Fitness, delivered in Istanbul and, for some courses, through structured video training. Course content, availability and format may change over time.</p>

<h2>Assumption of Risk</h2>
<p>Martial arts, self-defense and fitness training involve physical activity and inherent risk of injury. By participating, you confirm that you are physically able to take part, and you accept the risks involved. If you have any medical condition, please consult a doctor before starting training and inform us before your first session. See our <a href="/disclaimer">Disclaimer</a> for more detail.</p>

<h2>Booking, Payment and Cancellation</h2>
<p>Sessions and courses are booked directly through the <a href="/contact">Contact page</a>. Specific pricing, payment terms and cancellation notice will be confirmed with you individually when you book, since these vary by course format and session count.</p>

<h2>Code of Conduct</h2>
<p>All students are expected to treat instructors and fellow students with respect, follow safety instructions during training, and behave appropriately during sessions. We reserve the right to refuse or end a training relationship with anyone who does not follow these expectations.</p>

<h2>Intellectual Property</h2>
<p>All content on this website — including text, images, videos and course materials — is the property of Ehsan Dibazar unless otherwise stated, and may not be copied, redistributed or used commercially without prior written permission.</p>

<h2>Limitation of Liability</h2>
<p>To the fullest extent permitted by law, Ehsan Dibazar is not liable for any indirect, incidental or consequential damages arising from your use of this website or participation in training, except where such liability cannot be excluded by law.</p>

<h2>Governing Law</h2>
<p>These Terms are governed by the laws of the Republic of Türkiye.</p>

<h2>Changes to These Terms</h2>
<p>We may update these Terms and Conditions from time to time. The "Last updated" date at the top of this page reflects the most recent revision. Continued use of the site or our services after a change means you accept the updated terms.</p>

<h2>Contact</h2>
<p>Questions about these Terms? <a href="/contact">Contact us</a>.</p>
HTML,
                'seo_title' => 'Terms and Conditions — Ehsan Dibazar',
                'meta_description' => 'The terms and conditions for using trainwithehsan.com and booking Personal Training, Muay Thai, BJJ, Self-Defense or Fitness sessions with Ehsan Dibazar.',
                'meta_keywords' => 'terms and conditions, terms of service, Ehsan Dibazar',
                'og_title' => 'Terms and Conditions — Ehsan Dibazar',
                'og_description' => 'The terms governing use of this website and our training services.',
            ],
            'tr' => [
                'title' => 'Şartlar ve Koşullar',
                'body' => <<<'HTML'
<p>Bu Şartlar ve Koşullar, İstanbul, Türkiye merkezli Ehsan Dibazar'ın ("biz", "bize", "bizim") trainwithehsan.com'u kullanımınızı ve sunduğu Kişisel Antrenman, Muay Thai, Brezilya Jiu-Jitsu, Kendini Savunma ve Fitness hizmetlerini düzenler. Bu siteyi kullanarak veya bir seans rezervasyonu yaparak bu şartları kabul etmiş olursunuz.</p>

<h2>Hizmetlerimiz</h2>
<p>İstanbul'da yüz yüze ve bazı kurslar için yapılandırılmış video eğitimi aracılığıyla uzaktan Kişisel Antrenman, Muay Thai, Brezilya Jiu-Jitsu, Kendini Savunma ve Fitness koçluğu sunuyoruz. Kurs içeriği, müsaitlik ve format zaman içinde değişebilir.</p>

<h2>Risk Kabulü</h2>
<p>Dövüş sanatları, kendini savunma ve fitness antrenmanı fiziksel aktivite ve doğal bir yaralanma riski içerir. Katılarak, fiziksel olarak katılmaya uygun olduğunuzu onaylar ve ilgili riskleri kabul edersiniz. Herhangi bir sağlık durumunuz varsa, lütfen antrenmana başlamadan önce bir doktora danışın ve ilk seansınızdan önce bize bildirin. Daha fazla ayrıntı için <a href="/tr/disclaimer">Sorumluluk Reddi</a> sayfamıza bakın.</p>

<h2>Rezervasyon, Ödeme ve İptal</h2>
<p>Seanslar ve kurslar doğrudan <a href="/tr/contact">İletişim sayfası</a> üzerinden rezerve edilir. Belirli fiyatlandırma, ödeme koşulları ve iptal bildirimi, kurs formatına ve seans sayısına göre değiştiğinden, rezervasyon yaptığınızda sizinle ayrı ayrı teyit edilecektir.</p>

<h2>Davranış Kuralları</h2>
<p>Tüm öğrencilerin eğitmenlere ve diğer öğrencilere saygılı davranması, antrenman sırasında güvenlik talimatlarına uyması ve seanslar sırasında uygun şekilde davranması beklenir. Bu beklentilere uymayan herkesle antrenman ilişkisini reddetme veya sonlandırma hakkımızı saklı tutarız.</p>

<h2>Fikri Mülkiyet</h2>
<p>Bu web sitesindeki tüm içerik — metinler, görseller, videolar ve kurs materyalleri dahil — aksi belirtilmedikçe Ehsan Dibazar'ın mülkiyetindedir ve önceden yazılı izin olmadan kopyalanamaz, yeniden dağıtılamaz veya ticari amaçla kullanılamaz.</p>

<h2>Sorumluluk Sınırlaması</h2>
<p>Yasaların izin verdiği en geniş ölçüde, Ehsan Dibazar, kanunen hariç tutulamayan durumlar dışında, bu web sitesini kullanmanızdan veya antrenmana katılmanızdan kaynaklanan dolaylı, arızi veya sonuç niteliğindeki zararlardan sorumlu değildir.</p>

<h2>Geçerli Hukuk</h2>
<p>Bu Şartlar, Türkiye Cumhuriyeti yasalarına tabidir.</p>

<h2>Bu Şartlardaki Değişiklikler</h2>
<p>Bu Şartlar ve Koşulları zaman zaman güncelleyebiliriz. Bu sayfanın üstündeki "Son güncelleme" tarihi en son revizyonu yansıtır. Bir değişiklikten sonra siteyi veya hizmetlerimizi kullanmaya devam etmeniz, güncellenmiş şartları kabul ettiğiniz anlamına gelir.</p>

<h2>İletişim</h2>
<p>Bu Şartlar hakkında sorularınız mı var? <a href="/tr/contact">Bizimle iletişime geçin</a>.</p>
HTML,
                'seo_title' => 'Şartlar ve Koşullar — Ehsan Dibazar',
                'meta_description' => 'trainwithehsan.com kullanımı ve Ehsan Dibazar ile Kişisel Antrenman, Muay Thai, BJJ, Kendini Savunma veya Fitness seansı rezervasyonu için şartlar ve koşullar.',
                'meta_keywords' => 'şartlar ve koşullar, kullanım koşulları, Ehsan Dibazar',
                'og_title' => 'Şartlar ve Koşullar — Ehsan Dibazar',
                'og_description' => 'Bu web sitesinin ve antrenman hizmetlerimizin kullanımını düzenleyen şartlar.',
            ],
        ];
    }

    private function cookiePolicyPage(): array
    {
        return [
            'slug' => 'cookie-policy',
            'en' => [
                'title' => 'Cookie Policy',
                'body' => <<<'HTML'
<p>This Cookie Policy explains how Ehsan Dibazar uses cookies and similar technologies on trainwithehsan.com, and how you can control them.</p>

<h2>What Are Cookies?</h2>
<p>Cookies are small text files placed on your device when you visit a website. They are widely used to make websites work, and to provide information to the site owner about how visitors use the site.</p>

<h2>Cookies We Use</h2>
<ul>
<li><strong>Necessary cookies</strong> — required for the site to function correctly (for example, to remember whether you've accepted or declined our cookie banner).</li>
<li><strong>Analytics cookies</strong> — set by Google Tag Manager (which may host a Google Analytics 4 configuration) and Microsoft Clarity, used to understand how visitors use the site so we can improve it. These are only set after you click "Accept" on the cookie consent banner.</li>
</ul>
<p>We do not load any analytics or tracking script until you actively consent. If you click "Decline", these tools never load, and the banner will not be shown to you again on future visits.</p>

<h2>Your Choice</h2>
<p>The first time you visit this site, a banner at the bottom of the page lets you Accept or Decline non-essential cookies. Your choice is remembered in your browser (via local storage) so you are not asked again. To change your mind, clear your browser's site data for trainwithehsan.com, or adjust your browser's cookie settings directly — most browsers let you block or delete cookies through their settings menu.</p>

<h2>Third-Party Cookies</h2>
<p>Google (Tag Manager / Analytics) and Microsoft (Clarity) may set their own cookies once you've consented, governed by their own privacy policies. See <a href="https://policies.google.com/privacy" rel="noopener" target="_blank">Google's Privacy Policy</a> and <a href="https://privacy.microsoft.com/privacystatement" rel="noopener" target="_blank">Microsoft's Privacy Statement</a> for details.</p>

<h2>More Information</h2>
<p>For more on how we handle your data generally, see our <a href="/privacy-policy">Privacy Policy</a>. For questions about this Cookie Policy, please <a href="/contact">contact us</a>.</p>
HTML,
                'seo_title' => 'Cookie Policy — Ehsan Dibazar',
                'meta_description' => 'How Ehsan Dibazar uses cookies on trainwithehsan.com, including analytics cookies and how to accept, decline or change your choice.',
                'meta_keywords' => 'cookie policy, cookies, Ehsan Dibazar',
                'og_title' => 'Cookie Policy — Ehsan Dibazar',
                'og_description' => 'How we use cookies, and how to control your choice.',
            ],
            'tr' => [
                'title' => 'Çerez Politikası',
                'body' => <<<'HTML'
<p>Bu Çerez Politikası, Ehsan Dibazar'ın trainwithehsan.com'da çerezleri ve benzer teknolojileri nasıl kullandığını ve bunları nasıl kontrol edebileceğinizi açıklar.</p>

<h2>Çerez Nedir?</h2>
<p>Çerezler, bir web sitesini ziyaret ettiğinizde cihazınıza yerleştirilen küçük metin dosyalarıdır. Web sitelerinin çalışmasını sağlamak ve site sahibine ziyaretçilerin siteyi nasıl kullandığı hakkında bilgi vermek için yaygın olarak kullanılırlar.</p>

<h2>Kullandığımız Çerezler</h2>
<ul>
<li><strong>Gerekli çerezler</strong> — sitenin doğru çalışması için gereklidir (örneğin, çerez banner'ımızı kabul edip etmediğinizi hatırlamak için).</li>
<li><strong>Analitik çerezler</strong> — Google Tag Manager (bir Google Analytics 4 yapılandırması barındırabilir) ve Microsoft Clarity tarafından, ziyaretçilerin siteyi nasıl kullandığını anlamak ve iyileştirmek için ayarlanır. Bunlar yalnızca çerez onay banner'ında "Kabul Et"e tıkladıktan sonra ayarlanır.</li>
</ul>
<p>Siz aktif olarak onay vermeden hiçbir analitik veya izleme betiği yüklemiyoruz. "Reddet"e tıklarsanız bu araçlar hiçbir zaman yüklenmez ve banner gelecek ziyaretlerinizde size tekrar gösterilmez.</p>

<h2>Seçiminiz</h2>
<p>Bu siteyi ilk ziyaret ettiğinizde, sayfanın altındaki bir banner size zorunlu olmayan çerezleri Kabul Etme veya Reddetme seçeneği sunar. Seçiminiz tarayıcınızda (yerel depolama aracılığıyla) hatırlanır, böylece tekrar sorulmaz. Fikrinizi değiştirmek için trainwithehsan.com için tarayıcınızın site verilerini temizleyin veya doğrudan tarayıcınızın çerez ayarlarını değiştirin — çoğu tarayıcı, ayarlar menüsü aracılığıyla çerezleri engellemenize veya silmenize izin verir.</p>

<h2>Üçüncü Taraf Çerezleri</h2>
<p>Onay verdikten sonra Google (Tag Manager / Analytics) ve Microsoft (Clarity) kendi çerezlerini kendi gizlilik politikalarına tabi olarak ayarlayabilir. Ayrıntılar için <a href="https://policies.google.com/privacy" rel="noopener" target="_blank">Google Gizlilik Politikası</a>'na ve <a href="https://privacy.microsoft.com/privacystatement" rel="noopener" target="_blank">Microsoft Gizlilik Bildirimi</a>'ne bakın.</p>

<h2>Daha Fazla Bilgi</h2>
<p>Verilerinizi genel olarak nasıl işlediğimiz hakkında daha fazla bilgi için <a href="/tr/privacy-policy">Gizlilik Politikamıza</a> bakın. Bu Çerez Politikası hakkında sorularınız için lütfen <a href="/tr/contact">bizimle iletişime geçin</a>.</p>
HTML,
                'seo_title' => 'Çerez Politikası — Ehsan Dibazar',
                'meta_description' => 'Ehsan Dibazar\'ın trainwithehsan.com üzerinde çerezleri nasıl kullandığı; analitik çerezler ve seçiminizi nasıl kabul edeceğiniz, reddedeceğiniz veya değiştireceğiniz dahil.',
                'meta_keywords' => 'çerez politikası, çerezler, Ehsan Dibazar',
                'og_title' => 'Çerez Politikası — Ehsan Dibazar',
                'og_description' => 'Çerezleri nasıl kullandığımız ve seçiminizi nasıl kontrol edeceğiniz.',
            ],
        ];
    }

    private function disclaimerPage(): array
    {
        return [
            'slug' => 'disclaimer',
            'en' => [
                'title' => 'Disclaimer',
                'body' => <<<'HTML'
<p>The following disclaimer applies to trainwithehsan.com and the Personal Training, Muay Thai, Brazilian Jiu-Jitsu, Self-Defense and Fitness services offered by Ehsan Dibazar, based in Istanbul, Türkiye.</p>

<h2>Not Medical or Professional Advice</h2>
<p>Content on this website — including articles, videos and course material — is provided for general informational and educational purposes only. It is not a substitute for professional medical advice, diagnosis or treatment. Always consult a qualified physician before starting any new physical training program, especially if you have an existing medical condition, injury or health concern.</p>

<h2>Physical Risk in Martial Arts Training</h2>
<p>Muay Thai, Brazilian Jiu-Jitsu, Self-Defense and general fitness training involve physical exertion, contact and an inherent risk of injury. By participating in any session, in person or remote, you acknowledge and accept these risks. Instruction is designed to introduce technique progressively and safely, but no training method can eliminate risk entirely.</p>

<h2>No Guarantee of Results</h2>
<p>While we aim to provide effective, high-quality coaching, we do not guarantee specific outcomes — whether physical fitness results, skill acquisition, competition results, or self-defense effectiveness in a real-world scenario. Results depend on individual effort, consistency, physical condition and other factors outside our control.</p>

<h2>Self-Defense Is Not a Guarantee of Safety</h2>
<p>Self-defense training is intended to improve awareness, decision-making and physical capability under pressure. It is not a guarantee of safety in any specific real-world confrontation, and no training can promise a specific outcome in a dangerous situation.</p>

<h2>External Links</h2>
<p>This website may contain links to third-party websites (such as Instagram or YouTube). We are not responsible for the content, accuracy or practices of any external site.</p>

<h2>Limitation of Liability</h2>
<p>To the fullest extent permitted by law, Ehsan Dibazar is not liable for any injury, loss or damage arising from participation in training, use of information on this website, or reliance on any content published here.</p>

<h2>Contact</h2>
<p>Questions about this Disclaimer? <a href="/contact">Contact us</a>.</p>
HTML,
                'seo_title' => 'Disclaimer — Ehsan Dibazar',
                'meta_description' => 'Important information about physical risk, medical advice and the limits of guarantees related to Personal Training, Muay Thai, BJJ, Self-Defense and Fitness coaching.',
                'meta_keywords' => 'disclaimer, risk, Ehsan Dibazar',
                'og_title' => 'Disclaimer — Ehsan Dibazar',
                'og_description' => 'Important information about physical risk and the limits of guarantees in our training services.',
            ],
            'tr' => [
                'title' => 'Sorumluluk Reddi',
                'body' => <<<'HTML'
<p>Aşağıdaki sorumluluk reddi beyanı, İstanbul, Türkiye merkezli Ehsan Dibazar'ın trainwithehsan.com'u ve sunduğu Kişisel Antrenman, Muay Thai, Brezilya Jiu-Jitsu, Kendini Savunma ve Fitness hizmetleri için geçerlidir.</p>

<h2>Tıbbi veya Profesyonel Tavsiye Değildir</h2>
<p>Bu web sitesindeki içerik — makaleler, videolar ve kurs materyalleri dahil — yalnızca genel bilgilendirme ve eğitim amaçlıdır. Profesyonel tıbbi tavsiye, teşhis veya tedavinin yerini tutmaz. Herhangi bir yeni fiziksel antrenman programına başlamadan önce, özellikle mevcut bir sağlık durumunuz, yaralanmanız veya sağlık endişeniz varsa, her zaman kalifiye bir doktora danışın.</p>

<h2>Dövüş Sanatları Antrenmanında Fiziksel Risk</h2>
<p>Muay Thai, Brezilya Jiu-Jitsu, Kendini Savunma ve genel fitness antrenmanı fiziksel efor, temas ve doğal bir yaralanma riski içerir. Yüz yüze veya uzaktan herhangi bir seansa katılarak bu riskleri kabul etmiş ve onaylamış olursunuz. Eğitim, tekniği kademeli ve güvenli bir şekilde tanıtacak şekilde tasarlanmıştır, ancak hiçbir antrenman yöntemi riski tamamen ortadan kaldıramaz.</p>

<h2>Sonuç Garantisi Yoktur</h2>
<p>Etkili, yüksek kaliteli koçluk sağlamayı amaçlasak da, belirli sonuçları — fiziksel fitness sonuçları, beceri kazanımı, yarışma sonuçları veya gerçek dünya senaryosunda kendini savunma etkinliği olsun — garanti etmiyoruz. Sonuçlar bireysel çabaya, tutarlılığa, fiziksel duruma ve kontrolümüz dışındaki diğer faktörlere bağlıdır.</p>

<h2>Kendini Savunma Güvenlik Garantisi Değildir</h2>
<p>Kendini savunma antrenmanı, baskı altında farkındalığı, karar vermeyi ve fiziksel yetenekleri geliştirmeyi amaçlar. Belirli bir gerçek dünya çatışmasında güvenlik garantisi değildir ve hiçbir antrenman tehlikeli bir durumda belirli bir sonucu garanti edemez.</p>

<h2>Harici Bağlantılar</h2>
<p>Bu web sitesi üçüncü taraf web sitelerine (Instagram veya YouTube gibi) bağlantılar içerebilir. Herhangi bir harici sitenin içeriğinden, doğruluğundan veya uygulamalarından sorumlu değiliz.</p>

<h2>Sorumluluk Sınırlaması</h2>
<p>Yasaların izin verdiği en geniş ölçüde, Ehsan Dibazar, antrenmana katılımdan, bu web sitesindeki bilgilerin kullanımından veya burada yayınlanan herhangi bir içeriğe güvenilmesinden kaynaklanan hiçbir yaralanma, kayıp veya zarardan sorumlu değildir.</p>

<h2>İletişim</h2>
<p>Bu Sorumluluk Reddi hakkında sorularınız mı var? <a href="/tr/contact">Bizimle iletişime geçin</a>.</p>
HTML,
                'seo_title' => 'Sorumluluk Reddi — Ehsan Dibazar',
                'meta_description' => 'Kişisel Antrenman, Muay Thai, BJJ, Kendini Savunma ve Fitness koçluğuyla ilgili fiziksel risk, tıbbi tavsiye ve garanti sınırları hakkında önemli bilgiler.',
                'meta_keywords' => 'sorumluluk reddi, risk, Ehsan Dibazar',
                'og_title' => 'Sorumluluk Reddi — Ehsan Dibazar',
                'og_description' => 'Antrenman hizmetlerimizdeki fiziksel risk ve garanti sınırları hakkında önemli bilgiler.',
            ],
        ];
    }
};
