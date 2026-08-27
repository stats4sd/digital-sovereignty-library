@extends('layouts.app')
@section('title', $pillar->title)
@section('content')
    <div class="container mx-auto px-8 py-12 max-w-3xl">
        <a href="{{ route('curriculum') }}#toolkit" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-brand-secondary mb-6">
            &larr; {{ t('Back to toolkit') }}
        </a>

        <div class="mb-8">
            @if($pillar->subtitle)
                <div class="uppercase tracking-[0.1em] text-brand-secondary text-xs font-semibold mb-2">{{ $pillar->subtitle }}</div>
            @endif
            <h1 class="text-3xl md:text-4xl font-bold text-brand-primary mb-3">{{ $pillar->title }}</h1>
            @if($pillar->description)
                <div class="description text-gray-700 leading-relaxed max-w-2xl" data-glossary-scope>{!! $pillar->description !!}</div>
            @endif

            @if($pillar->note)
                <div class="note-callout mt-4">{{ $pillar->note }}</div>
            @endif

            @if(count($pillar->outcomes_list))
                <div class="mt-6 bg-white/60 border border-brand-primary/10 rounded-xl px-6 py-5 max-w-2xl">
                    <div class="uppercase tracking-[0.12em] text-brand-secondary text-xs font-semibold mb-3">{{ t('Learning outcomes') }}</div>
                    <ul class="list-disc ml-5 text-gray-700 space-y-1.5" data-glossary-scope>
                        @foreach($pillar->outcomes_list as $outcome)
                            <li>{{ $outcome }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @if($pillar->troves->isNotEmpty())
            <div class="flex flex-col gap-3">
                @foreach($pillar->troves as $trove)
                    <x-curriculum-resource-row :trove="$trove" />
                @endforeach
            </div>
        @else
            <p class="text-gray-500">{{ t('Resources for this section are coming soon.') }}</p>
        @endif
    </div>
@endsection
