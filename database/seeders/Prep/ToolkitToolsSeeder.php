<?php

namespace Database\Seeders\Prep;

use App\Models\CurriculumModule;
use App\Models\Tag;
use App\Models\TagType;
use App\Models\Trove;
use App\Models\TroveType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ToolkitToolsSeeder extends Seeder
{
    public function run(): void
    {
        $uploader = User::query()->orderBy('id')->first()
            ?? User::create([
                'name' => 'Library System',
                'email' => 'system@digital-sovereignty-library.local',
                'password' => bcrypt(Str::random(40)),
            ]);

        $toolType = TroveType::where('label->en', 'Tool')->first();

        $toolsTagType = TagType::firstOrCreate(
            ['slug' => 'tools'],
            [
                'label' => ['en' => 'Tools'],
                'description' => ['en' => 'Tool areas of the sovereign toolkit'],
                'freetext' => false,
                'show_in_filter' => true,
            ],
        );

        $pillarTags = collect([
            'knowledge' => 'Knowledge',
            'collaboration' => 'Collaboration',
            'farm' => 'Farm',
            'market' => 'Market',
        ])->map(fn (string $name) => Tag::firstOrCreate(
            ['type_id' => $toolsTagType->id, 'name->en' => $name],
            ['name' => ['en' => $name], 'type_id' => $toolsTagType->id],
        ));

        foreach ($this->tools() as $pillarKey => $tools) {
            $pillar = CurriculumModule::forSection(CurriculumModule::SECTION_TOOLKIT)
                ->where('key', $pillarKey)
                ->first();

            foreach (array_values($tools) as $index => $tool) {
                $trove = Trove::withDrafts()
                    ->where('title->en', $tool['title'])
                    ->first();

                if (! $trove) {
                    $trove = Trove::withoutSyncingToSearch(fn () => Trove::create([
                        'title' => ['en' => $tool['title']],
                        'description' => ['en' => $tool['description']],
                        'trove_type_id' => $toolType?->id,
                        'source' => true,
                        'creation_date' => now()->toDateString(),
                        'uploader_id' => $uploader->id,
                        'published_at' => ($tool['published'] ?? true) ? now() : null,
                    ]));
                }

                $pillar?->troves()->syncWithoutDetaching([
                    $trove->id => ['order_column' => $index + 1],
                ]);

                if ($pillarTag = $pillarTags->get($pillarKey)) {
                    $trove->tags()->syncWithoutDetaching([$pillarTag->id]);
                }
            }
        }
    }

    private function tools(): array
    {
        return [
            'knowledge' => [
                [
                    'title' => 'ODK (Open Data Kit)',
                    'description' => '<p>ODK is open-source software for collecting data with mobile devices (surveys, farm records, monitoring forms) that keeps working fully offline and syncs when a connection is available. Because you can run the server yourself, the data your community collects stays under your control.</p>'
                        .'<h2>How to use it</h2>'
                        .'<p>Design a form (ODK Build or an XLSForm spreadsheet), load it onto phones with the ODK Collect app, gather responses offline, and send them to your own ODK Central server when online. Tutorial videos are linked here.</p>',
                ],
                [
                    'title' => 'CoMapeo',
                    'description' => '<p>CoMapeo is an offline-first mapping tool built for communities documenting their own land, territory and observations. Maps and data live on the devices of the people who made them, with no company server required.</p>'
                        .'<h2>How to use it</h2>'
                        .'<p>Install the app, create or join a project with your community, and start dropping observations onto the map: photos, notes, GPS points. Devices sync with each other directly when nearby. The introduction video linked here shows a project from setup to shared map.</p>',
                ],
                [
                    'title' => 'Participatory Mapping with Mapeo',
                    'description' => '<p>Mapeo (CoMapeo\'s predecessor) pioneered participatory territory mapping: communities collectively record places, boundaries and evidence on their own devices, on their own terms.</p>'
                        .'<h2>How to use it</h2>'
                        .'<p>Agree as a group what you want to map and why, then use Mapeo on shared devices to collect points and stories in the field. The linked video shows a real participatory mapping process, useful for planning your own even if you adopt CoMapeo.</p>',
                ],
                [
                    'title' => 'EcoAgTube',
                    'description' => '<p>EcoAgTube is a video-sharing platform for agroecology and rural communities: an alternative to commercial platforms, run for and by the movement, where practical farming knowledge is shared without ads or algorithmic control.</p>'
                        .'<h2>How to use it</h2>'
                        .'<p>Browse or search for videos in your language, and create a free account to upload your own: field techniques, trainings, community stories. The introduction video linked here gives a tour.</p>',
                ],
                [
                    'title' => 'Guardian Connector',
                    'description' => '<p>Guardian Connector is data infrastructure for communities defending their territories: it brings together the data collected with tools like Mapeo and ODK into storage and dashboards the community governs.</p>'
                        .'<h2>How to use it</h2>'
                        .'<p>Connect your field-data tools so observations flow into one place, then use the dashboards to review, analyse and share what your community decides to share. The linked video introduces the setup and a real deployment.</p>',
                ],
            ],
            'collaboration' => [
                [
                    'title' => 'Tech Support by Our Sci',
                    'description' => '<p>Our Sci supports communities and researchers running their own open-source tools: help with setup, hosting and keeping systems running, so choosing sovereign technology doesn\'t mean being on your own.</p>'
                        .'<h2>How to use it</h2>'
                        .'<p>Watch the linked video to understand what support is available and when to reach out. If your community is adopting tools from this toolkit and needs a technical partner, this is a place to find one.</p>',
                ],
            ],
            'farm' => [
                [
                    'title' => 'LiteFarm',
                    'description' => '<p>LiteFarm is free, open-source farm management software built with and for farmers (crop planning, task management, harvest records and certification paperwork), developed at the University of British Columbia with sustainability at its core.</p>'
                        .'<h2>How to use it</h2>'
                        .'<p>Create a free account, map your farm, and start planning crops and logging tasks; records build up into reports you own and can export. Two linked videos cover it from both sides: the principles behind LiteFarm, and a practical walkthrough from a farming association using it day to day.</p>',
                ],
                [
                    'title' => 'farmOS',
                    'description' => '<p>farmOS is open-source farm record-keeping you can self-host: plantings, animals, inputs, observations and maps, stored in a system your farm or cooperative controls completely.</p>'
                        .'<h2>How to use it</h2>'
                        .'<p>Use the hosted Farmier service or install farmOS on your own server (including a local box), then record activities as logs against your fields and assets. Start simple, with a few key records, and grow into it.</p>',
                    'published' => false,
                ],
            ],
            'market' => [
                [
                    'title' => 'Open Food Network',
                    'description' => '<p>Open Food Network is open-source software for selling food directly: farmers and food hubs run their own online shops and manage orders without losing a cut, or their customer relationships, to a commercial marketplace.</p>'
                        .'<h2>How to use it</h2>'
                        .'<p>Set up a producer profile and shopfront on your local OFN instance, list products, and manage orders and pickups/deliveries through the platform. The linked video shows how farmers and buyers connect through it.</p>',
                ],
                [
                    'title' => 'Points of Sales',
                    'description' => '<p>Point-of-sale tools your community can own: taking payments and tracking sales at markets and farm stands without locking your transaction data into a commercial provider.</p>'
                        .'<h2>How to use it</h2>'
                        .'<p>A practical walkthrough video is being recorded and will be linked here. It will cover choosing and setting up a point-of-sale tool for a small farm or co-op stand.</p>',
                    'published' => false,
                ],
                [
                    'title' => 'KPL Food Coop Market Hub',
                    'description' => '<p>The Kenyan Peasant League built its own cooperative food-market platform: a homegrown alternative to commercial marketplaces, showing what a community-owned market hub can look like in practice.</p>'
                        .'<h2>How to use it</h2>'
                        .'<p>Listen to the linked recording to hear how KPL designed, built and runs the hub: the decisions, the difficulties, and what it changed for members. Use it as a blueprint if your cooperative is considering the same path.</p>',
                ],
            ],
        ];
    }
}
