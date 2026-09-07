@php
    $mapLayout = [
        ['key' => 'digital-landscape', 'x' => 12, 'y' => 12],
        ['key' => 'knowledge-justice', 'x' => 46, 'y' => 12],
        ['key' => 'community-needs', 'x' => 80, 'y' => 12],
        ['key' => 'tech-assessment', 'x' => 60, 'y' => 55],
        ['key' => 'tech-strategy', 'x' => 26, 'y' => 55],
    ];
@endphp

<div class="text-center mb-4">
    <h2 class="text-3xl font-bold text-brand-primary mb-2">{{ t('Explore in any order') }}</h2>
    <p class="text-gray-600 max-w-lg mx-auto">
        {{ t('The trail suggests a route, but there\'s no wrong way through. Start wherever makes sense for you.') }}
    </p>
</div>

<div class="relative max-w-4xl mx-auto mt-8 hidden sm:block" style="height: 300px;">
    <svg viewBox="0 0 100 100" class="absolute inset-0 w-full h-full" preserveAspectRatio="none" aria-hidden="true">
        {{-- Trail: arcs above the dots within a row, swings around the outside margins
             between rows (clear of the labels hanging below each dot), then runs down
             through the empty lower half to point at the toolkit button below. --}}
        <path d="M12,12 C23,7 35,7 46,12 C57,7 69,7 80,12 C94,14 98,42 88,53 C78,55 70,55 60,55 C48,55 38,55 26,55 C10,55 4,72 16,86 C21,92 28,96 36,98"
            fill="none" stroke="var(--brand-primary)" stroke-width="0.4" stroke-dasharray="2,2" opacity="0.4" />
    </svg>
    @foreach($mapLayout as $node)
        @if($mapModules->has($node['key']))
            @php $module = $mapModules[$node['key']]; @endphp
            <a href="{{ route('curriculum.show', $node['key']) }}"
                class="curriculum-node group"
                style="left: {{ $node['x'] }}%; top: {{ $node['y'] }}%;">
                <span class="curriculum-node-dot"></span>
                <span class="block text-sm font-semibold leading-snug text-gray-800 group-hover:text-brand-primary">{{ $module->title }}</span>
                @if($node['optional'] ?? false)
                    <span class="optional-pill mt-1">{{ t('Optional') }}</span>
                @endif
            </a>
        @endif
    @endforeach
</div>

<div class="sm:hidden flex flex-col gap-3 mt-8">
    @foreach($mapLayout as $node)
        @if($mapModules->has($node['key']))
            @php $module = $mapModules[$node['key']]; @endphp
            <a href="{{ route('curriculum.show', $node['key']) }}"
                class="flex items-center gap-3 bg-white/60 border border-brand-primary/10 rounded-xl px-4 py-3 hover-effect">
                <span class="curriculum-node-dot curriculum-node-dot-inline shrink-0"></span>
                <span class="font-semibold text-gray-800">{{ $module->title }}</span>
                @if($node['optional'] ?? false)
                    <span class="optional-pill ml-auto shrink-0">{{ t('Optional') }}</span>
                @endif
            </a>
        @endif
    @endforeach
</div>

{{-- Pulled up over the map's empty bottom edge so the trail's tail meets the box mid-height. --}}
<div class="text-center mt-2 sm:-mt-8">
    <a href="#toolkit"
        class="inline-flex items-center gap-2 border border-brand-primary/30 text-brand-primary font-semibold px-6 py-3 rounded-lg hover:border-brand-primary transition-colors">
        {{ t('Build your toolkit') }} &darr;
    </a>
</div>
