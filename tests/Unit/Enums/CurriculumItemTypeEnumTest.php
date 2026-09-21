<?php

use App\Enums\CurriculumItemType;

it('has the expected cases and stored values', function () {
    expect(array_map(fn (CurriculumItemType $type) => $type->value, CurriculumItemType::cases()))
        ->toBe(['prose', 'callout', 'note_prompt', 'note_canvas', 'note_matrix', 'quiz', 'trove']);
});

it('marks only the learner-input types as activities', function () {
    $activities = collect(CurriculumItemType::cases())
        ->filter(fn (CurriculumItemType $type) => $type->isActivity())
        ->map(fn (CurriculumItemType $type) => $type->value)
        ->values()
        ->all();

    expect($activities)->toBe(['note_prompt', 'note_canvas', 'note_matrix', 'quiz']);
});

it('gives every case a label and an icon and exposes them to Filament', function () {
    foreach (CurriculumItemType::cases() as $type) {
        expect($type->label())->toBeString()->not->toBe('')
            ->and($type->icon())->toStartWith('heroicon-')
            ->and($type->getLabel())->toBe($type->label())
            ->and($type->getIcon())->toBe($type->icon());
    }

    expect(CurriculumItemType::Trove->label())->toBe('Resource');
});
