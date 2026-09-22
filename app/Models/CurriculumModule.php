<?php

namespace App\Models;

use App\Support\TranslatableText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A content block in the curriculum: the intro, a learning-map node, or a toolkit pillar,
 * distinguished by 'section'. Single-table inheritance keyed on that column hydrates each row
 * as IntroModule, MapModule or ToolkitModule (see newFromBuilder / newInstance); this parent
 * carries everything they share and the children add only a section scope. Map modules may be
 * created and deleted by editors (the map lays itself out); intro and toolkit rows are seeded
 * by Database\Seeders\Prep\CurriculumSeeder and matched to fixed layout positions in the public
 * views by 'key'.
 *
 * Hand-rolled rather than tightenco/parental: that package's HasParent trait adds a public
 * property to every child, which makes Livewire's lazy model proxies (PHP 8.4) refuse to wrap
 * a child instance behind a parent-typed proxy.
 */
class CurriculumModule extends Model
{
    use HasFactory;
    use HasTranslations;

    public const SECTION_INTRO = 'intro';

    public const SECTION_MAP = 'map';

    public const SECTION_TOOLKIT = 'toolkit';

    /** @var array<string, class-string<self>> */
    protected static array $sectionClasses = [
        self::SECTION_INTRO => IntroModule::class,
        self::SECTION_MAP => MapModule::class,
        self::SECTION_TOOLKIT => ToolkitModule::class,
    ];

    protected $table = 'curriculum_modules';

    public array $translatable = [
        'title',
        'subtitle',
        'description',
        'note',
        'goal',
    ];

    protected $casts = [
        'learning_outcomes' => 'array',
        'number' => 'integer',
    ];

    /**
     * @return class-string<self>
     */
    public static function classForSection(?string $section): string
    {
        return static::$sectionClasses[$section] ?? self::class;
    }

    /**
     * The class to build for these attributes when called on `static`: the section's child
     * class if it is `static` or one of its descendants, otherwise `static` itself (a child
     * queried without a section column, e.g. pluck(), or a row from another section).
     *
     * @return class-string<static>
     */
    protected static function classForAttributes(array $attributes): string
    {
        $class = static::classForSection($attributes['section'] ?? null);

        if ($class === static::class) {
            return $class;
        }

        return is_subclass_of($class, static::class) ? $class : static::class;
    }

    public function newFromBuilder($attributes = [], $connection = null): static
    {
        $attributes = (array) $attributes;
        $class = static::classForAttributes($attributes);

        $model = (new $class)->newInstance([], true);
        $model->setRawAttributes($attributes, true);
        $model->setConnection($connection ?: $this->getConnectionName());
        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * A new instance carrying a `section` becomes the matching child class, so
     * `CurriculumModule::create([... 'section' => 'map'])` and factory states yield the child.
     */
    public function newInstance($attributes = [], $exists = false): static
    {
        $class = static::classForAttributes((array) $attributes);

        if ($class === static::class) {
            return parent::newInstance($attributes, $exists);
        }

        return (new $class)->newInstance($attributes, $exists);
    }

    public function getForeignKey(): string
    {
        return 'curriculum_module_id';
    }

    public function joiningTableSegment(): string
    {
        return 'curriculum_module';
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function troves(): BelongsToMany
    {
        return $this->belongsToMany(Trove::class)
            ->withPivot('id', 'order_column')
            ->orderByPivot('order_column');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CurriculumSession::class)
            ->orderBy('order_column')
            ->orderBy('id')
            ->chaperone('module');
    }

    public function scopeForSection(Builder $query, string $section): Builder
    {
        return $query->where('section', $section);
    }

    /**
     * Learning-map display order: module number, then insertion order for unnumbered rows.
     */
    public function scopeInMapOrder(Builder $query): Builder
    {
        return $query->orderByRaw('number IS NULL')->orderBy('number')->orderBy('id');
    }

    public function isMapModule(): bool
    {
        return $this->section === self::SECTION_MAP;
    }

    /**
     * `learning_outcomes` is a structured array of items (key, per-locale statement,
     * per-locale in_practice); this returns the current locale's outcomes as
     * ['key', 'statement', 'in_practice'] arrays, dropping items with no statement. A legacy
     * (pre-migration) locale-keyed dict is not a list and returns an empty array.
     */
    protected function outcomesList(): Attribute
    {
        return Attribute::get(function (): array {
            $items = $this->learning_outcomes;

            if (! is_array($items) || ! array_is_list($items)) {
                return [];
            }

            return collect($items)
                ->filter(fn (mixed $item) => is_array($item))
                ->map(fn (array $item) => [
                    'key' => $item['key'] ?? null,
                    'statement' => TranslatableText::pick($item['statement'] ?? null),
                    'in_practice' => TranslatableText::pick($item['in_practice'] ?? null),
                ])
                ->filter(fn (array $item) => filled($item['statement']))
                ->values()
                ->all();
        });
    }
}
