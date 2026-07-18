{{-- VideoObject JSON-LD — یک منبعِ واحد برای همه‌ی صفحات (خانه/مقاله/صفحه). داده از
     App\Services\Seo\VideoSchemaService می‌آید (کنترلر آن را در $videoSchemas می‌گذارد). خالی که
     باشد هیچ چیزی رندر نمی‌شود، پس کاملاً backward-compatible است. --}}
@foreach(($videoSchemas ?? []) as $__video)
<script type="application/ld+json">{!! json_encode($__video, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
