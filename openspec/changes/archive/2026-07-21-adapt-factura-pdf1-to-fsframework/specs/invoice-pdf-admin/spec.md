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

The form MUST render a widget for every known setting, grouped under
the following sections in this order: logo, layout (header blocks),
lines (table), totals, footer. Each widget MUST carry an HTML `name`
attribute matching the setting key.

#### Scenario: Every setting has a widget

- GIVEN the rendered form
- WHEN the test iterates the known settings list
- THEN for every key an input/select/checkbox with `name="<key>"` is present

#### Scenario: Group sections exist in the template

- GIVEN the admin template
- WHEN the template is parsed
- THEN group headings `logo`, `layout`, `lines`, `totals`, `footer` are present

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
