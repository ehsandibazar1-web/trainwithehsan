{{-- آیکن شبکه‌های اجتماعی فوتر — $links آرایه‌ای از URL؛ آیکن از روی دامنه انتخاب می‌شود --}}
<ul class="footer-socials">
    @foreach($links as $url)
        @php($h = strtolower((string) parse_url($url, PHP_URL_HOST)))
        @php($name = str_contains($h, 'instagram') ? 'Instagram' : (str_contains($h, 'youtu') ? 'YouTube' : ((str_contains($h, 't.me') || str_contains($h, 'telegram')) ? 'Telegram' : ((str_contains($h, 'wa.me') || str_contains($h, 'whatsapp')) ? 'WhatsApp' : ucfirst(preg_replace('/^www\./', '', $h))))))
        <li><a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $name }}">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            @if($name === 'Instagram')
                <rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
            @elseif($name === 'YouTube')
                <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/>
            @elseif($name === 'Telegram')
                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            @elseif($name === 'WhatsApp')
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
            @else
                <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            @endif
            </svg>
        </a></li>
    @endforeach
</ul>
