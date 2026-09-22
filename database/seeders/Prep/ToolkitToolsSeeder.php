<?php

namespace Database\Seeders\Prep;

use App\Models\Tag;
use App\Models\TagType;
use App\Models\ToolkitModule;
use App\Models\Trove;
use App\Models\TroveType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

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

        $tags = $this->load('toolkit/tags.yaml');

        $toolsTagType = TagType::firstOrCreate(
            ['slug' => $tags['tag_type']['slug']],
            [
                'label' => $tags['tag_type']['label'],
                'description' => $tags['tag_type']['description'],
                'freetext' => $tags['tag_type']['freetext'],
                'show_in_filter' => $tags['tag_type']['show_in_filter'],
            ],
        );

        $pillarTags = collect($tags['pillar_tags'])->map(fn (array $name, string $key) => Tag::firstOrCreate(
            ['type_id' => $toolsTagType->id, 'name->en' => $name['en']],
            ['name' => $name, 'type_id' => $toolsTagType->id],
        ));

        foreach ($this->toolFiles() as $file) {
            $definition = Yaml::parseFile($file);
            $pillar = ToolkitModule::where('key', $definition['pillar'])->first();

            foreach (array_values($definition['tools']) as $index => $tool) {
                $trove = Trove::withDrafts()
                    ->where('title->en', $tool['title']['en'])
                    ->first();

                if (! $trove) {
                    $trove = Trove::withoutSyncingToSearch(fn () => Trove::create([
                        'title' => $tool['title'],
                        'description' => $tool['description'],
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

                if ($pillarTag = $pillarTags->get($definition['pillar'])) {
                    $trove->tags()->syncWithoutDetaching([$pillarTag->id]);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $relativePath): array
    {
        return Yaml::parseFile(database_path('curriculum/'.$relativePath));
    }

    /**
     * @return list<string>
     */
    private function toolFiles(): array
    {
        $files = glob(database_path('curriculum/toolkit/tools/*.yaml')) ?: [];
        sort($files);

        return $files;
    }
}
