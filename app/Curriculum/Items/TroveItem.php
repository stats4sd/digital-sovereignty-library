<?php

namespace App\Curriculum\Items;

use App\Enums\CurriculumItemType;
use App\Models\Trove;
use App\Support\TranslatableText;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;

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
        ];
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

    private function isUnpublished(int|string|null $troveId): bool
    {
        if (blank($troveId)) {
            return false;
        }

        return ! Trove::withDrafts()
            ->whereKey($troveId)
            ->whereNotNull('published_at')
            ->whereNull('published_id')
            ->exists();
    }

    private function troveTitle(int|string|null $troveId): ?string
    {
        if (blank($troveId)) {
            return null;
        }

        $trove = Trove::withDrafts()->find($troveId);

        return $trove ? $this->displayTitle($trove) : null;
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
