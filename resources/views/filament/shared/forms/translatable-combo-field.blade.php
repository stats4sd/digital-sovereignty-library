@if ($isInline())
    {{-- Inline mode: a plain field wrapper, no Section card. label-tag="div" because the label
         names a group of locale inputs rather than one control. --}}
    <x-dynamic-component
        :component="$getFieldWrapperView()"
        :field="$field"
        label-tag="div"
        :attributes="
            \Filament\Support\prepare_inherited_attributes($attributes)
                ->merge([
                    'id' => $getId(),
                ], escape: false)
                ->merge($getExtraAttributes(), escape: false)
                ->merge($getExtraAlpineAttributes(), escape: false)
                ->class(['fi-translatable-combo-inline'])
        "
    >
        {{ $getChildComponentContainer() }}

        @if (filled($description = $getDescription()))
            <p class="fi-translatable-combo-inline-description text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
        @endif
    </x-dynamic-component>
@else
<x-filament::section
    :aside="false"
    :collapsed="$isCollapsed()"
    :collapsible="$isCollapsible()"
    :compact="$isCompact()"
    :content-before="false"
    :description="$getDescription()"
    :heading="$getHeading()"
    :icon="$getIcon()"
    :icon-color="$getIconColor()"
    :persist-collapsed="$shouldPersistCollapsed()"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->merge([
                'id' => $getId(),
            ], escape: false)
            ->merge($getExtraAttributes(), escape: false)
            ->merge($getExtraAlpineAttributes(), escape: false)
    "
>

    {{ $getChildComponentContainer() }}
</x-filament::section>
@endif
