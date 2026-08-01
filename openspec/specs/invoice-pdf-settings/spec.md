# invoice-pdf-settings Specification

## Purpose

Defines the persistence schema and lifecycle for the documented known
settings that control PDF rendering, used by the renderer (the
consumer of the `PrintableDocumentInterface`), the admin page, and
the public endpoint (`?page=factura_detallada&id=N`). The settings are
the contract between admin (writer) and renderer (reader); they MUST
round-trip through the database. Derives from proposal §"Capabilities"
→ "invoice-pdf-settings" and §"Approach" decision to use a dedicated
table (not `fs_var`). Licensed LGPL-3.0-or-later.

## Requirements

### Requirement: Dedicated settings table

The plugin MUST persist settings in a table named
`factura_pdf1_settings`. The plugin MUST NOT use `fs_var` for any
setting consumed by the renderer.

#### Scenario: Renderer reads from the dedicated table

- GIVEN a saved settings row with `name='default'`
- WHEN the renderer is invoked for the public endpoint
- THEN the renderer reads the row from `factura_pdf1_settings`
- AND no `fs_var` lookup occurs on the render path

### Requirement: Table schema

The `factura_pdf1_settings` table MUST define columns: `id` (PK),
`name` (unique key, e.g. `default`), `settings_json` (JSON),
`current_version` (integer), `created_at`, and `updated_at`. The
schema MUST be in `plugins/factura_pdf1/model/table/factura_pdf1_settings.xml`.

#### Scenario: Schema is parseable

- GIVEN the XML schema file at the path
- WHEN the framework installer parses it
- THEN every required column is created

#### Scenario: Unique constraint on name

- GIVEN two rows with the same `name`
- WHEN a third insert is attempted
- THEN the database rejects it with a unique-constraint violation

### Requirement: Load with default fallback and forward compatibility

Load MUST return a typed array of the documented known settings. Any
key absent from `settings_json` MUST be filled with the documented
default. Unknown keys present in `settings_json` MUST be preserved
(forward compatibility) and MUST NOT cause an error.

#### Scenario: Missing key falls back to default

- GIVEN a row whose `settings_json` omits `posicionlogo`
- WHEN settings are loaded
- THEN the returned array contains `posicionlogo` with the documented default value

#### Scenario: Unknown key is tolerated

- GIVEN a row whose `settings_json` contains a future key
- WHEN settings are loaded
- THEN load returns the unknown key alongside the known ones
- AND no exception is thrown

### Requirement: Atomic save

Save MUST persist the new `settings_json` and the new `current_version`
in a single database transaction. A failure mid-transaction MUST leave
the row unchanged.

#### Scenario: Successful save commits one row

- GIVEN a settings array and a new version
- WHEN `SettingsService::save($array, $version)` is called
- THEN exactly one UPDATE statement runs inside one transaction

#### Scenario: Simulated failure rolls back

- GIVEN the DB engine raises an error mid-update
- WHEN `save()` is called
- THEN the transaction is rolled back
- AND the previous `settings_json` is still present

### Requirement: Versioned init-upgrade migrations

The init-upgrade path MUST compare the row's `current_version` to
the in-code version. If the in-code version is higher, the path MUST
apply documented old-value migrations in ascending order (e.g.
`mostrarpais` → `ocultarpais`,
`ocultarreferenciasfact` → `documentosrelacionados`) BEFORE
persisting the new version. The new version MUST be persisted
atomically with the migrated JSON.

#### Scenario: mostrarpais migration runs

- GIVEN a row with `current_version=1` and `settings_json.mostrarpais=true`
- WHEN init-upgrade runs
- THEN the row is updated so `mostrarpais` is removed and `ocultarpais` is set
- AND `current_version` becomes the in-code version

#### Scenario: Already at current version is a no-op

- GIVEN a row with `current_version` equal to the in-code version
- WHEN init-upgrade runs
- THEN no UPDATE is executed and no migration runs

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
