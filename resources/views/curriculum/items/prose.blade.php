@php
    $heading = $item->text('heading');
    $headingId = "item-{$item->key}";
@endphp

<section class="mt-14" @if($heading) aria-labelledby="{{ $headingId }}" @endif data-item-type="prose">
    @if($heading)
        @include('curriculum.items._heading', ['id' => $headingId, 'heading' => $heading])
    @endif

    <div class="description item-body mt-4 text-lg leading-relaxed text-brand-ink" data-glossary-scope>{!! $item->text('body') !!}</div>
</section>
