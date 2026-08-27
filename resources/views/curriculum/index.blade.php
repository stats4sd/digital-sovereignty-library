@extends('layouts.app')
@section('title', t('Curriculum'))
@section('content')
    <div class="container mx-auto px-8 py-12 max-w-5xl">

        {{-- Stepper: Intro (links back to the intro on home) and Curriculum (current). --}}
        @if($intro)
            <nav class="flex items-start justify-center max-w-[16rem] mx-auto mb-12" aria-label="{{ t('Curriculum steps') }}">
                <a href="{{ route('home') }}#intro" class="flex flex-col items-center gap-1.5 group">
                    <span class="trail-dot trail-dot-done"></span>
                    <span class="text-xs leading-tight text-gray-600 transition-colors group-hover:text-brand-secondary">
                        {{ t('Intro') }}
                    </span>
                </a>
                <div class="flex-1 border-t border-dashed border-brand-secondary mt-[5px] mx-2"></div>
                <div class="flex flex-col items-center gap-1.5" aria-current="step">
                    <span class="trail-dot trail-dot-current"></span>
                    <span class="text-xs leading-tight text-brand-primary font-semibold">{{ t('Curriculum') }}</span>
                </div>
            </nav>
        @endif

        {{-- Learning map --}}
        <div id="map" class="scroll-mt-24">
            @include('curriculum.partials.map')
        </div>

        {{-- Toolkit --}}
        @include('curriculum.partials.toolkit')
    </div>
@endsection
