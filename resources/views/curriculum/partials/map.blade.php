@use('App\Support\Curriculum\MapLayout')
@php
    $nodes = MapLayout::nodes($mapModules->count());
    $trail = MapLayout::trail();
    $end = MapLayout::end();
    $flagIcon = 'M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5';
@endphp

<div class="text-center mb-4">
    <h2 class="text-3xl font-bold text-brand-primary mb-2">{{ t('1. Take the Online Course') }}</h2>
    <p class="text-gray-600 max-w-lg mx-auto">
        {{ t('Before you jump into build your toolkit, we recommend this path to help you learn the concepts and understand your own context. The trail suggests a route, but there\'s no wrong way through. Start wherever makes sense for you.') }}
    </p>
</div>

@if($mapModules->isNotEmpty())
    {{-- Bottom margin leaves room for the destination label, which hangs below the trail's end. --}}
    <div class="relative max-w-4xl mx-auto mt-8 mb-16 hidden sm:block" style="height: 300px;" data-learning-map data-node-count="{{ $mapModules->count() }}">
        <svg viewBox="0 0 100 100" class="absolute inset-0 w-full h-full" preserveAspectRatio="none" aria-hidden="true">
            {{-- Fixed trail; nodes are spaced evenly along its two rows and the tail ends at the toolkit destination. --}}
            <path d="{{ $trail }}" fill="none" stroke="var(--brand-primary)" stroke-width="0.4" stroke-dasharray="2,2" opacity="0.4" />
        </svg>
        @foreach($mapModules as $index => $module)
            <a href="{{ $curriculum->moduleUrl($module) }}"
                class="curriculum-node group"
                data-index="{{ $index }}"
                style="left: {{ $nodes[$index]['x'] }}%; top: {{ $nodes[$index]['y'] }}%;">
                <span class="curriculum-node-dot"></span>
                <span class="block text-sm font-semibold leading-snug text-gray-800 group-hover:text-brand-primary">{{ $module->title }}</span>
                @if($module->is_draft)
                    <span data-draft-badge class="mt-1 inline-block rounded bg-amber-100 px-2 py-0.5 text-[0.65rem] font-bold uppercase tracking-wider text-amber-800">{{ t('Draft') }}</span>
                @endif
            </a>
        @endforeach
        <div class="curriculum-destination" data-map-destination style="left: {{ $end['x'] }}%; top: {{ $end['y'] }}%;">
            <span class="curriculum-destination-dot">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $flagIcon }}" /></svg>
            </span>
            <span class="block text-sm font-bold leading-snug text-brand-primary">{{ t('Ready to build your toolkit') }}</span>
        </div>
    </div>

    <div class="sm:hidden flex flex-col gap-3 mt-8">
        @foreach($mapModules as $module)
            <a href="{{ $curriculum->moduleUrl($module) }}"
                class="flex items-center gap-3 bg-white/60 border border-brand-primary/10 rounded-xl px-4 py-3 hover-effect">
                <span class="curriculum-node-dot curriculum-node-dot-inline shrink-0"></span>
                <span class="font-semibold text-gray-800">{{ $module->title }}</span>
                @if($module->is_draft)
                    <span data-draft-badge class="rounded bg-amber-100 px-2 py-0.5 text-[0.65rem] font-bold uppercase tracking-wider text-amber-800">{{ t('Draft') }}</span>
                @endif
            </a>
        @endforeach
        <div class="flex items-center gap-3 px-4 py-2" data-map-destination>
            <span class="curriculum-destination-dot w-7 h-7 mx-0 mb-0 shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $flagIcon }}" /></svg>
            </span>
            <span class="font-bold text-brand-primary">{{ t('Ready to build your toolkit') }}</span>
        </div>
    </div>
@else
    <p class="mt-8 text-center text-gray-500">{{ t('Learning-map modules are coming soon.') }}</p>
@endif
