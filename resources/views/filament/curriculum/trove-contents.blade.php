{{--
    Read-only summary of a Trove's videos, links and files for the session Content Builder's
    "Resource" block. Mirrors the public card's order (videos, links, files) and locale
    fallback (current locale → en → others), but lists videos instead of embedding them.
--}}
@php
    /** @var \App\Models\Trove $trove */
    $videos = $trove->videoLinks();
    $links = $trove->externalLinks();
    $files = $trove->getContentMedia();
    $isEmpty = $videos->isEmpty() && $links->isEmpty() && $files->isEmpty();

    $locales = config('branding.locales', ['en' => 'English']);
    $localeName = $locales[app()->getLocale()] ?? app()->getLocale();

    $hostOf = function (?string $url): ?string {
        $host = $url ? preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)) : null;

        return $host ?: $url;
    };
@endphp

<div class="rounded-lg border border-gray-200 bg-white text-sm dark:border-white/10 dark:bg-white/5" data-trove-contents>
    <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-3 py-2 dark:border-white/10">
        <span class="font-medium text-gray-700 dark:text-gray-200">Resource contents</span>
        <x-filament::link :href="$editUrl" target="_blank" rel="noopener" icon="heroicon-m-arrow-top-right-on-square" icon-position="after" size="sm">
            Edit resource
        </x-filament::link>
    </div>

    @if($isEmpty)
        <p class="px-3 py-3 text-gray-500 dark:text-gray-400">This resource has no videos, links or files yet.</p>
    @else
        <ul class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach($videos as $video)
                @php $host = $hostOf($video['url'] ?? null); @endphp
                <li class="flex items-center justify-between gap-3 px-3 py-2" data-trove-content-row="video">
                    <div class="flex min-w-0 items-center gap-2">
                        <x-filament::icon icon="heroicon-o-play-circle" class="h-4 w-4 shrink-0 text-gray-400" />
                        <span class="truncate text-gray-800 dark:text-gray-100">{{ $video['title'] ?? $host }}</span>
                        @if(filled($video['title'] ?? null) && $host)
                            <span class="hidden truncate text-xs text-gray-400 sm:inline">({{ $host }})</span>
                        @endif
                    </div>
                    @if($video['embeddable'] ?? false)
                        <x-filament::badge color="success" size="sm">Embedded player</x-filament::badge>
                    @else
                        <x-filament::badge color="gray" size="sm">Link card only</x-filament::badge>
                    @endif
                </li>
            @endforeach

            @foreach($links as $link)
                <li class="flex items-center justify-between gap-3 px-3 py-2" data-trove-content-row="link">
                    <div class="flex min-w-0 items-center gap-2">
                        <x-filament::icon icon="heroicon-o-link" class="h-4 w-4 shrink-0 text-gray-400" />
                        <span class="truncate text-gray-800 dark:text-gray-100">{{ $link['link_title'] }}</span>
                    </div>
                    <span class="shrink-0 truncate text-xs text-gray-400">{{ $hostOf($link['link_url']) }}</span>
                </li>
            @endforeach

            @foreach($files as $media)
                <li class="flex items-center justify-between gap-3 px-3 py-2" data-trove-content-row="file">
                    <div class="flex min-w-0 items-center gap-2">
                        <x-filament::icon icon="heroicon-o-document" class="h-4 w-4 shrink-0 text-gray-400" />
                        <span class="truncate text-gray-800 dark:text-gray-100">{{ $media->file_name }}</span>
                    </div>
                    <span class="shrink-0 text-xs text-gray-400">{{ Illuminate\Support\Number::fileSize((int) $media->size) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="border-t border-gray-200 px-3 py-2 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
        Showing {{ $localeName }} content, falling back to other languages where {{ $localeName }} is missing. Learners see their own language where it exists.
    </p>
</div>
