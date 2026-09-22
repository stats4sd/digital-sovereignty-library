<?php

namespace App\Filament\Curriculum;

use App\Curriculum\Items\ItemRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;

/**
 * The "Content" Builder on the session edit page: one block per CurriculumItemType.
 *
 * `content` is not a session column. EditCurriculumSession hydrates the field from item rows
 * (mutateFormDataBeforeFill) and, on save, pulls its dehydrated blocks out of the form data
 * before the model update and hands them to CurriculumSessionContentSync. It dehydrates
 * normally (rather than dehydrated(false)) so that field validation runs and RichEditor
 * leaves arrive as HTML instead of TipTap JSON.
 */
class SessionContentBuilder
{
    public static function make(string $name = 'content'): Builder
    {
        return Builder::make($name)
            ->label('Content')
            ->helperText('The learner works down this list in order. Add text, callouts, worksheets, a quiz or library resources; drag or use the arrows to reorder.')
            ->blocks(fn (): array => app(ItemRegistry::class)->blocks())
            ->reorderableWithButtons()
            ->blockIcons()
            ->blockNumbers(false)
            ->blockPickerColumns(2)
            ->collapsible()
            // Each block is shown as a read-only preview of its content and edited in a modal
            // (docs/specs/2026-09-21-session-builder-visual-hierarchy-design.md, option F), so
            // the list stays flat and an "Add option" can only ever belong to the open block.
            ->blockPreviews()
            ->editAction(fn (Action $action): Action => $action
                ->modalHeading('Edit content block')
                ->modalWidth('5xl')
                ->slideOver())
            ->addActionLabel('Add content block')
            // Solid primary: the top of the add-button hierarchy (block > question > option),
            // see ItemDefinition::listRepeater() for the two levels below it.
            ->addAction(fn (Action $action): Action => $action->button()->color('primary')->icon('heroicon-o-plus-circle'))
            ->addBetweenActionLabel('Insert here')
            ->columnSpanFull();
    }
}
