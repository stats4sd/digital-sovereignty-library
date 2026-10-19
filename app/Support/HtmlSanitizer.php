<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Sanitises rich-text HTML produced by (or pasted into) Filament's RichEditor
 * before it is persisted, stripping scripts, event handlers, iframes and any
 * other markup outside the curated formatting allow-list below.
 *
 * Backed by symfony/html-sanitizer (already a framework dependency). Used as the
 * dehydration step on the Trove/Collection `description` fields so the stored
 * value is always safe to render with {!! !!} on the public site.
 */
class HtmlSanitizer
{
    /**
     * Sanitise a single HTML string. Null/empty passes through untouched so
     * empty locale values stay empty rather than becoming "".
     */
    public static function clean(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return static::sanitizer()->sanitize($html);
    }

    /**
     * Whether rich-text HTML carries no visible text (e.g. the "<p></p>" an empty editor
     * produces). Only text counts: this sanitiser allows no images or embeds.
     */
    public static function isEmpty(?string $html): bool
    {
        return trim(strip_tags($html ?? '')) === '';
    }

    protected static function sanitizer(): SymfonyHtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            // Block-level & structural. `style` on paragraphs/headings carries the
            // editor's text alignment; textAlignOnly() drops any other declaration.
            ->allowElement('p', ['style'])
            ->allowElement('br')
            ->allowElement('hr')
            ->allowElement('blockquote')
            ->allowElement('pre')
            ->allowElement('h1', ['style'])
            ->allowElement('h2', ['style'])
            ->allowElement('h3', ['style'])
            ->allowElement('h4', ['style'])
            ->allowElement('h5', ['style'])
            ->allowElement('h6', ['style'])
            ->withAttributeSanitizer(static::textAlignOnly())
            // Lists
            ->allowElement('ul')
            ->allowElement('ol', ['start'])
            ->allowElement('li')
            // Tables
            ->allowElement('table')
            ->allowElement('thead')
            ->allowElement('tbody')
            ->allowElement('tr')
            ->allowElement('th', ['colspan', 'rowspan'])
            ->allowElement('td', ['colspan', 'rowspan'])
            // Inline formatting
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('del')
            ->allowElement('sub')
            ->allowElement('sup')
            ->allowElement('code')
            ->allowElement('span')
            // Links: only these attributes survive; schemes are restricted below.
            ->allowElement('a', ['href', 'target', 'rel'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks()
            // Force safe rel on every link (defends target="_blank" tab-nabbing).
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        return new SymfonyHtmlSanitizer($config);
    }

    /**
     * Reduces a `style` attribute to the single `text-align` declaration the RichEditor's
     * alignment buttons emit (e.g. "text-align: center;"); anything else drops the attribute.
     */
    protected static function textAlignOnly(): AttributeSanitizerInterface
    {
        return new class implements AttributeSanitizerInterface
        {
            public function getSupportedElements(): ?array
            {
                return null;
            }

            public function getSupportedAttributes(): ?array
            {
                return ['style'];
            }

            public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
            {
                return preg_match('/^\s*text-align\s*:\s*(start|center|end|justify|left|right)\s*;?\s*$/i', $value, $m)
                    ? 'text-align: '.strtolower($m[1]).';'
                    : null;
            }
        };
    }
}
