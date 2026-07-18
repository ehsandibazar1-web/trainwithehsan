{{-- Click-to-load facade برای embedهای درون‌متنی (یوتیوب/ویمئو/فایلِ خودمیزبان) — سرور فقط یک
     placeholder می‌فرستد (App\Services\Content\EmbedRenderer)؛ پخش‌کننده‌ی واقعی اینجا و فقط هنگامِ
     کلیک/Enter ساخته می‌شود، پس هیچ منبعِ ثالثی پیش از تعاملِ کاربر بارگذاری نمی‌شود (حریمِ خصوصی +
     Core Web Vitals). قابِ ۱۶:۹ رزرو شده تا با ساختِ پخش‌کننده جابه‌جاییِ چیدمان (CLS) رخ ندهد. --}}
<style>
    .twe-embed{
        position:relative;display:flex;align-items:center;justify-content:center;gap:.6rem;
        width:100%;aspect-ratio:16/9;margin:1.5rem 0;border-radius:.75rem;cursor:pointer;
        background:#101013;color:#fff;border:1px solid rgba(255,255,255,.08);overflow:hidden;
        flex-direction:column;text-align:center;
    }
    .twe-embed--audio{aspect-ratio:auto;min-height:72px;flex-direction:row}
    .twe-embed__play{
        width:64px;height:64px;border-radius:9999px;background:var(--gold,#d9bb75);
        display:flex;align-items:center;justify-content:center;flex:none;transition:transform .15s ease;
    }
    .twe-embed--audio .twe-embed__play{width:44px;height:44px}
    .twe-embed__play::after{content:"";display:block;margin-left:4px;border-style:solid;border-width:12px 0 12px 20px;border-color:transparent transparent transparent #101013}
    .twe-embed--audio .twe-embed__play::after{border-width:8px 0 8px 13px;margin-left:3px}
    .twe-embed__label{font-size:.85rem;color:rgba(255,255,255,.85);letter-spacing:.02em}
    .twe-embed:hover .twe-embed__play,.twe-embed:focus-visible .twe-embed__play{transform:scale(1.08)}
    .twe-embed:focus-visible{outline:2px solid var(--gold,#d9bb75);outline-offset:2px}
    .twe-embed iframe,.twe-embed video{position:absolute;inset:0;width:100%;height:100%;border:0}
    .twe-embed--audio.twe-embed--loaded{background:transparent;border:0;min-height:0;padding:0}
    .twe-embed--audio audio{width:100%}
    @media (prefers-reduced-motion: reduce){.twe-embed__play{transition:none}}
</style>
<script>
    (function () {
        // منبعِ ثالث فقط هنگامِ تعامل ساخته می‌شود — نه در زمانِ بارگذاریِ صفحه
        function activate(el) {
            if (el.dataset.embedLoaded) return;
            el.dataset.embedLoaded = '1';
            var kind = el.getAttribute('data-embed-kind');
            var src = el.getAttribute('data-embed-src');
            if (!src) return;

            var node;
            if (kind === 'iframe') {
                node = document.createElement('iframe');
                node.setAttribute('src', src);
                node.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen');
                node.setAttribute('allowfullscreen', '');
                node.setAttribute('loading', 'lazy');
                node.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
                node.setAttribute('title', el.getAttribute('aria-label') || 'Embedded media');
            } else {
                node = document.createElement(kind); // video | audio
                node.setAttribute('src', src);
                node.setAttribute('controls', '');
                node.setAttribute('autoplay', '');
                node.setAttribute('playsinline', '');
                node.setAttribute('preload', 'metadata');
            }

            el.innerHTML = '';
            el.appendChild(node);
            el.classList.add('twe-embed--loaded');
            el.removeAttribute('role');
            el.removeAttribute('tabindex');
            el.style.cursor = 'default';
        }

        document.addEventListener('click', function (e) {
            var el = e.target.closest ? e.target.closest('.twe-embed') : null;
            if (el && !el.dataset.embedLoaded) activate(el);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') return;
            var el = e.target.closest ? e.target.closest('.twe-embed') : null;
            if (el && !el.dataset.embedLoaded) { e.preventDefault(); activate(el); }
        });
    })();
</script>
