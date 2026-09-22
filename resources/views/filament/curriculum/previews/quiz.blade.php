@use('App\Filament\Curriculum\BlockPreview')
@php $questions = $preview->list('items'); @endphp
<x-filament.curriculum.previews.frame :definition="$definition" :heading="$preview->text('heading')">
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ count($questions) }} {{ \Illuminate\Support\Str::plural('question', count($questions)) }} · pass mark {{ (int) ($preview->raw('passMark') ?? 1) }}</p>
    <ol class="grid gap-y-2 text-sm">
        @forelse ($questions as $index => $question)
            <li>
                <p class="font-medium text-gray-900 dark:text-white">Q{{ $index + 1 }} · {{ BlockPreview::pickText($question['stem'] ?? null) ?? '(no question)' }}</p>
                <ul class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-gray-600 dark:text-gray-400">
                    @foreach (array_values(array_filter($question['options'] ?? [], 'is_array')) as $option)
                        <li>{{ filter_var($option['correct'] ?? false, FILTER_VALIDATE_BOOLEAN) ? '✓' : '○' }} {{ BlockPreview::pickText($option['text'] ?? null) ?? '(blank)' }}</li>
                    @endforeach
                </ul>
            </li>
        @empty
            <li class="italic text-gray-400">No questions yet.</li>
        @endforelse
    </ol>
</x-filament.curriculum.previews.frame>
