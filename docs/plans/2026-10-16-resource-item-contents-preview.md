# Resource item: show trove contents in the session Builder

**Status:** Completed — see [docs/change-logs/resource-item-contents-preview.md](../change-logs/resource-item-contents-preview.md)

## Goal

When an editor adds a `trove` ("Resource") block to a session's Content Builder, they pick a Trove and write an Introduction, but cannot see what the Trove actually contains without previewing the whole session. Show the Trove's videos, links and files read-only beneath the Introduction field, inside the block. Videos are listed, not embedded, with a badge saying whether the public page will embed a player.

## What exists

- The Builder block is `App\Curriculum\Items\TroveItem::fields()`: a live `Select trove_id`, an "unpublished" warning `Placeholder`, then the translatable `intro` RichEditor. Nothing follows the Introduction.
- Public rendering (`resources/views/components/trove-content.blade.php`) is driven by three `Trove` accessors with current-locale → `en` → other-locale fallback: `videoLinks()`, `externalLinks()`, `getContentMedia()`.
- Video links are resolved at save time by `VideoLinkResolver` (`embeddable`, `embed_url`, `title`), so "an embedded player exists" is known without a network call.
- `Placeholder->content(Closure)` returning an `HtmlString` is the established pattern for read-only admin content (`CurriculumSessionResource` module link, `TroveResource` status).

## Proposed look

Below the Introduction, inside the same block:

```
┌ Resource contents ──────────────────────────── Edit resource ↗ ┐
│ ▶ Soil health basics (youtube.com)         [Embedded player]  │
│ ▶ ecoagtube.org/videos/123                 [Link card only]   │
│ 🔗 FAO guide to agroecology                 fao.org           │
│ 📄 field-notes-template.pdf                 240 KB            │
│ 📄 worksheet.docx                           1.2 MB            │
│ Showing English content (learners see their own language      │
│ where it exists).                                             │
└───────────────────────────────────────────────────────────────┘
```

- Three row types in the same order as the public card: videos, then links, then files.
- Video rows show the resolved title (or host) plus a badge: **Embedded player** when `embeddable`, **Link card only** otherwise. No iframe.
- Empty state: "This resource has no videos, links or files yet." The **Edit resource** link (opens the `TroveResource` edit page in a new tab) is shown in both states so the editor can fix content without leaving the session.
- Hidden until a trove is selected; swaps when the Select changes (the Select is already `->live()`).
- Locale: mirror the public fallback for the admin's current locale and state which locale was used. Showing every locale's content would be more complete but noisy; revisit if editors ask for it.
- Draft shadow copies of a trove are irrelevant: the block points at the canonical id and only canonical content reaches learners, so the panel shows canonical content too. Unpublished troves still render their contents beneath the existing warning.

## Steps

1. **New Blade view** `resources/views/filament/curriculum/trove-contents.blade.php`. Takes `$trove`, builds rows from the three accessors, uses Filament-neutral Tailwind (gray/primary tokens, dark-mode aware), no brand classes. Includes the locale note and the Edit resource link.
2. **`TroveItem::fields()`**: append `Placeholder::make('trove_contents')->label('Resource contents')->content(fn (Get $get) => new HtmlString(view(...)->render()))->visible(fn (Get $get) => filled($get('trove_id')))`. Load the trove once via `Trove::withDrafts()->with('media')->find()`. Add a small per-request memo so `troveTitle()`, `isUnpublished()` and the new placeholder share one load instead of three queries per block.
3. **Tests** in `tests/Feature/Filament/CurriculumSessionContentTest.php`:
   - selected trove with a file, an external link and an embeddable video → edit page shows the file name, link title and "Embedded player";
   - non-embeddable video → "Link card only";
   - trove with no content → empty-state text and the Edit resource link;
   - block with no trove selected → section absent.
4. **Change log** entry in `docs/change-logs/` on completion, referencing this plan.

## Risks / notes

- `Placeholder` content is rendered server-side on every Livewire round-trip of the form. Cost is one trove + one media query per resource block, fine at session scale.
- `getContentMedia()` calls `getMedia()` on the canonical, so eager-load `media` to avoid an N+1 across blocks.
- Media `getUrl()` is not needed; file rows show name and `Number::fileSize($media->size)` only.

Estimated size: one view, ~30 lines in `TroveItem`, four tests.
