@extends('layouts.app')
@section('title', $session->title)
@section('content')
    <div class="mx-auto w-full max-w-4xl px-6 py-12 sm:px-8">
        <a href="{{ route('curriculum.show', $module->key) }}" class="inline-flex items-center gap-2 text-sm font-bold text-brand-primary hover:underline">
            <x-dir-arrow back /> {{ t('Back to') }} {{ $module->title }}
        </a>

        <div class="mt-8 flex gap-6">
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
@endsection
