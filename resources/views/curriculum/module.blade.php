@extends('layouts.app')
@section('title', $module->title)
@section('content')
    @php
        $firstSession = $module->sessions->first();
        $outcomes = $module->outcomes_list;
    @endphp

    {{-- Hero --}}
    <section class="pt-12 pb-10 lg:pt-16">
        <div class="mx-auto w-full max-w-6xl px-6 sm:px-8 lg:px-12">
            <a href="{{ route('curriculum') }}" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-brand-secondary">
                <x-dir-arrow back /> {{ t('Back to the learning map') }}
            </a>

            @if($module->number)
                <p class="mt-6 text-3xl font-bold text-brand-primary md:text-4xl">{{ t('Module %d', $module->number) }}</p>
            @endif

            <h1 class="mt-1 text-4xl font-bold leading-[1.1] text-brand-ink md:text-5xl">{{ $module->title }}</h1>

            @if($firstSession)
                <div class="mt-8 flex">
                    <a href="{{ route('curriculum.session', ['key' => $module->key, 'session' => $firstSession->slug]) }}" class="brand-pill group">
                        <span class="flex flex-col text-left">
                            <span class="text-lg font-bold leading-snug">{{ t('Start Session 1') }}</span>
                            <span class="text-sm font-normal leading-snug opacity-80">{{ $firstSession->title }}</span>
                        </span>
                        <x-arrow-right class="h-6 w-6 flex-shrink-0 transition-transform duration-300 group-hover:translate-x-2" />
                    </a>
                </div>
            @endif

            @if($module->description)
                <div class="description mt-10 max-w-3xl text-lg leading-relaxed text-gray-700" data-glossary-scope>{!! $module->description !!}</div>
            @endif

            @if($module->note)
                <div class="note-callout mt-4">{{ $module->note }}</div>
            @endif
        </div>
    </section>

    {{-- Goal --}}
    @if($module->goal)
        <section class="pb-14 lg:pb-16" aria-labelledby="goal-heading">
            <div class="mx-auto w-full max-w-6xl px-6 sm:px-8 lg:px-12">
                <div class="max-w-4xl mx-auto border-y-2 border-brand-line py-10 text-center">
                    <p class="text-xs font-bold uppercase tracking-[0.25em] text-brand-secondary">{{ t('Goal') }}</p>
                    <h2 id="goal-heading" class="mt-4 font-display text-xl font-bold leading-snug text-brand-primary md:text-3xl" data-glossary-scope>
                        {{ $module->goal }}
                    </h2>
                </div>
            </div>
        </section>
    @endif

    {{-- Learning outcomes --}}
    @if(count($outcomes))
        <section class="bg-brand-primary py-14 text-white lg:py-16" aria-labelledby="outcomes-heading">
            <div class="mx-auto w-full max-w-6xl px-6 sm:px-8 lg:px-12">
                <div class="grid gap-8 lg:grid-cols-[18rem_1fr] lg:gap-16">
                    <div>
                        <h2 id="outcomes-heading" class="text-2xl font-bold">{{ t('Learning Outcomes') }}</h2>
                        <p class="mt-3 text-lg opacity-80">{{ t('By the end of this module, you will be able to:') }}</p>
                    </div>

                    <ol class="flex flex-col gap-5">
                        @foreach($outcomes as $outcome)
                            <li class="flex gap-5 rounded-2xl bg-white/10 p-6">
                                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-bg text-lg font-bold text-brand-primary" aria-hidden="true">
                                    {{ $loop->iteration }}
                                </span>
                                <div data-glossary-scope>
                                    <p class="text-lg font-bold leading-snug">{{ $outcome['statement'] }}</p>
                                    @if($outcome['in_practice'])
                                        <p class="mt-2 text-base italic opacity-85">{{ t('In practice:') }} {{ $outcome['in_practice'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </section>
    @endif

    {{-- Sessions --}}
    <section class="py-14 lg:py-16">
        <div class="mx-auto w-full max-w-6xl px-6 sm:px-8 lg:px-12">
            <div class="flex gap-6">
                <div class="brand-rule" aria-hidden="true"></div>
                <div>
                    <h2 class="text-2xl font-bold text-brand-ink">
                        @if($module->sessions->isNotEmpty())
                            {{ n('The %d session', 'The %d sessions', $module->sessions->count(), $module->sessions->count()) }}
                        @else
                            {{ t('Sessions') }}
                        @endif
                    </h2>
                    <p class="mt-3 text-lg text-brand-ink-muted">{{ t('Each session builds toward one of the module learning outcomes.') }}</p>
                </div>
            </div>

            @if($module->sessions->isNotEmpty())
                <div class="mt-10 flex flex-wrap gap-6">
                    @foreach($module->sessions as $session)
                        <x-session-card :module="$module" :session="$session" :number="$loop->iteration" />
                    @endforeach
                </div>
            @else
                <p class="mt-10 text-gray-500">{{ t('Sessions for this module are coming soon.') }}</p>
            @endif
        </div>
    </section>
@endsection
