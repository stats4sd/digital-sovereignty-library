@use('App\Filament\Curriculum\BlockPreview')
@php $columns = $preview->list('fields'); @endphp
<x-filament.curriculum.previews.frame :definition="$definition" :heading="$preview->text('heading')">
    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ (int) ($preview->raw('rows') ?? 1) }} × "{{ $preview->text('rowLabel') ?? 'Row' }}" · {{ count($columns) }} {{ \Illuminate\Support\Str::plural('column', count($columns)) }}
    </p>
    <ul class="flex flex-wrap gap-2 text-sm">
        @forelse ($columns as $column)
            <li class="rounded-md border border-gray-200 px-2 py-1 dark:border-white/10">
                <span class="font-medium text-gray-900 dark:text-white">{{ BlockPreview::pickText($column['label'] ?? null) ?? '(no label)' }}</span>
                @if (($column['kind'] ?? 'text') === 'select')
                    <span class="text-gray-500 dark:text-gray-400">· {{ count(array_filter($column['options'] ?? [], 'is_array')) }} choices</span>
                @endif
            </li>
        @empty
            <li class="italic text-gray-400">No columns yet.</li>
        @endforelse
    </ul>
</x-filament.curriculum.previews.frame>
