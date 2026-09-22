<?php

namespace App\Curriculum\Items;

use App\Enums\CurriculumItemType;
use App\Filament\Translatable\Form\TranslatableComboField;
use App\Support\HtmlSanitizer;
use App\Support\TranslatableText;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Everything the app knows about one CurriculumItemType: the Filament Builder block admins
 * edit it with, the shape of its `config` JSON (which leaves are translatable locale
 * dictionaries, HTML, integers, lists), validation, and the Blade view that renders it.
 *
 * `normalise()` is the single entry point for config written from anywhere (admin Builder
 * state, seeders): it drops unknown keys, re-indexes Repeater lists, strips empty locales,
 * sanitises HTML, casts scalars and validates, throwing a ValidationException on bad input.
 */
abstract class ItemDefinition
{
    abstract public function type(): CurriculumItemType;

    /**
     * Top-level `config` keys this type stores. Anything else in the block data is dropped.
     *
     * @return list<string>
     */
    abstract public function configKeys(): array;

    /**
     * Dot paths (with `*` for list members) of the translatable leaves inside `config`.
     * Each is stored as a locale dictionary `['en' => …, 'fr' => …]` (spec D11).
     *
     * @return list<string>
     */
    abstract public function translatableLeaves(): array;

    /**
     * Laravel validation rules over a normalised `config` array.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * The block's form fields, excluding the hidden `key` that block() prepends.
     *
     * @return list<Component>
     */
    abstract protected function fields(): array;

    /**
     * Translatable leaves whose values are rich-text HTML and must be sanitised.
     *
     * @return list<string>
     */
    public function htmlLeaves(): array
    {
        return [];
    }

    /** @return list<string> */
    public function integerLeaves(): array
    {
        return [];
    }

    /** @return list<string> */
    public function booleanLeaves(): array
    {
        return [];
    }

    /**
     * Dot paths of lists. Filament Repeater state is keyed by uuid; these are re-indexed
     * to plain lists before storage so JSON stays an array, not an object.
     *
     * @return list<string>
     */
    public function listLeaves(): array
    {
        return [];
    }

    public function view(): string
    {
        return 'curriculum.items.'.$this->type()->value;
    }

    public function block(): Block
    {
        return Block::make($this->type()->value)
            ->label(fn (?array $state): string => $this->blockLabel($state ?? []))
            ->icon($this->type()->icon())
            ->schema([
                // The row identity. Filament regenerates Builder item uuids on every hydration,
                // so this hidden key (a uuid fixed when the block is first added) is what the
                // sync layer matches rows on. See plan §Spike findings 0.2.
                Hidden::make('key')
                    ->default(fn (): string => (string) Str::uuid()),
                ...$this->fields(),
            ]);
    }

    /**
     * The collapsed block's label in the Builder, from the block's current state.
     *
     * @param  array<string, mixed>  $state
     */
    public function blockLabel(array $state): string
    {
        $heading = is_array($state['heading'] ?? null) ? TranslatableText::pick($state['heading']) : null;

        return filled($heading)
            ? $this->type()->label().': '.$heading
            : $this->type()->label();
    }

    /**
     * Clean and validate a raw config array (Builder block data or seeder input).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function normalise(array $config): array
    {
        // Every declared key is present (null when absent) so stored JSON has one shape.
        $config = array_merge(
            array_fill_keys($this->configKeys(), null),
            Arr::only($config, $this->configKeys()),
        );

        foreach ($this->listLeaves() as $path) {
            $this->transform($config, $path, fn ($value) => is_array($value) ? array_values($value) : $value);
        }

        foreach ($this->translatableLeaves() as $path) {
            $this->transform($config, $path, fn ($value) => $this->cleanTranslations($value));
        }

        foreach ($this->htmlLeaves() as $path) {
            $this->transform($config, $path, fn ($value) => is_array($value)
                ? $this->cleanHtml($value)
                : $value);
        }

        foreach ($this->integerLeaves() as $path) {
            $this->transform($config, $path, function ($value) {
                if ($value === null || $value === '') {
                    return null;
                }

                return is_numeric($value) ? (int) $value : $value;
            });
        }

        foreach ($this->booleanLeaves() as $path) {
            $this->transform($config, $path, fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN));
        }

        Validator::make($config, $this->rules())->validate();

        $this->validateNormalised($config);

        return $config;
    }

    /**
     * Hook for cross-field checks that plain rules cannot express. Throw a
     * ValidationException (e.g. via ValidationException::withMessages) to reject.
     *
     * @param  array<string, mixed>  $config
     */
    protected function validateNormalised(array $config): void {}

    /**
     * A locale dictionary with empty locales removed, or null if nothing is filled. A bare
     * string is treated as the fallback-locale value so seeders can pass plain English.
     */
    protected function cleanTranslations(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = [config('app.fallback_locale', 'en') => $value];
        }

        if (! is_array($value)) {
            return null;
        }

        $clean = array_filter($value, fn ($text) => is_string($text) && trim($text) !== '');

        return $clean === [] ? null : $clean;
    }

    /**
     * Sanitise each locale's HTML and drop locales with no visible text (an emptied rich
     * editor dehydrates to "<p></p>", which must count as unfilled).
     *
     * @param  array<string, mixed>  $translations
     * @return array<string, string>|null
     */
    protected function cleanHtml(array $translations): ?array
    {
        $clean = collect($translations)
            ->map(fn ($html) => is_string($html) ? HtmlSanitizer::clean($html) : null)
            ->reject(fn ($html) => TranslatableComboField::isEmptyRichContent($html))
            ->all();

        return $clean === [] ? null : $clean;
    }

    /**
     * Apply $fn to every value at a dot path, expanding `*` over list members. Missing
     * segments are skipped, never created.
     *
     * @param  array<string, mixed>  $data
     */
    protected function transform(array &$data, string $path, callable $fn): void
    {
        $this->walk($data, explode('.', $path), $fn);
    }

    /**
     * @param  array<int|string, mixed>  $node
     * @param  list<string>  $segments
     */
    private function walk(array &$node, array $segments, callable $fn): void
    {
        $segment = array_shift($segments);

        if ($segment === '*') {
            foreach ($node as &$child) {
                if ($segments === []) {
                    $child = $fn($child);
                } elseif (is_array($child)) {
                    $this->walk($child, $segments, $fn);
                }
            }
            unset($child);

            return;
        }

        if (! array_key_exists($segment, $node)) {
            return;
        }

        if ($segments === []) {
            $node[$segment] = $fn($node[$segment]);

            return;
        }

        if (is_array($node[$segment])) {
            $this->walk($node[$segment], $segments, $fn);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Shared form-field builders
    |--------------------------------------------------------------------------
    */

    /**
     * A per-locale field reading from block state only (never the session record).
     */
    protected function translatable(string $name, string $label, string|Field $child, bool $required = false): TranslatableComboField
    {
        $field = TranslatableComboField::make($name)
            ->fromRecord(false)
            ->label($label)
            ->childField($child);

        if (is_string($child) && $child === TextInput::class) {
            $field->columns(['default' => 1, 'lg' => 2]);
        }

        return $required ? $field->required() : $field;
    }

    /**
     * A list of repeated sub-structures inside a block. The "Add" button's look encodes the
     * list's depth so the three add actions that can share a screen (block, question, option)
     * read differently: the Builder's is a solid primary button, a level-1 list (questions,
     * columns, rows) gets a small grey button, a level-2 list (options, choices) a small link.
     */
    protected function listRepeater(string $name, string $label, string $addLabel, int $level = 1): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->addActionLabel($addLabel)
            ->addAction(fn (Action $action): Action => $level === 1
                ? $action->button()->color('gray')->size('sm')
                : $action->link()->size('sm')->icon('heroicon-m-plus'))
            ->reorderable();
    }

    /**
     * A repeater item header that says what it is even when collapsed: "Q2 · Which of these…",
     * "Column 1 · Challenge". $index is the Repeater's zero-based item index.
     */
    protected function numberedLabel(string $prefix, int $index, mixed $text): string
    {
        $number = strlen($prefix) === 1 ? $prefix.($index + 1) : $prefix.' '.($index + 1);
        $text = is_array($text) ? TranslatableText::pick($text) : null;

        return filled($text) ? "{$number} · {$text}" : $number;
    }

    protected function headingField(bool $required): TranslatableComboField
    {
        return $this->translatable('heading', 'Heading', TextInput::class, $required);
    }

    /**
     * The author-entered identifier of a repeated sub-structure (a canvas field, matrix
     * column, quiz question or option). Learners' notes are stored under it in their browser.
     */
    protected function idField(string $stores): TextInput
    {
        return TextInput::make('id')
            ->label('ID')
            ->required()
            ->rule('alpha_dash')
            ->maxLength(40)
            ->default(fn (): string => Str::lower(Str::random(6)))
            ->helperText("Short identifier that learners' {$stores} are saved under in their browser. Renaming it once the session is live orphans anything they have already saved.");
    }
}
