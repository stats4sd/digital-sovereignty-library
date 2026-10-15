@php
    $trove = $item->trove;
    $intro = $item->intro;
    $headingId = "item-{$item->key}";
@endphp

<section class="mt-10 rounded-2xl border-2 border-brand-line bg-brand-surface shadow-sm overflow-hidden"
    aria-labelledby="{{ $headingId }}" data-item-type="trove">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b-2 border-brand-line bg-brand-surface-muted px-6 py-4 sm:px-8">
        @if($trove->troveType)
            <span class="grey-badge whitespace-nowrap uppercase tracking-wide">{{ $trove->troveType->label }}</span>
        @endif
        <h2 id="{{ $headingId }}" class="min-w-0 flex-1 text-lg font-bold leading-snug text-brand-primary">{{ $trove->title }}</h2>
        <a href="{{ route('resources.show', $trove->slug) }}"
            class="whitespace-nowrap text-sm font-semibold border border-brand-primary/30 rounded-lg px-4 py-1.5 hover:border-brand-secondary hover:text-brand-secondary transition-colors">
            {{ t('View in library') }}
        </a>
    </div>

    <div class="px-6 py-6 sm:px-8">
        @if(filled($intro))
            <div class="description item-body text-lg leading-relaxed text-brand-ink" data-glossary-scope>{!! $intro !!}</div>
        @endif

        <x-trove-content :trove="$trove" :wide="true" :class="filled($intro) ? 'mt-6' : ''" />
    </div>
</section>
