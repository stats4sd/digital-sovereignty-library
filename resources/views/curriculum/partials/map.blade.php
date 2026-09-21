@use('App\Support\Curriculum\MapLayout')
@php
    $nodes = MapLayout::nodes($mapModules->count());
    $trail = MapLayout::trail();
@endphp

<div class="text-center mb-4">
    <h2 class="text-3xl font-bold text-brand-primary mb-2">{{ t('Explore in any order') }}</h2>
    <p class="text-gray-600 max-w-lg mx-auto">
        {{ t('The trail suggests a route, but there\'s no wrong way through. Start wherever makes sense for you.') }}
    </p>
</div>

@if($mapModules->isNotEmpty())
    <div class="relative max-w-4xl mx-auto mt-8 hidden sm:block" style="height: 300px;" data-learning-map data-node-count="{{ $mapModules->count() }}">
        <svg viewBox="0 0 100 100" class="absolute inset-0 w-full h-full" preserveAspectRatio="none" aria-hidden="true">
            {{-- Fixed trail; nodes are spaced evenly along its two rows and the tail points at the toolkit button below. --}}
            <path d="{{ $trail }}" fill="none" stroke="var(--brand-primary)" stroke-width="0.4" stroke-dasharray="2,2" opacity="0.4" />
        </svg>
        @foreach($mapModules as $index => $module)
            <a href="{{ route('curriculum.show', $module->key) }}"
                class="curriculum-node group"
                data-index="{{ $index }}"
                style="left: {{ $nodes[$index]['x'] }}%; top: {{ $nodes[$index]['y'] }}%;">
                <span class="curriculum-node-dot"></span>
                <span class="block text-sm font-semibold leading-snug text-gray-800 group-hover:text-brand-primary">{{ $module->title }}</span>
            </a>
        @endforeach
    </div>

    <div class="sm:hidden flex flex-col gap-3 mt-8">
        @foreach($mapModules as $module)
            <a href="{{ route('curriculum.show', $module->key) }}"
                class="flex items-center gap-3 bg-white/60 border border-brand-primary/10 rounded-xl px-4 py-3 hover-effect">
                <span class="curriculum-node-dot curriculum-node-dot-inline shrink-0"></span>
                <span class="font-semibold text-gray-800">{{ $module->title }}</span>
            </a>
        @endforeach
    </div>
@else
    <p class="mt-8 text-center text-gray-500">{{ t('Learning-map modules are coming soon.') }}</p>
@endif

{{-- Pulled up over the map's empty bottom edge so the trail's tail meets the box mid-height. --}}
<div class="text-center mt-2 {{ $mapModules->isNotEmpty() ? 'sm:-mt-8' : 'sm:mt-6' }}">
    <a href="#toolkit"
        class="inline-flex items-center gap-2 border border-brand-primary/30 text-brand-primary font-semibold px-6 py-3 rounded-lg hover:border-brand-primary transition-colors">
        {{ t('Build your toolkit') }} &darr;
    </a>
</div>
