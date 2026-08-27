@extends('layouts.app')
@section('title', $module->title)
@section('content')
    <div class="container mx-auto px-8 py-12 max-w-3xl">
        <a href="{{ route('curriculum') }}#map" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-brand-secondary mb-6">
            &larr; {{ t('Back to the learning map') }}
        </a>

        <div class="mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-brand-primary mb-3">{{ $module->title }}</h1>

            @if($module->description)
                <div class="description text-gray-700 leading-relaxed max-w-2xl" data-glossary-scope>{!! $module->description !!}</div>
            @endif

            @if($module->note)
                <div class="note-callout mt-4">{{ $module->note }}</div>
            @endif

            @if(count($module->outcomes_list))
                <div class="mt-6 bg-white/60 border border-brand-primary/10 rounded-xl px-6 py-5 max-w-2xl">
                    <div class="uppercase tracking-[0.12em] text-brand-secondary text-xs font-semibold mb-3">{{ t('Learning outcomes') }}</div>
                    <ul class="list-disc ml-5 text-gray-700 space-y-1.5" data-glossary-scope>
                        @foreach($module->outcomes_list as $outcome)
                            <li>{{ $outcome }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="divider"></div>
        <h2 class="text-brand-primary mb-4">{{ t('Resources') }}</h2>

        @if($module->troves->isNotEmpty())
            <div class="flex flex-col gap-3">
                @foreach($module->troves as $trove)
                    <x-curriculum-resource-row :trove="$trove" />
                @endforeach
            </div>
        @else
            <p class="text-gray-500">{{ t('Resources for this stop are coming soon.') }}</p>
        @endif
    </div>
@endsection
