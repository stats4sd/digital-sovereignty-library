<x-filament.curriculum.previews.frame :definition="$definition" :heading="$preview->text('heading')">
    @if ($body = $preview->html('body'))
        <div class="fi-prose max-w-none text-sm text-gray-700 dark:text-gray-300">{!! $body !!}</div>
    @else
        <p class="text-sm italic text-gray-400">No text yet.</p>
    @endif
</x-filament.curriculum.previews.frame>
