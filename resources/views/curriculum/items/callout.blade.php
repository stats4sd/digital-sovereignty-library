@php
    $kind = $config['kind'] ?? 'recall';
    $tones = [
        'recall' => ['label' => t('Recall'), 'tone' => 'border-brand-secondary bg-brand-surface-muted'],
        'example' => ['label' => t('Example'), 'tone' => 'border-brand-footer-text bg-brand-surface'],
        'takeaways' => ['label' => t('Key takeaways'), 'tone' => 'border-brand-primary bg-brand-primary/8'],
        'check' => ['label' => t('Check'), 'tone' => 'border-brand-info bg-brand-surface'],
    ];
    $tone = $tones[$kind] ?? $tones['recall'];
    $heading = $item->text('heading');
    $lesson = $item->text('lesson');
    $headingId = "item-{$item->key}";
@endphp

<section class="mt-10 rounded-2xl border-2 border-s-[1.5rem] p-6 sm:p-8 {{ $tone['tone'] }}"
    @if($heading) aria-labelledby="{{ $headingId }}" @endif
    data-item-type="callout" data-callout-kind="{{ $kind }}">
    <p class="text-xs font-bold uppercase tracking-[0.25em] text-brand-footer-text">{{ $tone['label'] }}</p>

    @if($heading)
        <h2 id="{{ $headingId }}" class="mt-2 text-xl font-bold leading-snug text-brand-primary md:text-2xl">{{ $heading }}</h2>
    @endif

    <div class="description item-body mt-4 max-w-3xl text-lg leading-relaxed text-brand-ink" data-glossary-scope>{!! $item->text('body') !!}</div>

    @if($lesson)
        <div class="mt-6 border-t-2 border-brand-line pt-5">
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-brand-footer-text">&#10003; {{ t('Key lesson') }}</p>
            <p class="mt-2 text-lg font-semibold leading-relaxed text-brand-primary" data-glossary-scope>{{ $lesson }}</p>
        </div>
    @endif
</section>
