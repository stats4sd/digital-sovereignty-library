@php
    $troveId = $preview->raw('trove_id');
    $trove = filled($troveId) ? \App\Models\Trove::withDrafts()->find($troveId) : null;
    $title = $trove ? (\App\Support\TranslatableText::pick($trove->getTranslations('title')) ?? "Resource #{$trove->id}") : null;
@endphp
<x-filament.curriculum.previews.frame :definition="$definition" :heading="$title ?? 'No resource selected'">
    @if ($trove && $trove->published_at === null)
        <p class="text-xs text-warning-600 dark:text-warning-400">Unpublished: hidden from learners.</p>
    @endif
    @if ($intro = $preview->html('intro'))
        <div class="fi-prose max-w-none text-sm text-gray-700 dark:text-gray-300">{!! $intro !!}</div>
    @endif
</x-filament.curriculum.previews.frame>
