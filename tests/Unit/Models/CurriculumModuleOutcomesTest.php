<?php

use App\Models\CurriculumModule;

beforeEach(function () {
    config(['branding.locales' => ['en' => 'English', 'fr' => 'Français']]);
    app()->setLocale('en');
});

it('returns structured outcome items for the current locale', function () {
    $module = CurriculumModule::factory()->create([
        'learning_outcomes' => [
            [
                'key' => 'outcome-1',
                'statement' => ['en' => 'Explain the basics', 'fr' => 'Expliquer les bases'],
                'in_practice' => ['en' => 'Write a summary', 'fr' => 'Écrire un résumé'],
            ],
        ],
    ]);

    expect($module->outcomes_list)->toBe([
        [
            'key' => 'outcome-1',
            'statement' => 'Explain the basics',
            'in_practice' => 'Write a summary',
        ],
    ]);
});

it('falls back to another locale when the current locale is missing', function () {
    app()->setLocale('fr');

    $module = CurriculumModule::factory()->create([
        'learning_outcomes' => [
            [
                'key' => 'outcome-1',
                'statement' => ['en' => 'Explain the basics'],
                'in_practice' => null,
            ],
        ],
    ]);

    expect($module->outcomes_list[0]['statement'])->toBe('Explain the basics')
        ->and($module->outcomes_list[0]['in_practice'])->toBeNull();
});

it('returns an empty array when learning_outcomes is null', function () {
    $module = CurriculumModule::factory()->create(['learning_outcomes' => null]);

    expect($module->outcomes_list)->toBe([]);
});

it('returns an empty array for a legacy locale-keyed dict shape', function () {
    $module = CurriculumModule::factory()->make();
    $module->setRawAttributes(array_merge($module->getAttributes(), [
        'learning_outcomes' => json_encode(['en' => "Line one\nLine two"]),
    ]));

    expect($module->outcomes_list)->toBe([]);
});

it('skips items that are not arrays', function () {
    $module = CurriculumModule::factory()->make();
    $module->setRawAttributes(array_merge($module->getAttributes(), [
        'learning_outcomes' => json_encode([
            'not an array item',
            [
                'key' => 'outcome-1',
                'statement' => ['en' => 'Kept outcome'],
                'in_practice' => null,
            ],
        ]),
    ]));

    expect($module->outcomes_list)->toBe([
        [
            'key' => 'outcome-1',
            'statement' => 'Kept outcome',
            'in_practice' => null,
        ],
    ]);
});

it('drops items with an empty statement', function () {
    $module = CurriculumModule::factory()->create([
        'learning_outcomes' => [
            [
                'key' => 'outcome-1',
                'statement' => ['en' => ''],
                'in_practice' => null,
            ],
            [
                'key' => 'outcome-2',
                'statement' => ['en' => 'Kept outcome'],
                'in_practice' => null,
            ],
        ],
    ]);

    expect($module->outcomes_list)->toBe([
        [
            'key' => 'outcome-2',
            'statement' => 'Kept outcome',
            'in_practice' => null,
        ],
    ]);
});
