@extends('layouts.app')
@section('content')
    <div class="container mx-auto px-8 py-12 max-w-5xl"
        x-data="{
            step: 'landing',
            showIntro: false,
            revealIntro(scroll = false) {
                this.step = 'intro';
                this.showIntro = true;
                if (scroll) this.$nextTick(() => document.getElementById('intro-content')?.scrollIntoView());
            },
            init() {
                // Deep link from the curriculum page's stepper (/home#intro): open the
                // question with the intro content revealed and jump down to it.
                if (location.hash === '#intro') this.revealIntro(true);
            },
        }">

        <div x-show="step === 'landing'" class="text-center pt-12 pb-20">
            <div class="uppercase tracking-[0.14em] text-brand-secondary text-sm font-semibold mb-2">{{ t('Digital Sovereignty Curriculum') }}</div>
            <h1 class="text-4xl md:text-6xl font-bold text-brand-primary leading-tight mb-6">
                {{ t('Your tools. Your data.') }}<br>
                <span class="text-brand-secondary">{{ t('Your terms.') }}</span>
            </h1>
            <p class="text-lg text-gray-600 max-w-xl mx-auto mb-10 leading-relaxed">
                {{ t('Whether you\'re leading, training, farming, or supporting, digital sovereignty starts with your community. Learn what fits, then build your toolkit.') }}
            </p>
            <button type="button" x-on:click="step = 'intro'"
                class="inline-flex items-center gap-2 bg-brand-secondary hover:opacity-90 text-white font-semibold px-8 py-4 rounded-lg shadow transition-opacity">
                {{ t('Start Learning') }} &rarr;
            </button>
            <div class="mt-6">
                <a href="/browse-all" class="text-sm text-gray-600 underline hover:text-brand-secondary">
                    {{ t('or browse the full library') }}
                </a>
            </div>
        </div>

        <div x-show="step === 'intro'" style="display: none;">
            <div class="text-center mb-10">
                <h2 class="text-3xl font-bold text-brand-primary">{{ t('How familiar are you with digital sovereignty?') }}</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 max-w-2xl mx-auto">
                @if($intro)
                    <button type="button" x-on:click="revealIntro()" class="role-card text-center py-10"
                        :class="{ 'role-card-selected': showIntro }" :aria-pressed="showIntro">
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
                <a href="{{ route('curriculum') }}" class="role-card block text-center py-10">
                    <div class="text-xl font-bold text-brand-primary mb-2">{{ t('I know the basics') }}</div>
                    <p class="text-sm text-gray-600">{{ t('Skip ahead, straight to the learning map.') }}</p>
                </a>
            </div>

            @if($intro)
                <div x-show="showIntro" style="display: none;" id="intro-content" class="mt-14 scroll-mt-24">
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

                        <a href="{{ route('curriculum') }}"
                            class="mt-4 inline-flex items-center gap-2 bg-brand-secondary hover:opacity-90 text-white font-semibold px-6 py-3 rounded-lg transition-opacity">
                            {{ t('Continue to the learning map') }} &rarr;
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
    <div class="pb-10"></div>
@endsection
