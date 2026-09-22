# Session content Builder: visual hierarchy options

**Status:** Proposal (design review only, no code). Side-quest to [2026-09-21-curriculum-session-items.md](../plans/2026-09-21-curriculum-session-items.md); pick up after that plan's Phase 4, since option F reuses the Phase 4 item views.
**Scope:** the "Content" tab on `EditCurriculumSession` (`SessionContentBuilder` + the blocks in `app/Curriculum/Items/`). Data structures are fixed; everything here is layout, CSS and Filament configuration.

## The problem

Every level of the form uses the same visual grammar: a white `rounded-xl` card with `ring-1` and `shadow-xs`. Depth is therefore not encoded anywhere, and every "Add …" button is the same outlined button sitting at the bottom of a list, which is exactly where the next level's button also sits.

Current nesting on the Content tab, worst case (a quiz):

| Depth | Component | Rendered as |
|---|---|---|
| 1 | `Tabs` (`fi-contained`) | white card |
| 2 | `Builder` item (`fi-fo-builder-item`) | white card |
| 3 | `TranslatableComboField` (heading) and `Repeater` "Questions" item | white card (the combo field renders as `<x-filament::section>`) |
| 4 | Inside a question: combo field (stem), `Repeater` "Options" item, `Fieldset` "Feedback" | white card, white card, bordered box |
| 5 | Inside an option: combo field (text) | white card |

So a quiz option's text input is five white cards deep, and three "Add" buttons (option, question, content block) can be visible within one screen height. `NoteMatrix` with a select column has the same depth (columns → choices → text).

The white-on-white is the root cause, but it compounds with two others: leaf fields carry card chrome they do not need (the combo field's Section wrapper), and the add buttons do not signal their level.

## Options

Ordered roughly by effort. A, C and D are configuration and CSS only and can be done together in about half a day. B and F need a spike first.

### A. Alternate background by depth

Dave's suggestion, extended. Swapping only the tab background (grey) and block background (white) fixes the boundary between depths 1 and 2, but the confusion described is at depths 3 to 5, where everything stays white. The rule that helps is **alternate white and grey at each depth**, so any two adjacent containers always contrast:

```
page (grey)
└─ Builder block (white)
   ├─ combo field / question (grey)
   │  └─ option (white)
   │     └─ option text combo field (grey, or no card, see B)
   └─ "Add question" (grey zone)
└─ "Add content block" (page)
```

How, with default Filament:

- `Tabs::make()->contained(false)` drops the depth-1 card so the Builder sits on the page background. This also affects the Session tab, whose fields would then float on grey, so wrap that tab's schema in a `Section` (or apply `contained(false)` only if the Session tab's fields are already in one).
- Depth 3 grey: `Section::secondary()` exists in this Filament version, and the repo already has `.grey-box` for the same purpose (`resources/css/filament/admin/theme.css`). For Repeater items there is no method, so use CSS scoped to the Builder, which keeps Trove and Tag forms untouched:

```css
/* theme.css: alternate depth inside the session content Builder */
.fi-fo-builder-item .fi-fo-repeater-item,
.fi-fo-builder-item .fi-section { background-color: #F3F4F6; }               /* depth 3: grey */
.fi-fo-builder-item .fi-fo-repeater-item .fi-fo-repeater-item,
.fi-fo-builder-item .fi-fo-repeater-item .fi-section { background-color: #fff; } /* depth 4: white */
.fi-fo-builder-item .fi-fo-repeater-item .fi-fo-repeater-item .fi-section { background-color: #F3F4F6; } /* depth 5 */
```

Filament's own `.grey-box` note applies: target the inner card, not the `fi-sc-section` wrapper, or grey peeks through the rounded corners. Dark mode needs matching `dark:` values (the panel currently ships light only, check `AdminPanelProvider` before assuming).

Verdict: cheap and clearly an improvement, but on its own it still leaves five nested cards; it just makes them countable.

### B. Remove chrome from leaf fields

The single most numerous card is the `TranslatableComboField`, because it renders as a full `<x-filament::section>` with heading, so a one-line option text becomes a titled card containing two inputs (primary locale plus the picked secondary locale).

- **Inline render mode on the combo field.** Add an opt-in `->inline()` (or reuse `->compact()`, which the view already passes through) that swaps the Section wrapper for a plain field wrapper: label, then the locale inputs side by side, no card. Use it for every leaf inside a Repeater (`text`, `label`, `placeholder`, `stem`), keep the Section look for block-level fields (`heading`, `body`). Opt-in means Trove and Tag forms are unaffected. This removes depth 5 entirely and most of depth 4.
- **Table repeater for options and choices.** `Repeater::table([TableColumn::make('ID'), TableColumn::make('Text'), TableColumn::make('Correct')])` renders items as rows in one compact grid with no per-item card, which is the right shape for quiz options, matrix choices and canvas fields. Risk: a Section-rendering combo field inside a table cell will look wrong, so this depends on the inline mode above landing first, and the 2-locale toggle needs checking inside a cell (the `visibleJs` locale switch toggles `fi-hidden` on a grid wrapper). Spike before committing.
- Leave the `Fieldset` for Feedback alone: a bordered box is already a different grammar from a card, which is what we want.

Verdict: highest payoff per line of code after A, but it touches a shared component, so do it as an opt-in and test the Trove edit page still looks the same.

### C. Make "Add" buttons signal their level

Labels are already specific ("Add content block", "Add question", "Add option", "Add column"). Add shape and weight:

- Builder: `->addAction(fn (Action $a) => $a->button()->color('primary')->icon('heroicon-o-plus-circle'))`, and keep "Insert here" between blocks as the small link it already is.
- First-level repeaters (questions, columns, fields): `->addAction(fn (Action $a) => $a->button()->color('gray')->size('sm'))`.
- Second-level repeaters (options, choices): `->addAction(fn (Action $a) => $a->link()->size('sm')->icon('heroicon-m-plus'))`.

Three distinct looks (solid primary, small grey button, small link) map onto the three questions Dave listed: new block, new question, new option. Do it once in `ItemDefinition` helpers (`$this->listRepeater(level: 1|2)`) so every type agrees.

### D. Self-describing headers and collapse defaults

- Turn on `->itemNumbers()` on the Questions and Columns repeaters, and prefix `itemLabel` so collapsed rows read "Q2 · Which of these …" rather than just the stem. Options repeater: keep uncollapsible (they are short and you want to see the correct toggles), but give the header a small type badge.
- Collapse questions by default (`Repeater::collapsed()`) so a quiz block shows a list of question headers and you open one at a time. That alone cuts the visible depth in the common case to three. The Builder already gets collapse-all and expand-all actions from `->collapsible()`; make sure they are visible in the header.
- Optional: a left accent stripe per depth (`border-inline-start: 3px solid`), primary colour on Builder blocks, grey-400 on questions, none on options. Cheap CSS and a common convention in block editors, but only worth it if A does not already read clearly.

### E. Type colour per block (nice to have)

The block picker already has icons per type. Carrying the icon colour onto the block header (a coloured icon chip on `fi-fo-builder-item-header-icon`) helps scanning a long list. Filament does not put a per-block class on the item element, so this needs either `->extraItemActions()` trickery or an `extraAttributes` check on the Block; spike before promising it.

### F. Edit blocks in a modal with previews (structural fix)

`Builder::blockPreviews()` renders each block as a **preview** of its content in the list, and opens that block's form in a modal on click. This is default Filament and is the tool designed for exactly this problem: the list stays flat, and inside the modal "Add option" can only ever mean within this question, because the modal contains one block. Validation also happens per block on modal save, so errors surface next to the field instead of through the tab badge.

Cost: one preview Blade view per block type, rendered from block state (the config array) rather than a saved model. Phase 4 of the items plan builds `curriculum.items.<type>` views that take a `CurriculumSessionItem`; a small adapter that builds an unsaved item from state (`CurriculumSessionItem::make(['type' => …, 'config' => $state])`) lets the previews reuse them, wrapped in a "this is a preview" frame and with learner-state Alpine disabled (`blockPreviews(areInteractive: false)`).

Risks to check in a spike: the page-wide secondary locale picker must reach inputs inside the modal; the `Hidden` `key` field must survive the modal round trip; `TranslatableComboField::fromRecord(false)` behaviour inside a modal form. Also decide whether previews render in the active locale only.

Verdict: the only option that removes the nesting rather than decorating it. Do it after Phase 4 so the views exist, and after A, C, D, because those are worth having even with previews (the modal still contains question and option repeaters).

## Recommendation

1. Now, as a polish pass (half a day): A (alternating backgrounds, `contained(false)` with a Section on the Session tab), C (three button styles via shared `ItemDefinition` helpers), D (numbered, collapsed questions). All configuration and scoped CSS, no data changes, low regression risk.
2. Next: B inline mode on `TranslatableComboField` as an opt-in used only inside Builder blocks. Then trial the table repeater for options and choices behind that.
3. After items plan Phase 4: spike F. If the locale picker and key round trip work in the modal, adopt it and drop the depth-4/5 CSS from A, which becomes unnecessary.

Out of scope: changing the config JSON shapes, replacing Builder with per-item Filament resources, or a bespoke JS editor.
