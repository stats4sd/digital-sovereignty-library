<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'learning_outcomes',
    ];

    public function troves(): BelongsToMany
    {
        return $this->belongsToMany(Trove::class)
            ->withPivot('id', 'order_column')
            ->orderByPivot('order_column');
    }

    public function scopeForSection(Builder $query, string $section): Builder
    {
        return $query->where('section', $section);
    }

    /**
     * `learning_outcomes` is stored as one outcome per line; this returns the
     * current locale's outcomes as a clean array of strings.
     */
    protected function outcomesList(): Attribute
    {
        return Attribute::get(function (): array {
            $raw = (string) $this->getTranslation('learning_outcomes', app()->getLocale());

            return collect(preg_split('/\r\n|\r|\n/', $raw))
                ->map(fn (string $line) => trim($line))
                ->filter()
                ->values()
                ->all();
        });
    }
}
