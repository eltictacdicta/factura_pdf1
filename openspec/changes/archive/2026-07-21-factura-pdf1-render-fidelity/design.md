# Design: Bring upstream FacturaPDF1 render fidelity to `plugins/factura_pdf1/`

## Meta

- **Change**: `factura-pdf1-render-fidelity`
- **Plugin root**: `plugins/factura_pdf1/`
- **Project**: `fs-framework` (parent monorepo; 100% plugin-internal per `plugins/factura_pdf1/openspec/config.yaml: ownership: plugin-local`)
- **Artifact store**: `hybrid` (engram + filesystem)
- **Strict TDD**: ACTIVE (every task has RED-then-GREEN evidence)
- **Chained PR strategy**: 2 PRs, both `size:exception`, chain `stacked-to-main` (PR-2 → PR-1 → main)

## Executive summary

This change wires the 27 dead settings (audit obs #367) to real render paths. PR-1 makes the new `SettingsEffectCoverageTest` RED, adds pedido/presupuesto endpoint coverage, and removes 3 empty placeholder partials. PR-2 implements the 19 features (Twig/CSS/PHP), the 5 adapter getters, the `RelatedModelsLoader` extensions, `_address_split` macro, `mpdf->SetFooter`, per-tipo titulo, and the 14 text-block partials. The contract between templates and tests is a `data-<key>="<value>"` token convention (AD-10) — the test that would have caught the 27/28 dead-settings bug is now part of the regression suite.

## Architecture decisions

### Kept from prior design (cite, confirm, do not re-justify)

| # | Title | Source |
|---|-------|--------|
| **AD-1** | Reuse `factura_detallada` skeleton (composer, Init, shims, PSR-4, mpdf + Twig pipeline) | prior design §AD-1 |
| **AD-2** | Dedicated `factura_pdf1_settings` table (JSON + `current_version`) | prior design §AD-2 |
| **AD-3** | `PrintableDocumentInterface` + 4 adapters behind `AbstractClienteDocumentAdapter` | prior design §AD-3 |
| **AD-4** | Preserve `?page=factura_detallada` URL contract (`plugins/tpvmod/controller/tpvmod.php:206` pin) | prior design §AD-4 |
| **AD-6** | Structural golden regression (`GoldenPdfTest` + `smalot/pdfparser`) | prior design §AD-6 |
| **AD-7** | Single-row singleton (`name='default'`, `UNIQUE (name)`) | prior design §AD-7 |

### Superseded

| # | Prior rule | New rule |
|---|------------|----------|
| **AD-5** | mpdf LOW fidelity; only `colorcabecera` drives render | **SUPERSEDED.** Every persisted setting MUST have a distinctive effect on the rendered PDF. The `SettingsEffectCoverageTest` (AD-9) is the enforcement mechanism. |
| **AD-8** | One `pdf.html.twig` + 9 partials | **SUPERSEDED.** Final structure: `pdf.html.twig` + 6 partials (3 dead partials removed) + 14 text-block partials (7 positions × 2 text blocks) + 1 new macro file (`view/factura_pdf1/macro/address.html.twig`). The 3 dead partials (`_client_billing`, `_company_header`, `_invoice_number_date`) are REMOVED; their content is already inlined in `_parties_header.html.twig`. |

### New (introduce this change)

| # | Title | Choice | Rationale |
|---|-------|--------|-----------|
| **AD-9** | **Settings effect coverage test** | `SettingsEffectCoverageTest` walks `UPSTREAM_SETTING_KEYS` (28 keys), renders the same fixture invoice 28 times (each time with one key set to a distinctive sentinel), and asserts each render contains a `data-<key>="<value>"` token. | The test that would have caught the 27/28 dead-settings bug. Gates the "every setting has an effect" requirement. Lives in `tests/Unit/SettingsEffectCoverageTest.php`. |
| **AD-10** | **Distinctive render token convention** | Every setting that drives a render feature MUST emit a `data-<key>="<value>"` attribute on the relevant DOM element. The token MUST be asserted by at least one test. The convention is documented inline (`view/factura_pdf1/_render_tokens.md`) so future contributors know to follow it. | Locks the contract between templates and tests. Makes regression failures diagnostic. |
| **AD-11** | **Adapter getter convention** | Every new adapter getter MUST: (a) be declared on `PrintableDocumentInterface`; (b) have a default implementation in `AbstractClienteDocumentAdapter` that returns `null`/`[]`/`''`; (c) be overridden only by the adapter that needs it; (d) be exercised by a unit test for at least one adapter. | Avoids the dead-interface-method pattern from the prior change (`getIban()`, `getPaymentBreakdown()`, `getRelatedDocuments()`, `getCarrier()` all declared but unread). |
| **AD-12** | **`RelatedModelsLoader` for cross-model joins** | The 5 new model loads (`Almacen`, `Contacto` shipping, `cuenta_banco*`, `AgenciaTransporte`, `ReciboCliente`) are centralized in a single `RelatedModelsLoader` service (already exists at `Model/View/RelatedModelsLoader.php`), not scattered in adapter code. The loader takes the document id and returns a DTO with all related data. Adapters call the loader once in `fromId()`. | Single point of change; single point of testing; no duplicated SQL across 4 adapters. |
| **AD-13** | **Text-block rendering via `{% include %}` with `data-text-block-{1,2}-position-N`** | The 7 position modes per text block are implemented as 14 named partials (`_text_block_1_pos_1.html.twig` … `_text_block_2_pos_7.html.twig`); the runtime selects the right partial via a `{% if %}{% elseif %}` chain on `posiciontexto{1,2}`. | Mechanical, easy to test (each partial is unit-testable in isolation), avoids runtime string concat. The 14 partials live under `view/factura_pdf1/partials/`. |

## Data flow

**Public endpoint with settings effect:**

```
HTTP GET /index.php?page=factura_detallada&id=N&tipo=pedido
   |
   v
controller/factura_detallada.php (29-line shim)
   | extends
   v
Controller\FacturaPdf1Controller::processRequest()
   |-- validate id (getInt, <= 0 -> 404)
   |-- resolveAdapter(tipo) -> XxxClienteAdapter::fromId(N)
   |
   v
Services\SettingsService::load()  (28 keys, JSON row)
   |
   v
Model\View\RelatedModelsLoader::load($doc)  (5 new load* methods)
   |  -> DTO { almacen, contactoEnvio, cuentaBancaria, agenciaTransporte, recibos }
   v
Model\View\XxxPrintView (5 new getters, parentDocuments() walk + dedup)
   |
   v
Services\PdfRenderService::render(view, settings)
   |  -- SetFooter('{PAGENO} / {nbpg}')
   |  -- Twig render(view/factura_pdf1/pdf.html.twig + 6 partials + 14 text-block partials + macro)
   |  -- mpdf WriteHTML -> Output('S') -> binary
   v
Symfony Response(application/pdf, body=%PDF-...)
   |
   v
Test asserts: data-pagoyvencimiento-mode, data-warehouse-mode, data-text-block-1-position, ...
```

**Admin save with effect verification:**

```
HTTP POST /index.php?page=admin_factura_pdf1  (CSRF + form)
   |
   v
Controller\Admin\FacturaPdf1SettingsController::private_core()
   |-- isCsrfValid() == false -> 403
   |-- bind form (Symfony Request -> 28 keys)
   |
   v
Services\SettingsService::save(array, currentVersion+1)  (atomic UPDATE)
   |
   v
Response::redirect('?page=admin_factura_pdf1')  (302 + success flash)
   |
   v
Next render: SettingsService::load() reads new value
   -> Twig emits new data-* token
   -> SettingsEffectCoverageTest passes for the changed key
```

## File changes

| File | Action | Description |
|------|--------|-------------|
| `view/factura_pdf1/pdf.html.twig` | MODIFY | Drop 3 dead `{% block %}` stubs; drop `_corporate_image` include; add `{% if posiciontexto1 %}{% include ... %}` for texto1/texto2 dispatch; add 5 new `data-*` tokens for the 19 features |
| `view/factura_pdf1/partials/_parties_header.html.twig` | MODIFY | Logo 4-position (1) + hide-province (13) + ref2 (14) + max-width (15) + auto-shrink (19) — 5 feature blocks |
| `view/factura_pdf1/partials/_line_items.html.twig` | MODIFY | `colorfilas` + `espaciofilas` (2) + hide-reference (11) — 2 feature blocks |
| `view/factura_pdf1/partials/_payment_footer.html.twig` | MODIFY | `pagoyvencimiento` (3) + IBAN (4) + carrier (5) + shipping address (6) — 4 feature blocks |
| `view/factura_pdf1/partials/_vat_breakdown.html.twig` | MODIFY | `ocultartablaimpuestos` (12) — 1 feature block + VAT-collapse logic |
| `view/factura_pdf1/partials/_totals.html.twig` | MODIFY | Hook for texto1/texto2 position-6 placement |
| `view/factura_pdf1/partials/_client_billing.html.twig` | REMOVE | Empty placeholder; content inlined in `_parties_header` |
| `view/factura_pdf1/partials/_company_header.html.twig` | REMOVE | Same |
| `view/factura_pdf1/partials/_invoice_number_date.html.twig` | REMOVE | Same |
| `view/factura_pdf1/partials/_text_block_1_pos_{1..7}.html.twig` (×7) | NEW | 7 partials for the 7 positions of texto1 (AD-13) |
| `view/factura_pdf1/partials/_text_block_2_pos_{1..7}.html.twig` (×7) | NEW | 7 partials for texto2 (AD-13) |
| `view/factura_pdf1/macro/address.html.twig` | NEW | `_address_split` macro for feature 18 |
| `view/factura_pdf1/_render_tokens.md` | NEW | AD-10 convention doc (which key emits which token) |
| `Services/PdfRenderService.php` | MODIFY | Read all 28 settings (line 116 today reads only `colorcabecera`); add `SetFooter` (16); pass new keys to template |
| `Model/View/RelatedModelsLoader.php` | MODIFY | 5 new `load*()` methods: `loadAlmacen`, `loadContactoEnvio`, `loadCuentaBancaria`, `loadAgenciaTransporte`, `loadRecibos` (AD-12) |
| `Model/View/ClientDocumentPrintViewInterface.php` | MODIFY | Add 5 new getters per AD-11 (mirror PrintableDocumentInterface) |
| `Model/View/{Factura,Albaran,Pedido,Presupuesto}PrintView.php` | MODIFY | Override `getDocumentTypeLabel()` per AD-12 (feature 17); call new getters |
| `Model/Adapters/AbstractClienteDocumentAdapter.php` | MODIFY | Wire 5 new getters; default implementations return `null`/`[]`/`''` (AD-11) |
| `Model/Adapters/{Factura,Albaran,Pedido,Presupuesto}ClienteAdapter.php` (×4) | MODIFY | Populate the 5 new getters in `fromId()`; implement `parentDocuments()` walk + dedup (feature 7) |
| `Model/PrintableDocumentInterface.php` | MODIFY | Add 5 new getters (AD-11) |
| `themes/AdminLTE/view/admin/factura_pdf1/settings.html.twig` | MODIFY | New `text-block-1` + `text-block-2` widget groups; `texto2` textarea; each widget group emits its own `data-*` token for the effect test |
| `translations/messages.es.yaml` | MODIFY | +14 keys: `factura-pdf1.text-block-{1,2}-position-{1..7}` |
| `translations/messages.en.yaml` | MODIFY | Same 14 keys in `en_EN` |
| `tests/Unit/SettingsEffectCoverageTest.php` | NEW | AD-9 — the 28-key effect coverage test |
| `tests/Unit/RenderFeatureTest.php` | NEW | 19 setting-effect scenarios (one per feature) — RED in PR-1, GREEN in PR-1/PR-2 |
| `tests/Unit/AdapterExtensionsTest.php` | NEW | 4 adapters × 5 new getters = 20 triangulation cases (AD-11) |
| `tests/Unit/AddressSplitMacroTest.php` | NEW | 2 scenarios for feature 18 |
| `tests/Integration/PublicEndpointTest.php` | MODIFY | +2 methods: `testEndpointStreamsPdfForSeededPedido`, `testEndpointStreamsPdfForSeededPresupuesto` (SUGGESTION #2 closure) |
| `tests/Regression/GoldenPdfTest.php` | MODIFY | Asserts the 19 new `data-*` tokens are present in the rendered PDF for `FAKT-2026-0001` |
| `openspec/specs/{invoice-pdf-rendering,invoice-pdf-settings,invoice-pdf-adapters,invoice-pdf-public-endpoint,invoice-pdf-admin}/spec.md` (×5) | MODIFY | Source-of-truth merges (delta → main) |
| `base/`, `src/`, `controller/` (root), `model/` (root) | UNTOUCHED | Core is not touched; per AGENTS.md plugin-local rule |
| Parent `openspec/` | UNTOUCHED | This is 100% plugin-internal |

**Counts**: ~25 MODIFY · ~21 NEW (14 text-block + macro + 5 tests + tokens doc) · 3 REMOVE.

## Chained-PR migration plan

### PR-1 — settings effects + endpoint coverage + dead-partial cleanup (~600 LoC, `size:exception`)

Tasks: (1.1) Write `SettingsEffectCoverageTest` (the 28-key test, AD-9) — RED. (1.2) Write 19 `RenderFeatureTest` scenarios — RED. (1.3) Write 2 endpoint tests (pedido, presupuesto) — RED. (1.4) Implement 5 new adapter getters on `PrintableDocumentInterface` + `AbstractClienteDocumentAdapter` defaults per AD-11. (1.5) Remove 3 dead partials (`_client_billing`, `_company_header`, `_invoice_number_date`) and edit `pdf.html.twig` to drop their stubs. (1.6) GREEN: minimum viable Twig/PHP edits to make the new tests pass (one or two easy feature blocks to prove the test pattern works). (1.7) Commit with `size:exception` justification: "PR is mostly tests; render is still a clone of `factura_detallada` modulo 3 dead-partial REMOVEs. Visual diff vs main is zero." **Standalone.**

### PR-2 — full upstream fidelity (~800 LoC, `size:exception`)

Tasks: (2.1) Implement 17 features in Twig (1–8, 11–19), each emitting its `data-<key>` token. (2.2) Implement 14 text-block partials + the `{% if posiciontextoX %}{% include %}` chain in `pdf.html.twig` (features 9, 10). (2.3) Implement `view/factura_pdf1/macro/address.html.twig` for feature 18. (2.4) Add `mpdf->SetFooter('{PAGENO} / {nbpg}')` in `PdfRenderService` (feature 16). (2.5) Override `getDocumentTypeLabel()` in 4 `*PrintView` classes with `FormatoDocumento` lookup (feature 17). (2.6) Extend `RelatedModelsLoader` with 5 new `load*()` methods (AD-12). (2.7) Extend `*PrintView` classes to call the 5 new getters and the parentDocuments walk. (2.8) Add `text-block-1` + `text-block-2` admin widget groups + `texto2` textarea. (2.9) Add 14 new i18n keys to `translations/messages.{es,en}.yaml`. (2.10) GREEN: re-run all tests, assert all `data-*` tokens present, assert pedido/presupuesto endpoint tests pass with full coverage. (2.11) Commit with `size:exception` justification: "PR is the visible-to-users PR. The PDF render changes substantially. Operators with existing saved settings will see new tokens in their next render. This is the intended behavior; called out in PR description." **Requires PR-1 on the target branch.**

## Test strategy (per PR, Strict TDD)

| PR | RED tests written | GREEN work | Verification |
|----|-------------------|------------|--------------|
| PR-1 | `SettingsEffectCoverageTest` (28 cases) + `RenderFeatureTest` (19 cases) + 2 endpoint tests + `AdapterExtensionsTest` stubs (returns null/[]) | Implement 5 new getters with default `null`/`[]`/`''`; remove 3 dead partials; minimum Twig edits to make ~2 easy feature blocks pass | `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml` — expect: 28+19+2 = 49 new tests, ~28 fail in RED, all pass in GREEN |
| PR-2 | 14 text-block position tests + 4 `getDocumentTypeLabel` override tests + 5 `RelatedModelsLoader` extension tests + 2 `AddressSplitMacro` tests + `GoldenPdfTest` updates | All 19 features in Twig/PHP + 14 partials + macro + SetFooter + 5 load* methods + 14 i18n keys | `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml` — expect: 70+ new tests, all pass; `ddev exec php -d memory_limit=512M vendor/dev-tools/bin/phpstan analyse -c plugins/factura_pdf1/phpstan.neon` — 0 errors |

## Risk matrix

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Visual regression on the legacy `factura_detallada` look the user complained about | Low | Defaults match upstream `XMLView/SettingsInvoice.xml`; `GoldenPdfTest` asserts structural tokens, not bytes; `SettingsEffectCoverageTest` is the runtime check |
| Performance regression (Twig renders with more blocks) | Low–Medium | mpdf is the bottleneck, not Twig; profile before/after with `GoldenPdfTest` timing in CI |
| Settings migration (existing rows lack new keys) | Low | `SettingsService::load()` fills missing keys from `defaults()` (already implemented); no DB migration needed |
| `getDocumentTypeLabel()` per-tipo override breaks the existing template | Low | Override falls back to the current hardcoded literal when `formato_documento->titulo` is `null`; `GoldenPdfTest` regression catches the no-titulo case |
| Auto-shrink company name (feature 19) `clamp()` CSS not supported by mpdf | Low | Fallback to `text-overflow: ellipsis` + `white-space: nowrap` (already the AD-rendering spec fallback); triangulation test renders both Twig output and final PDF |
| 3 dead-partial REMOVEs break a custom theme that overrides them | Low | `themes/AdminLTE/` is the only theme in this repo; no plugin theme overrides `factura_pdf1/partials/`; README documents the removal |
| Pedido/presupuesto endpoint tests expose a real defect in `XxxPrintView::build()` | Medium | Tests are RED in PR-1; defect fix is small and lands in PR-1 or PR-2; if defect > 100 LoC, orchestrator re-splits PR-2 |
| 5 SUGGESTIONs from prior verify-report | Low | Pick any that fit (e.g. `RenderModuleIsolationGrepTest`, full-column schema assertion) as SUGGESTIONs in the new verify-report; pedido/presupuesto is in scope per user decision |
| Vendor `vendor/` not committed at parent-repo level | Known | Out of scope for this change; follows the prior change's documented WARNING |

## Supersession note

This design SUPERSEDES AD-5 and AD-8 of the prior design at `plugins/factura_pdf1/openspec/changes/archive/2026-07-21-adapt-factura-pdf1-to-fsframework/design.md`. The prior design stays as historical record. For render-fidelity decisions, this design is the authoritative document. AD-1, AD-2, AD-3, AD-4, AD-6, AD-7 are KEPT (no change). The 5 new ADs (AD-9 through AD-13) lock the test/contract/loader patterns that close the audit's gap.

## Handoff to sdd-tasks

`sdd-tasks` will:
1. Translate the PR-1 / PR-2 task lists above into 21+ task rows in `tasks.md`.
2. Add a TDD Cycle Evidence table (RED / GREEN / TRIANGULATE / REFACTOR per task) — Strict TDD requirement.
3. Lock the chained-PR forecast with `size:exception` per PR (`Decision needed before apply: Yes`, `Chained PRs recommended: Yes`, `400-line budget risk: High`).
4. Forecast ~1500–2000 LoC across 2 PRs (PR-1 ~600, PR-2 ~800) — both exceed the 400-line budget and require `size:exception`.
5. Confirm the 5 SUGGESTIONs from the prior verify-report that are NOT in scope (suggestion #1, #3, #4, #5) and write them into the new verify-report as forward SUGGESTIONs.
6. Pin the hybrid persistence: `tasks.md` lives at `plugins/factura_pdf1/openspec/changes/factura-pdf1-render-fidelity/tasks.md`; engram `topic_key: sdd/factura-pdf1-render-fidelity/tasks`.
