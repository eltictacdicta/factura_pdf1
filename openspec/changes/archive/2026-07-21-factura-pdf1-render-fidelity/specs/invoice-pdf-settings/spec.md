# Delta for invoice-pdf-settings

## Purpose

This change retires the narrow "every setting has a widget" assertion
in favor of a stronger invariant: **every persisted setting has a
distinctive effect on the rendered PDF**. The audit
(Engram obs #367, 2026-07-21) confirmed 27 of 28 settings were dead
in the render path; the new requirement is the contract that closes
the gap. This delta also adds the two text-block requirements
(`texto1` and `texto2` with their 7 position modes each) that map to
rows 9 and 10 of the proposal's feature table.

## MODIFIED Requirements

### Requirement: Settings coverage test

A test MUST iterate over the documented known settings and assert
that each one has a distinctive effect on the rendered PDF. A
"distinctive effect" is defined as: when the setting's value is
changed, the rendered HTML contains a token (data attribute, CSS
class, or text content) that uniquely identifies the new value.
This is the formal pin against accidental key drops AND against
"decorative" settings that exist in the JSON column but never reach
the renderer.

(Previously: "A test MUST iterate over the documented known settings
and assert that each one appears as a rendered widget in the admin
form. This is the formal pin against accidental key drops.")

#### Scenario: All 28 settings are read by the render pipeline

- GIVEN `SettingsService::load()` returns the 28 documented settings
- WHEN `SettingsEffectCoverageTest` iterates `UPSTREAM_SETTING_KEYS`
- THEN for every key, the test renders a fixture with that key set to a sentinel value
- AND the rendered HTML MUST contain a token (data attribute, CSS class, or text content) that reflects the sentinel
- AND the count of effective settings MUST equal 28 (asserted by `assertCount(28, $effectiveKeys)`)

#### Scenario: A change to any single setting produces a different rendered HTML

- GIVEN the seeded fixture and the baseline settings row
- WHEN the operator changes one setting in the admin form and saves
- THEN a re-render of the same `PrintableDocumentInterface` MUST produce a different HTML body
- AND the difference MUST be attributable to the changed setting (asserted by a per-setting effect test that pins the new token in the output)

#### Scenario: `SettingsService::load()` round-trip preserves every key

- GIVEN a row whose `settings_json` contains all 28 keys
- WHEN settings are loaded
- THEN the returned array MUST contain all 28 keys with the exact persisted values
- AND no key MAY be silently dropped on the read path

## ADDED Requirements

### Requirement: texto1 block has 7 position modes

The `posiciontexto1` setting (1=above header, 2=below header,
3=top of page, 4=bottom of page, 5=after line items, 6=after totals,
7=before footer) MUST drive the rendered position of the
`texto1` content (sourced from `formato_documento->texto`). The
`medidatexto1` setting (px) MUST set the rendered block's `max-width`
and `font-size`; `colortexto1` MUST set the block's text color; and
`justiftexto1` MUST set the CSS `text-align` (left/center/right/justify).

#### Scenario: `posiciontexto1=1` places texto1 above the header

- GIVEN a settings row with `posiciontexto1=1` and a `formato_documento` with `texto='NOTA IMPORTANTE'`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain a `data-text-block-1-position="1"` attribute
- AND a `.text-block-1` element MUST appear before the parties header
- AND its inner text MUST equal `NOTA IMPORTANTE`

#### Scenario: `posiciontexto1=2` places texto1 below the header

- GIVEN a settings row with `posiciontexto1=2`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-1-position="2"`
- AND `.text-block-1` MUST appear after the parties header and before the line items

#### Scenario: `posiciontexto1=3` places texto1 at the top of the page

- GIVEN a settings row with `posiciontexto1=3`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-1-position="3"`
- AND `.text-block-1` MUST be the first child of `body` in the rendered DOM

#### Scenario: `posiciontexto1=4` places texto1 at the bottom of the page

- GIVEN a settings row with `posiciontexto1=4`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-1-position="4"`
- AND `.text-block-1` MUST be the last child of `body` in the rendered DOM

#### Scenario: `posiciontexto1=5` places texto1 after the line items

- GIVEN a settings row with `posiciontexto1=5`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-1-position="5"`
- AND `.text-block-1` MUST appear between the line-items table and the VAT breakdown

#### Scenario: `posiciontexto1=6` places texto1 after the totals

- GIVEN a settings row with `posiciontexto1=6`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-1-position="6"`
- AND `.text-block-1` MUST appear between the totals block and the payment footer

#### Scenario: `posiciontexto1=7` places texto1 before the footer

- GIVEN a settings row with `posiciontexto1=7`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-1-position="7"`
- AND `.text-block-1` MUST appear between the payment footer and the page-number footer

#### Scenario: `medidatexto1=14` and `colortexto1=#333333` style the texto1 block

- GIVEN a settings row with `medidatexto1=14` and `colortexto1=#333333`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-1-font-size="14"`
- AND the `.text-block-1` element MUST carry `style="font-size: 14px; color: #333333"`

#### Scenario: `justiftexto1=center` centers the texto1 block

- GIVEN a settings row with `justiftexto1=center`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the `.text-block-1` element MUST carry `style="text-align: center"`

### Requirement: texto2 block has 7 position modes with free-text content

The `posiciontexto2` setting MUST mirror `posiciontexto1` (same 7
modes). The `texto2` content MUST be free text editable in the
admin, persisted through `SettingsService`, and used directly (no
`FormatoDocumento` lookup) when rendering. `medidatexto2`,
`colortexto2`, and `justiftexto2` MUST mirror their `texto1`
counterparts.

#### Scenario: `posiciontexto2=1` places texto2 above the header

- GIVEN a settings row with `posiciontexto2=1` and `texto2='Recordatorio: pago a 30 días'`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain a `data-text-block-2-position="1"` attribute
- AND a `.text-block-2` element MUST appear before the parties header
- AND its inner text MUST equal `Recordatorio: pago a 30 días`

#### Scenario: `posiciontexto2=2` places texto2 below the header

- GIVEN a settings row with `posiciontexto2=2`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-2-position="2"`
- AND `.text-block-2` MUST appear after the parties header and before the line items

#### Scenario: `posiciontexto2=3` places texto2 at the top of the page

- GIVEN a settings row with `posiciontexto2=3`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-2-position="3"`
- AND `.text-block-2` MUST be the first child of `body` in the rendered DOM

#### Scenario: `posiciontexto2=4` places texto2 at the bottom of the page

- GIVEN a settings row with `posiciontexto2=4`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-2-position="4"`
- AND `.text-block-2` MUST be the last child of `body` in the rendered DOM

#### Scenario: `posiciontexto2=5` places texto2 after the line items

- GIVEN a settings row with `posiciontexto2=5`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-2-position="5"`
- AND `.text-block-2` MUST appear between the line-items table and the VAT breakdown

#### Scenario: `posiciontexto2=6` places texto2 after the totals

- GIVEN a settings row with `posiciontexto2=6`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-2-position="6"`
- AND `.text-block-2` MUST appear between the totals block and the payment footer

#### Scenario: `posiciontexto2=7` places texto2 before the footer

- GIVEN a settings row with `posiciontexto2=7`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-2-position="7"`
- AND `.text-block-2` MUST appear between the payment footer and the page-number footer

#### Scenario: Free-text `texto2` round-trips through `SettingsService`

- GIVEN an admin saves a settings form with `texto2='Custom operator note: please review line 3'`
- WHEN the operator reloads the admin page
- THEN the form MUST echo back the literal `Custom operator note: please review line 3` in the textarea
- AND `SettingsService::load()` MUST return the exact same string
- AND a re-render of any `PrintableDocumentInterface` MUST render that string in `.text-block-2`

#### Scenario: `medidatexto2`, `colortexto2`, and `justiftexto2` style the texto2 block

- GIVEN a settings row with `medidatexto2=12`, `colortexto2=#666666`, and `justiftexto2=right`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-text-block-2-font-size="12"`
- AND the `.text-block-2` element MUST carry `style="font-size: 12px; color: #666666; text-align: right"`

## REMOVED Requirements

_None. The existing 6 requirements of `invoice-pdf-settings` remain
valid; the coverage test requirement is REPLACED (in the MODIFIED
section) and 2 new text-block requirements are added._
