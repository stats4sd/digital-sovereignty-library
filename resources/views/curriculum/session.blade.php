@extends('layouts.app')
@section('title', $session->title)

@php
    // Contents outline: the overview, then every renderable item that has a heading.
    $calloutTags = [
        'recall' => t('Recall'),
        'example' => t('Example'),
        'takeaways' => t('Key takeaways'),
        'check' => t('Check'),
    ];
    $tagFor = fn (\App\Models\CurriculumSessionItem $item) => match ($item->type) {
        \App\Enums\CurriculumItemType::Prose => null,
        \App\Enums\CurriculumItemType::Callout => $calloutTags[$item->config['kind'] ?? 'recall'] ?? t('Callout'),
        \App\Enums\CurriculumItemType::Quiz => t('Quiz'),
        \App\Enums\CurriculumItemType::Trove => t('Resource'),
        default => t('Activity'),
    };
    $outline = $session->items
        ->filter(fn ($item) => $item->isRenderable() && filled($item->outlineLabel()))
        ->map(fn ($item) => [
            'id' => "item-{$item->key}",
            'key' => $item->key,
            'label' => $item->outlineLabel(),
            'tag' => $tagFor($item),
            'activity' => $item->type->isActivity(),
        ])
        ->values();
    $hasOutline = $outline->isNotEmpty();
    if ($hasOutline) {
        $outline->prepend(['id' => 'session-overview', 'key' => null, 'label' => t('Overview'), 'tag' => null, 'activity' => false]);
    }
@endphp

@push('head')
    <style>
        /* Keep the global scroll-to-top button clear of the session contents bar on small screens. */
        @media (max-width: 1023px) { #scrollToTopButton { bottom: 5.5rem; } }
    </style>
@endpush

@section('content')
    <div class="mx-auto w-full px-6 py-12 sm:px-8 {{ $hasOutline ? 'max-w-6xl lg:grid lg:grid-cols-[14rem_minmax(0,1fr)] lg:gap-12 pb-24 lg:pb-12' : 'max-w-4xl' }}"
        @if($hasOutline) x-data="sessionOutline({{ Js::from($outline) }})" @endif>

        @if($hasOutline)
            <x-session-outline :entries="$outline" :module="$module" :previous="$previous" :next="$next" />
        @endif

        <div class="min-w-0 {{ $hasOutline ? 'max-w-4xl' : '' }}">
            <a href="{{ route('curriculum.show', $module->key) }}" class="inline-flex items-center gap-2 text-sm font-bold text-brand-primary hover:underline">
                <x-dir-arrow back /> {{ t('Back to') }} {{ $module->title }}
            </a>

            <div id="session-overview" class="mt-8 flex gap-6">
                <div class="brand-rule" aria-hidden="true"></div>
                <div>
                    <p class="text-sm font-bold uppercase tracking-[0.2em] text-brand-secondary">{{ t('Session %d', $session->number) }}</p>
                    <h1 class="mt-2 text-4xl font-bold leading-tight text-brand-ink md:text-5xl">{{ $session->title }}</h1>

                    @if($session->builds_toward_outcome)
                        <p class="mt-4 text-lg text-brand-ink-muted" data-glossary-scope>
                            <span class="font-semibold text-brand-footer-text">{{ t('Builds toward LO%d', $session->builds_toward_outcome['position']) }}</span>
                            &mdash; {{ $session->builds_toward_outcome['statement'] }}
                        </p>
                    @endif
                </div>
            </div>

            @if($session->description)
                <div class="description mt-10 text-lg leading-relaxed text-gray-700" data-glossary-scope>{!! $session->description !!}</div>
            @endif

            @if($session->items->contains(fn ($item) => $item->isRenderable()))
                <div class="session-items" data-session-items>
                    @foreach($session->items as $item)
                        <x-curriculum-item :item="$item" />
                    @endforeach
                </div>
            @else
                <div class="divider mt-12"></div>
                <p class="text-gray-500">{{ t('Content for this session is coming soon.') }}</p>
            @endif

            <x-learner-store :namespace="'dsl:v1:'.$module->key.':'.$session->slug" />

            <nav class="mt-12 flex flex-wrap items-center justify-between gap-4 border-t border-brand-line pt-8" aria-label="{{ t('Session navigation') }}">
                @if($previous)
                    <a href="{{ route('curriculum.session', ['key' => $module->key, 'session' => $previous->slug]) }}" aria-label="{{ t('Previous session') }}" class="text-sm font-bold text-brand-primary hover:underline">
                        <x-dir-arrow back /> {{ t('Session %d', $previous->number) }}
                    </a>
                @else
                    <span></span>
                @endif

                @if($next)
                    <a href="{{ route('curriculum.session', ['key' => $module->key, 'session' => $next->slug]) }}" aria-label="{{ t('Next session') }}" class="brand-pill">
                        {{ t('Session %d', $next->number) }} <x-dir-arrow />
                    </a>
                @else
                    <span></span>
                @endif
            </nav>

            <div class="mt-8">
                <a href="{{ route('curriculum.show', $module->key) }}" class="text-sm font-bold text-brand-primary hover:underline">
                    {{ t('Back to module home') }}
                </a>
            </div>
        </div>
    </div>
@endsection
