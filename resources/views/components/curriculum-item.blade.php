@props(['item'])

@if($item->isRenderable())
    @include($item->definition()->view(), ['item' => $item, 'config' => $item->config ?? []])
@endif
