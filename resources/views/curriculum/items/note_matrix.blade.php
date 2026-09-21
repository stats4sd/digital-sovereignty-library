@use('App\Support\TranslatableText')
@php
    $headingId = "item-{$item->key}";
    $rows = max(1, (int) ($config['rows'] ?? 1));
    $rowLabel = $item->text('rowLabel') ?? t('Row');
    $fields = collect($config['fields'] ?? [])->filter(fn ($field) => is_array($field) && filled($field['id'] ?? null));
    $gridColumns = match (true) {
        $fields->count() >= 4 => 'lg:grid-cols-4',
        $fields->count() === 3 => 'lg:grid-cols-3',
        $fields->count() === 2 => 'lg:grid-cols-2',
        default => '',
    };
@endphp

<section class="note-block mt-14" aria-labelledby="{{ $headingId }}" data-item-type="note_matrix" x-data>
    @include('curriculum.items._heading', ['id' => $headingId, 'heading' => $item->text('heading')])

    <div class="mt-8 flex flex-col gap-6">
        @for($row = 0; $row < $rows; $row++)
            <fieldset class="note-field rounded-2xl border-2 border-brand-line bg-brand-surface-muted p-6">
                <legend class="px-2 text-xs font-bold uppercase tracking-[0.25em] text-brand-footer-text">{{ $rowLabel }} {{ $row + 1 }}</legend>

                <div class="grid gap-5 {{ $gridColumns }}">
                    @foreach($fields as $field)
                        @php
                            $inputId = "note-{$item->key}-{$row}-{$field['id']}";
                            $path = "{$item->key}.{$row}.{$field['id']}";
                            $isSelect = ($field['kind'] ?? 'text') === 'select';
                        @endphp
                        <div class="note-field">
                            <label for="{{ $inputId }}" class="block text-base font-bold text-brand-primary">{{ TranslatableText::pick($field['label'] ?? null) }}</label>

                            @if($isSelect)
                                <select id="{{ $inputId }}"
                                    class="mt-2 w-full rounded-xl border-2 border-brand-line bg-brand-surface px-3 py-2 text-base text-brand-ink"
                                    :value="$store.learner.get(@js($path))"
                                    @change="$store.learner.set(@js($path), $event.target.value)">
                                    <option value="">{{ t('Not set') }}</option>
                                    @foreach(($field['options'] ?? []) as $option)
                                        @if(is_array($option) && filled($option['id'] ?? null))
                                            <option value="{{ $option['id'] }}">{{ TranslatableText::pick($option['text'] ?? null) }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            @else
                                <textarea id="{{ $inputId }}"
                                    rows="5"
                                    placeholder="{{ TranslatableText::pick($field['placeholder'] ?? null) }}"
                                    class="mt-2 w-full rounded-xl border-2 border-brand-line bg-brand-surface p-3 text-base leading-relaxed text-brand-ink"
                                    :value="$store.learner.get(@js($path))"
                                    @input="$store.learner.set(@js($path), $event.target.value)"></textarea>
                            @endif
                        </div>
                    @endforeach
                </div>
            </fieldset>
        @endfor
    </div>

    @include('curriculum.items._saved-status')
</section>
