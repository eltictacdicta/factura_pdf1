# Tasks: Bring upstream FacturaPDF1 render fidelity to `plugins/factura_pdf1/`

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1400 across 2 PRs |
| 400-line budget risk | High (both PRs exceed 400) |
| Chained PRs recommended | Yes (forced by preflight) |
| Suggested split | PR-1 (~600 LoC) + PR-2 (~800 LoC) |
| Delivery strategy | force-chained |
| Chain strategy | stacked-to-main |
| `size:exception` per PR | Yes (both PRs require it; explicit justification in the PR description) |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Tests + RelatedModelsLoader + adapter getters + dead-partial REMOVEs | PR-1 | `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml --filter SettingsEffectCoverageTest` + `--filter PublicEndpointTest` | 28 SettingsEffectCoverageTest cases RED→GREEN; 2 endpoint tests RED→GREEN | `git revert` reverts the entire unit; plugin still active and renders, but tests fail |
| 2 | Full upstream fidelity: 17 features Twig/CSS + 14 text-block partials + macro + SetFooter + per-tipo titulo + i18n strings | PR-2 | same phpunit + `ddev exec php -d memory_limit=512M vendor/dev-tools/bin/phpstan analyse -c plugins/factura_pdf1/phpstan.neon` | 19 RenderFeatureTest cases RED→GREEN; 14 TextBlockPositionTest cases RED→GREEN; visual diff in admin + endpoint | `git revert` reverts the entire unit; plugin still active and renders, but tokens are missing |

## Phase 1: Tests + Foundation (PR-1, ~600 LoC, size:exception)

- [x] 1.1 Add `tests/Unit/SettingsEffectCoverageTest.php` with 28 data-provider cases (one per `UPSTREAM_SETTING_KEYS` key). Each case sets one key to a sentinel and asserts a `data-<key>="<value>"` token appears in the rendered HTML. RED initially (28 fail).
- [x] 1.2 Add 2 integration tests: `PublicEndpointTest::testEndpointStreamsPdfForSeededPedido` and `::testEndpointStreamsPdfForSeededPresupuesto` (SUGGESTION #2 closure). RED initially; if a runtime defect is exposed, the fix lands in PR-1 or PR-2.
- [x] 1.3 Add 5 unit tests for new adapter getters: `getShippingAddress()`, `getWarehouse()`, `getBankData()`, `getCarrier()`, `getReceipts()`. Each asserts null when source is empty and the expected DTO/shape when populated. RED initially.
- [x] 1.4 Add 5 unit tests for `RelatedModelsLoader` (one per `load*()` method), asserting each returns `null` when the joined model is missing and the expected DTO/shape when it exists. RED initially.
- [x] 1.5 Implement `Services/RelatedModelsLoader.php` with 5 new `load*()` methods (`loadAlmacen`, `loadContactoEnvio`, `loadCuentaBancaria`, `loadAgenciaTransporte`, `loadRecibos`) returning a DTO. GREEN for 1.4.
- [x] 1.6 Add 5 getters to `Model/PrintableDocumentInterface.php` and default implementations to `Model/Adapters/AbstractClienteDocumentAdapter.php` (return `null`/`[]`/`''` per AD-11). Override in 1-2 concrete adapters that need them. GREEN for 1.3.
- [x] 1.7 REMOVE 3 dead partials (`view/factura_pdf1/partials/_client_billing.html.twig`, `_company_header.html.twig`, `_invoice_number_date.html.twig`) and edit `pdf.html.twig` to drop the 3 `{% block %}` stubs and the `_corporate_image` include.
- [x] 1.8 GREEN: re-run all tests, assert all 28+2+5+5 = 40 new tests pass. Run `ddev exec php -d memory_limit=512M vendor/dev-tools/bin/phpstan analyse -c plugins/factura_pdf1/phpstan.neon` (no new errors). Commit with `size:exception` justification (~600 LoC of mostly tests + minimum viable code to GREEN).

## Phase 2: Full upstream fidelity (PR-2, ~800 LoC, size:exception)

- [x] 2.1 Add `tests/Unit/RenderFeatureTest.php` with 17-19 data-provider cases (one per non-text-block feature; features 1, 2, 4, 5, 6, 7, 8, 11, 12, 13, 14, 15, 16, 17, 18, 19 of the proposal's table). Each asserts a `data-<key>` token. RED initially.
- [x] 2.2 Add `tests/Unit/TextBlockPositionTest.php` with 14 cases (7 positions × 2 text blocks; features 9, 10). Each asserts `data-text-block-{1,2}-position-N` and the element's position in the DOM. RED initially.
- [x] 2.3 Add `tests/Unit/AddressSplitMacroTest.php` with 2 cases (feature 18): split at parens when over `PARTIR_DIR` width; no split when under. RED initially.
- [x] 2.4 Implement 17 features in Twig/CSS (features 1, 2, 4, 5, 6, 7, 8, 11, 12, 13, 14, 15, 16, 17, 18, 19, plus the 2 text-block features 9, 10). Each emits its `data-<key>` token per AD-10. Extend `Services/PdfRenderService.php` to read all 28 settings (today only `colorcabecera` is read at line 116). GREEN for 2.1, 2.2, 2.3.
- [x] 2.5 Create `view/factura_pdf1/macro/address.html.twig` with `_address_split` macro (feature 18). GREEN for 2.3.
- [x] 2.6 Add `mpdf->SetFooter('{PAGENO} / {nbpg}')` in `Services/PdfRenderService::render()` (feature 16, before `WriteHTML()`). Update `tests/Regression/GoldenPdfTest.php` to ignore the page-number line when asserting structural tokens.
- [x] 2.7 Override `getDocumentTypeLabel()` in the 4 `*PrintView` classes (`FacturaPrintView`, `AlbaranPrintView`, `PedidoPrintView`, `PresupuestoPrintView`) with `formato_documento->titulo` lookup (feature 17; fallback to current hardcoded literal). Add 4 unit tests asserting the override + fallback. GREEN.
- [x] 2.8 Add 14 new i18n keys (`factura-pdf1.text-block-{1,2}-position-{1..7}`) to `translations/messages.{es,en}.yaml`. Update `tests/Integration/TranslationLoadingTest.php` to assert the new keys resolve in es_ES and en_EN.
- [x] 2.9 GREEN: re-run all tests, assert all 17+14+2+4 = 37 new tests pass. Run PHPStan. Visual smoke test of a real PDF render (curl + `%PDF-` check). Commit with `size:exception` justification: "PR is the visible-to-users PR; the PDF render changes substantially; operators with existing saved settings will see new tokens in their next render (intended behavior; called out in PR description)."

## Phase 3: Cleanup (post-verify)

- [ ] 3.1 Re-run sdd-verify; expect verdict `pass_with_warnings` (the parent-repo `.gitignore` vendor rule and the legacy `factura_detallada` cleanup remain out of scope per the prior change's §4.2).
- [ ] 3.2 Update `archive-report.md` with the chained PR execution, the 5 new ADs (AD-9 through AD-13) that superseded AD-5/AD-8 from the prior change, and the new "every setting has an effect" guarantee (closes the audit obs #367 gap).
- [ ] 3.3 Open follow-up SDD for §4.2: archive `plugins/factura_detallada/` + remove `plugins/FacturaPDF1/`.
- [ ] 3.4 Open follow-up SDD for the parent-repo `.gitignore` whitelist fix (in **core** `openspec/`, not this plugin's; the plugin-local rule from `AGENTS.md` forbids absorbing core changes into a plugin SDD).

## TDD Cycle Evidence

Strict TDD requires every Phase 1 and Phase 2 task to have a RED test (or be the implementation that makes a prior RED test GREEN). Impl-only tasks are marked `➖` for RED/TRIANGULATE and `✅` for GREEN.

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 1.1 | `tests/Unit/SettingsEffectCoverageTest.php` | Unit | ➖ (new) | ✅ Written (28 cases + 1 sanity) | ✅ All 29 GREEN (PR-2 closes 28 RED tokens) | ✅ 28 data-provider cases | ➖ None needed |
| 1.2 | `tests/Integration/PublicEndpointTest.php` | Integration | ✅ (existing) | ✅ Written (2 cases) | ✅ Passed (no defect) | ✅ pedido + presupuesto | ➖ None needed |
| 1.3 | `tests/Unit/AdapterGettersTest.php` | Unit | ➖ (new) | ✅ Written (5 cases) | ✅ Passed | ✅ 5 getters × default | ➖ None needed |
| 1.4 | `tests/Unit/RelatedModelsLoaderTest.php` | Unit | ➖ (new) | ✅ Written (5 cases) | ✅ Passed | ✅ null + edge for each | ➖ None needed |
| 1.5 | `Services/RelatedModelsLoader.php` (impl for 1.4) | Unit | ✅ 1.4 | ➖ (impl-only) | ✅ 1.4 GREEN | ➖ (impl-only) | ➖ None needed |
| 1.6 | `Model/PrintableDocumentInterface.php` + `Model/Adapters/AbstractClienteDocumentAdapter.php` (impl for 1.3) | Unit | ✅ 1.3 | ➖ (impl-only) | ✅ 1.3 GREEN | ➖ (impl-only) | ➖ None needed |
| 1.7 | (cleanup, no test) | — | ✅ 1.1 | ➖ (cleanup-only) | ✅ 1.1 still RED by design (zero visual diff) | ➖ (cleanup-only) | ➖ None needed |
| 1.8 | (verify, no commit) | All | ✅ 75/103 pass; 28 RED by design (1.1) | ➖ (verify-only) | ✅ Passed | ➖ (verify-only) | ➖ None needed |
| 2.1 | `tests/Unit/RenderFeatureTest.php` | Unit | ➖ (new) | ✅ Written (25 cases incl. feature_3 recibos) | ✅ All 25 GREEN | ✅ 1 per feature | ➖ None needed |
| 2.2 | `tests/Unit/TextBlockPositionTest.php` | Unit | ➖ (new) | ✅ Written (14 cases) | ✅ All 14 GREEN | ✅ 7 × 2 | ➖ None needed |
| 2.3 | `tests/Unit/AddressSplitMacroTest.php` | Unit | ➖ (new) | ✅ Written (2 cases) | ✅ All 2 GREEN | ✅ split + no-split | ➖ None needed |
| 2.4 | (impl for 2.1/2.2/2.3) | Unit | ✅ 2.1/2.2/2.3 | ➖ (impl-only) | ✅ All GREEN | ➖ (impl-only) | ✅ 14 text-block partials + macro + body data-* tokens |
| 2.5 | (impl for 2.3) | Unit | ✅ 2.3 | ➖ (impl-only) | ✅ 2.3 GREEN | ➖ (impl-only) | ➖ None needed |
| 2.6 | `tests/Regression/GoldenPdfTest.php` | Regression | ✅ (existing) | ✅ Updated for footer | ✅ Passed | ➖ Single footer test | ➖ None needed |
| 2.7 | `tests/Unit/PrintViewDocumentTypeLabelTest.php` | Unit | ➖ (new) | ✅ Written (4 cases) | ✅ All 4 GREEN | ✅ 4 `*PrintView` classes | ➖ None needed |
| 2.8 | `tests/Integration/TranslationLoadingTest.php` | Integration | ✅ (existing) | ✅ Updated (14 keys) | ✅ All keys resolve in es_ES + en_EN | ✅ es_ES + en_EN | ➖ None needed |
| 2.9 | (verify commit) | All | ✅ 111/148 pass; 37 PR-2 new | ➖ (verify-only) | ✅ 148/148 GREEN; 0 PHPStan errors | ➖ (verify-only) | ➖ None needed |
| 3.1 | sdd-verify (post-apply) | — | — | N/A | N/A | N/A | N/A |
| 3.2 | (archive-report update) | — | — | N/A | N/A | N/A | N/A |
| 3.3 | (open follow-up SDD) | — | — | N/A | N/A | N/A | N/A |
| 3.4 | (open follow-up SDD) | — | — | N/A | N/A | N/A | N/A |

**Discipline check**: every Phase 1/Phase 2 row has ✅ on RED (for test-writing tasks) or ➖ (for impl-only), ✅ on GREEN, ✅ or ➖ on TRIANGULATE, and ✅ or ➖ on REFACTOR. Zero ❌ rows. The 4 Phase 3 rows are post-apply cleanup and have N/A TDD evidence.

## Chained PR Execution Note

This change is **`force-chained`** (per preflight) into 2 PRs, both requiring `size:exception` because each exceeds the 400-line review budget. The 2-PR split is the result of:

- **User product decision** (cached in preflight): "1-2 PRs with `size:exception`".
- **Design analysis** (obs #375): PR-1 is tests + foundation (mostly test code; visible diff vs main is zero because the render is still a clone of `factura_detallada` modulo 3 dead-partial REMOVEs). PR-2 is the visible-to-users PR; the PDF render changes substantially.

**Delivery strategy**: `force-chained` (preflight).
**Chain strategy**: `stacked-to-main` (PR-2 → PR-1 → main, in order).
**`size:exception` per PR**: Yes. Each PR description MUST include the explicit justification: "this is the minimum cohesive slice for the [PR-X] phase; splitting further would either orphan tests from implementation or split a single feature across PRs, both of which violate strict TDD."

## Handoff to sdd-apply

The next phase is **sdd-apply**. The apply agent will:

1. Open PR-1 first (the 8 task rows in Phase 1). Run the focused test command after each RED→GREEN cycle. Commit with the `size:exception` justification. Wait for review/merge before starting PR-2.
2. Open PR-2 (the 9 task rows in Phase 2) on top of the PR-1 branch (`stacked-to-main`). Run the focused test command + PHPStan. Commit with the `size:exception` justification. Merge to main.
3. Run Phase 3 cleanup (4 task rows) AFTER both PRs are merged.

If `sdd-apply` discovers a missing core helper (e.g., `IBANResolver` for `cuenta_banco_cliente`, or a `FormAttachment` service for `idcontactoenv`), it MUST surface it as a follow-up change in the **core** `openspec/`, not absorb it into this plugin-local SDD. The plugin-local rule in `AGENTS.md` is explicit and not negotiable inside a single PR.
