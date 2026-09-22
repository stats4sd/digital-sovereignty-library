<?php

namespace App\Services;

use App\Curriculum\Items\ItemRegistry;
use App\Enums\CurriculumItemType;
use App\Filament\Translatable\Form\TranslatableComboField;
use App\Models\CurriculumSession;
use App\Models\CurriculumSessionItem;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bridges a session's CurriculumSessionItem rows and the admin Builder's block array.
 *
 * Block shape (the Builder's dehydrated state: a list, RichEditor leaves already HTML):
 *   ['type' => 'prose', 'data' => ['key' => '<uuid>', ...config leaves]]
 *   ['type' => 'trove', 'data' => ['key' => '<uuid>', 'trove_id' => 12, 'intro' => ['en' => …]]]
 *
 * Rows are matched on `data.key` (the item's immutable key), never on position, so a
 * reorder is a position update and learners' browser notes stay attached to the same row.
 * No Filament dependency: pure arrays in, rows out.
 */
class CurriculumSessionContentSync
{
    public function __construct(private readonly ItemRegistry $registry) {}

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function toBlocks(CurriculumSession $session): array
    {
        return $session->items()->get()
            ->map(fn (CurriculumSessionItem $item): array => [
                'type' => $item->type->value,
                'data' => [
                    'key' => $item->key,
                    ...($item->type === CurriculumItemType::Trove
                        ? ['trove_id' => $item->trove_id, 'intro' => $item->getTranslations('intro')]
                        : ($item->config ?? [])),
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Make the session's items match $blocks: update rows whose key is present, create rows
     * for new keys, delete rows whose key is absent, and renumber positions 0..n-1. Runs in a
     * transaction; a ValidationException from any block's normalisation rolls everything back.
     *
     * @param  array<int|string, array{type: string, data?: array<string, mixed>}>  $blocks
     *
     * @throws ValidationException
     */
    public function apply(CurriculumSession $session, array $blocks): void
    {
        DB::transaction(function () use ($session, $blocks): void {
            $existing = $session->items()->get()->keyBy('key');
            $seen = [];

            foreach (array_values($blocks) as $position => $block) {
                $type = CurriculumItemType::tryFrom((string) ($block['type'] ?? ''))
                    ?? throw ValidationException::withMessages([
                        "content.{$position}.type" => 'Block '.($position + 1).' has an unknown type.',
                    ]);
                $data = is_array($block['data'] ?? null) ? $block['data'] : [];

                $key = $data['key'] ?? null;

                if (! is_string($key) || $key === '') {
                    $key = (string) Str::uuid();
                } elseif (strlen($key) > 36) {
                    throw ValidationException::withMessages([
                        "content.{$position}.data.key" => 'Block '.($position + 1).' has an invalid key.',
                    ]);
                }

                // A key can only be duplicated by a cloned block; the copy becomes a new row.
                if (in_array($key, $seen, true)) {
                    $key = (string) Str::uuid();
                }

                $seen[] = $key;

                $item = $existing->get($key) ?? new CurriculumSessionItem(['key' => $key]);

                $item->session()->associate($session);
                $item->type = $type;
                $item->position = $position;

                if ($type === CurriculumItemType::Trove) {
                    $item->trove_id = filled($data['trove_id'] ?? null) ? (int) $data['trove_id'] : null;
                    $item->config = null;
                    $item->setTranslations('intro', []);
                    $item->setTranslations('intro', $this->cleanTranslations($data['intro'] ?? null));
                } else {
                    $item->trove_id = null;
                    $item->setTranslations('intro', []);
                    $item->config = $this->normaliseBlock($type, $data, $position);
                }

                $item->save();
            }

            // Eloquent\Collection::except() filters by primary key, so query by key instead.
            $session->items()->whereNotIn('key', $seen)->get()->each->delete();
        });
    }

    /**
     * Normalise one block's config, prefixing any validation message with the block's
     * position and type so the admin can find it in a long session.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normaliseBlock(CurriculumItemType $type, array $data, int $position): array
    {
        try {
            return $this->registry->for($type)->normalise($data);
        } catch (ValidationException $exception) {
            $prefix = 'Block '.($position + 1).' ('.$type->label().'): ';

            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field) => [
                        "content.{$position}.data.{$field}" => array_map(fn (string $m) => $prefix.$m, $messages),
                    ])
                    ->all(),
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function cleanTranslations(mixed $value): array
    {
        if (is_string($value)) {
            $value = [config('app.fallback_locale', 'en') => $value];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($html) => is_string($html) ? HtmlSanitizer::clean($html) : null)
            ->reject(fn ($html) => TranslatableComboField::isEmptyRichContent($html))
            ->all();
    }
}
