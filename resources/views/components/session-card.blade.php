@props(['module', 'session', 'number'])

<a href="{{ route('curriculum.session', ['key' => $module->key, 'session' => $session->slug]) }}"
    class="group flex grow basis-full flex-col rounded-2xl border border-brand-line bg-brand-surface p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-brand-primary hover:shadow-lg md:basis-[calc(50%-0.75rem)] lg:basis-[calc(25%-1.125rem)]">
    <span class="text-xs font-bold tracking-[0.2em] text-brand-secondary uppercase">
        {{ t('Session %d', $number) }}
    </span>

    <h3 class="mt-3 text-xl font-bold leading-snug text-brand-primary">
        {{ $session->title }}
    </h3>

    <p class="mt-3 text-sm text-brand-ink-muted" data-glossary-scope>
        @if($session->builds_toward_outcome)
            <span class="font-semibold text-brand-footer-text">{{ t('Builds toward LO%d', $session->builds_toward_outcome['position']) }}</span>
            &mdash;
        @endif
        {{ $session->summary }}
    </p>

    <span class="mt-auto inline-flex items-center gap-3 pt-6 text-sm font-bold text-brand-primary">
        {{ t('Start session') }}
        <x-arrow-right class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-2" />
    </span>
</a>
