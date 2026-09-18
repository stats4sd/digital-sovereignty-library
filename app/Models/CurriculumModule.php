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
 * A content block in the curriculum: the intro, a learning-map node, or a
 * toolkit pillar (distinguished by 'section'). Rows are seeded by
 * Database\Seeders\Prep\CurriculumSeeder and matched to fixed layout positions
 * in the public views by 'key'. Admins edit content but never create/delete
 * rows.
 */
class CurriculumModule extends Model
{
    use HasFactory;
    use HasTranslations;

    public const SECTION_INTRO = 'intro';

    public const SECTION_MAP = 'map';

    public const SECTION_TOOLKIT = 'toolkit';

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
