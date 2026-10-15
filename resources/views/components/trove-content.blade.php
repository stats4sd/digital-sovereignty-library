@props(['trove', 'wide' => false])

@php
    $videoLinks = $trove->videoLinks();

    $downloadItems = $trove->externalLinks()
        ->map(fn ($link) => ['type' => 'link', 'data' => $link])
        ->concat($trove->getContentMedia()->map(fn ($media) => ['type' => 'file', 'data' => $media]));
@endphp

@if($videoLinks->isNotEmpty() || $downloadItems->isNotEmpty())
    <div {{ $attributes->class(['space-y-6']) }} data-trove-content>
        @if($videoLinks->isNotEmpty())
            <div class="space-y-4">
                @foreach($videoLinks as $link)
                    <x-video-link :link="$link" />
                @endforeach
            </div>
        @endif

        @if($downloadItems->isNotEmpty())
            <div class="flex flex-col gap-3">
                @foreach($downloadItems as $item)
                    @if($item['type'] === 'link')
                        <a href="{{ $item['data']['link_url'] }}" target="_blank" rel="noopener noreferrer"
                            class="flex items-center justify-between p-4 rounded-2xl bg-white shadow-sm border border-gray-100 hover:border-brand-primary transition group">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-brand-bg flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-brand-primary">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800">{{ $item['data']['link_title'] }}</p>
                                    <p class="text-xs text-gray-400 truncate">{{ $item['data']['link_url'] }}</p>
                                </div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-300 group-hover:text-brand-primary transition flex-shrink-0 ms-3 rtl:-scale-x-100">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                            </svg>
                        </a>
                    @else
                        <a href="{{ $item['data']->getUrl() }}" target="_blank" rel="noopener noreferrer"
                            class="flex items-center justify-between p-4 rounded-2xl bg-white shadow-sm border border-gray-100 hover:border-brand-primary transition group">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-brand-bg flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-brand-primary">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $item['data']->name }}</p>
                                    <p class="text-xs text-gray-400">{{ Number::fileSize($item['data']->size) }}</p>
                                </div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-300 group-hover:text-brand-primary transition flex-shrink-0 ms-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                            </svg>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@endif
