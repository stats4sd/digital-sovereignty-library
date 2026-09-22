<?php

namespace App\Models;

use App\Enums\CurriculumItemType;
use App\Support\TranslatableText;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * An ordered stop within a learning-map module (Database\Seeders\Prep\CurriculumSeeder /
 * admin-created). Its content is an ordered list of typed CurriculumSessionItem rows; troves
 * attach here (as `trove` items), not on the parent module, for map-section modules.
 */
class CurriculumSession extends Model
{
    use HasFactory;
    use HasTranslations;

    public array $translatable = [
        'title',
        'summary',
        'description',
    ];

    protected $casts = [
        'order_column' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (CurriculumSession $session) {
            if (filled($session->slug)) {
                return;
            }

            $session->slug = $session->generateSlug();
        });
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(MapModule::class, 'curriculum_module_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CurriculumSessionItem::class)
            ->orderBy('position')
            ->orderBy('id')
            ->chaperone('session');
    }

    /**
     * The troves referenced by this session's `trove` items, in item order. A convenience
     * view over items(): the item row is the pivot, so withPivotValue pins type = trove on
     * both reads and attach(). Attaching through this relation cannot fill the item's
     * NOT NULL key/position columns, so create trove items via items() instead.
     */
    public function troves(): BelongsToMany
    {
        return $this->belongsToMany(Trove::class, 'curriculum_session_items')
            ->withPivotValue('type', CurriculumItemType::Trove->value)
            ->withPivot('id', 'position', 'intro')
            ->orderByPivot('position');
    }

    /**
     * The session's 1-based position among its module's siblings (ordered by order_column,
     * id) — never the raw order_column, so numbering stays contiguous even with gaps. Uses
     * the already-loaded module->sessions relation when present, falling back to 1 if the
     * module relation is unavailable.
     */
    protected function number(): Attribute
    {
        return Attribute::get(function (): int {
            $module = $this->module;

            if ($module === null) {
                return 1;
            }

            $siblings = $module->relationLoaded('sessions')
                ? $module->sessions
                : $module->sessions()->get();

            $position = $siblings->pluck('id')->search($this->id);

            return $position === false ? 1 : $position + 1;
        });
    }

    /**
     * The outcome (from the parent module's outcomes_list) this session builds toward, plus
     * its 1-based position (for an "LO3" style label). Null if unset or the key is unknown.
     */
    protected function buildsTowardOutcome(): Attribute
    {
        return Attribute::get(function (): ?array {
            if (blank($this->builds_toward)) {
                return null;
            }

            $module = $this->module;

            if ($module === null) {
                return null;
            }

            foreach ($module->outcomes_list as $position => $outcome) {
                if ($outcome['key'] === $this->builds_toward) {
                    return [
                        'position' => $position + 1,
                        'key' => $outcome['key'],
                        'statement' => $outcome['statement'],
                        'in_practice' => $outcome['in_practice'],
                    ];
                }
            }

            return null;
        })->shouldCache();
    }

    public function generateSlug(): string
    {
        $base = Str::slug((string) TranslatableText::pick($this->getTranslations('title'))) ?: 'session';

        $slug = $base;
        $suffix = 2;

        while ($this->slugExistsInModule($slug)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function slugExistsInModule(string $slug): bool
    {
        return static::query()
            ->where('curriculum_module_id', $this->curriculum_module_id)
            ->where('slug', $slug)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }
}
