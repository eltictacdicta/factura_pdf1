# Delta for invoice-pdf-admin

## Purpose

This change notes that the 28 admin widgets are now EFFECTIVE
(each one changes the rendered output, per the `invoice-pdf-settings`
delta) rather than purely decorative. It also adds 5 new i18n
strings and 2 new widget groups (text-block-1 and text-block-2) to
support the 7 position modes of the two text-block features.

## MODIFIED Requirements

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

## ADDED Requirements

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

## REMOVED Requirements

_None. The existing 5 requirements of `invoice-pdf-admin` remain
valid; the all-settings-rendered requirement is EXTENDED (in the
MODIFIED section) and 2 new widget-group requirements are added._
