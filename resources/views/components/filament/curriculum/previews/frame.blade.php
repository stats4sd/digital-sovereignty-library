{{-- Shared frame for block previews: type chip, optional heading, then the slot. --}}
@props(['definition', 'heading' => null, 'chip' => null])

<div class="fi-curriculum-preview grid gap-y-2 px-4 py-3" data-preview-type="{{ $definition->type()->value }}">
    <div class="flex items-center gap-x-2">
        <span class="fi-badge fi-color-gray fi-size-sm rounded-md bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $chip ?? $definition->type()->label() }}</span>
        @if (filled($heading))
            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $heading }}</span>
        @endif
    </div>

    {{ $slot }}
</div>
