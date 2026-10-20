@php
    $heading = t('Key initiatives for supporting community-led digital pathways');

    $initiatives = [
        [
            'title' => 'Earth Defenders Toolkit',
            'description' => t('Have you tried to find a tool to help your community take action, only to be overwhelmed by the range and extent of options? You are not alone. There are numerous tools that may be useful to earth defender communities, and it can be difficult to figure out which ones will work best for you. This interactive toolfinder guide is designed to help you find the right tool by answering a few questions that are tailored to earth defender work.'),
            'url' => 'https://www.earthdefenderstoolkit.com/toolkit/',
            'image' => 'earth_defenders.jpg',
        ],
        [
            'title' => 'AfriTAP',
            'description' => t('The Africa Technology Assessment Platform (AfriTAP) brings together African civil society groups, social movements, independent scientists, lawyers and media professionals working together to assess the implications of new emerging technologies on the continent. AfriTAP aims to enable Africans to choose their future pathway and assert control over new technologies and the continent’s technological advancement.'),
            'url' => 'https://assess.technology/regional-technology-assessment-platforms/africa/',
            'image' => 'afritap.jpg',
        ],
        [
            'title' => 'Red TECLA',
            'description' => t('The Latin American Network for the Assessment of Technologies, called Red TECLA, is a network of organizations and individuals who challenge the technological tools and processes that are being imposed upon peoples, without consideration of their impacts on life, nature and the livelihoods of peoples, communities and collectives.'),
            'url' => 'https://assess.technology/regional-technology-assessment-platforms/latin-america/',
            'image' => 'red_tecla.jpg',
        ],
        [
            'title' => 'GOAT',
            'description' => t('Aligning technology and actual users. Finding people to try, test, break, and give feedback on new technology is hard but critically important to making technology relevant and useful. By bringing users and developers together with open lines of communication, we can increase the utility of open technology in ag across the board.'),
            'url' => 'https://goatech.org/',
            'image' => 'goat.jpg',
        ],
        [
            'title' => 'GIAA',
            'description' => t('The Grassroots Innovations Assembly for Agroecology (GIAA) is valuing and promoting local knowledge and daily inventiveness of food producers worldwide. It is an Assembly of grassroots organizations and local networks, rooted in agroecology (Nyéléni, 2015), active to strengthen, defend and share food producers’ knowledge, tools, equipment, machines and innovations in general.'),
            'url' => 'https://www.gia-agroecology.org/',
            'image' => 'giaa.jpg',
        ],
        [
            'title' => 'SPACAT',
            'description' => t('SPACAT provides funds to “on-the-ground” organizations working to strengthen local and regional food systems who want to adopt open source tools. Examples include (but not limited to) organizations promoting adoption of sustainable production practices (organic, agroecological, regenerative) or strengthening farmer-consumer connections.'),
            'url' => 'https://www.our-sci.net/support/',
            'image' => 'our_sci.jpg',
        ],
        [
            'title' => 'Guardian Connector',
            'description' => t('Guardian Connector is a free and open-source Indigenous data sovereignty platform for communities to securely centralize, access, and derive insights from their environmental and cultural data. Developed through collaboration between Indigenous communities, Conservation Metrics, and technical partners, Guardian Connector addresses the unique challenges faced by communities on the frontlines of protecting their territories.'),
            'url' => 'https://docs.guardianconnector.net/overview/',
            'image' => 'guardian_connector.jpg',
        ],
        [
            'title' => 'Float',
            'description' => t('Float is an experiment in community-led prioritisation and resource allocation that aims to bridge the needs and expertise of agroecological farmers with developers, civil society actors, designers, and technologists.'),
            'url' => 'https://float.ag/',
            'image' => 'float.jpg',
        ],
    ];
@endphp

@extends('layouts.app')
@section('title', t('Get Involved'))
@section('content')
    <div class="container mx-auto px-8 py-12 max-w-5xl">
        <div class="mb-10">
            <div class="uppercase tracking-[0.14em] text-brand-secondary text-sm font-semibold mb-2">{{ t('Get Involved') }}</div>
            <h1 class="text-3xl md:text-4xl font-bold text-brand-primary">{{ $heading }}</h1>
        </div>

        <div class="flex flex-col gap-6">
            @foreach($initiatives as $initiative)
                @php $imagePath = 'images/get-involved/'.$initiative['image']; @endphp
                <article class="role-card flex flex-col md:flex-row md:items-start gap-6 md:gap-8 cursor-default">
                    <div class="w-full md:w-56 h-40 shrink-0 flex items-center justify-center rounded-xl bg-white/80 p-3">
                        @if(file_exists(public_path($imagePath)))
                            <img src="{{ asset($imagePath) }}" alt="{{ $initiative['title'] }}"
                                class="max-w-full max-h-full object-contain">
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <h2 class="text-xl md:text-2xl font-bold text-brand-primary mb-2">{{ $initiative['title'] }}</h2>
                        <p class="text-gray-700 leading-relaxed mb-4">{{ $initiative['description'] }}</p>
                        <a href="{{ $initiative['url'] }}" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-2 font-semibold text-brand-secondary hover:underline break-all">
                            {{ rtrim(preg_replace('#^https?://#', '', $initiative['url']), '/') }} <x-dir-arrow />
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
@endsection
