# Rich-Text Formatting Fidelity — Change Log

**Date**: 2026-10-19
**Branch**: `fix-block-quotes`

## Summary

Block quotes authored in the RichEditor were invisible on the public site, and text alignment was silently lost on save. An audit of every default RichEditor toolbar feature (bold, italic, underline, strike, sub/superscript, link, H2/H3, alignment, blockquote, code block, bullet/numbered lists, table) against both the sanitiser and the stylesheet found two separate causes.

## Sanitiser (`App\Support\HtmlSanitizer`)

- Text alignment renders as `style="text-align: …;"` on `p`/`h1`–`h6`, and the sanitiser stripped every `style` attribute. Those elements now allow `style`, and a custom attribute sanitiser (`textAlignOnly()`) keeps it only when it is a single `text-align: start|center|end|justify|left|right` declaration, normalised. Any other style (or a style that also contains other declarations) drops the attribute entirely.
- `<ol start>` is now kept, so numbered lists that the editor starts at a number other than 1 keep that number.
- Every other toolbar feature already survived sanitising unchanged; `tests/Unit/Support/HtmlSanitizerTest.php` now locks the full set in.
- Alignment already stripped from content saved before this fix cannot be recovered; editors need to re-apply it.

## Stylesheet (`resources/css/app.css`)

All rich text renders inside `.description` (trove/collection/module/session/pillar descriptions and the prose, callout and trove session items). Tailwind preflight resets `blockquote`, `pre` and `code` to plain text and nothing restored them, so block quotes were stored correctly but looked like ordinary paragraphs.

- `.description blockquote`: brand-secondary start border, inset, italic.
- `.description pre` and inline `code`: muted surface background, monospace block scrolls horizontally.
- Table rules moved from `.item-body` to `.description`, so tables in trove/collection/module descriptions are styled too.
- The first `<p>` inside a list item, block quote or table cell no longer gets the `.description p` top margin.
