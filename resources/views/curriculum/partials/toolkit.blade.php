@php
    $pillarLayout = [
        'knowledge' => ['icon' => 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375'],
        'collaboration' => ['icon' => 'M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155'],
        'farm' => ['icon' => 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'],
        'market' => ['icon' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z'],
    ];
@endphp

<div id="toolkit" class="mt-20 scroll-mt-24">
    <div class="text-center mb-10">
        <h2 class="text-3xl font-bold text-brand-primary mb-3">{{ t('Build Your Sovereign Toolkit') }}</h2>
        <p class="text-gray-600 max-w-xl mx-auto">{{ t('Tools your community can own and run.') }}</p>
    </div>

    {{-- Featured: the Farm Hack Box (a toolkit module surfaced above the pillar grid) --}}
    @if($pillars->has('farm-hack-box'))
        @php $farmHackBox = $pillars['farm-hack-box']; @endphp
        <a href="{{ route('toolkit.show', 'farm-hack-box') }}"
            class="flex flex-col sm:flex-row sm:items-center gap-4 bg-white/60 border border-brand-secondary/40 rounded-2xl p-6 mb-6 hover-effect">
            <div class="flex-1">
                <div class="flex items-center gap-3 mb-1">
                    <h3 class="text-brand-primary text-xl">{{ $farmHackBox->title }}</h3>
                </div>
                <p class="text-sm text-gray-600 leading-relaxed max-w-3xl" data-glossary-scope>{{ Str::limit(strip_tags($farmHackBox->description), 220) }}</p>
            </div>
            <span class="self-start sm:self-center whitespace-nowrap text-sm font-semibold border border-brand-primary/30 rounded-lg px-4 py-1.5 text-brand-primary">
                {{ t('Learn more') }} &rarr;
            </span>
        </a>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($pillarLayout as $key => $layout)
            @if($pillars->has($key))
                @php $pillar = $pillars[$key]; @endphp
                <a href="{{ route('toolkit.show', $key) }}"
                    class="relative flex flex-col bg-white/60 border border-brand-primary/10 rounded-2xl p-6 min-h-[210px] hover-effect">
                    <span class="absolute top-4 right-5 text-xs text-gray-400">{{ $pillar->troves_count }} {{ $pillar->troves_count === 1 ? t('tool') : t('tools') }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-8 h-8 text-brand-primary mb-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $layout['icon'] }}" />
                    </svg>
                    <h3 class="text-brand-primary text-xl mb-1">{{ $pillar->title }}</h3>
                    <p class="text-sm text-gray-600 leading-relaxed" data-glossary-scope>{{ strip_tags($pillar->description) }}</p>
                </a>
            @endif
        @endforeach
    </div>
</div>
