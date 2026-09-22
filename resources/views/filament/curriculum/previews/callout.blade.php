@php
    $kind = \App\Curriculum\Items\CalloutItem::KINDS[$preview->raw('kind') ?? ''] ?? 'Callout';
@endphp
<x-filament.curriculum.previews.frame :definition="$definition" :heading="$preview->text('heading')" :chip="'Callout · '.$kind">
    <div class="border-s-4 border-gray-300 ps-3 dark:border-white/20">
        @if ($body = $preview->html('body'))
            <div class="fi-prose max-w-none text-sm text-gray-700 dark:text-gray-300">{!! $body !!}</div>
        @else
            <p class="text-sm italic text-gray-400">No text yet.</p>
        @endif
        @if ($lesson = $preview->text('lesson'))
            <p class="mt-2 text-sm font-semibold text-gray-800 dark:text-gray-200">Lesson: {{ $lesson }}</p>
        @endif
    </div>
</x-filament.curriculum.previews.frame>
