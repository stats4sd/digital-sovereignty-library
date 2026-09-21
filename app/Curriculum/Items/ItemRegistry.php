<?php

namespace App\Curriculum\Items;

use App\Enums\CurriculumItemType;
use Filament\Forms\Components\Builder\Block;
use InvalidArgumentException;

/**
 * The item definitions keyed by type. Bound as a singleton in AppServiceProvider; resolve
 * via app(ItemRegistry::class) or CurriculumSessionItem::definition().
 */
class ItemRegistry
{
    /** @var array<string, ItemDefinition> */
    private array $definitions = [];

    /**
     * @param  iterable<ItemDefinition>  $definitions
     */
    public function __construct(iterable $definitions)
    {
        foreach ($definitions as $definition) {
            $this->definitions[$definition->type()->value] = $definition;
        }
    }

    public static function default(): self
    {
        return new self([
            new ProseItem,
            new CalloutItem,
            new NotePromptItem,
            new NoteCanvasItem,
            new NoteMatrixItem,
            new QuizItem,
            new TroveItem,
        ]);
    }

    /** @return list<ItemDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function for(CurriculumItemType|string $type): ItemDefinition
    {
        $value = $type instanceof CurriculumItemType ? $type->value : $type;

        return $this->definitions[$value]
            ?? throw new InvalidArgumentException("No curriculum item definition registered for type [{$value}].");
    }

    public function has(CurriculumItemType|string $type): bool
    {
        $value = $type instanceof CurriculumItemType ? $type->value : $type;

        return isset($this->definitions[$value]);
    }

    /**
     * One Builder block per type, in registry order.
     *
     * @return list<Block>
     */
    public function blocks(): array
    {
        return array_map(fn (ItemDefinition $definition): Block => $definition->block(), $this->all());
    }
}
