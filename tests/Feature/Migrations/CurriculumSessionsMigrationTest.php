<?php

use Illuminate\Support\Facades\DB;

it('converts legacy newline-separated learning outcomes into the structured shape, idempotently', function () {
    $moduleId = DB::table('curriculum_modules')->insertGetId([
        'key' => 'legacy-map-module',
        'section' => 'map',
        'title' => json_encode(['en' => 'Community Needs']),
        'learning_outcomes' => json_encode(['en' => "Explain the basics\nApply the concept"]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $convertOutcomes = include base_path('database/migrations/2026_09_08_100300_convert_curriculum_learning_outcomes_to_structured.php');

    $convertOutcomes->up();

    $module = DB::table('curriculum_modules')->find($moduleId);
    $outcomes = json_decode($module->learning_outcomes, true);

    expect(array_is_list($outcomes))->toBeTrue()
        ->and($outcomes)->toHaveCount(2)
        ->and($outcomes[0]['statement'])->toBe(['en' => 'Explain the basics'])
        ->and($outcomes[0]['in_practice'])->toBeNull()
        ->and($outcomes[0]['key'])->toBeString()
        ->and($outcomes[1]['statement'])->toBe(['en' => 'Apply the concept']);

    // Re-running must be a no-op.
    $convertOutcomes->up();

    expect(json_decode(DB::table('curriculum_modules')->find($moduleId)->learning_outcomes, true))->toBe($outcomes);
});

it('skips modules with an already-structured or empty learning_outcomes value', function () {
    $structuredId = DB::table('curriculum_modules')->insertGetId([
        'key' => 'already-structured',
        'section' => 'map',
        'title' => json_encode(['en' => 'Already Structured']),
        'learning_outcomes' => json_encode([
            ['key' => 'existing-key', 'statement' => ['en' => 'Existing'], 'in_practice' => null],
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $emptyId = DB::table('curriculum_modules')->insertGetId([
        'key' => 'empty-outcomes',
        'section' => 'map',
        'title' => json_encode(['en' => 'Empty Outcomes']),
        'learning_outcomes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $convertOutcomes = include base_path('database/migrations/2026_09_08_100300_convert_curriculum_learning_outcomes_to_structured.php');
    $convertOutcomes->up();

    expect(json_decode(DB::table('curriculum_modules')->find($structuredId)->learning_outcomes, true))->toBe([
        ['key' => 'existing-key', 'statement' => ['en' => 'Existing'], 'in_practice' => null],
    ])->and(DB::table('curriculum_modules')->find($emptyId)->learning_outcomes)->toBeNull();
});

it('skips a module whose learning_outcomes decodes to a non-array scalar', function () {
    $scalarId = DB::table('curriculum_modules')->insertGetId([
        'key' => 'scalar-outcomes',
        'section' => 'map',
        'title' => json_encode(['en' => 'Scalar Outcomes']),
        'learning_outcomes' => json_encode('just a string'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $convertOutcomes = include base_path('database/migrations/2026_09_08_100300_convert_curriculum_learning_outcomes_to_structured.php');

    $convertOutcomes->up();

    expect(json_decode(DB::table('curriculum_modules')->find($scalarId)->learning_outcomes, true))
        ->toBe('just a string');
});
