<?php

namespace App\Models;

use App\Enums\CurriculumItemType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Translatable\HasTranslations;

/**
 * One ordered content block on a CurriculumSession: prose, a callout, a learner activity
 * (note prompt / canvas / matrix, quiz) or an attached Trove. `type` selects the shape of
 * `config`; `intro` is translatable HTML shown above a trove item. `key` is a uuid fixed at
 * creation: it identifies the row across admin Builder round-trips and namespaces learners'
 * client-side notes, so it must never change once learners may have data under it.
 */
class CurriculumSessionItem extends Model
{
    use HasFactory;
    use HasTranslations;

    public array $translatable = [
        'intro',
    ];

    protected $casts = [
        'type' => CurriculumItemType::class,
        'config' => 'array',
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (CurriculumSessionItem $item) {
            if (blank($item->key)) {
                $item->key = (string) Str::uuid();
            }
        });

        static::updating(function (CurriculumSessionItem $item) {
            if ($item->isDirty('key')) {
                throw new LogicException('A curriculum session item key is immutable once created.');
            }
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CurriculumSession::class, 'curriculum_session_id');
    }

    /**
     * Only meaningful when type = trove. Trove's global PublishedScope applies outside the
     * admin panel, so publicly this resolves to null for an unpublished trove and callers
     * must skip the item rather than assume a record.
     */
    public function trove(): BelongsTo
    {
        return $this->belongsTo(Trove::class);
    }
}
