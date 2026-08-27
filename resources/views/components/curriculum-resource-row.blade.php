@props(['trove'])

<div class="flex gap-4 items-start bg-white/60 border border-brand-primary/10 rounded-xl px-5 py-4 hover-effect">
    @if($trove->troveType)
        <span class="grey-badge mt-1 whitespace-nowrap uppercase tracking-wide">{{ $trove->troveType->label }}</span>
    @endif
    <div class="flex-1 min-w-0">
        <div class="font-semibold text-brand-primary">{{ $trove->title }}</div>
        @if($trove->description)
            <div class="text-sm text-gray-600 mt-0.5">{{ Str::limit(strip_tags($trove->description), 110) }}</div>
        @endif
    </div>
    <a href="{{ route('resources.show', $trove->slug) }}"
        class="self-center whitespace-nowrap text-sm font-semibold border border-brand-primary/30 rounded-lg px-4 py-1.5 hover:border-brand-secondary hover:text-brand-secondary transition-colors">
        {{ t('View') }}
    </a>
</div>
