# Proposal: Adapt FacturaPDF1 to FSFramework (replaces `factura_detallada`)

## Intent

`plugins/FacturaPDF1/` is a FacturaScripts 2025 plugin that **does not boot on FSFramework** today: `Init.php` extends `FacturaScripts\Core\Template\InitClass` (does not exist), `Lib/PDF/PDFDocument.php` extends `FacturaScripts\Core\Lib\PDF\PDFDocument` (does not exist), references 10 `FacturaScripts\Dinamic\Model\*` classes (none exist), and uses `Cezpdf` (not vendored) plus `Tools::settings/fixHtml` (do not exist). Engram #352 documents the full incompatibility.

The in-house replacement `plugins/factura_detallada/` is a thin 1-setting, FacturaCliente-only plugin. It cannot match the upstream's 30 settings, 7 document types, IBAN/carrier/related-docs/text-blocks/multi-locale/init-upgrade surface — and rewriting it in-place would lock us into the wrong plugin name. The pain is concrete: TPV print routing through `?page=factura_detallada&id=N` (hardcoded at `plugins/tpvmod/controller/tpvmod.php:206`) needs a faithful, modern, multi-doc backend.

## Scope

### In Scope
- New plugin folder `plugins/factura_pdf1/` (lowercase + snake_case rename from `FacturaPDF1/`; new folder coexists with the upstream folder during porting, then upstream is removed in a follow-up archive change).
- `fsframework.ini` + `composer.json` + `composer.lock` + vendored `vendor/` (mpdf ^8.0, no Cezpdf) + `composer_autoload.php` shim.
- Namespaced `Init.php` under `FSFramework\Plugins\factura_pdf1\`.
- PSR-4 `Controller/`, `Controller/Admin/`, `Services/`, `Model/`, plus the legacy lowercase `controller/` shim for the preserved URL contract.
- 4 client-document adapters behind a `PrintableDocumentInterface`: `FacturaCliente`, `AlbaranCliente`, `PedidoCliente`, `PresupuestoCliente`.
- Dedicated `factura_pdf1_settings` table (XML schema + `Model/FacturaPdf1Setting.php`); all 30 settings persisted as JSON, atomic save/load, versioned `current_version` column for init-upgrade migrations.
- Twig template `view/factura_pdf1/pdf.html.twig` (with the QR block as a no-op placeholder for the future Verifactu change) + AdminLTE admin page `themes/AdminLTE/view/admin/factura_pdf1/settings.html.twig` with `csrf_field()`.
- `translations/messages.es.yaml` + `translations/messages.en.yaml` (es_ES + en_EN baseline; 24-locale parity is follow-up).
- `phpunit.xml` + `tests/` mirroring `plugins/factura_detallada/` (PDF render, settings persistence, CSRF, public endpoint, integration with tpvmod URL, security grep tests).
- `README.md` + LGPL-3.0-or-later headers on every new file.

### Out of Scope
- Real Verifactu QR generation (deferred to a separate `QrForVerifactuService` change).
- The 3 supplier document types (`FacturaProveedor`, `AlbaranProveedor`, `PresupuestoProveedor`).
- The 22 non-ES/EN translation locales from upstream `Translation/`.
- Physical removal of `plugins/FacturaPDF1/` and deprecation of `plugins/factura_detallada/` (follow-up archive change).
- Any change in core `base/`, `src/`, `controller/`, `model/`, or other plugins. Core is not touched.

## Capabilities

### New Capabilities
- `invoice-pdf-rendering`: mpdf + Twig PDF rendering pipeline, including the 4-adapter polymorphism and structural-fidelity regression test.
- `invoice-pdf-settings`: the 30-setting schema, JSON column, atomic persistence in `factura_pdf1_settings`, versioned init-upgrade path.
- `invoice-pdf-adapters`: the `PrintableDocumentInterface` and the 4 client-document adapters (`Factura`, `Albaran`, `Pedido`, `Presupuesto`).
- `invoice-pdf-admin`: the CSRF-protected admin settings page under `?page=admin_factura_pdf1`.
- `invoice-pdf-public-endpoint`: the public `?page=factura_detallada&id=N` contract preserved for tpvmod (HTTP 200, `application/pdf`, valid PDF, 404 on missing id).

### Modified Capabilities
None. This is a new plugin; no existing core capability changes at the spec level.

## Approach

**Approach B — full FSFramework port** (rejected A: shim is bigger than the port; rejected C: two PDF engines coexist and the shim surface dwarfs the port). We re-use the architectural skeleton of `plugins/factura_detallada/`:

- `composer.json` + `composer.lock` + `vendor/` commit pattern → mirror `plugins/factura_detallada/composer.json`.
- `composer_autoload.php` shim → mirror `plugins/factura_detallada/composer_autoload.php`.
- `Controller/FacturaDetalladaController.php` (public endpoint), `Controller/Admin/ColoresController.php` (admin CSRF), `Services/PdfRenderService.php` (mpdf wrapper), `Model/FacturaPrintView.php` (view-model join) → same PSR-4 shape, retargeted to the 4 doc types.
- `controller/factura_detallada.php` (lowercase legacy shim) → reused verbatim, retargeted to the new controller class; the URL contract `?page=factura_detallada&id=N` is the **only** public page name and tpvmod continues to print through it.
- Twig templates `view/factura_detallada/pdf.html.twig` and `themes/AdminLTE/view/admin/factura_detallada/colores.html.twig` → retargeted and extended to 30 settings.
- `tests/` pattern (anonymous `fs_model` subclasses, mock `fs_db2`, static-state reset, integration test that reads tpvmod's controller source for the URL literal) → mirrored.
- `phpunit.xml` → mirror.

Settings storage deviates from `factura_detallada/`: 30 keys are too many for `fs_var`. We adopt a dedicated `factura_pdf1_settings` table with a JSON column and a versioned `current_version` for the init-upgrade path (the same `current_version` mechanism the upstream plugin uses for old-value migration).

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `plugins/factura_pdf1/` (new folder) | New | ~25 new files (composer.json, composer.lock, fsframework.ini, Init.php, composer_autoload.php, 4 controllers, 4-6 services, 4-5 model files, 5-9 Twig templates, 10+ tests, README, translations × 2, phpunit.xml, phpstan.neon). |
| `plugins/FacturaPDF1/` (existing upstream) | Removed (follow-up) | `Lib/PDF/PDFDocument.php` (1117 lines) and `XMLView/SettingsInvoice.xml` (191 lines) are NOT carried over. `Init.php` (153 lines) and `Translation/*.json` (24 files) are referenced but not copied. The folder is removed in the follow-up archive change. |
| `plugins/factura_detallada/` | Deprecated (follow-up) | No edit in this change. The folder is removed in the follow-up archive change; until then, both plugins can coexist but only `factura_pdf1` is the active detail-print backend. |
| `plugins/tpvmod/controller/tpvmod.php:206` | Untouched | The hardcoded `'./index.php?page=factura_detallada&id='` stays; the new plugin serves it. Integration test pins it. |
| `openspec/specs/` (core) | Untouched | Per AGENTS.md "OpenSpec per Plugin", this change is 100% plugin-internal. Core does not receive entries. |
| `base/`, `src/`, `controller/` (root), `model/` (root) | Untouched | No core change. If a missing helper or base class is discovered during apply, file as a follow-up; do not absorb. |
| `translations/messages.*.yaml` (core) | Untouched | New plugin carries its own `translations/messages.{es,en}.yaml` with `factura-pdf1.` key prefix. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Visual regression: mpdf HTML ≠ Cezpdf pixel layout. | Medium | Locked LOW priority. Regression test asserts `%PDF-` magic bytes, page count, expected text content (invoice number, cliente name, total). No byte-equality. Same approach as `factura-detallada-modernizacion` golden PDF. |
| Multi-document polymorphism: 4 doc types have different field shapes (`idfactura` vs `idalbaran` vs `idpedido` vs `idpresupuesto`, etc.). | Medium | `PrintableDocumentInterface` is the only shape the PDF renderer sees. Each adapter maps its own model. View-models (`FacturaPrintView`, `AlbaranPrintView`, etc.) join the line/iva/IRPF/RE tables per type. Test per adapter. |
| `factura_pdf1_settings` schema migration risk (e.g. adding a 31st setting later). | Low | `settings_json` is a JSON column; adding a new key is non-breaking. `current_version` column tracks init-upgrade runs; migrations are additive. |
| `vendor/` not committed (forbidden pattern). | Low | `tasks.md` makes the `git add plugins/factura_pdf1/vendor/` step explicit. Verify-report asserts `git ls-files plugins/factura_pdf1/vendor/ | wc -l` is non-zero. `/vendor/` is **not** in the plugin's `.gitignore`. |
| License of upstream `Lib/PDF/PDFDocument.php` (LGPL-3.0) propagating into new code. | Low | Do not copy the 1117-line file verbatim. Re-implement layout math in CSS + Twig against the upstream's documented behavior. Every new file carries the LGPL-3.0-or-later header. |
| Sub-repo boundary drift (someone runs `git add` from inside the plugin and initializes a sub-repo). | Low | `README.md` warns explicitly. `factura_pdf1/` is **parent-tracked** (mirror `clientes_facturacion`). No `.git` is created here. |
| Deprecation of `factura_detallada/` causing a hard-cutover outage if both plugins are active. | Low | The two plugins use the **same** public page name `?page=factura_detallada`. The new plugin owns it; `factura_detallada/` is removed in the follow-up archive change before any operator upgrades past the deprecation notice. The integration test asserts only one page name resolves at a time. |

## Rollback Plan

This change creates a **new** plugin; no in-use code is replaced. Rollback is **plugin deactivation**: an operator who hits a blocker disables `factura_pdf1` from the admin plugin manager and (if `factura_detallada` is still installed) re-enables it. No data migration is required because the new settings table is brand new; abandoning it is a DROP TABLE. The follow-up archive change that removes `plugins/factura_detallada/` is the only step that is **not** trivially reversible — that one will gate on a user-confirmed smoke test before merging.

## Dependencies

- `mpdf/mpdf` ^8.0 (vendored, `vendor/` committed).
- `clientes_facturacion` (provides `factura_cliente` + `linea_factura_cliente` + `linea_iva_factura_cliente`).
- `catalogo_core` (provides `articulo`, `familia`, `fabricante`, `impuesto`).
- `business_data` (provides `empresa`, `ejercicio`, `serie`, `divisa`, `forma_pago`).
- `clientes_core` (provides `cliente`, `direccion_cliente`, `pais`, `cuenta_banco_cliente`).
- Symfony 7.4 components already in the fork: `symfony/routing`, `symfony/security-csrf`, `symfony/http-foundation`, `symfony/translation`, `symfony/cache`. No new core dependency.

## Success Criteria

- [ ] `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml` is GREEN, with cases covering: 4 adapter renders, settings JSON round-trip, `current_version` migration, CSRF on admin, public endpoint Content-Type + non-empty body + 404 on missing id, tpvmod URL literal pinned, SQL-injection grep, Twig `|raw` grep.
- [ ] `curl -sL "https://<project>.ddev.site/index.php?page=factura_detallada&id={N}"` for a real `idfactura` returns HTTP 200, `Content-Type: application/pdf`, and a non-empty body that begins with `%PDF-`. The smoke command (HTTP + grep for `fatal`/`class .* not found` returns 0) passes for all 4 doc types.
- [ ] The admin page `?page=admin_factura_pdf1` saves a setting change; the new value persists across reload; it round-trips through `factura_pdf1_settings.settings_json`.
- [ ] All 30 settings keys from upstream `XMLView/SettingsInvoice.xml` are present and editable (verified by a settings-coverage test).
- [ ] Plugin activation from scratch (fresh DB, `fsframework.ini` only, no upstream legacy) succeeds with zero `ERROR`-level messages in `fs_core_log`.
- [ ] `git ls-files plugins/factura_pdf1/vendor/ | wc -l` is non-zero; `/vendor/` is **not** in the plugin's `.gitignore`; `composer.lock` and `vendor/` are in the same commit.
- [ ] Zero entries in `openspec/changes/adapt-factura-pdf1-to-fsframework/` (this is a plugin-local SDD; core `openspec/` does not see it).

## Chained PR Forecast

Given the **400-line review budget**, this change will be split into chained PRs. The orchestrator will surface the final split to the user per the cached `ask-always` strategy. Provisional 3-PR shape (revisited in `sdd-tasks`):

- **PR-1 (bootstrap + data)** — `composer.json` + `composer.lock` + `vendor/` (split into 2 atomic commits per `factura-detallada-modernizacion` convention), `fsframework.ini`, `Init.php`, `composer_autoload.php`, the `factura_pdf1_settings` table + XML schema + `Model/FacturaPdf1Setting.php` + `SettingsService.php`, base `PrintableDocumentInterface` + 4 empty adapter stubs, `Model/*PrintView.php` skeletons, PHPUnit scaffolding. ~350 lines. **Standalone**. Within budget.
- **PR-2 (PDF rendering + endpoint)** — `Services/PdfRenderService.php` (mpdf wrapper), the full Twig `view/factura_pdf1/pdf.html.twig` + 9 partials, `Controller/FacturaPdf1Controller.php`, legacy `controller/factura_detallada.php` shim retargeted, all 4 adapter implementations, PDF regression test. ~550 lines. **Requires `size:exception` in PR body**. Standalone if PR-1 is on the target branch.
- **PR-3 (admin + i18n + tests)** — `Controller/Admin/FacturaPdf1SettingsController.php`, `themes/AdminLTE/view/admin/factura_pdf1/settings.html.twig` (30 widgets + CSRF), `translations/messages.{es,en}.yaml`, full `tests/` (unit + integration + security grep + settings coverage), `README.md` rewrite, init-upgrade migration tests, `phpstan.neon`. ~600 lines. **Requires `size:exception`**. Standalone if PR-1 and PR-2 are on the target branch.

The orchestrator will confirm this 3-PR split (or re-forecast to 4-5 smaller PRs) before `sdd-tasks` locks the final shape. No implementation begins until the user has explicitly chosen the chained-PR strategy.

## Open Questions

None at this time. The five critical decisions (REPLACE `factura_detallada`; lowercase `factura_pdf1`; LOW visual fidelity; dedicated `factura_pdf1_settings` table; 4 client doc types behind a `PrintableDocumentInterface`) are locked from the orchestrator's preflight. Three secondary decisions (parent-tracked sub-repo boundary, Verifactu stays as a label, 24-locale parity deferred) are also locked. No ambiguity remains that would change the in-scope deliverables.

One **follow-up** (not blocking this change, recorded here for traceability): if `sdd-apply` discovers that the new code needs a missing core helper (e.g. an `IBANResolver` for `cuenta_banco_cliente`, or a `PrintableDocument` interface in `src/`), the apply agent MUST surface it as a follow-up change in the **core** `openspec/`, not absorb it into this change. The plugin-local rule in `AGENTS.md` is explicit and not negotiable inside a single PR.
