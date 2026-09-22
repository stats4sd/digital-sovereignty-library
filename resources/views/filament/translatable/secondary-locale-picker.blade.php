@php
    /**
     * Page-wide "also show" picker for TranslatableComboField.
     *
     * Registers the Alpine store that every combo field's non-primary locale input reads via
     * visibleJs(), and that TranslatableTableColumn headers read to name the locales on screen.
     * The selection is persisted in localStorage so it follows the editor across pages. Pages
     * that don't render this picker have no store, and combo fields there fall back to showing
     * every locale.
     */
    $store = \App\Filament\Translatable\Form\TranslatableComboField::LOCALE_STORE;
    $locales = $locales ?? config('branding.locales', ['en' => 'English']);
    $primary = $primary ?? config('app.fallback_locale', 'en');
    $primaryLabel = $locales[$primary] ?? $primary;
@endphp

<script>
    (() => {
        const register = () => {
            if (window.Alpine.store(@js($store))) {
                return
            }

            window.Alpine.store(@js($store), {
                // '' = primary only, '*' = every locale, otherwise one locale code.
                secondary: window.Alpine.$persist('').as('filament.translatableLocales.secondary'),

                labels: @js($locales),
                primary: @js($primary),

                isVisible(locale) {
                    return this.secondary === '*' || this.secondary === locale
                },

                // Labels of the locales whose inputs are currently shown, in input order.
                visibleLabels() {
                    return Object.entries(this.labels)
                        .filter(([locale]) => locale === this.primary || this.isVisible(locale))
                        .map(([, label]) => label)
                },
            })
        }

        // Alpine may already be running (e.g. after a Livewire SPA navigation).
        window.Alpine ? register() : document.addEventListener('alpine:init', register)
    })()
</script>

<div
    x-data
    class="flex items-center gap-x-2"
    data-translatable-secondary-locale-picker
>
    <label
        for="translatable-secondary-locale"
        class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white whitespace-nowrap"
    >
        Also show
    </label>

    <x-filament::input.wrapper>
        <x-filament::input.select
            id="translatable-secondary-locale"
            x-model="$store.{{ $store }}.secondary"
        >
            <option value="">{{ $primaryLabel }} only</option>

            @foreach ($locales as $code => $label)
                @continue($code === $primary)
                <option value="{{ $code }}">{{ $label }}</option>
            @endforeach

            <option value="*">All languages</option>
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
