<?php

namespace Database\Seeders\Prep;

use App\Models\CurriculumModule;
use App\Models\GlossaryTerm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $translations = $this->translations();

        foreach ($this->modules() as $definition) {
            $this->seedModule($this->translateModule($definition, $translations));
        }

        foreach ($this->glossaryTerms() as $term => $definition) {
            $termTranslations = ['en' => $term];
            $definitionTranslations = ['en' => $definition];

            foreach ($translations as $locale => $set) {
                $entry = $set['glossary'][$term] ?? [];
                if (! empty($entry['term'])) {
                    $termTranslations[$locale] = $entry['term'];
                }
                if (! empty($entry['definition'])) {
                    $definitionTranslations[$locale] = $entry['definition'];
                }
            }

            GlossaryTerm::firstOrCreate(
                ['term->en' => $term],
                [
                    'term' => $termTranslations,
                    'definition' => $definitionTranslations,
                ],
            );
        }
    }

    /**
     * Per-locale translations of the English content below, one file per locale in
     * curriculum-translations/{locale}.php (see any of those files for the shape).
     * The English source in this class stays the single place content is authored;
     * a missing locale or key simply falls back to English at render time.
     *
     * @return array<string, array{modules: array, glossary: array}>
     */
    private function translations(): array
    {
        $translations = [];

        foreach (glob(__DIR__.'/curriculum-translations/*.php') ?: [] as $file) {
            $translations[basename($file, '.php')] = require $file;
        }

        return $translations;
    }

    /** Adds every available locale to each translatable (['en' => ...]) field of a module. */
    private function translateModule(array $module, array $translations): array
    {
        foreach ($module as $field => $value) {
            if (! is_array($value) || ! array_key_exists('en', $value)) {
                continue;
            }

            foreach ($translations as $locale => $set) {
                $translated = $set['modules'][$module['key']][$field] ?? null;
                if (is_string($translated) && $translated !== '') {
                    $module[$field][$locale] = $translated;
                }
            }
        }

        $module = $this->translateOutcomes($module, $translations);

        foreach ($module['sessions'] ?? [] as $index => $session) {
            foreach (['title', 'summary'] as $field) {
                $module['sessions'][$index][$field] = ['en' => $session[$field]];
            }

            foreach ($translations as $locale => $set) {
                $entry = $set['modules'][$module['key']]['sessions'][$session['slug']] ?? [];

                foreach (['title', 'summary'] as $field) {
                    if (! empty($entry[$field])) {
                        $module['sessions'][$index][$field][$locale] = $entry[$field];
                    }
                }
            }
        }

        return $module;
    }

    /**
     * Structured outcomes are matched to the locale file by position. A locale may give
     * either the legacy one-statement-per-line string or a list of ['statement', 'in_practice']
     * entries. A count mismatch (the English outcomes were rewritten after translation) is
     * skipped so the whole module falls back to English rather than mislabelling items.
     */
    private function translateOutcomes(array $module, array $translations): array
    {
        $outcomes = $module['learning_outcomes'] ?? null;

        if (! is_array($outcomes) || ! array_is_list($outcomes)) {
            return $module;
        }

        foreach ($translations as $locale => $set) {
            $translated = $set['modules'][$module['key']]['learning_outcomes'] ?? null;

            if (is_string($translated)) {
                $translated = collect(preg_split('/\r?\n/', trim($translated)) ?: [])
                    ->map(fn (string $line) => ['statement' => trim($line)])
                    ->all();
            }

            if (! is_array($translated) || $translated === [] || count($translated) !== count($outcomes)) {
                continue;
            }

            foreach (array_values($translated) as $index => $entry) {
                foreach (['statement', 'in_practice'] as $field) {
                    $value = is_array($entry) ? ($entry[$field] ?? null) : null;

                    if (is_string($value) && trim($value) !== '' && isset($outcomes[$index][$field]['en'])) {
                        $outcomes[$index][$field][$locale] = trim($value);
                    }
                }
            }
        }

        $module['learning_outcomes'] = $outcomes;

        return $module;
    }

    private function seedModule(array $definition): void
    {
        $sessions = $definition['sessions'] ?? [];
        $attributes = collect($definition)->except(['key', 'sessions'])->all();

        $module = CurriculumModule::firstOrCreate(
            ['key' => $definition['key']],
            $attributes,
        );

        $this->backfillModule($module, $attributes);

        if ($definition['key'] === 'community-needs') {
            $this->refreshLegacyCommunityNeeds($module);
        }

        foreach ($sessions as $index => $session) {
            $this->seedSession($module, $session, $index);
        }
    }

    /**
     * Fills number/goal/learning_outcomes on rows that already existed before those fields
     * were introduced, without touching a value an admin has since edited — including a
     * deliberate clear to an empty array/string, which is not the same as never having been set.
     */
    private function backfillModule(CurriculumModule $module, array $attributes): void
    {
        $dirty = false;

        foreach (['number', 'goal', 'learning_outcomes'] as $field) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            if (! is_null($module->$field)) {
                continue;
            }

            $module->$field = $attributes[$field];
            $dirty = true;
        }

        if ($dirty) {
            $module->save();
        }
    }

    /**
     * Migration 100300 converted the pre-existing three community-needs outcomes into the
     * structured shape verbatim. Replace them with the four spike outcomes, but only when the
     * stored statements still exactly match that legacy set, so an admin's own edits survive.
     * The description gets the same edit-preserving treatment.
     */
    private function refreshLegacyCommunityNeeds(CurriculumModule $module): void
    {
        $legacyOutcomeStatements = [
            'Map the stakeholders who produce, control, and profit from data in your context',
            'Distinguish between a problem, a constraint, and a genuine need',
            'Write a clear statement of need before choosing any technology',
        ];

        $storedOutcomeStatements = collect($module->learning_outcomes ?? [])
            ->map(fn (array $item) => $item['statement']['en'] ?? null)
            ->all();

        if ($storedOutcomeStatements === $legacyOutcomeStatements) {
            $module->learning_outcomes = $this->communityNeedsOutcomes();
            $module->save();
        }

        $legacyDescription = '<p>Map who holds power and data in your context, then turn real problems into a clear statement of need before reaching for any tool.</p>';

        if ($module->getTranslation('description', 'en') === $legacyDescription) {
            $module->description = ['en' => $this->communityNeedsDescription()];
            $module->save();
        }
    }

    private function seedSession(CurriculumModule $module, array $session, int $index): void
    {
        $outcomes = $module->learning_outcomes ?? [];
        $position = $session['builds_toward_position'] - 1;
        $buildsToward = $outcomes[$position]['key'] ?? null;

        $module->sessions()->firstOrCreate(
            ['slug' => $session['slug']],
            [
                'title' => is_array($session['title']) ? $session['title'] : ['en' => $session['title']],
                'summary' => is_array($session['summary']) ? $session['summary'] : ['en' => $session['summary']],
                'builds_toward' => $buildsToward,
                'order_column' => $index + 1,
            ],
        );
    }

    private function outcome(string $statement, ?string $inPractice = null): array
    {
        return [
            'key' => (string) Str::uuid(),
            'statement' => ['en' => $statement],
            'in_practice' => $inPractice === null ? null : ['en' => $inPractice],
        ];
    }

    private function communityNeedsDescription(): string
    {
        return '<p>This module provides a practical framework for applying the concepts introduced in Modules 1 and 2 to your own context. It focuses on community and organisational settings, while recognising that many of the approaches can also be applied at individual, multi-organisational, and broader system levels. Through practical exercises and real-world examples, you will learn how to understand your operational context, identify genuine needs and constraints, and make informed decisions about data and digital tools before choosing a technology.</p>';
    }

    private function communityNeedsOutcomes(): array
    {
        return [
            $this->outcome(
                'Conduct a system-level diagnostic of your organisational and operational context.',
                'you will complete a Context Diagnostic Canvas and write a short diagnostic statement about your own organisation.',
            ),
            $this->outcome(
                'Define your needs based on real operational constraints, rather than assumed solutions.',
                'you will apply the Problem → Constraint → Need method and build a first Needs Matrix.',
            ),
            $this->outcome(
                'Understand the need to evaluate and select tools based on sovereignty criteria, including control, accessibility, and sustainability.',
                'you will be able to name these criteria and explain why they matter — full tool-by-tool evaluation is covered in Module 4.',
            ),
            $this->outcome(
                'Explain what is required to approach data governance from a needs-based perspective, including ownership, access, and protection.',
                'you will be able to explain the four components of a data governance framework and sketch one for your own organisation.',
            ),
        ];
    }

    private function communityNeedsSessions(): array
    {
        return [
            [
                'slug' => 'understanding-your-operational-context',
                'title' => 'Understanding Your Operational Context',
                'summary' => 'Conduct a system-level diagnostic of organisational and operational context',
                'builds_toward_position' => 1,
            ],
            [
                'slug' => 'defining-what-you-actually-need',
                'title' => 'Defining What You Actually Need',
                'summary' => 'Needs from constraints, not assumed solutions',
                'builds_toward_position' => 2,
            ],
            [
                'slug' => 'deciding-what-belongs-in-a-digital-system',
                'title' => 'Deciding What Belongs in a Digital System',
                'summary' => 'Sovereignty criteria for tool evaluation',
                'builds_toward_position' => 3,
            ],
            [
                'slug' => 'why-data-governance-starts-with-your-needs',
                'title' => 'Why Data Governance Starts with Your Needs',
                'summary' => 'Needs-based data governance',
                'builds_toward_position' => 4,
            ],
        ];
    }

    private function modules(): array
    {
        return [
            // ---- Intro ----
            [
                'key' => 'intro',
                'section' => CurriculumModule::SECTION_INTRO,
                'title' => ['en' => 'What is digital sovereignty?'],
                'description' => ['en' => '<p>In short: the right of a community, not a distant company, to decide how its data, tools, and digital systems are owned, governed, and used. These resources lay out the idea before you start choosing tools.</p>'],
            ],

            // ---- Learning map nodes ----
            [
                'key' => 'digital-landscape',
                'section' => CurriculumModule::SECTION_MAP,
                'title' => ['en' => 'Understanding the Digital Landscape'],
                'description' => ['en' => '<p>Get a shared picture of the digital terrain your community is already standing on: connectivity, devices, literacy, and who controls what.</p>'],
                'number' => 1,
                'goal' => ['en' => 'To help learners build a shared, honest picture of the digital landscape their community already operates in before introducing any new technology.'],
                'learning_outcomes' => [
                    $this->outcome('Describe the connectivity, devices, and digital skills your community is working with'),
                    $this->outcome('Identify who controls the digital systems your community currently relies on'),
                    $this->outcome('Recognize where hybrid (paper and digital) systems make sense'),
                ],
            ],
            [
                'key' => 'knowledge-justice',
                'section' => CurriculumModule::SECTION_MAP,
                'title' => ['en' => 'Understanding Knowledge, Justice & Data Rights'],
                'description' => ['en' => '<p>Ask whose knowledge counts, who owns the data a community produces, and what rights protect both.</p>'],
                'number' => 2,
                'goal' => ['en' => 'To help learners recognise whose knowledge and data rights are at stake in digital systems, and apply a rights-based lens to their own context.'],
                'learning_outcomes' => [
                    $this->outcome('Explain whose knowledge counts in digital systems, and who benefits from it'),
                    $this->outcome('Describe the rights a community holds over the data it produces'),
                    $this->outcome('Apply a rights-based frame (like the CARE principles) to community data'),
                ],
            ],
            [
                'key' => 'community-needs',
                'section' => CurriculumModule::SECTION_MAP,
                'title' => ['en' => 'Understanding Community Needs'],
                'description' => ['en' => $this->communityNeedsDescription()],
                'number' => 3,
                'goal' => ['en' => 'To help learners better understand their community or organisational needs for data and digital tools before making technology decisions.'],
                'learning_outcomes' => $this->communityNeedsOutcomes(),
                'sessions' => $this->communityNeedsSessions(),
            ],
            [
                'key' => 'tech-assessment',
                'section' => CurriculumModule::SECTION_MAP,
                'title' => ['en' => 'Tech Assessment'],
                'description' => ['en' => '<p>Weigh options against ownership, openness, offline function, cost, and lock-in risk before you commit to any single platform.</p>'],
                'number' => 4,
                'goal' => ['en' => 'To help learners assess digital tools against ownership, openness, offline function, cost, and lock-in risk before committing to any platform.'],
                'learning_outcomes' => [
                    $this->outcome("Decide what should be digitized, what shouldn't, and who decides"),
                    $this->outcome('Assess tools against ownership, openness, offline function, and cost'),
                    $this->outcome('Spot lock-in risks before committing to a platform'),
                ],
            ],
            [
                'key' => 'tech-strategy',
                'section' => CurriculumModule::SECTION_MAP,
                'title' => ['en' => 'Tech Strategy'],
                'description' => ['en' => '<p>Set the rules for how data is owned and shared, connect tools to real market access, and pull it all into one local strategy.</p>'],
                'number' => 5,
                'goal' => ['en' => "To help learners translate what they've learned into a single, actionable digital sovereignty strategy for their own community."],
                'learning_outcomes' => [
                    $this->outcome("Set rules for how your community's data is owned, accessed, and stored"),
                    $this->outcome('Connect digital tools to real market access without losing value to middlemen'),
                    $this->outcome('Draw every step together into one local digital sovereignty strategy'),
                ],
            ],

            // ---- FarmHackBox ----
            [
                'key' => 'farm-hack-box',
                'section' => CurriculumModule::SECTION_TOOLKIT,
                'title' => ['en' => 'Get Started with the Farm Hack Box'],
                'description' => ['en' => '<p>A box to locally host your sovereign digital tools. The Farm Hack box is community-built hardware: a small local server your community owns and runs, hosting your own tools and data (file storage, communication, farm records), even without reliable internet.</p>'],
                'note' => ['en' => 'This stop is entirely optional. Everything else in the curriculum and toolkit works with or without the box.'],
                'learning_outcomes' => [
                    $this->outcome('Explain what the Farm Hack box is and what it can host'),
                    $this->outcome("Judge whether local hosting fits your community's needs and capacity"),
                    $this->outcome("Identify what you'd need to set one up"),
                ],
            ],

            // ---- Toolkit pillars ----
            [
                'key' => 'knowledge',
                'section' => CurriculumModule::SECTION_TOOLKIT,
                'title' => ['en' => 'Knowledge'],
                'subtitle' => ['en' => 'Data & Knowledge Repositories'],
                'description' => ['en' => '<p>Collect, store and hold onto your own records and community knowledge.</p>'],
            ],
            [
                'key' => 'collaboration',
                'section' => CurriculumModule::SECTION_TOOLKIT,
                'title' => ['en' => 'Collaboration'],
                'subtitle' => ['en' => 'Social Coordination'],
                'description' => ['en' => '<p>Talk, organize and make decisions together without handing the conversation to Big Tech.</p>'],
            ],
            [
                'key' => 'farm',
                'section' => CurriculumModule::SECTION_TOOLKIT,
                'title' => ['en' => 'Farm'],
                'subtitle' => ['en' => 'Farm Management'],
                'description' => ['en' => '<p>Run day-to-day farm records and hardware on your own terms.</p>'],
            ],
            [
                'key' => 'market',
                'section' => CurriculumModule::SECTION_TOOLKIT,
                'title' => ['en' => 'Market'],
                'subtitle' => ['en' => 'Market Access'],
                'description' => ['en' => '<p>Reach buyers and run sales without losing value to a middleman platform.</p>'],
            ],
        ];
    }

    /**
     * The base glossary by Marion Girard Cisneros (credited in the drawer footer)
     */
    private function glossaryTerms(): array
    {
        return [
            'Big data' => 'Big data refers to vast volumes of structured, semistructured, and unstructured data collected by governments, business and organizations, which can be mined for valuable information.',
            'Community sovereignty' => 'Community sovereignty refers to the autonomy and political self-governance of a community. This concept is based on the principle that each community is free to determine its own destiny and relations with other communities. Data policies should respect the sovereignty of communities to refuse, restrict, or remain unconnected from data collection.',
            'Data' => "Any set of codified symbols representing units of information regarding specific aspects of the world that can be captured or generated, recorded, stored, and transmitted in analogue or digital form.\n\nFrom a farmer’s perspective, data is not an individual resource to be owned or controlled in isolation. It is embedded in their relationships—with their agroecological territories, shared resources, communities, and governments—and emerges from the questions, constraints, and challenges they confront in daily life. Data justice, therefore, cannot be universal or abstract; it must be grounded in these lived relationships. https://agroecologynow.net/what-does-data-justice-mean-for-african-small-holder-farmers-towards-envisioning-a-human-rights-based-approach-in-africa/",
            'Data extraction' => "Data extraction is the process of retrieving data from data sources for further processing or storage. It's often used to migrate data to a new system, integrate data from various sources, or to analyze data.",
            'Data for FSN' => 'Data for Food Security and Nutrition refers to the information collected and analyzed to design and evaluate effective policies in ensuring food security and nutrition.',
            'Data governance' => "A political and economic regime that sets boundaries for data collection by upholding human rights and outlawing any form of data processing that infringes individual and collective autonomy or self-determination. Only data governance for food security and nutrition based on a human rights approach will ensure the availability of high-quality, timely and relevant qualitative and quantitative data that improves food security and nutrition and contribute to the progressive realization of the right to healthy and sustainable food. Small food producers across the world are advocating for new approaches to data governance that protect not only their privacy, but also their sovereignty and autonomy.\n\nSee also: https://digifoodproject.org/what-is-data-governance",
            'Data infrastructures' => 'Data infrastructures refer to the underlying frameworks and services that are necessary for the collection, processing, storage, and distribution of data. This includes hardware, software, networks, and facilities used to develop, test, operate, monitor, manage, and support data applications.',
            'Data justice' => "Data justice refers to the equitable distribution of benefits and burdens related to data. It's a concept that uses social justice ideas to address rights, fairness, and protections in the context of datafication.",
            'Data literacy' => 'Data literacy is the ability to read, understand, create, and communicate data as information. It includes the essential skills to critically analyze, interpret, and use data effectively.',
            'Data privacy' => "Data privacy, also known as information privacy, involves the handling and protection of sensitive data from unauthorized access, use, disclosure, disruption, modification, or destruction. It's a key aspect of data governance that ensures the confidentiality and privacy of personal data.",
            'Data sovereignty' => 'The capacity of various actors of food systems to exercise control and make autonomous decisions over the data collected within their operations.',
            'Datafication' => "With the advent of data-driven technologies like AI-enabled plant breeding, digital farm platforms, and online food retail, immense quantities of data are being generated, leading to what is referred to as 'datafication'. This process encompasses a diverse range of activities, purposes, organisms, communities, and applications across the entire food supply chain. However, the question of who benefits from this transformation and how it addresses existing inequalities leading to the perpetuation of food insecurity largely depends on the governance of this data.",
            'Digital food chain' => 'The application of digital technologies and datafication in agricultural and food systems, transforming traditional food production, distribution, and consumption processes into data-driven ones.',
            'Digital grocery' => 'Digital grocery refers to online platforms where customers can buy food and other grocery items and get them delivered to their doorstep.',
            'Digital technologies' => 'Tools and methods that use digital information to solve problems, communicate, and create products.',
            'Digitalization of food systems' => 'The digitalization of food systems involves the application of digital technologies and datafication in the agricultural sector, transforming traditional food production, distribution, and consumption processes into data-driven ones.',
            'FAIR and CARE principles' => 'The FAIR and CARE principles are guidelines for data management and stewardship, with FAIR standing for Findable, Accessible, Interoperable, and Reusable, while CARE stands for Collective benefit, Authority to control, Responsibility, and Ethics.',
            'False climate solutions' => 'Measures or technologies that claim to address climate change but do not reduce greenhouse gas emissions at the source or create other social and environmental harms.',
            'Free, Prior, Informed Consent (FPIC)' => 'Free, Prior, Informed Consent (FPIC) is a principle that seeks to protect the rights of Indigenous Peoples and local communities in decision-making processes, particularly in relation to their lands, territories, and resources.',
            'Hyper-nudging' => 'Hyper-nudging is a concept that involves the use of big data and machine learning to provide highly personalized nudges, influencing individual behavior in real-time.',
            'Internet of Things' => 'The network of physical objects—"things"—that are embedded with sensors, software, and other technologies for the purpose of connecting and exchanging data with other devices and systems over the internet.',
        ];
    }
}
