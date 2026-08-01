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
that each one appears as a rendered widget in the admin form. This
is the formal pin against accidental key drops.

#### Scenario: Every known setting is rendered

- GIVEN the rendered admin form
- WHEN the test iterates the known settings list
- THEN for every known setting name, the form contains a `name="<key>"` field
