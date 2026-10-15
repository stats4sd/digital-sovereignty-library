@props(['session'])

@if($session->number !== null)
    {{ t('Session %d', $session->number) }}
@else
    <span data-draft-badge class="inline-block rounded bg-amber-100 px-2 py-0.5 text-xs font-bold uppercase tracking-[0.2em] text-amber-800">{{ t('Draft') }}</span>
@endif
