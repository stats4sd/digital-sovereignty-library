<?php

namespace App\Filament\Curriculum;

use App\Support\HtmlSanitizer;
use App\Support\TranslatableText;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

/**
 * Read-only view over a Builder block's raw state for the preview views. Raw state differs
 * from saved config: rich-text leaves hold TipTap documents (arrays) until dehydration, and
 * Repeater lists are keyed by uuid rather than position, so the accessors normalise both.
 */
class BlockPreview
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function __construct(private readonly array $state) {}

    public function raw(string $path, mixed $default = null): mixed
    {
        return Arr::get($this->state, $path, $default);
    }

    /**
     * The current-locale string of a translatable leaf (a locale dictionary), with fallback.
     */
    public function text(string $path): ?string
    {
        $leaf = $this->raw($path);

        return is_array($leaf) ? TranslatableText::pick($leaf) : null;
    }

    /**
     * The current-locale rich text of a translatable HTML leaf, as safe HTML. Each locale's
     * value may be sanitised HTML (freshly hydrated from the database) or a TipTap document
     * (once the editor has touched it).
     */
    public function html(string $path): ?HtmlString
    {
        $leaf = $this->raw($path);

        if (! is_array($leaf)) {
            return null;
        }

        $value = TranslatableText::pick(array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $leaf));

        if (blank($value)) {
            return null;
        }

        $decoded = json_decode($value, true);
        $html = is_array($decoded) && isset($decoded['type'])
            ? RichContentRenderer::make($decoded)->toHtml()
            : HtmlSanitizer::clean($value);

        return HtmlSanitizer::isEmpty($html) ? null : new HtmlString($html);
    }

    /**
     * A Repeater list in display order, as plain arrays.
     *
     * @return list<array<string, mixed>>
     */
    public function list(string $path): array
    {
        $list = $this->raw($path);

        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    public static function pickText(mixed $leaf): ?string
    {
        return is_array($leaf) ? TranslatableText::pick($leaf) : null;
    }
}
