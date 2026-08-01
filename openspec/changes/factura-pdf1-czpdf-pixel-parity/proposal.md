# Proposal: Switch `plugins/factura_pdf1/` from mpdf to Cezpdf for upstream pixel-parity

## Intent

The previous cycle `factura-pdf1-render-fidelity` (archived 2026-07-21; verdict `pass_with_warnings`; 148/148 tests, 85/85 spec scenarios COMPLIANT, 0 PHPStan errors) shipped a **working** `plugins/factura_pdf1/` on top of mpdf + Twig. The user inspected a real PDF served at `https://panel-ab.ddev.site/index.php?page=factura_detallada&id=1` and reported: *"el formato sigue siendo el antiguo de factura detallada, no se parece nada al que originariamente tenia plugins/FacturaPDF1."* After we confirmed the 19 new features (logo, ref2, texto2, IBAN, color header, page numbering) are visible in the rendered PDF, the user chose the Cezpdf pixel-parity path with full knowledge of the cost (Engram #386, 2026-07-21): ~2 000–2 500 LoC of new code, 2–3 chained PRs with `size:exception`, and ~1 000 LoC of the previous cycle's Twig work discarded.

This is a **major scope change** and warrants a **new** SDD, not a re-open of the archived one. The new change is a **SUPERSESSION of AD-5 of the previous design** (LOW fidelity → HIGH fidelity). The previous cycle's `design.md` and `archive-report.md` stay as historical record; this change's `design.md` is the new authoritative doc for render-fidelity decisions.

## Scope

### In Scope (9 deliverables, A–I)

| # | Deliverable | Description |
|---|-------------|-------------|
| **A** | Engine swap | `Services/PdfRenderService.php` (mpdf+Twig, 180 LoC) → `Services/CezpdfRenderService.php` (~100 LoC). Drop `mpdf/mpdf ^8.0` from `composer.json`; vendor Cezpdf locally. |
| **B** | Port upstream `PDFDocument` | ~1 000 of 1 117 lines verbatim from `plugins/FacturaPDF1/Lib/PDF/PDFDocument.php` into `Lib/PDF/PortedPdfDocument.php`. Adapt ~117 lines to remove FS2025 deps (abstract + `ExtensionsTrait` + `BusinessDocument` + `Tools::*` + `Where::eq` + `FacturaScripts\Dinamic\Model\*`). |
| **C** | New `AbstractPdfDocument` parent | ~250 LoC at `Lib/PDF/AbstractPdfDocument.php` providing the methods the upstream relied on the FS2025 parent for (`getTaxesRows`, `getCountryName`, `getDivisaName`, `removeEmptyCols`, `addImageFromFile`, `addImageFromAttachedFile`, `i18n->trans`, `format->idlogo/titulo/texto`, `tableWidth`, `insertedHeader`, `getFileName`, `newLine`). |
| **D** | Discard Twig template work | REMOVE `view/factura_pdf1/pdf.html.twig` (64 LoC) + 19 text-block partials (~570 LoC) + `macro/address.html.twig` (~20 LoC) + the entire `view/factura_pdf1/` tree. The 19 features are now produced by Cezpdf draw calls, not Twig. |
| **E** | Strict byte-equality test | Rewrite `tests/Regression/GoldenPdfTest.php` to compare the Cezpdf-rendered PDF against a pre-generated fixture (`tests/Fixtures/legacy_invoice_FACT20260001.pdf`). `REGENERATE_FIXTURE=1` env var for intentional updates. |
| **F** | Rewrite `SettingsEffectCoverageTest` | The 28 data-provider cases rewrite to assert against Cezpdf PDF output (text extraction via smalot/pdfparser + raw byte inspection for color hex). ~250 LoC replacement. The regression net ("every setting has an effect") is preserved. |
| **G** | TRUE HTTP integration test | New `tests/Integration/RealHttpEndpointTest.php` (80 LoC) that hits `index.php?page=factura_detallada&id=N` via the **actual** HTTP stack (not `Request::create()`). Closes the test bypass that let the tpvmod URL helper gap slip through in the prior change. |
| **H** | Cezpdf vendor commit | `plugins/factura_pdf1/vendor/cezpdf/{Cezpdf.php, Cpdf.php, fonts/*.afm}`. 850 KB. Committed to repo per AGENTS.md "Plugin Composer Dependencies — vendor/ MUST be committed". |
| **I** | `Small helpers` | `Services/FormatoDocumento.php` (~30 LoC), `Services/PdfNumberFormatter.php` (~20 LoC), `Services/LocaleSettings.php` (~40 LoC). Replace the upstream's `$this->format->X` value-object access + `Tools::number()` + `Tools::settings('default', ...)` calls. |

### Out of Scope (deferred to follow-up SDDs)

- **Verifactu / QR** — the 4 `pipe()` calls in the upstream become no-ops. `QRimg()` is kept (real Cezpdf draw code) but never invoked. Deferred to a future `QrForVerifactuService` SDD.
- **3 supplier doc types** (`FacturaProveedor`, `AlbaranProveedor`, `PedidoProveedor`, `PresupuestoProveedor`) — locked in previous cycle's AD-3.
- **22 non-ES/EN translation locales** — 24-locale parity is a follow-up.
- **Physical removal of `plugins/factura_detallada/`** and **`plugins/FacturaPDF1/`** — the §4.2 follow-up from the previous cycle; still deferred.
- **Parent-repo `.gitignore` whitelist fix for `plugins/factura_pdf1/**`** — a CORE concern per AGENTS.md; not this plugin's SDD.
- **`texto1` setting** — only `texto2` is in `UPSTREAM_SETTING_KEYS` (verified at `Services/SettingsService.php:33–62`); `texto1` uses the `FormatoDocumento->texto` value object and is dropped.
- **The 5 SUGGESTIONs from the previous cycle's verify-report** (carry-forward: `RenderModuleIsolationGrepTest`, full-column schema assertion, `fs_var` render-path static test, `NumberFormatter` warning root cause, pedido/presupuesto unit render test).

## Capabilities (delta vs the 5 source-of-truth specs)

### Modified Capabilities (5 of 5)

- **`invoice-pdf-rendering`**: MODIFIED — every "Twig template renders X" requirement becomes "Cezpdf draw primitives produce X". The `data-*` HTML token convention (previous AD-10) is **removed**; assertions move to direct PDF text extraction via `smalot/pdfparser` and to byte-level inspection for color hex. ADDED requirement: **byte-equality regression** (the new `GoldenPdfTest`).
- **`invoice-pdf-settings`**: KEEP the "every setting has a widget" scenario; MODIFY the "every setting has an effect" scenario to assert against Cezpdf output (text or color hex from the PDF). Data-provider convention changes from `data-*` HTML tokens to extracted PDF signals.
- **`invoice-pdf-adapters`**: KEEP (engine-independent; the 4-adapter polymorphism is preserved).
- **`invoice-pdf-admin`**: KEEP (engine-independent; the admin form is preserved).
- **`invoice-pdf-public-endpoint`**: KEEP + ADD a new scenario for the TRUE HTTP integration test that closes the test-bypass gap that let the tpvmod URL helper issue slip through.

### New Capabilities

None. All 5 baseline specs are extended; no new capability file is needed.

## Approach

1. **Vendor Cezpdf locally** from `plugins/facturacion_base/extras/ezpdf/` into `plugins/factura_pdf1/vendor/cezpdf/` (Cezpdf.php 88 KB + Cpdf.php 161 KB + 12 AFM fonts 608 KB = ~850 KB). No Composer dep; commit the tree to the repo per AGENTS.md "vendor/ MUST be committed".
2. **Port the upstream `PDFDocument.php` to standalone**: not `abstract`, not `extends anything`. Use `PrintableDocumentInterface` (already in `plugins/factura_pdf1/Model/`) instead of `BusinessDocument`. Inject `SettingsService::load()` array instead of `Tools::settings()`. Use `FSTranslator::trans()` instead of `$this->i18n->trans`. Inline `QRimg()` (real Cezpdf draw code, kept on disk) and stub `pipe()` to return `''` (Verifactu deferred). The 2 `Where::eq()` calls become `loadWhere(['field' => $val])`.
3. **Create `AbstractPdfDocument`** as the thin shim the port consumes: provides the methods the upstream relied on the FS2025 parent for. Holds the `$pdf` Cezpdf property; receives `FSTranslator`, `FormatoDocumento`, `SettingsService` via constructor.
4. **Replace `PdfRenderService` with `CezpdfRenderService`** — same public API (`render`, `renderHtml` for the legacy test seam, `save`) so the controller doesn't change. Internally: `$pdf = new Cezpdf(...)` + `$port = new PortedPdfDocument($pdf, $view, $settings, $translator, ...)` + `$port->render()` + `$pdf->ezOutput()`.
5. **Discard the Twig template** — `view/factura_pdf1/` is removed entirely. The 19 features are now produced by the upstream's exact Cezpdf draw calls (verbatim port).
6. **Strict byte-equality test** against `tests/Fixtures/legacy_invoice_FACT20260001.pdf` (generated once in PR-1 by `tests/Fixtures/generate_legacy_fixture.php`). `REGENERATE_FIXTURE=1` env var escape hatch for intentional updates.
7. **Chained PR split** (3 PRs; PR-1 in budget, PR-2 and PR-3 with `size:exception`).

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `plugins/factura_pdf1/Lib/PDF/AbstractPdfDocument.php` | **NEW** | ~250 LoC; the new parent class providing the methods the upstream relied on the FS2025 parent for |
| `plugins/factura_pdf1/Lib/PDF/PortedPdfDocument.php` | **NEW** | ~1 117 LoC, ~90% verbatim from upstream; adapter calls re-routed to FSFramework `PrintableDocumentInterface` |
| `plugins/factura_pdf1/Services/CezpdfRenderService.php` | **NEW** | ~100 LoC; replaces `PdfRenderService`; same public API |
| `plugins/factura_pdf1/Services/FormatoDocumento.php` | **NEW** | ~30 LoC value object (`idlogo`, `titulo`, `texto`) |
| `plugins/factura_pdf1/Services/PdfNumberFormatter.php` | **NEW** | ~20 LoC; `format(float $n): string` respecting locale |
| `plugins/factura_pdf1/Services/LocaleSettings.php` | **NEW** | ~40 LoC; reads `default/decimal_separator`, `default/thousands_separator`, `default/idempresa` |
| `plugins/factura_pdf1/vendor/cezpdf/{Cezpdf.php, Cpdf.php, fonts/*.afm}` | **NEW** | 850 KB; vendored from `plugins/facturacion_base/extras/ezpdf/` |
| `plugins/factura_pdf1/composer.json` | **MODIFY** | Drop `mpdf/mpdf ^8.0`; add Cezpdf autoload entry |
| `plugins/factura_pdf1/composer_autoload.php` | **MODIFY** | Add `require_once vendor/cezpdf/Cezpdf.php` |
| `plugins/factura_pdf1/Init.php` | **MODIFY** | Drop `registerTwigPaths()` call; keep `runSettingsUpgrade()` |
| `plugins/factura_pdf1/Controller/FacturaPdf1Controller.php` | **MODIFY** | Swap `PdfRenderService` → `CezpdfRenderService` (~5 LoC) |
| `plugins/factura_pdf1/Services/PdfRenderService.php` (180 LoC) | **REMOVE** | Replaced by `CezpdfRenderService` |
| `plugins/factura_pdf1/view/factura_pdf1/**` (24 files) | **REMOVE** | `pdf.html.twig` + 19 partials + macro + empty directories |
| `plugins/factura_pdf1/vendor/mpdf/**` (94 MB) + transitive deps | **REMOVE** | mpdf no longer needed; keep `vendor/smalot/**` for PDF text extraction in tests |
| `plugins/factura_pdf1/Model/PrintableDocumentInterface.php` | **MODIFY** | Add 4 new methods (`getModelClassName`, `getCodigoRect`, `getObservaciones`, `getLines`) so the port can read these without going through `BusinessDocument` |
| `plugins/factura_pdf1/tests/Fixtures/legacy_invoice_FACT20260001.pdf` | **NEW** | Binary fixture; generated once in PR-1 |
| `plugins/factura_pdf1/tests/Fixtures/generate_legacy_fixture.php` | **NEW** | ~30 LoC one-off script |
| `plugins/factura_pdf1/tests/Unit/CezpdfRenderServiceTest.php` | **NEW** | ~60 LoC; replaces `PdfRenderServiceTest` |
| `plugins/factura_pdf1/tests/Unit/AbstractPdfDocumentTest.php` | **NEW** | ~150 LoC; asserts each helper method behaves |
| `plugins/factura_pdf1/tests/Unit/CezpdfRenderFeatureTest.php` | **NEW** | ~400 LoC; replaces `RenderFeatureTest` |
| `plugins/factura_pdf1/tests/Unit/AddressSplitTest.php` | **NEW** | ~80 LoC; replaces `AddressSplitMacroTest` |
| `plugins/factura_pdf1/tests/Unit/SettingsEffectCoverageTest.php` | **REWRITE** | ~250 LoC; data-provider rewritten against Cezpdf output |
| `plugins/factura_pdf1/tests/Integration/PublicEndpointTest.php` | **MODIFY** | Update expected PDF content (~20 LoC) |
| `plugins/factura_pdf1/tests/Integration/RealHttpEndpointTest.php` | **NEW** | ~80 LoC; TRUE HTTP integration test |
| `plugins/factura_pdf1/tests/Regression/GoldenPdfTest.php` | **REWRITE** | Byte-equality against the fixture; ~50 LoC |
| `plugins/factura_pdf1/tests/Unit/{AddressSplitMacroTest,RenderFeatureTest,PdfRenderServiceTest}.php` | **REMOVE** | Replaced by new tests |
| `plugins/factura_pdf1/Services/{SettingsService,RelatedModelsLoader,EmpresaLogoResolver,SettingsFormBinder,SettingsValidator}.php` | **KEEP** | All engine-independent |
| `plugins/factura_pdf1/Model/{Contacto,ReciboCliente,FacturaPdf1Setting,Adapters/*,View/*}.php` | **KEEP** | All engine-independent |
| `plugins/factura_pdf1/Controller/{FacturaPdf1Controller,Admin/*}.php` | **KEEP** | Public endpoint + admin controller engine-independent |
| `plugins/factura_pdf1/controller/{factura_detallada,admin_factura_pdf1}.php` | **KEEP** | URL contract shims |
| `plugins/factura_pdf1/translations/messages.{es,en}.yaml` | **KEEP** | Engine-independent |
| `base/`, `src/`, `controller/` (root), `model/` (root), parent `openspec/` | **UNTOUCHED** | 100% plugin-local per AGENTS.md "OpenSpec per Plugin" |
| `plugins/FacturaPDF1/`, `plugins/factura_detallada/`, `plugins/tpvmod/` | **UNTOUCHED** | Out of scope; separate follow-up SDDs |

## Risks

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| **Engine-swap bug surface** (1 117 lines of layout math to port verbatim) | MEDIUM-HIGH | HIGH — any arithmetic mistake shifts the layout | Byte-equality test (`GoldenPdfTest`) catches it immediately; PR-2 has a tight RED→GREEN cycle |
| **Byte-equality test fragility** (breaks on any Cezpdf version change, any seed-data tweak, any timestamp drift) | MEDIUM | MEDIUM — test goes RED on benign changes | `REGENERATE_FIXTURE=1` env var for intentional updates; documented workflow in the test |
| **Discarded work** (~1 000 LoC of Twig + 19 partials + macro + tests) | HIGH (it WILL happen) | HIGH (opportunity cost) | Acknowledged in Engram #386; the user's explicit choice. PR-3 documents the discarded work in `archive-report.md` |
| **Cezpdf unmaintained** (last release 0.11.6) | LOW (PHP 8.3 still works) | HIGH if a future PHP breaks it | Vendored with the plugin; any patches can be applied locally |
| **License compatibility** (Cezpdf public-domain + plugin LGPL-3.0 + upstream LGPL-3.0) | LOW | LOW — all permissive + LGPL | Already verified in the explore report §I; no action needed |
| **tpvmod URL contract regression** (`?page=factura_detallada&id=N`) | LOW | HIGH — would break tpvmod printing | `TpvmodUrlPinTest` already exists; PR-3 adds a TRUE HTTP integration test |
| **Multi-document regression** (4 doc types work end-to-end) | LOW | HIGH — would break 3 of 4 doc types | `AdapterGettersTest` + `ClienteDocumentAdapterTest` + new `CezpdfRenderFeatureTest` cover the path |
| **Settings persistence regression** (`factura_pdf1_settings` table) | LOW | HIGH — would lose admin settings | `SettingsServiceTest` + `InitUpgradeTest` cover persistence; engine-independent |
| **PDF binary size / parse time** (Cezpdf PDFs are larger than mpdf HTML→PDF) | MEDIUM | LOW — admin-only feature, not user-facing | Acceptable; a `phpunit` benchmark can be added if it becomes a concern |

## Rollback Plan

Pure engine swap; no data migration. `CezpdfRenderService` is binary-compatible with `PdfRenderService` (same public API: `render`, `renderHtml`, `save`). Rollback is **plugin deactivation + git revert** of the active change folder. The `vendor/cezpdf/` tree is removable with the rest. The `factura_pdf1_settings` JSON table is untouched (engine-independent).

## Dependencies

- **New vendor (local file)**: `Cezpdf 0.11.6` from `plugins/facturacion_base/extras/ezpdf/` (88 KB Cezpdf.php + 161 KB Cpdf.php + 608 KB fonts = 850 KB).
- **Removed vendor**: `mpdf/mpdf ^8.0` (94 MB) + transitive deps (`setasign`, `myclabs`, `paragonie`, `psr`, `symfony`); `vendor/smalot/**` (476 KB) is KEPT for PDF text extraction in tests.
- **Preserved (no change)**: `clientes_facturacion`, `catalogo_core`, `business_data`, `clientes_core`, `formatos` (same as previous cycle).
- **New in-plugin models (preserved from previous cycle)**: `Contacto`, `ReciboCliente`, `factura_cliente.idcontactoenv` + `factura_cliente.codigoenv` columns.

## Success Criteria

- [ ] `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml` is GREEN; 0 CRITICAL, 0 failures, byte-equality test passes
- [ ] `ddev exec php -d memory_limit=512M vendor/dev-tools/bin/phpstan analyse -c plugins/factura_pdf1/phpstan.neon` is GREEN; 0 errors
- [ ] `curl -sL "https://panel-ab.ddev.site/index.php?page=factura_detallada&id={N}"` for a real `idfactura` returns HTTP 200, `Content-Type: application/pdf`, body starts with `%PDF-`, body bytes match the byte-equality fixture for the same seed
- [ ] **Visual smoke test**: render a real PDF; the user (the project lead) confirms "this looks like the upstream `FacturaPDF1` CamelCase"
- [ ] The TRUE HTTP integration test hits `http://localhost/index.php?page=factura_detallada&id=N` and asserts a valid PDF (closes the test-bypass gap that let the tpvmod URL helper issue slip through)
- [ ] The 28 settings each have a Cezpdf-output effect (the rewritten `SettingsEffectCoverageTest` is GREEN for all 28)
- [ ] `git ls-files plugins/factura_pdf1/vendor/cezpdf/ | wc -l` is non-zero (850 KB committed)
- [ ] `phpunit --testdox` reports 0 warnings related to fixture regeneration (the byte-equality test is deterministic)
- [ ] Zero entries in `openspec/changes/factura-pdf1-czpdf-pixel-parity/` at the parent-repo level (this is a plugin-local SDD; core `openspec/` does not see it)

## Supersession Note (CRITICAL)

This change **SUPERSEDES AD-5** of the previous cycle (`archive/2026-07-21-factura-pdf1-render-fidelity/design.md`):

| Prior AD-5 (archived 2026-07-21) | New AD-5 (this change) |
|-----|-----|
| "mpdf HTML/CSS (LOW fidelity). Visual intentionally NOT pixel-parity with upstream Cezpdf. Same license granted in `factura-detallada-modernizacion`." | "Cezpdf pixel-parity (HIGH fidelity). Visual IS the upstream's exact Cezpdf output. Vendored Cezpdf 0.11.6 from `plugins/facturacion_base/extras/ezpdf/`." |

The previous cycle's `design.md` and `archive-report.md` stay as historical record. The new change's `design.md` is the new authoritative doc for render-fidelity decisions. AD-1, AD-2, AD-3, AD-4, AD-6, AD-7, AD-8, AD-9, AD-10, AD-11, AD-12, AD-13 from the prior design are **KEPT** (no change) unless the new design explicitly supersedes them.

## Chained PR Forecast (3 PRs; PR-1 in budget, PR-2 + PR-3 with `size:exception`)

### PR-1 — foundation (~400 LoC, in budget)
**Goal**: vendor Cezpdf + create the new parent class + small helpers + RED tests + byte-equality fixture.
- NEW `Lib/PDF/AbstractPdfDocument.php` (~250 LoC)
- NEW `Services/{FormatoDocumento,PdfNumberFormatter,LocaleSettings}.php` (~90 LoC)
- NEW `vendor/cezpdf/{Cezpdf.php, Cpdf.php, fonts/*.afm}` (850 KB)
- MODIFY `composer.json`, `composer_autoload.php`, `Init.php`
- REMOVE `vendor/mpdf/**` (94 MB) + transitive deps
- NEW `tests/Fixtures/generate_legacy_fixture.php` (~30 LoC) + `tests/Fixtures/legacy_invoice_FACT20260001.pdf` (binary)
- NEW `tests/Unit/CezpdfRenderServiceTest.php` (RED), `tests/Unit/AbstractPdfDocumentTest.php` (RED), `tests/Regression/GoldenPdfFixtureTest.php` (RED)
- **Standalone**.

### PR-2 — engine swap (~1 200 LoC, `size:exception` required)
**Goal**: port the upstream `PDFDocument.php` to standalone + wire it into the service + remove the Twig template.
- NEW `Lib/PDF/PortedPdfDocument.php` (~1 117 LoC, mostly verbatim from upstream)
- NEW `Services/CezpdfRenderService.php` (~100 LoC)
- MODIFY `Controller/FacturaPdf1Controller.php` (swap service, ~5 LoC); MODIFY `Model/PrintableDocumentInterface.php` (+4 methods, ~30 LoC)
- REMOVE `Services/PdfRenderService.php` (180 LoC); REMOVE `view/factura_pdf1/**` (24 files, ~650 LoC)
- NEW `tests/Unit/AddressSplitTest.php` (~80 LoC), `tests/Unit/CezpdfRenderFeatureTest.php` (~400 LoC)
- REMOVE `tests/Unit/{AddressSplitMacroTest,RenderFeatureTest,PdfRenderServiceTest}.php`
- MODIFY `tests/Integration/PublicEndpointTest.php`, `tests/Regression/GoldenPdfTest.php` (rewrite as byte-equality)
- **Requires PR-1 on the target branch.**

### PR-3 — cleanup + TRUE HTTP integration test (~400 LoC, `size:exception` recommended)
**Goal**: rewrite the `SettingsEffectCoverageTest` to assert against Cezpdf output; add the TRUE HTTP integration test.
- MODIFY `tests/Unit/SettingsEffectCoverageTest.php` (rewrite, ~250 LoC)
- NEW `tests/Integration/RealHttpEndpointTest.php` (~80 LoC, real HTTP test, requires `ddev` running)
- MODIFY `phpunit.xml` (~10 LoC); MODIFY `phpstan.neon` if needed; MODIFY `README.md` (~20 LoC)
- **Requires PR-2 on the target branch.**

The orchestrator will confirm this 3-PR split (or re-forecast) before `sdd-tasks` locks the final shape. No implementation begins until the user explicitly approves the chained-PR strategy.

## Handoff to sdd-spec

This proposal defines the contract: 9 deliverables (A–I) with 5 source-of-truth spec deltas. `sdd-spec` writes 5 delta specs (one per source-of-truth file) under `plugins/factura_pdf1/openspec/changes/factura-pdf1-czpdf-pixel-parity/specs/` and updates the 5 source-of-truth specs in `plugins/factura_pdf1/openspec/specs/`. Specifically:

- **`invoice-pdf-rendering`**: every "Twig template renders X" requirement → "Cezpdf draw primitives produce X". The `data-*` HTML token convention (previous AD-10) is **removed**. ADDED: byte-equality regression requirement.
- **`invoice-pdf-settings`**: KEEP "every setting has a widget" scenario; MODIFY the "every setting has an effect" scenario to assert against Cezpdf output (text or color hex from the PDF).
- **`invoice-pdf-adapters`**: KEEP (engine-independent; 4-adapter polymorphism preserved).
- **`invoice-pdf-admin`**: KEEP (engine-independent; admin form preserved).
- **`invoice-pdf-public-endpoint`**: KEEP + ADD a TRUE HTTP integration scenario (closes the test-bypass gap).

`sdd-design` then plans the Cezpdf vendor path + the port mechanics + the chained-PR split (3 PRs, `size:exception` per PR-2 and PR-3). `sdd-tasks` locks the final shape. Implementation does not begin until the user explicitly approves the chained-PR strategy and the byte-equality strictness (the only open product question — locked by the user in the explore round: STRICT byte-for-byte with `REGENERATE_FIXTURE=1` env var escape hatch).
