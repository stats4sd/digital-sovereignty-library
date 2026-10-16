# Resource block: trove contents summary in the session Builder — Change Log

**Date**: 2026-10-16
**Branch**: `worktree-resource-item-contents-preview`
**Plan**: [docs/plans/2026-10-16-resource-item-contents-preview.md](../plans/2026-10-16-resource-item-contents-preview.md)

## Summary

When an editor adds a **Resource** block to a session's Content Builder and picks a Trove, the block now shows a read-only "Resource contents" panel beneath the Introduction: the Trove's videos, external links and files, in the same order and with the same locale fallback as the public resource card. Editors can write the intro, and spot a thin or broken resource, without previewing the whole session.

## Behaviour

- Rows are listed videos → links → files. A video row shows the resolved title (or host) plus a badge: **Embedded player** when the stored resolution is embeddable, **Link card only** otherwise. No player is embedded in the admin. Link rows show title and host; file rows show file name and size.
- Empty state: "This resource has no videos, links or files yet." An **Edit resource** link (the Trove's admin edit page, new tab) is shown in both states.
- The panel is hidden until a trove is selected and swaps when the Select changes. It also renders for an unpublished trove, beneath the existing warning. A trove that has since been **deleted** (or an id that no longer resolves) now triggers the "not currently published" warning and shows no panel; previously a deleted trove was also flagged, so this preserves that behaviour while sharing the single loader.
- A footer note states which language is being shown (the admin's current locale, falling back to other languages where missing) and that learners see their own language where it exists.
- Content comes from the canonical row the block points at. Shadow drafts are not consulted, since only canonical content reaches learners.

## Implementation

- `resources/views/filament/curriculum/trove-contents.blade.php`: the panel, using Filament's `badge`, `link` and `icon` Blade components and neutral gray utilities (dark-mode aware). Driven by the existing `Trove::videoLinks()`, `externalLinks()` and `getContentMedia()` accessors.
- `App\Curriculum\Items\TroveItem`: a `trove_contents` `Placeholder` after the Introduction, rendered via `HtmlString`. A private `trove()` loader (`withDrafts()->withTrashed()->with('media')`, memoised with `once()`) is now shared by the block label, the unpublished warning and the panel, so each resource block costs one trove query plus one media query per render instead of three trove queries. `once()` is request-scoped under PHP-FPM; this class is not used from queues or long-running workers.

## Tests

New `tests/Feature/Filament/CurriculumSessionContentSummaryTest.php` with six cases: embeddable video + link + file rows; non-embeddable video badge; empty state with edit link; no panel while the block has no trove selected; two resource blocks each show their own trove; a soft-deleted trove shows the warning and no panel. Full Unit+Feature run: 645 passed.
