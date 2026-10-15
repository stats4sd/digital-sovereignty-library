@props(['item'])

@if($item->isRenderable())
    @if($item->is_draft)
        <div data-draft-item class="relative rounded-lg outline-dashed outline-2 outline-amber-400/70 outline-offset-4">
            <span data-draft-badge class="absolute -top-3 end-2 z-10 rounded bg-amber-100 px-2 py-0.5 text-[0.65rem] font-bold uppercase tracking-wider text-amber-800">{{ t('Draft') }}</span>
            @include($item->definition()->view(), ['item' => $item, 'config' => $item->config ?? []])
        </div>
    @else
        @include($item->definition()->view(), ['item' => $item, 'config' => $item->config ?? []])
    @endif
@endif
