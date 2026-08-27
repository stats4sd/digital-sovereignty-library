<?php

namespace Database\Seeders\Prep;

use App\Models\CurriculumModule;
use App\Models\GlossaryTerm;
use Illuminate\Database\Seeder;

class CurriculumSeeder extends Seeder
{
    public const BASE_GLOSSARY_SOURCE = 'Marion Girard Cisneros';

    public function run(): void
    {
        foreach ($this->modules() as $module) {
            CurriculumModule::firstOrCreate(
                ['key' => $module['key']],
                collect($module)->except('key')->all(),
            );
        }

        foreach ($this->glossaryTerms() as $term => $definition) {
            GlossaryTerm::firstOrCreate(
                ['term->en' => $term],
                [
                    'term' => ['en' => $term],
                    'definition' => ['en' => $definition],
                    'source' => self::BASE_GLOSSARY_SOURCE,
                ],
            );
        }
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
                'learning_outcomes' => ['en' => implode("\n", [
                    'Describe the connectivity, devices, and digital skills your community is working with',
                    'Identify who controls the digital systems your community currently relies on',
                    'Recognize where hybrid (paper and digital) systems make sense',
                ])],
            ],
            [
                'key' => 'knowledge-justice',
                'section' => CurriculumModule::SECTION_MAP,
                'title' => ['en' => 'Understanding Knowledge, Justice & Data Rights'],
                'description' => ['en' => '<p>Ask whose knowledge counts, who owns the data a community produces, and what rights protect both.</p>'],
                'learning_outcomes' => ['en' => implode("\n", [
                    'Explain whose knowledge counts in digital systems, and who benefits from it',
                    'Describe the rights a community holds over the data it produces',
                    'Apply a rights-based frame (like the CARE principles) to community data',
                ])],
            ],
            [
                'key' => 'community-needs',
                'section' => CurriculumModule::SECTION_MAP,
                'title' => ['en' => 'Understanding Community Needs'],
                'description' => ['en' => '<p>Map who holds power and data in your context, then turn real problems into a clear statement of need before reaching for any tool.</p>'],
                'learning_outcomes' => ['en' => implode("\n", [
                    'Map the stakeholders who produce, control, and profit from data in your context',
                    'Distinguish between a problem, a constraint, and a genuine need',
                    'Write a clear statement of need before choosing any technology',
                ])],
            ],
            [
                'key' => 'tech-assessment',
                'section' => CurriculumModule::SECTION_MAP,
                'title' => ['en' => 'Tech Assessment'],
                'description' => ['en' => '<p>Weigh options against ownership, openness, offline function, cost, and lock-in risk before you commit to any single platform.</p>'],
                'learning_outcomes' => ['en' => implode("\n", [
                    "Decide what should be digitized, what shouldn't, and who decides",
                    'Assess tools against ownership, openness, offline function, and cost',
                    'Spot lock-in risks before committing to a platform',
                ])],
            ],
            [
                'key' => 'tech-strategy',
                'section' => CurriculumModule::SECTION_MAP,
                'title' => ['en' => 'Tech Strategy'],
                'description' => ['en' => '<p>Set the rules for how data is owned and shared, connect tools to real market access, and pull it all into one local strategy.</p>'],
                'learning_outcomes' => ['en' => implode("\n", [
                    "Set rules for how your community's data is owned, accessed, and stored",
                    'Connect digital tools to real market access without losing value to middlemen',
                    'Draw every step together into one local digital sovereignty strategy',
                ])],
            ],

            // ---- FarmHackBox ----
            [
                'key' => 'farm-hack-box',
                'section' => CurriculumModule::SECTION_TOOLKIT,
                'title' => ['en' => 'Get Started with the Farm Hack Box'],
                'description' => ['en' => '<p>A box to locally host your sovereign digital tools. The Farm Hack box is community-built hardware: a small local server your community owns and runs, hosting your own tools and data (file storage, communication, farm records), even without reliable internet.</p>'],
                'note' => ['en' => 'This stop is entirely optional. Everything else in the curriculum and toolkit works with or without the box.'],
                'learning_outcomes' => ['en' => implode("\n", [
                    'Explain what the Farm Hack box is and what it can host',
                    "Judge whether local hosting fits your community's needs and capacity",
                    "Identify what you'd need to set one up",
                ])],
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
     * The base glossary by Marion Girard Cisneros (see BASE_GLOSSARY_SOURCE)
     */
    private function glossaryTerms(): array
    {
        return [
            'Big data' => 'Big data refers to vast volumes of structured, semistructured, and unstructured data collected by governments, business and organizations, which can be mined for valuable information.',
            'Community sovereignty' => 'Community sovereignty refers to the autonomy and political self-governance of a community. This concept is based on the principle that each community is free to determine its own destiny and relations with other communities. Data policies should respect the sovereignty of communities to refuse, restrict, or remain unconnected from data collection.',
            'Data' => 'Any set of codified symbols representing units of information regarding specific aspects of the world that can be captured or generated, recorded, stored, and transmitted in analogue or digital form.',
            'Data extraction' => "Data extraction is the process of retrieving data from data sources for further processing or storage. It's often used to migrate data to a new system, integrate data from various sources, or to analyze data.",
            'Data for FSN' => 'Data for Food Security and Nutrition refers to the information collected and analyzed to design and evaluate effective policies in ensuring food security and nutrition.',
            'Data governance' => 'A political and economic regime that sets boundaries for data collection by upholding human rights and outlawing any form of data processing that infringes individual and collective autonomy or self-determination. Only data governance for food security and nutrition based on a human rights approach will ensure the availability of high-quality, timely and relevant qualitative and quantitative data that improves food security and nutrition and contribute to the progressive realization of the right to healthy and sustainable food. Small food producers across the world are advocating for new approaches to data governance that protect not only their privacy, but also their sovereignty and autonomy.',
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
