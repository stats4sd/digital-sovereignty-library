<?php

namespace App\Curriculum\Items;

use App\Enums\CurriculumItemType;
use App\Filament\Resources\TroveResource;
use App\Models\Trove;
use App\Support\TranslatableText;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * A reference to a library Trove with an inline `intro` ("why this is here, what to do with
 * it"). Unlike the other types it stores nothing in `config`: the block's `trove_id` and
 * `intro` map onto the item row's own columns (handled by CurriculumSessionContentSync).
 */
class TroveItem extends ItemDefinition
{
    public function type(): CurriculumItemType
    {
        return CurriculumItemType::Trove;
    }

    public function configKeys(): array
    {
        return [];
    }

    public function translatableLeaves(): array
    {
        return [];
    }

    public function rules(): array
    {
        return [];
    }

    public function blockLabel(array $state): string
    {
        $title = $this->troveTitle($state['trove_id'] ?? null);

        return filled($title)
            ? $this->type()->label().': '.$title
            : $this->type()->label();
    }

    protected function fields(): array
    {
        return [
            Select::make('trove_id')
                ->label('Resource')
                ->options(fn (Get $get): array => $this->options($get('trove_id')))
                ->searchable()
                ->required()
                ->live(),

            Placeholder::make('trove_status')
                ->hiddenLabel()
                ->content('This resource is not currently published, so this item is hidden from learners until it is published again.')
                ->extraAttributes(['class' => 'text-warning-600 dark:text-warning-400'])
                ->visible(fn (Get $get): bool => $this->isUnpublished($get('trove_id'))),

            $this->translatable(
                'intro',
                'Introduction',
                RichEditor::make('intro')->disableToolbarButtons(['attachFiles']),
            )->description('Shown above the resource card: why it is here and what to do with it.'),

            // Read-only view of what the trove holds, so editors can write the intro (and spot
            // a thin or broken resource) without previewing the whole session.
            Placeholder::make('trove_contents')
                ->hiddenLabel()
                ->content(fn (Get $get): HtmlString => $this->contentsSummary($get('trove_id')))
                ->visible(fn (Get $get): bool => $this->liveTrove($get('trove_id')) !== null),
        ];
    }

    private function contentsSummary(int|string|null $troveId): HtmlString
    {
        $trove = $this->liveTrove($troveId);

        if ($trove === null) {
            return new HtmlString('');
        }

        return new HtmlString(view('filament.curriculum.trove-contents', [
            'trove' => $trove,
            'editUrl' => TroveResource::getUrl('edit', ['record' => $trove]),
        ])->render());
    }

    /**
     * Published canonical troves, plus the currently selected trove even if it has since
     * been unpublished (so the block still shows what it points at).
     *
     * @return array<int, string>
     */
    public function options(int|string|null $selected = null): array
    {
        $options = static::publishedCanonicals()
            ->mapWithKeys(fn (Trove $trove) => [$trove->id => $this->displayTitle($trove)]);

        if (filled($selected) && ! $options->has((int) $selected)) {
            $current = Trove::withDrafts()->find($selected);

            if ($current) {
                $options->put($current->id, $this->displayTitle($current).' (unpublished)');
            }
        }

        return $options->all();
    }

    /**
     * True when the block points at something learners cannot see: a never-published or
     * shadow-draft row, a soft-deleted trove, or an id that no longer resolves at all.
     */
    private function isUnpublished(int|string|null $troveId): bool
    {
        if (blank($troveId)) {
            return false;
        }

        $trove = $this->trove($troveId);

        return $trove === null
            || $trove->trashed()
            || $trove->published_at === null
            || $trove->published_id !== null;
    }

    private function troveTitle(int|string|null $troveId): ?string
    {
        $trove = $this->trove($troveId);

        return $trove ? $this->displayTitle($trove) : null;
    }

    /**
     * The selected trove (any publication state, including soft-deleted), with media
     * eager-loaded for the contents summary. Memoised with once(): the label, the unpublished
     * warning and the contents placeholder all ask for it, and a Builder may hold several
     * resource blocks. once() lives for the request under FPM; this class is never used from
     * a queue or long-running worker.
     */
    private function trove(int|string|null $troveId): ?Trove
    {
        if (blank($troveId) || ! is_numeric($troveId)) {
            return null;
        }

        return once(fn (): ?Trove => Trove::withDrafts()->withTrashed()->with('media')->find((int) $troveId));
    }

    /** The selected trove unless it has been deleted; the contents panel has nothing to show for a trashed row. */
    private function liveTrove(int|string|null $troveId): ?Trove
    {
        $trove = $this->trove($troveId);

        return $trove === null || $trove->trashed() ? null : $trove;
    }

    private function displayTitle(Trove $trove): string
    {
        return TranslatableText::pick($trove->getTranslations('title')) ?? "Resource #{$trove->id}";
    }

    /**
     * @return Collection<int, Trove>
     */
    private static function publishedCanonicals(): Collection
    {
        return Trove::withDrafts()
            ->whereNotNull('published_at')
            ->whereNull('published_id')
            ->orderBy('title')
            ->get(['id', 'title']);
    }
}
