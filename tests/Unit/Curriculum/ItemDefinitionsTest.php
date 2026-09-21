<?php

use App\Curriculum\Items\ItemDefinition;
use App\Curriculum\Items\ItemRegistry;
use App\Curriculum\Items\QuizItem;
use App\Curriculum\Items\TroveItem;
use App\Enums\CurriculumItemType;
use App\Models\CurriculumSessionItem;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['app.locales' => ['en' => 'English', 'fr' => 'French'], 'branding.locales' => ['en' => 'English', 'fr' => 'French']]);
    app()->setLocale('en');
});

/**
 * The factory's per-type configs double as the seeder-shaped fixtures every definition
 * must accept unchanged.
 */
function fixtureConfig(CurriculumItemType $type): array
{
    $factory = CurriculumSessionItem::factory();

    $state = match ($type) {
        CurriculumItemType::Prose => $factory->prose(),
        CurriculumItemType::Callout => $factory->callout(),
        CurriculumItemType::NotePrompt => $factory->notePrompt(),
        CurriculumItemType::NoteCanvas => $factory->noteCanvas(),
        CurriculumItemType::NoteMatrix => $factory->noteMatrix(),
        CurriculumItemType::Quiz => $factory->quiz(),
        CurriculumItemType::Trove => $factory->trove(1),
    };

    return $state->raw()['config'] ?? [];
}

function definitionFor(CurriculumItemType $type): ItemDefinition
{
    return app(ItemRegistry::class)->for($type);
}

it('registers one definition per item type, with matching Builder blocks and views', function () {
    $registry = app(ItemRegistry::class);

    expect($registry->all())->toHaveCount(count(CurriculumItemType::cases()));

    foreach (CurriculumItemType::cases() as $type) {
        $definition = $registry->for($type);

        expect($definition->type())->toBe($type)
            ->and($definition->view())->toBe('curriculum.items.'.$type->value)
            ->and($registry->for($type->value))->toBe($definition);
    }

    $blocks = $registry->blocks();

    expect($blocks)->toHaveCount(count(CurriculumItemType::cases()))
        ->and(array_map(fn (Block $block) => $block->getName(), $blocks))
        ->toBe(array_map(fn (CurriculumItemType $type) => $type->value, CurriculumItemType::cases()));
});

it('throws for an unknown type', function () {
    expect(fn () => app(ItemRegistry::class)->for('carousel'))->toThrow(InvalidArgumentException::class);
});

it('resolves a definition from an item row', function () {
    $item = CurriculumSessionItem::factory()->quiz()->make();

    expect($item->definition())->toBeInstanceOf(QuizItem::class);
});

it('accepts the fixture config for every type', function (CurriculumItemType $type) {
    $definition = definitionFor($type);
    $fixture = fixtureConfig($type);

    $normalised = $definition->normalise($fixture);

    // Nulls for optional translatable leaves are the only permitted difference.
    $stripNulls = function (array $config) use (&$stripNulls): array {
        return collect($config)
            ->reject(fn ($value) => $value === null)
            ->map(fn ($value) => is_array($value) ? $stripNulls($value) : $value)
            ->all();
    };

    expect($stripNulls($normalised))->toEqual($stripNulls($fixture));
})->with(fn () => collect(CurriculumItemType::cases())
    ->reject(fn (CurriculumItemType $t) => $t === CurriculumItemType::Trove) // no config; covered below
    ->mapWithKeys(fn ($t) => [$t->value => $t])
    ->all());

it('requires ids to be unique only within their own question or column', function () {
    $quiz = fixtureConfig(CurriculumItemType::Quiz);
    $quiz['items'][] = ['id' => 'q2', ...Arr::except($quiz['items'][0], ['id'])]; // same option ids a/b as q1
    $quiz['passMark'] = 2;

    expect(definitionFor(CurriculumItemType::Quiz)->normalise($quiz)['items'])->toHaveCount(2);

    $quiz['items'][1]['options'][1]['id'] = 'a';
    expect(fn () => definitionFor(CurriculumItemType::Quiz)->normalise($quiz))->toThrow(ValidationException::class);

    $matrix = fixtureConfig(CurriculumItemType::NoteMatrix);
    $matrix['fields'][] = ['id' => 'urgency', ...Arr::except($matrix['fields'][1], ['id'])]; // same choice ids high/low

    expect(definitionFor(CurriculumItemType::NoteMatrix)->normalise($matrix)['fields'])->toHaveCount(3);

    $matrix['fields'][2]['options'][1]['id'] = 'high';
    expect(fn () => definitionFor(CurriculumItemType::NoteMatrix)->normalise($matrix))->toThrow(ValidationException::class);
});

it('treats rich text with no visible text as unfilled', function () {
    expect(fn () => definitionFor(CurriculumItemType::Prose)->normalise(['body' => ['en' => '<hr><ul><li></li></ul>']]))
        ->toThrow(ValidationException::class);
});

it('rejects a fixture missing a required leaf', function (CurriculumItemType $type, string $leaf) {
    $config = fixtureConfig($type);
    data_forget($config, $leaf);

    expect(fn () => definitionFor($type)->normalise($config))->toThrow(ValidationException::class);
})->with([
    'prose body' => [CurriculumItemType::Prose, 'body'],
    'callout kind' => [CurriculumItemType::Callout, 'kind'],
    'callout body' => [CurriculumItemType::Callout, 'body'],
    'note prompt heading' => [CurriculumItemType::NotePrompt, 'heading'],
    'note prompt prompt' => [CurriculumItemType::NotePrompt, 'prompt'],
    'note prompt rows' => [CurriculumItemType::NotePrompt, 'rows'],
    'note canvas fields' => [CurriculumItemType::NoteCanvas, 'fields'],
    'note canvas field label' => [CurriculumItemType::NoteCanvas, 'fields.0.label'],
    'note canvas field id' => [CurriculumItemType::NoteCanvas, 'fields.0.id'],
    'note matrix rows' => [CurriculumItemType::NoteMatrix, 'rows'],
    'note matrix field kind' => [CurriculumItemType::NoteMatrix, 'fields.0.kind'],
    'quiz passMark' => [CurriculumItemType::Quiz, 'passMark'],
    'quiz stem' => [CurriculumItemType::Quiz, 'items.0.stem'],
    'quiz option text' => [CurriculumItemType::Quiz, 'items.0.options.0.text'],
]);

it('strips empty locales, drops unknown keys and sanitises html', function () {
    $normalised = definitionFor(CurriculumItemType::Prose)->normalise([
        'heading' => ['en' => '', 'fr' => null],
        'body' => ['en' => '<p>Safe</p><script>alert(1)</script>', 'fr' => '  '],
        'unknown' => 'dropped',
        'key' => 'not-config',
    ]);

    expect($normalised)->toBe([
        'heading' => null,
        'body' => ['en' => '<p>Safe</p>'],
    ]);
});

it('treats a bare string leaf as the fallback-locale value', function () {
    $normalised = definitionFor(CurriculumItemType::Callout)->normalise([
        'kind' => 'recall',
        'body' => '<p>Plain English</p>',
    ]);

    expect($normalised['body'])->toBe(['en' => '<p>Plain English</p>']);
});

it('re-indexes uuid-keyed repeater lists and casts scalars', function () {
    $normalised = definitionFor(CurriculumItemType::NoteMatrix)->normalise([
        'heading' => ['en' => 'Needs'],
        'rows' => '3',
        'rowLabel' => ['en' => 'Challenge'],
        'fields' => [
            'uuid-a' => ['id' => 'need', 'label' => ['en' => 'Need'], 'placeholder' => null, 'kind' => 'text', 'options' => []],
            'uuid-b' => [
                'id' => 'priority',
                'label' => ['en' => 'Priority'],
                'kind' => 'select',
                'options' => [
                    'uuid-x' => ['id' => 'high', 'text' => ['en' => 'High']],
                    'uuid-y' => ['id' => 'low', 'text' => ['en' => 'Low']],
                ],
            ],
        ],
    ]);

    expect($normalised['rows'])->toBe(3)
        ->and(array_keys($normalised['fields']))->toBe([0, 1])
        ->and(array_keys($normalised['fields'][1]['options']))->toBe([0, 1])
        ->and($normalised['fields'][1]['options'][1]['id'])->toBe('low');
});

it('rejects duplicate ids and invalid kinds inside lists', function () {
    $config = fixtureConfig(CurriculumItemType::NoteCanvas);
    $config['fields'][1]['id'] = $config['fields'][0]['id'];

    expect(fn () => definitionFor(CurriculumItemType::NoteCanvas)->normalise($config))->toThrow(ValidationException::class);

    $config = fixtureConfig(CurriculumItemType::Callout);
    $config['kind'] = 'warning';

    expect(fn () => definitionFor(CurriculumItemType::Callout)->normalise($config))->toThrow(ValidationException::class);
});

it('requires a select column to have choices', function () {
    $config = fixtureConfig(CurriculumItemType::NoteMatrix);
    $config['fields'][1]['options'] = [];

    expect(fn () => definitionFor(CurriculumItemType::NoteMatrix)->normalise($config))->toThrow(ValidationException::class);
});

it('casts quiz correct flags to booleans and enforces the answer-key rules', function () {
    $definition = definitionFor(CurriculumItemType::Quiz);
    $config = fixtureConfig(CurriculumItemType::Quiz);
    $config['items'][0]['options'][0]['correct'] = '1';
    $config['items'][0]['options'][1]['correct'] = '0';
    $config['passMark'] = '1';

    $normalised = $definition->normalise($config);
    expect($normalised['items'][0]['options'][0]['correct'])->toBeTrue()
        ->and($normalised['items'][0]['options'][1]['correct'])->toBeFalse()
        ->and($normalised['passMark'])->toBe(1);

    // No correct option at all.
    $none = fixtureConfig(CurriculumItemType::Quiz);
    $none['items'][0]['options'][0]['correct'] = false;
    expect(fn () => $definition->normalise($none))->toThrow(ValidationException::class);

    // Pick-one with two correct options.
    $two = fixtureConfig(CurriculumItemType::Quiz);
    $two['items'][0]['options'][1]['correct'] = true;
    expect(fn () => $definition->normalise($two))->toThrow(ValidationException::class);

    // Pick-many with two correct options is fine.
    $two['items'][0]['kind'] = 'pick-many';
    expect($definition->normalise($two)['items'][0]['kind'])->toBe('pick-many');

    // Pass mark above the question count.
    $high = fixtureConfig(CurriculumItemType::Quiz);
    $high['passMark'] = 5;
    expect(fn () => $definition->normalise($high))->toThrow(ValidationException::class);
});

it('stores no config for trove items', function () {
    expect(definitionFor(CurriculumItemType::Trove))->toBeInstanceOf(TroveItem::class)
        ->and(definitionFor(CurriculumItemType::Trove)->normalise(['trove_id' => 3, 'intro' => ['en' => 'x'], 'body' => 'y']))->toBe([]);
});

it('labels blocks from their heading, kind or trove title', function () {
    $trove = publishedTrove(['title' => ['en' => 'Makueni case study']]);

    expect(definitionFor(CurriculumItemType::Prose)->blockLabel(['heading' => ['en' => 'Why this matters']]))->toBe('Text: Why this matters')
        ->and(definitionFor(CurriculumItemType::Prose)->blockLabel([]))->toBe('Text')
        ->and(definitionFor(CurriculumItemType::Callout)->blockLabel(['kind' => 'example', 'heading' => ['en' => 'Cocoa']]))->toBe('Callout (Example): Cocoa')
        ->and(definitionFor(CurriculumItemType::Trove)->blockLabel(['trove_id' => $trove->id]))->toBe('Resource: Makueni case study')
        ->and(definitionFor(CurriculumItemType::Trove)->blockLabel([]))->toBe('Resource');
});

it('builds every block with a hidden key field first', function () {
    foreach (app(ItemRegistry::class)->blocks() as $block) {
        $components = $block->getDefaultChildComponents();

        expect($components[0])->toBeInstanceOf(Hidden::class)
            ->and($components[0]->getName())->toBe('key')
            ->and(count($components))->toBeGreaterThan(1);
    }
});
