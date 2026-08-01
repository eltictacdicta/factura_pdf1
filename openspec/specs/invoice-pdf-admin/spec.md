# invoice-pdf-admin Specification

## Purpose

Defines the CSRF-protected admin settings page of the `factura_pdf1`
plugin. The page edits the settings that drive the renderer (and its
`PrintableDocumentInterface` adapters) used by the public endpoint
(`?page=factura_detallada&id=N`). Derives from proposal
§"Capabilities" → "invoice-pdf-admin" and §"Approach" admin
decision. Reuses the AdminLTE theme and `FSTranslator` for es_ES +
en_EN. Licensed LGPL-3.0-or-later.

## Requirements

### Requirement: URL contract and CSRF protection

The admin page MUST be served at `?page=admin_factura_pdf1`. POST
handlers MUST be CSRF-protected: the template MUST emit
`{{ csrf_field() }}` and the controller MUST call
`$this->isCsrfValid()`. A missing or invalid token MUST be rejected
before any settings are persisted.

#### Scenario: GET renders the form

- GIVEN a logged-in admin with access to admin pages
- WHEN `?page=admin_factura_pdf1` is requested
- THEN the response is HTTP 200 and the body is the rendered form

#### Scenario: POST without CSRF token is rejected

- GIVEN a POST with a valid settings body but no CSRF token
- WHEN the controller runs
- THEN `$this->isCsrfValid()` returns `false`
- AND no `SettingsService::save()` call is made

#### Scenario: POST with valid CSRF proceeds

- GIVEN a POST with a valid CSRF token and a valid settings body
- WHEN the controller runs
- THEN `$this->isCsrfValid()` returns `true` and the save path runs

### Requirement: All settings rendered, grouped logically

The form MUST render a widget for every known setting, grouped
under the following sections in this order: logo, layout (header
blocks), lines (table), totals, text-block-1, text-block-2, footer.
Each widget MUST carry an HTML `name` attribute matching the
setting key. Every widget MUST be effective: changing the value and
re-rendering any `PrintableDocumentInterface` MUST produce a
different HTML body, attributable to the changed widget (this is
the lesson from Engram obs #367 — "every setting has an effect",
not just "every setting has a widget").

#### Scenario: Every setting has a widget

- GIVEN the rendered form
- WHEN the test iterates the known settings list
- THEN for every key an input/select/checkbox with `name="<key>"` is present

#### Scenario: Group sections exist in the template

- GIVEN the admin template
- WHEN the template is parsed
- THEN group headings `logo`, `layout`, `lines`, `totals`, `text-block-1`, `text-block-2`, `footer` are present

#### Scenario: Every widget has a render effect

- GIVEN a widget in the form
- WHEN the operator changes the widget's value and saves
- THEN a re-render of the seeded `PrintableDocumentInterface` fixture MUST produce a different HTML body
- AND the difference MUST be attributable to the changed widget (per `SettingsEffectCoverageTest`)

### Requirement: Save persists atomically and redirects

A successful save MUST call `SettingsService::save()` (atomic, per the
`invoice-pdf-settings` spec) and MUST redirect to the same page with a
success message.

#### Scenario: Valid save persists and redirects

- GIVEN a valid form POST with a valid CSRF token
- WHEN the controller runs
- THEN `SettingsService::save()` is called exactly once
- AND the response is a 302 to `?page=admin_factura_pdf1`
- AND a success message is registered

#### Scenario: Validation failure re-renders the form

- GIVEN a POST with a malformed color (e.g. `#GGG`)
- WHEN the controller runs
- THEN `SettingsService::save()` is NOT called
- AND the form is re-rendered with an error message

### Requirement: Reset to defaults

The page MUST expose a "reset to defaults" action that restores the
documented default for every known setting, persists via
`SettingsService::save()`, and re-renders the form with a success
message.

#### Scenario: Reset action restores every default

- GIVEN a saved row with non-default values
- WHEN the operator triggers the reset action
- THEN every known setting is set to its documented default
- AND `SettingsService::save()` is called once
- AND the form re-renders with a success message

### Requirement: Server-side i18n with key fallback

The form MUST render server-side in `es_ES` and `en_EN` using
`FSTranslator`. A missing key MUST fall back to the key string, not
a stack trace or 500.

#### Scenario: Spanish locale renders es_ES strings

- GIVEN the active locale is `es_ES`
- WHEN the form is rendered
- THEN section labels and buttons resolve to their Spanish values

#### Scenario: English locale renders en_EN strings

- GIVEN the active locale is `en_EN`
- WHEN the form is rendered
- THEN section labels and buttons resolve to their English values

#### Scenario: Missing key falls back gracefully

- GIVEN a translation key with no entry in the YAML files
- WHEN the form is rendered
- THEN the literal key string is emitted (no exception, no 500)

### Requirement: text-block-1 widget group exposes the 7 position modes

The form MUST render a widget group named `text-block-1` with: (1) a
select for `posiciontexto1` whose options are the 7 position modes
(1=above header, 2=below header, 3=top of page, 4=bottom of page,
5=after line items, 6=after totals, 7=before footer); (2) a number
input for `medidatexto1`; (3) a color input for `colortexto1`; (4) a
select for `justiftexto1` (left/center/right/justify). Each option
label MUST be served by `FSTranslator` from the new key
`factura-pdf1.text-block-1-position-N` (where N=1..7).

#### Scenario: Each of the 7 position options has a translated label

- GIVEN the active locale is `es_ES`
- WHEN the form is rendered
- THEN the `posiciontexto1` select MUST contain 7 `<option>` elements
- AND each `<option>` MUST carry a `value="N"` attribute
- AND the displayed text for option N MUST be the value of the i18n key `factura-pdf1.text-block-1-position-{N}` in `es_ES`

#### Scenario: English locale renders en_EN labels for text-block-1

- GIVEN the active locale is `en_EN`
- WHEN the form is rendered
- THEN the displayed text for option N MUST be the value of the i18n key `factura-pdf1.text-block-1-position-{N}` in `en_EN`

### Requirement: text-block-2 widget group exposes the 7 position modes + free text

The form MUST render a widget group named `text-block-2` with: (1) a
select for `posiciontexto2` whose options are the 7 position modes
(mirroring `posiciontexto1`); (2) a number input for `medidatexto2`;
(3) a color input for `colortexto2`; (4) a select for
`justiftexto2`; (5) a textarea for `texto2` (free text). Each
position option label MUST be served by `FSTranslator` from the new
key `factura-pdf1.text-block-2-position-N` (where N=1..7).

#### Scenario: Each of the 7 position options has a translated label

- GIVEN the active locale is `es_ES`
- WHEN the form is rendered
- THEN the `posiciontexto2` select MUST contain 7 `<option>` elements
- AND each `<option>` MUST carry a `value="N"` attribute
- AND the displayed text for option N MUST be the value of the i18n key `factura-pdf1.text-block-2-position-{N}` in `es_ES`

#### Scenario: English locale renders en_EN labels for text-block-2

- GIVEN the active locale is `en_EN`
- WHEN the form is rendered
- THEN the displayed text for option N MUST be the value of the i18n key `factura-pdf1.text-block-2-position-{N}` in `en_EN`

#### Scenario: `texto2` textarea is editable and round-trips

- GIVEN the operator opens the admin form
- WHEN the operator types `Custom operator note: please review line 3` into the `texto2` textarea and saves
- THEN the controller MUST validate the CSRF token
- AND `SettingsService::save()` MUST persist the literal `Custom operator note: please review line 3` in `settings_json.texto2`
- AND a reload of the form MUST echo back the same string in the textarea

#### Scenario: Missing i18n key falls back to the literal key string

- GIVEN a translation key `factura-pdf1.text-block-1-position-3` is missing in the active locale
- WHEN the form is rendered
- THEN the select option MUST display the literal `factura-pdf1.text-block-1-position-3` (no exception, no 500)
