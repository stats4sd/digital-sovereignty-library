<?php

namespace App\Filament\Curriculum;

use App\Curriculum\Items\ItemDefinition;
use Filament\Forms\Components\Builder\Block;
use Illuminate\Contracts\View\View;

/**
 * A Builder block for one ItemDefinition whose preview (Builder::blockPreviews()) is rendered
 * from the block's raw state through a BlockPreview reader instead of Filament's default of
 * spreading the state array into the view as loose variables.
 */
class ItemBlock extends Block
{
    protected ?ItemDefinition $definition = null;

    public function definition(ItemDefinition $definition): static
    {
        $this->definition = $definition;

        return $this;
    }

    public function renderPreview(array $data): View
    {
        return view($this->evaluate($this->preview), [
            'preview' => new BlockPreview($data),
            'definition' => $this->definition,
        ]);
    }
}
