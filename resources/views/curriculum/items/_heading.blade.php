{{-- Ruled section heading shared by every item type that has a heading. --}}
@props(['id', 'heading'])

<div class="flex gap-6">
    <div class="brand-rule" aria-hidden="true"></div>
    <h2 id="{{ $id }}" class="text-2xl font-bold leading-tight text-brand-primary md:text-3xl">{{ $heading }}</h2>
</div>
