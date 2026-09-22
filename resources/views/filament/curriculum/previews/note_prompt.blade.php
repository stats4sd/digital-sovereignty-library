<x-filament.curriculum.previews.frame :definition="$definition" :heading="$preview->text('heading')">
    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $preview->text('prompt') ?? 'No prompt yet.' }}</p>
    <div class="rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500 dark:border-white/20 dark:text-gray-400">
        {{ $preview->text('label') ?? 'Your notes' }}
        @if ($placeholder = $preview->text('placeholder')) · <span class="italic">{{ $placeholder }}</span> @endif
        · {{ (int) ($preview->raw('rows') ?? 4) }} rows
    </div>
</x-filament.curriculum.previews.frame>
