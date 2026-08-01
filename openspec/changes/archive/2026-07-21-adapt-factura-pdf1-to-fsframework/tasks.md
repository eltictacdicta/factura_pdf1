# Tasks: Adapt FacturaPDF1 to FSFramework (replaces factura_detallada)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1500 across 3 PRs |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR-1 (~350) → PR-2 (~550, size:exception) → PR-3 (~600, size:exception) |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Bootstrap + settings table | PR-1 | `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml` | plugin activates in admin | revert bootstrap files + DROP `factura_pdf1_settings` |
| 2 | PDF pipeline + public endpoint | PR-2 | same phpunit | `curl ?page=factura_detallada&id=N` → 200 + `%PDF-` (4 types) | revert PR-2 paths; PR-1 stays |
| 3 | Admin + i18n + security tests | PR-3 | phpunit + `ddev exec php -d memory_limit=512M vendor/bin/phpstan analyse -c plugins/factura_pdf1/phpstan.neon` | POST admin save/reset → 302 + DB persists | revert admin/templates/tests |

## Phase 1: Foundation (PR-1, ~350 LoC)

- [x] 1.1 Create `composer.json`, `composer.lock`, committed `vendor/` (mpdf ^8.0); `composer_autoload.php`; `fsframework.ini`; `facturascripts.ini`; `Init.php` (`registerTwigPaths()`).
- [x] 1.2 Create `model/table/factura_pdf1_settings.xml`, `Model/FacturaPdf1Setting.php`, `Services/SettingsService.php` (load, atomic save, defaults, `currentVersion`, `applyMigrations`, reset).
- [x] 1.3 Create `Model/PrintableDocumentInterface.php`, `Model/Exception/PrintableDocumentNotFoundException.php`, stubs in `Model/Adapters/*`, skeletons in `Model/View/*PrintView.php`.
- [x] 1.4 Create `phpunit.xml`, `phpstan.neon`, `tests/bootstrap.php`, `README.md`, `LICENSE` (LGPL-3.0+).
- [x] 1.5 RED→GREEN `tests/Unit/SettingsServiceTest.php`: JSON round-trip, defaults, missing-key fallback, unknown-key tolerance.
- [x] 1.6 Verify phpunit GREEN; `vendor/` tracked (no `/vendor/` in `.gitignore`); commit PR-1.

## Phase 2: Core PDF (PR-2, ~550 LoC, size:exception)

- [x] 2.1 RED→GREEN adapter tests; implement `Model/Adapters/{Factura,Albaran,Pedido,Presupuesto}ClienteAdapter.php` + full `Model/View/*PrintView.php` joins.
- [x] 2.2 Create `Services/EmpresaLogoResolver.php`; RED→GREEN `tests/Unit/PdfRenderServiceTest.php`; implement `Services/PdfRenderService.php` (mpdf + Twig).
- [x] 2.3 Create `view/factura_pdf1/pdf.html.twig` + 9 partials in `view/factura_pdf1/partials/_*.html.twig` (no `|raw` on user data).
- [x] 2.4 RED→GREEN `tests/Regression/GoldenPdfTest.php` with `tests/Fixtures/factura_cliente.seed.php` (page count, A4 width, key text; no byte compare).
- [x] 2.5 Create `Controller/FacturaPdf1Controller.php` + shim `controller/factura_detallada.php` (`getInt('id')`, 404 + JSON `not_found`).
- [x] 2.6 RED→GREEN `tests/Integration/PublicEndpointTest.php` + `tests/Integration/TpvmodUrlPinTest.php` (literal `./index.php?page=factura_detallada&id=`).
- [x] 2.7 Smoke curl 4 doc types; commit PR-2 with `size:exception` justification (~550 LoC minimum cohesive PDF slice).

## Phase 3: Admin + i18n (PR-3, ~600 LoC, size:exception)

- [x] 3.1 RED→GREEN `tests/Controller/Admin/FacturaPdf1SettingsControllerTest.php` (CSRF reject, valid save, malformed color, reset); implement `Controller/Admin/FacturaPdf1SettingsController.php`.
- [x] 3.2 Create shim `controller/admin_factura_pdf1.php` + `themes/AdminLTE/view/admin/factura_pdf1/settings.html.twig` (30 widgets, logo/layout/lines/totals/footer, `csrf_field()`).
- [x] 3.3 RED→GREEN init-upgrade tests (`mostrarpais→ocultarpais`, no-op at parity) + `tests/Unit/SettingsCoverageTest.php` (all 30 upstream keys).
- [x] 3.4 Create `translations/messages.es.yaml` + `messages.en.yaml` (`factura-pdf1.*` keys; es_ES/en_EN render tests).
- [x] 3.5 Create `tests/Security/{SqlInjectionGrepTest,TwigRawGrepTest,CezpdfUsageGrepTest,AdapterIsolationGrepTest}.php` + `tests/Integration/AdminEndpointTest.php`.
- [x] 3.6 Update `README.md` runbook; phpstan + phpunit GREEN; commit PR-3 with `size:exception`.

## Phase 4: Cleanup (post-verify)

- [x] 4.1 Map delta specs to tests; write `verify-report.md` (`phpunit --testdox`).
- [ ] 4.2 Follow-up SDD: archive `factura_detallada` + remove `plugins/FacturaPDF1/` (no core edits).
