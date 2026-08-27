@extends('layouts.app')
@php
    $onboardingSteps = [
        ['key' => 'role', 'label' => t('Who you are')],
        ['key' => 'experience', 'label' => t('Familiarity')],
    ];
    if ($intro) {
        $onboardingSteps[] = ['key' => 'intro', 'label' => t('Intro')];
    }
@endphp
@section('content')
    <div class="container mx-auto px-8 py-12 max-w-5xl"
        x-data="{
            step: 'landing',
            role: null,
            order: @js(array_column($onboardingSteps, 'key')),
            idx(key) { return this.order.indexOf(key); },
            current() { return this.idx(this.step); },
            goBackTo(key) {
                if (this.idx(key) < this.current()) this.step = key;
            },
            pickRole(label) {
                this.role = label;
                this.step = 'experience';
            },
            init() {
                // Deep links from the curriculum page's stepper (e.g. /home#intro).
                const key = location.hash.slice(1);
                if (this.order.includes(key)) this.step = key;
            },
        }">

        <nav class="flex items-start justify-center max-w-sm mx-auto mb-12" aria-label="{{ t('Onboarding steps') }}"
            x-show="step !== 'landing'" style="display: none;">
            @foreach($onboardingSteps as $i => $onboardingStep)
                @if($i > 0)
                    <div class="flex-1 border-t border-dashed mt-[5px] mx-2 transition-colors"
                        :class="current() >= {{ $i }} ? 'border-brand-secondary' : 'border-brand-primary/25'"></div>
                @endif
                <button type="button"
                    class="flex flex-col items-center gap-1.5 group"
                    :disabled="{{ $i }} > current()"
                    :aria-current="step === '{{ $onboardingStep['key'] }}' ? 'step' : null"
                    x-on:click="goBackTo('{{ $onboardingStep['key'] }}')">
                    <span class="trail-dot"
                        :class="{
                            'trail-dot-done': current() > {{ $i }},
                            'trail-dot-current': step === '{{ $onboardingStep['key'] }}',
                        }"></span>
                    <span class="text-xs leading-tight transition-colors"
                        :class="step === '{{ $onboardingStep['key'] }}' ? 'text-brand-primary font-semibold' : (current() > {{ $i }} ? 'text-gray-600 group-hover:text-brand-secondary' : 'text-gray-400')">
                        {{ $onboardingStep['label'] }}
                    </span>
                </button>
            @endforeach

            <div class="flex-1 border-t border-dashed border-brand-primary/25 mt-[5px] mx-2"></div>
            <a href="{{ route('curriculum') }}#map" class="flex flex-col items-center gap-1.5 group">
                <span class="trail-dot group-hover:scale-125"></span>
                <span class="text-xs leading-tight text-gray-400 transition-colors group-hover:text-brand-secondary">
                    {{ t('Curriculum') }}
                </span>
            </a>
        </nav>

        <div x-show="step === 'landing'" class="text-center pt-12 pb-20">
            <div class="uppercase tracking-[0.14em] text-brand-secondary text-sm font-semibold mb-2">{{ t('Digital Sovereignty Curriculum') }}</div>
            <h1 class="text-4xl md:text-6xl font-bold text-brand-primary leading-tight mb-6">
                {{ t('Your tools. Your data.') }}<br>
                <span class="text-brand-secondary">{{ t('Your terms.') }}</span>
            </h1>
            <p class="text-lg text-gray-600 max-w-xl mx-auto mb-10 leading-relaxed">
                {{ t('Whether you\'re leading, training, farming, or supporting, digital sovereignty starts with your community. Learn what fits, then build your toolkit.') }}
            </p>
            <button type="button" x-on:click="step = 'role'"
                class="inline-flex items-center gap-2 bg-brand-secondary hover:opacity-90 text-white font-semibold px-8 py-4 rounded-lg shadow transition-opacity">
                {{ t('Start Learning') }} &rarr;
            </button>
            <div class="mt-6">
                <a href="/browse-all" class="text-sm text-gray-600 underline hover:text-brand-secondary">
                    {{ t('or browse the full library') }}
                </a>
            </div>
        </div>

        <div x-show="step === 'role'" style="display: none;">
            <div class="text-center mb-10">
                <div class="text-sm text-brand-secondary font-semibold mb-2">{{ t('QUESTION 1 OF 2') }}</div>
                <h2 class="text-3xl font-bold text-brand-primary">{{ t('I am…') }}</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-3xl mx-auto">
                @foreach([
                    ['label' => t('Community Leader'), 'desc' => t('Looking for sovereign tools to bring back to my community.')],
                    ['label' => t('Trainer / Facilitator'), 'desc' => t('Planning to run digital sovereignty training in my community.')],
                    ['label' => t('Farmer or Co-op Member'), 'desc' => t('Looking for tools that support my farm or cooperative.')],
                    ['label' => t('Technologist or Ally'), 'desc' => t('Supporting a community\'s own technology choices.')],
                ] as $roleCard)
                    <button type="button" x-on:click="pickRole(@js($roleCard['label']))"
                        class="role-card text-left">
                        <h3 class="text-brand-primary text-lg mb-1">{{ $roleCard['label'] }}</h3>
                        <p class="text-sm text-gray-600 leading-relaxed">{{ $roleCard['desc'] }}</p>
                    </button>
                @endforeach
            </div>
        </div>

        <div x-show="step === 'experience'" style="display: none;">
            <div class="text-center mb-10">
                <div class="text-sm text-brand-secondary font-semibold mb-2">{{ t('QUESTION 2 OF 2') }}</div>
                <h2 class="text-3xl font-bold text-brand-primary">{{ t('How familiar are you with digital sovereignty?') }}</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 max-w-2xl mx-auto">
                @if($intro)
                    <button type="button" x-on:click="step = 'intro'" class="role-card text-center py-10">
                        <div class="text-xl font-bold text-brand-primary mb-2">{{ t('New here') }}</div>
                        <p class="text-sm text-gray-600">{{ t('Start with a short primer before the learning map.') }}</p>
                    </button>
                @else
                    {{-- Fail-soft: no intro module seeded — send them to the curriculum page. --}}
                    <a href="{{ route('curriculum') }}" class="role-card block text-center py-10">
                        <div class="text-xl font-bold text-brand-primary mb-2">{{ t('New here') }}</div>
                        <p class="text-sm text-gray-600">{{ t('Start with a short primer before the learning map.') }}</p>
                    </a>
                @endif
                <a href="{{ route('curriculum') }}#map" class="role-card block text-center py-10">
                    <div class="text-xl font-bold text-brand-primary mb-2">{{ t('I know the basics') }}</div>
                    <p class="text-sm text-gray-600">{{ t('Skip ahead, straight to the learning map.') }}</p>
                </a>
            </div>
        </div>

        @if($intro)
            <div x-show="step === 'intro'" style="display: none;">
                <div class="max-w-2xl mx-auto bg-white/60 border border-brand-secondary/40 rounded-2xl p-8 md:p-10">
                    <div class="uppercase tracking-[0.1em] text-brand-secondary text-xs font-semibold mb-3">{{ t('A quick grounding') }}</div>
                    <h2 class="text-2xl md:text-3xl font-bold text-brand-primary mb-4">{{ $intro->title }}</h2>
                    <div class="description text-gray-700 leading-relaxed" data-glossary-scope>{!! $intro->description !!}</div>

                    @if($intro->troves->isNotEmpty())
                        <div class="flex flex-col gap-3 my-6">
                            @foreach($intro->troves as $trove)
                                <x-curriculum-resource-row :trove="$trove" />
                            @endforeach
                        </div>
                    @endif

                    <a href="{{ route('curriculum') }}#map"
                        class="mt-4 inline-flex items-center gap-2 bg-brand-secondary hover:opacity-90 text-white font-semibold px-6 py-3 rounded-lg transition-opacity">
                        {{ t('Continue to the learning map') }} &rarr;
                    </a>
                </div>
            </div>
        @endif
    </div>
    <div class="pb-10"></div>
@endsection
