@php $intro = $item->intro; @endphp

<section class="mt-10" data-item-type="trove">
    @if(filled($intro))
        <div class="description item-body mb-4 text-lg leading-relaxed text-brand-ink" data-glossary-scope>{!! $intro !!}</div>
    @endif

    <x-curriculum-resource-row :trove="$item->trove" />
</section>
