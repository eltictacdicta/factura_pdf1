# Delta for invoice-pdf-settings

## Purpose

This change is a **major engine swap** for `plugins/factura_pdf1/`:
the mpdf + Twig pipeline shipped in the archived
`factura-pdf1-render-fidelity` cycle is replaced with a Cezpdf
pipeline. The "every persisted setting has a distinctive effect on
the rendered PDF" contract from the previous cycle is **kept**,
but the data-provider assertion mechanism changes: the
`data-*` HTML token convention (previous AD-10) is **obliterated**.
The rewritten `SettingsEffectCoverageTest` (per the user's product
decision in the explore round) asserts each of the 28 settings
against the **Cezpdf-rendered PDF output** — via `smalot/pdfparser`
text extraction, raw-byte inspection for color hex, and
`Cezpdf` draw-call spied invocation.

The `texto1` block (rows 9 of the previous cycle's feature table)
is **dropped** — only `texto2` is in `UPSTREAM_SETTING_KEYS`
(verified at `Services/SettingsService.php:33–62`); the
`texto1`/`posiciontexto1`/`medidatexto1`/`colortexto1`/
`justiftexto1` settings are no longer consumed by the Cezpdf
draw path. The `texto2` block (rows 10) is **kept** and its
scenarios are rewritten to assert Cezpdf-output signals (text
extraction, color hex).

This delta does **not** alter the persistence contract
(`factura_pdf1_settings` table schema, `SettingsService::load()`,
`SettingsService::save()`, `IN_CODE_VERSION = 2` already current)
or the init-upgrade migration. It only changes the renderer-side
effect assertion mechanism.

## MODIFIED Requirements

### Requirement: Settings coverage test

A test MUST iterate over the documented known settings and assert
that each one has a distinctive effect on the Cezpdf-rendered
PDF. A "distinctive effect" is defined as: when the setting's
value is changed, the rendered PDF contains a distinctive
byte-level signal (extracted text via `smalot/pdfparser`, raw
color hex bytes in the PDF graphics state, or a spied
`Cezpdf` draw-call invocation) that uniquely identifies the new
value. This is the formal pin against accidental key drops AND
against "decorative" settings that exist in the JSON column but
never reach the renderer.

(Previously: the test asserted that each setting produced a
`data-*` HTML token in the rendered mpdf HTML output. The Twig
template is removed and the assertion mechanism moves to direct
PDF text extraction + byte-level inspection + draw-call spying.)

#### Scenario: All 28 settings affect the Cezpdf-rendered PDF

- GIVEN `SettingsService::load()` returns the 28 documented settings
- WHEN the rewritten `SettingsEffectCoverageTest` iterates `UPSTREAM_SETTING_KEYS`
- THEN for every key, the test renders a fixture with that key set to a sentinel value
- AND the rendered PDF MUST contain a distinctive byte-level signal (extracted text, color hex, or draw-call invocation) that reflects the sentinel
- AND the count of effective settings MUST equal 28 (asserted by `assertCount(28, $effectiveKeys)`)

#### Scenario: A change to any single setting produces a different rendered PDF

- GIVEN the seeded fixture and the baseline settings row
- WHEN the operator changes one setting in the admin form and saves
- THEN a re-render of the same `PrintableDocumentInterface` MUST produce a different PDF body
- AND the difference MUST be attributable to the changed setting (asserted by a per-setting effect test that pins the new byte-level signal in the output)

#### Scenario: `SettingsService::load()` round-trip preserves every key

- GIVEN a row whose `settings_json` contains all 28 keys
- WHEN settings are loaded
- THEN the returned array MUST contain all 28 keys with the exact persisted values
- AND no key MAY be silently dropped on the read path

### Requirement: texto2 block has 7 position modes with free-text content

The `posiciontexto2` setting MUST drive the rendered position of
the `texto2` content via the Cezpdf draw path (1=above header,
2=below header, 3=top of page, 4=bottom of page, 5=after line
items, 6=after totals, 7=before footer). The `texto2` content MUST
be free text editable in the admin, persisted through
`SettingsService`, and used directly (no `FormatoDocumento`
lookup) when rendering. `medidatexto2`, `colortexto2`, and
`justiftexto2` MUST drive the Cezpdf text sizing / color /
alignment.

(Previously: the position was asserted via
`data-text-block-2-position="N"` HTML attributes and the styling
via `style="font-size: 12px; color: #666666; text-align: right"`
inline CSS; both HTML conventions are removed and assertions move
to extracted PDF text + raw color hex bytes.)

#### Scenario: `posiciontexto2=1` places texto2 above the header

- GIVEN a settings row with `posiciontexto2=1` and `texto2='Recordatorio: pago a 30 días'`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the literal `Recordatorio: pago a 30 días` as visible text in the above-header region (extracted via `smalot/pdfparser`)

#### Scenario: `posiciontexto2=2` places texto2 below the header

- GIVEN a settings row with `posiciontexto2=2`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the texto2 content in the below-header region (text extraction plus a distinctive byte-level signal distinguishing it from `posiciontexto2=1` and `posiciontexto2=3`)

#### Scenario: `posiciontexto2=3` places texto2 at the top of the page

- GIVEN a settings row with `posiciontexto2=3`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the texto2 content as the first text block on the page (asserted by PDF text extraction order)

#### Scenario: `posiciontexto2=4` places texto2 at the bottom of the page

- GIVEN a settings row with `posiciontexto2=4`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the texto2 content as the last text block on the page (asserted by PDF text extraction order)

#### Scenario: `posiciontexto2=5` places texto2 after the line items

- GIVEN a settings row with `posiciontexto2=5`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the texto2 content between the line-items table and the VAT breakdown (asserted by PDF text extraction order)

#### Scenario: `posiciontexto2=6` places texto2 after the totals

- GIVEN a settings row with `posiciontexto2=6`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the texto2 content between the totals block and the payment footer (asserted by PDF text extraction order)

#### Scenario: `posiciontexto2=7` places texto2 before the footer

- GIVEN a settings row with `posiciontexto2=7`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the texto2 content between the payment footer and the page-number footer (asserted by PDF text extraction order)

#### Scenario: Free-text `texto2` round-trips through `SettingsService`

- GIVEN an admin saves a settings form with `texto2='Custom operator note: please review line 3'`
- WHEN the operator reloads the admin page
- THEN the form MUST echo back the literal `Custom operator note: please review line 3` in the textarea
- AND `SettingsService::load()` MUST return the exact same string
- AND a re-render of any `PrintableDocumentInterface` MUST render that string as visible text in the PDF (extracted via `smalot/pdfparser`)

#### Scenario: `medidatexto2`, `colortexto2`, and `justiftexto2` style the texto2 block

- GIVEN a settings row with `medidatexto2=12`, `colortexto2=#666666`, and `justiftexto2=right`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the literal color hex sequence for `#666666` (gray, RGB 102/102/102) in a PDF graphics state operator near the texto2 text
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the texto2 color hex presence

## ADDED Requirements

### Requirement: Rewritten `SettingsEffectCoverageTest` is GREEN for all 28 settings

The rewritten `SettingsEffectCoverageTest` (per deliverable F of
the proposal) MUST assert that all 28 settings in
`UPSTREAM_SETTING_KEYS` produce a distinctive Cezpdf-output
signal. The test MUST be GREEN after PR-2 lands, and it MUST
remain GREEN under the `GoldenPdfTest` byte-equality contract
(i.e., the per-setting effect assertions must not be the source
of byte-equality breakage).

#### Scenario: All 28 settings pass the rewritten coverage test

- GIVEN the rewritten `SettingsEffectCoverageTest` (data-provider of 28 cases)
- WHEN the test is executed via `ddev exec php vendor/bin/phpunit --filter SettingsEffectCoverageTest`
- THEN all 28 cases MUST pass
- AND the test reports 0 failures and 0 errors
- AND the total assertions count is ≥ 28 (one per setting)

#### Scenario: The rewritten test does not contradict the byte-equality test

- GIVEN the `GoldenPdfTest` byte-equality contract and the rewritten `SettingsEffectCoverageTest`
- WHEN both tests are executed against the same `SeedInvoiceFakt20260001` fixture
- THEN both tests MUST pass
- AND no per-setting effect assertion may alter the bytes of the byte-equality fixture (the fixture is generated with default settings)

#### Scenario: Each setting's signal mechanism is documented in the test

- GIVEN the rewritten `SettingsEffectCoverageTest` source code
- WHEN the test is reviewed
- THEN every one of the 28 cases MUST document which assertion mechanism it uses (text extraction via `smalot/pdfparser`, raw color hex bytes, or `Cezpdf` draw-call spy)
- AND the documentation MUST be in a `// mechanism:` comment above each case

## REMOVED Requirements

### Requirement: texto1 block has 7 position modes

(Reason: the user product decision in the explore round (decision
#6) drops `texto1` — only `texto2` is in `UPSTREAM_SETTING_KEYS`
(verified at `Services/SettingsService.php:33–62`). The Cezpdf
draw path does not consume `texto1`/`posiciontexto1`/
`medidatexto1`/`colortexto1`/`justiftexto1`. The requirement
also references the previous cycle's `data-text-block-1-*` HTML
token convention, which is obliterated by this change.)
(Migration: none. The `texto1` settings are not migrated to any
replacement; they remain persisted in `settings_json` for
backward-compat but are silently ignored on the read path. A
future migration may retire them entirely in a separate change.)
