@use('App\Support\TranslatableText')
@php
    $headingId = "item-{$item->key}";
    $columns = $config['columns'] ?? [];
    $fields = collect($config['fields'] ?? [])->filter(fn ($field) => is_array($field) && filled($field['id'] ?? null));
@endphp

<section class="note-block mt-14" aria-labelledby="{{ $headingId }}" data-item-type="note_canvas" x-data>
    @include('curriculum.items._heading', ['id' => $headingId, 'heading' => $item->text('heading')])

    @if($columns)
        <div class="mt-8 hidden gap-8 text-xs font-bold uppercase tracking-[0.25em] text-brand-footer-text lg:grid lg:grid-cols-[12rem_1fr_1.5fr]" aria-hidden="true">
            <span>{{ TranslatableText::pick($columns['label'] ?? null) ?? t('Area') }}</span>
            <span>{{ TranslatableText::pick($columns['prompt'] ?? null) ?? t('Guiding question') }}</span>
            <span>{{ TranslatableText::pick($columns['notes'] ?? null) ?? t('Your notes') }}</span>
        </div>
    @endif

    <ol class="{{ $columns ? 'mt-2' : 'mt-8' }} divide-y-2 divide-brand-line border-y-2 border-brand-line">
        @foreach($fields as $field)
            @php
                $inputId = "note-{$item->key}-{$field['id']}";
                $prompt = TranslatableText::pick($field['prompt'] ?? null);
                $path = "{$item->key}.{$field['id']}";
            @endphp
            <li class="note-field grid gap-3 py-6 lg:grid-cols-[12rem_1fr_1.5fr] lg:gap-8">
                <label for="{{ $inputId }}" class="block text-base font-bold text-brand-primary">{{ TranslatableText::pick($field['label'] ?? null) }}</label>
                <p id="{{ $inputId }}-prompt" class="text-sm italic text-brand-ink-muted" data-glossary-scope>{{ $prompt }}</p>
                <div>
                    <textarea id="{{ $inputId }}"
                        rows="4"
                        @if($prompt) aria-describedby="{{ $inputId }}-prompt" @endif
                        class="w-full rounded-xl border-2 border-brand-line bg-brand-surface p-3 text-base leading-relaxed text-brand-ink"
                        :value="$store.learner.get(@js($path))"
                        @input="$store.learner.set(@js($path), $event.target.value)"></textarea>
                </div>
            </li>
        @endforeach
    </ol>

    @include('curriculum.items._saved-status')
</section>
