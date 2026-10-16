@php
    $url = $item->imageUrl();
    $size = in_array($item->config['size'] ?? null, ['wide', 'medium', 'small'], true) ? $item->config['size'] : 'wide';
    $sizeClass = match ($size) {
        'medium' => 'max-w-2xl mx-auto',
        'small' => 'max-w-md mx-auto',
        default => '',
    };
    $caption = $item->text('caption');
@endphp

@if($url)
    <figure class="mt-10 {{ $sizeClass }}" data-item-type="image" data-image-size="{{ $size }}">
        <img src="{{ $url }}" loading="lazy" alt="{{ $item->text('alt') }}" class="w-full h-auto rounded-2xl border-2 border-brand-line">

        @if(filled($caption))
            <figcaption class="mt-3 text-sm text-brand-footer-text" data-glossary-scope>{{ $caption }}</figcaption>
        @endif
    </figure>
@endif
