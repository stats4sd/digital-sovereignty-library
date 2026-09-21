@php
    $headingId = "item-{$item->key}";
    $inputId = "note-{$item->key}";
    $prompt = $item->text('prompt');
    $path = $item->key;
@endphp

<section class="note-block mt-14" aria-labelledby="{{ $headingId }}" data-item-type="note_prompt" x-data>
    @include('curriculum.items._heading', ['id' => $headingId, 'heading' => $item->text('heading')])

    <div class="note-field mt-8 rounded-2xl border-2 border-brand-line bg-brand-surface-muted p-6">
        <label for="{{ $inputId }}" class="block text-base font-bold text-brand-primary">{{ $item->text('label') ?? t('Your notes') }}</label>
        @if($prompt)
            <p id="{{ $inputId }}-prompt" class="mt-1 text-sm italic text-brand-ink-muted" data-glossary-scope>{{ $prompt }}</p>
        @endif

        <textarea id="{{ $inputId }}"
            rows="{{ $config['rows'] ?? 6 }}"
            placeholder="{{ $item->text('placeholder') }}"
            @if($prompt) aria-describedby="{{ $inputId }}-prompt" @endif
            class="mt-3 w-full rounded-xl border-2 border-brand-line bg-brand-surface p-3 text-base leading-relaxed text-brand-ink"
            :value="$store.learner.get(@js($path))"
            @input="$store.learner.set(@js($path), $event.target.value)"></textarea>

        @include('curriculum.items._saved-status')
    </div>
</section>
