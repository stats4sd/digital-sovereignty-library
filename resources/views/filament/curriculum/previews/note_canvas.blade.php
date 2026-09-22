@use('App\Filament\Curriculum\BlockPreview')
@php $rows = $preview->list('fields'); @endphp
<x-filament.curriculum.previews.frame :definition="$definition" :heading="$preview->text('heading')">
    <ol class="grid gap-y-1 text-sm">
        @forelse ($rows as $index => $row)
            <li class="flex gap-x-2">
                <span class="w-5 shrink-0 text-gray-400">{{ $index + 1 }}.</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ BlockPreview::pickText($row['label'] ?? null) ?? '(no label)' }}</span>
                @if ($prompt = BlockPreview::pickText($row['prompt'] ?? null))
                    <span class="text-gray-500 dark:text-gray-400">— {{ $prompt }}</span>
                @endif
            </li>
        @empty
            <li class="italic text-gray-400">No rows yet.</li>
        @endforelse
    </ol>
</x-filament.curriculum.previews.frame>
