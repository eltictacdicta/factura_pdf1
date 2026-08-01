# Design: Switch `plugins/factura_pdf1/` from mpdf to Cezpdf for upstream pixel-parity

## Meta

- **Change**: `factura-pdf1-czpdf-pixel-parity`
- **Change root**: `plugins/factura_pdf1/openspec/changes/factura-pdf1-czpdf-pixel-parity/`
- **Plugin root**: `plugins/factura_pdf1/`
- **Project**: `fs-framework` (parent monorepo; 100% plugin-internal per `plugins/factura_pdf1/openspec/config.yaml: ownership: plugin-local`)
- **Artifact store**: `hybrid` (engram + filesystem)
- **Strict TDD**: ACTIVE
- **Chained PR strategy**: 3 PRs stacked-to-main; PR-1 in 400 LoC budget, PR-2 + PR-3 require `size:exception`
- **Supersession**: this design SUPERSEDES **AD-5** and **AD-8** of `plugins/factura_pdf1/openspec/changes/archive/2026-07-21-factura-pdf1-render-fidelity/design.md`; AD-1, AD-2, AD-3, AD-4, AD-6, AD-7, AD-9, AD-10, AD-11, AD-12, AD-13 of the prior design are KEPT, REMOVED, or re-mapped as documented below.

## Executive summary

This change is an **engine swap**, not a feature add. The mpdf + Twig pipeline from the previous cycle is replaced by a verbatim port of the upstream `FacturaPDF1` `PDFDocument.php` (CamelCase, 1117 LoC) running against a locally-vendored Cezpdf 0.11.6 engine. The 19 features are now produced by Cezpdf draw calls, not Twig; the test seam is the rendered PDF bytes (extracted text + color hex + draw-call spy), and a strict byte-equality regression test against a generated fixture is the new gate. The Twig template tree is discarded; ~1 000 LoC of previous-cycle work is removed; ~2 100 LoC of new code lands across 3 chained PRs.

## Architecture decisions

### Kept from the prior design (cite, confirm, do not re-justify)

| # | Title | Source |
|---|-------|--------|
| **AD-1** | Reuse `factura_detallada` skeleton (composer, Init, shims, PSR-4) | prior design §AD-1 |
| **AD-2** | Dedicated `factura_pdf1_settings` table (JSON + `current_version`) | prior design §AD-2 |
| **AD-3** | `PrintableDocumentInterface` + 4 adapters behind `AbstractClienteDocumentAdapter` | prior design §AD-3 |
| **AD-4** | Preserve `?page=factura_detallada` URL contract (`plugins/tpvmod/controller/tpvmod.php:206` pin) | prior design §AD-4 |
| **AD-7** | Single-row singleton (`name='default'`, `UNIQUE (name)`) | prior design §AD-7 |
| **AD-11** | Adapter getter convention (declare on interface; default in abstract; override only where needed) | prior design §AD-11 |
| **AD-12** | `RelatedModelsLoader` for cross-model joins | prior design §AD-12 |

### Removed (no longer applicable under the new engine)

| # | Prior rule | New rule |
|---|------------|----------|
| **AD-10** | Distinctive render signal via `data-*` HTML tokens | **REMOVED.** The Twig template is gone; HTML tokens have no consumer. The new convention is AD-10-new (extracted text + raw color hex + Cezpdf draw-call spy). |
| **AD-13** | 14 text-block partials in Twig | **REMOVED.** The 14 text-block positions are now 14 Cezpdf draw branches in `Lib/PDF/PortedPdfDocument.php::render()`. Same position modes (`posiciontexto{1,2}` ∈ {1..7}); same visual effect; different mechanism. |

### Superseded (explicit override of the prior design)

| # | Prior rule | New rule |
|---|------------|----------|
| **AD-5** | mpdf HTML/CSS (LOW fidelity). Visual intentionally NOT pixel-parity with upstream Cezpdf. | **SUPERSEDED.** Cezpdf pixel-parity (HIGH fidelity). The rendered PDF MUST byte-match the upstream `FacturaPDF1` layout, modulo timestamp metadata. The new `GoldenPdfTest` is the enforcement mechanism; `REGENERATE_FIXTURE=1` is the operator escape hatch for intentional fixture updates. |
| **AD-6** | Structural-fidelity `GoldenPdfTest` (smalot/pdfparser + `data-*` token assertions) | **SUPERSEDED.** Strict byte-equality `GoldenPdfFixtureTest` (`assertSame($fixtureBytes, $renderedBytes)`). Smalot still KEPT for the rewritten `SettingsEffectCoverageTest`. |
| **AD-8** | 1 `pdf.html.twig` + 6 partials + 14 text-block partials + 1 macro | **SUPERSEDED.** The Twig template tree is **REMOVED**. New structure: 1 ported class `Lib/PDF/PortedPdfDocument.php` (1117 LoC) + 1 helper class `Lib/PDF/AbstractPdfDocument.php` (250 LoC) + 0 Twig files. |
| **AD-9** | `SettingsEffectCoverageTest` over `data-*` HTML tokens | **SUPERSEDED.** The 28 data-provider cases are REWRITTEN to assert against Cezpdf output (extracted text for booleans/numbers; raw byte scan for color hex; spied Cezpdf draw calls for per-feature invocation). |

### New (introduce this change)

| # | Title | Choice | Rationale |
|---|-------|--------|-----------|
| **AD-14** | **Engine swap strategy** | The new render path is `CezpdfRenderService` (replaces `PdfRenderService`). The service constructs a `Cezpdf` instance, injects it into a `PortedPdfDocument` along with the `PrintableDocumentInterface` and `SettingsService::load()` result, and calls `$port->render()` to populate the PDF. Output is `$pdf->ezOutput()`. The service exposes the same public API (`render`, `renderHtml`, `save`) so the controller swap is a 1-line change. | Public-API compatibility keeps the blast radius at one controller line; the upstream-port logic stays inside the port class. |
| **AD-15** | **`PortedPdfDocument` is standalone** | The new class is **not abstract** and **does not extend any parent**. The methods the upstream relied on the missing FS2025 parent for are inlined either as private methods on the port or moved into the new `AbstractPdfDocument` helper (AD-16). | The previous cycle's attempts to keep a thin parent failed (the upstream assumed a thick FS2025 parent with `i18n`, `format`, `tools`, `BusinessDocument` wiring). Going standalone makes the port self-describing. |
| **AD-16** | **`AbstractPdfDocument` is the missing-parent shim** | A 250-LoC helper class provides: `getTaxesRows`, `getCountryName`, `getDivisaName`, `removeEmptyCols`, `addImageFromFile`, `addImageFromAttachedFile`, `i18n->trans` (via injected `FSTranslator`), `format->idlogo/titulo/texto` (via injected `FormatoDocumento` value object), `tableWidth`, `insertedHeader`, `getFileName`, `newLine`. The `PortedPdfDocument` extends it for shared state, but nothing else extends `AbstractPdfDocument`. | Single point of change for the "FS2025 parent surface"; the port is testable without a real PDF document. |
| **AD-17** | **Strict byte-equality with `REGENERATE_FIXTURE=1` escape hatch** | `tests/Regression/GoldenPdfFixtureTest::testByteEquality` compares the Cezpdf-rendered PDF against `tests/Fixtures/legacy_invoice_FACT20260001.pdf` using `assertSame($fixtureBytes, $renderedBytes)`. The fixture is generated once in PR-1 by `tests/Fixtures/generate_legacy_fixture.php`. The `REGENERATE_FIXTURE=1` env var rewrites the fixture for intentional updates; the test output indicates when regeneration happens. | The strongest possible regression gate: any arithmetic mistake, any Cezpdf version change, any seed-data tweak breaks the test loudly. The escape hatch is the only way the test goes back to green. |
| **AD-18** | **`pipe()` is a no-op; `QRimg()` is preserved but not invoked** | The 4 `pipe('qrImageHeader', $model)` calls in the upstream (from `ExtensionsTrait`) are replaced with a private `pipe($hook, $model): string` method that always returns `''`. The `QRimg()` method (lines 1048–1115 of the upstream) is preserved as a self-contained method on the port — it has real Cezpdf draw code — but is never invoked unless an extension registers a QR provider. Verifactu stays deferred to a future SDD. | Keeps the port verbatim on the draw path (no need to remove a 67-line method) while making the QR-pipeline integration opt-in. |
| **AD-19** | **TRUE HTTP integration test** | `tests/Integration/RealHttpEndpointTest.php` hits `http://localhost/index.php?page=factura_detallada&id=N` via the actual HTTP stack (Symfony `HttpClient` or `file_get_contents()` inside `ddev exec`). The test is RED in PR-3 and goes GREEN once the engine swap is complete. Requires `ddev` running. | Closes the test-bypass gap that let the tpvmod URL helper issue slip through in the previous cycle: `Request::create()` does not exercise the actual entrypoint, the `$_GET` superglobal, or the `page=` routing table. |
| **AD-10-new** | **Distinctive render signal convention (replaces prior AD-10)** | Each of the 28 settings is asserted against the Cezpdf PDF output via one of three signals: (1) text presence via `smalot/pdfparser` for booleans/numbers/strings; (2) raw-byte hex scan for `colorcabecera` and the 3 color settings; (3) spied Cezpdf draw-call invocation (counter on `$pdf->ezText`/`$pdf->ezTable`/`$pdf->line`) for layout-mode settings. The convention is documented in the test class docblock. | A signal must exist for every setting; the test class is the spec for which signal applies to which key. |

## Data flow

**Public endpoint with Cezpdf engine (replaces the prior data flow):**

```
HTTP GET /index.php?page=factura_detallada&id=N
   |
   v
controller/factura_detallada.php (29-line shim) -> Controller\FacturaPdf1Controller::processRequest()
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
Services\CezpdfRenderService::render(view, settings)
   |  -- $pdf = new Cezpdf('a4', 'portrait')
   |  -- $port = new Lib\PDF\PortedPdfDocument($pdf, $view, $settings, $translator, $formatter, $locale, $formats, $loader)
   |  -- $port->render()                 <-- verbatim upstream logic, Cezpdf draw calls
   |  -- return $pdf->ezOutput()         <-- PDF binary (bytes)
   v
Symfony Response(application/pdf, body=%PDF-...)
   |
   v
GoldenPdfFixtureTest::testByteEquality()  assertSame($fixtureBytes, $renderedBytes)
```

**Settings effect via Cezpdf draw-call spy (replaces the prior `data-*` flow):**

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
   -> PortedPdfDocument::render() draws with new color/position/font-size
   -> Rewritten SettingsEffectCoverageTest (AD-10-new) asserts:
        * extracted text (smalot/pdfparser) for boolean/number settings
        * raw byte hex scan for color settings
        * spy counter on Cezpdf draw calls for layout-mode settings
```

## File changes

### NEW (~16 files, ~2 100 LoC + 850 KB vendored)

| File | LoC | Description |
|------|-----|-------------|
| `Lib/PDF/AbstractPdfDocument.php` | 250 | AD-16: missing-parent shim. `getTaxesRows`, `getCountryName`, `getDivisaName`, `removeEmptyCols`, `addImageFromFile`, `addImageFromAttachedFile`, `tableWidth`, `insertedHeader`, `getFileName`, `newLine`, i18n + format dispatch. |
| `Lib/PDF/PortedPdfDocument.php` | 1117 | AD-15: verbatim port of `plugins/FacturaPDF1/Lib/PDF/PDFDocument.php` (1117 LoC, `~90 %` byte-identical, ~117 lines of FS2025-dependency replacements). |
| `Services/CezpdfRenderService.php` | 100 | AD-14: same public API as `PdfRenderService` (`render`, `renderHtml`, `save`); constructs Cezpdf, injects into port, calls `ezOutput()`. |
| `Services/FormatoDocumento.php` | 30 | Value object (`idlogo`, `titulo`, `texto`); replaces upstream `$this->format->X`. |
| `Services/PdfNumberFormatter.php` | 20 | `format(float $n): string` respecting locale; replaces `Tools::number()`. |
| `Services/LocaleSettings.php` | 40 | Reads `default/decimal_separator`, `default/thousands_separator`, `default/idempresa`; replaces `Tools::settings('default', ...)`. |
| `vendor/cezpdf/Cezpdf.php` | 2036 (88 KB) | Vendored from `plugins/facturacion_base/extras/ezpdf/Cezpdf.php`. |
| `vendor/cezpdf/Cpdf.php` | 3907 (161 KB) | Vendored from `plugins/facturacion_base/extras/ezpdf/Cpdf.php`. |
| `vendor/cezpdf/fonts/*.afm` | 12 files (608 KB) | 12 AFM fonts (Courier + Helvetica + Times). |
| `tests/Fixtures/generate_legacy_fixture.php` | 30 | One-off script: renders the seed invoice via Cezpdf and writes `legacy_invoice_FACT20260001.pdf`. |
| `tests/Fixtures/legacy_invoice_FACT20260001.pdf` | binary | The byte-equality fixture. |
| `tests/Unit/CezpdfRenderServiceTest.php` | 60 | RED in PR-1; asserts the new service exists, has the public API, and fails fast when Cezpdf vendor is missing. |
| `tests/Unit/AbstractPdfDocumentTest.php` | 200 | RED in PR-1; asserts each helper method (`getTaxesRows`, `getCountryName`, `getDivisaName`, `removeEmptyCols`, etc.). |
| `tests/Unit/CezpdfRenderFeatureTest.php` | 400 | RED in PR-2; replaces `RenderFeatureTest`; 19 feature-level scenarios asserted against Cezpdf output. |
| `tests/Unit/AddressSplitTest.php` | 80 | RED in PR-2; replaces `AddressSplitMacroTest`; 2 scenarios for the address-split logic. |
| `tests/Regression/GoldenPdfFixtureTest.php` | 50 | RED in PR-1; asserts the fixture is a valid PDF. PR-2 adds `testByteEquality`. |
| `tests/Integration/RealHttpEndpointTest.php` | 80 | NEW in PR-3; AD-19. Hits the real HTTP endpoint via `ddev exec`. |

### MODIFY (~10 files)

| File | Description |
|------|-------------|
| `composer.json` | Drop `mpdf/mpdf ^8.0`; add Cezpdf autoload entry. |
| `composer_autoload.php` | Add `require_once vendor/cezpdf/Cezpdf.php`. |
| `Init.php` | Drop `registerTwigPaths()` call; keep `runSettingsUpgrade()`. |
| `Controller/FacturaPdf1Controller.php` | Swap `PdfRenderService` → `CezpdfRenderService` (~5 LoC). |
| `Model/PrintableDocumentInterface.php` | Add 4 new getters (`getModelClassName`, `getCodigoRect`, `getObservaciones`, `getLines`). |
| `tests/Integration/PublicEndpointTest.php` | Update expected PDF content for the new engine. |
| `tests/Regression/GoldenPdfTest.php` | REWRITE as byte-equality (`GoldenPdfFixtureTest::testByteEquality`). |
| `tests/Unit/SettingsEffectCoverageTest.php` | REWRITE the 28 data-provider cases against Cezpdf output (~250 LoC replacement). |
| `phpunit.xml` | Register the new test files. |
| `README.md` | Update engine section (mpdf → Cezpdf). |

### REMOVE (~25 files + 94 MB)

| File | LoC | Description |
|------|-----|-------------|
| `Services/PdfRenderService.php` | 180 | Replaced by `CezpdfRenderService`. |
| `view/factura_pdf1/pdf.html.twig` | 64 | Twig template tree removed. |
| `view/factura_pdf1/partials/_*.html.twig` (×19) | ~570 | 19 text-block + structural partials removed. |
| `view/factura_pdf1/macro/address.html.twig` | ~20 | Address-split macro removed (logic moves to Cezpdf branch). |
| `view/factura_pdf1/` directory | — | Empty after removals. |
| `tests/Unit/AddressSplitMacroTest.php` | — | Replaced by `tests/Unit/AddressSplitTest.php`. |
| `tests/Unit/RenderFeatureTest.php` | — | Replaced by `tests/Unit/CezpdfRenderFeatureTest.php`. |
| `tests/Unit/PdfRenderServiceTest.php` | — | Replaced by `tests/Unit/CezpdfRenderServiceTest.php`. |
| `vendor/mpdf/**` | 94 MB | No longer needed. |
| `vendor/myclabs/`, `vendor/paragonie/`, `vendor/psr/`, `vendor/symfony/` (mpdf transitive), `vendor/setasign/` | — | mpdf transitive deps; removed. `vendor/smalot/**` (476 KB) is KEPT for PDF text extraction in tests. |

### KEEP (engine-independent)

- 4 adapters (`Model/Adapters/{Factura,Albaran,Pedido,Presupuesto}ClienteAdapter.php`) + `AbstractClienteDocumentAdapter.php`
- All `Model/View/*` classes, `Model/{Contacto,ReciboCliente,FacturaPdf1Setting}.php`
- All controllers (`Controller/FacturaPdf1Controller.php` line-swap only; `controller/{factura_detallada,admin_factura_pdf1}.php`; `Controller/Admin/*`)
- `Services/{SettingsService,RelatedModelsLoader,EmpresaLogoResolver,SettingsFormBinder,SettingsValidator}.php`
- `translations/messages.{es,en}.yaml`
- `themes/AdminLTE/view/admin/factura_pdf1/settings.html.twig`

**Hard rule (per AGENTS.md "OpenSpec per Plugin"): `base/`, `src/`, `controller/` (root), `model/` (root), parent `openspec/`, and any other plugin remain UNTOUCHED.**

## Chained-PR migration plan (3 PRs, stacked-to-main)

### PR-1 — foundation (~400 LoC, in budget)
**Goal**: vendor Cezpdf + create the helper + small services + RED tests + byte-equality fixture.

1.1. Vendor Cezpdf: copy `Cezpdf.php` (88 KB) + `Cpdf.php` (161 KB) + 12 AFM fonts (608 KB) from `plugins/facturacion_base/extras/ezpdf/` to `plugins/factura_pdf1/vendor/cezpdf/`. Total ~850 KB.
1.2. MODIFY `composer.json` to drop `mpdf/mpdf ^8.0` and add Cezpdf autoload entry (`"files": ["vendor/cezpdf/Cezpdf.php"]`).
1.3. MODIFY `composer_autoload.php` to `require_once` Cezpdf.
1.4. MODIFY `Init.php` to drop `registerTwigPaths()` (keep `runSettingsUpgrade()`).
1.5. REMOVE `vendor/mpdf/**` + transitive deps (`myclabs`, `paragonie`, `psr`, `symfony`, `setasign`). KEEP `vendor/smalot/**`.
1.6. NEW `Lib/PDF/AbstractPdfDocument.php` (250 LoC, AD-16).
1.7. NEW `Services/FormatoDocumento.php` (30 LoC) + `Services/PdfNumberFormatter.php` (20 LoC) + `Services/LocaleSettings.php` (40 LoC).
1.8. NEW `tests/Fixtures/generate_legacy_fixture.php` (30 LoC).
1.9. NEW `tests/Fixtures/legacy_invoice_FACT20260001.pdf` (binary, generated by 1.8).
1.10. RED `tests/Unit/CezpdfRenderServiceTest.php` (60 LoC): asserts the new service exists + fails fast on missing vendor.
1.11. RED `tests/Unit/AbstractPdfDocumentTest.php` (200 LoC): asserts each helper method.
1.12. RED `tests/Regression/GoldenPdfFixtureTest.php` (50 LoC): asserts the fixture is a valid PDF.
1.13. GREEN: all RED tests pass; PHPStan 0 errors; commit without `size:exception` (within 400 LoC budget).

### PR-2 — engine swap (~1 200 LoC, `size:exception` required)
**Goal**: port the upstream `PDFDocument.php` to standalone + wire it into the service + remove the Twig template.

2.1. NEW `Lib/PDF/PortedPdfDocument.php` (1117 LoC, ~90% verbatim from upstream, ~117 lines of FS2025-dependency replacements — `Where::eq` → `loadWhere`, `$this->i18n->trans` → `$this->translator->trans`, `$this->format->X` → `$this->formats->X`, `Tools::number()` → `$this->numberFormatter->format()`, `Tools::settings()` → `LocaleSettings::get()`, `pipe()` → no-op stub, `BusinessDocument` → `PrintableDocumentInterface`).
2.2. NEW `Services/CezpdfRenderService.php` (100 LoC, AD-14).
2.3. MODIFY `Controller/FacturaPdf1Controller.php` (swap service, ~5 LoC).
2.4. MODIFY `Model/PrintableDocumentInterface.php` (add 4 getters, ~30 LoC).
2.5. REMOVE `Services/PdfRenderService.php` (180 LoC).
2.6. REMOVE `view/factura_pdf1/pdf.html.twig` (64 LoC).
2.7. REMOVE 19 text-block + structural partials (~570 LoC).
2.8. REMOVE `view/factura_pdf1/macro/address.html.twig` (~20 LoC).
2.9. REMOVE `view/factura_pdf1/` directory.
2.10. NEW `tests/Unit/AddressSplitTest.php` (80 LoC, replaces `AddressSplitMacroTest`).
2.11. NEW `tests/Unit/CezpdfRenderFeatureTest.php` (400 LoC, replaces `RenderFeatureTest`).
2.12. REMOVE `tests/Unit/{AddressSplitMacroTest,RenderFeatureTest,PdfRenderServiceTest}.php`.
2.13. MODIFY `tests/Integration/PublicEndpointTest.php` (update expected PDF content).
2.14. GREEN: `tests/Regression/GoldenPdfFixtureTest::testByteEquality` passes (port produces same bytes as fixture).
2.15. GREEN: 19 feature tests pass; existing adapter/admin/endpoint tests pass.
2.16. PHPStan 0 errors; commit with `size:exception` justification (~1200 LoC of mostly the verbatim 1117-line port + minimum viable code to GREEN).

### PR-3 — cleanup + TRUE HTTP integration test (~400 LoC, `size:exception` recommended)
**Goal**: rewrite `SettingsEffectCoverageTest` to assert against Cezpdf output; add the TRUE HTTP integration test.

3.1. REWRITE `tests/Unit/SettingsEffectCoverageTest.php` to assert against Cezpdf output (text extraction via `smalot/pdfparser` for booleans/numbers, raw-byte hex scan for colors, spied Cezpdf draw-call invocation per feature; ~250 LoC replacement).
3.2. NEW `tests/Integration/RealHttpEndpointTest.php` (80 LoC, AD-19; hits `http://localhost/index.php?page=factura_detallada&id=N` via the actual HTTP stack; requires `ddev` running).
3.3. MODIFY `phpunit.xml` to register the new test files.
3.4. MODIFY `README.md` to update the engine section (mpdf → Cezpdf).
3.5. GREEN: all tests pass (including the rewritten `SettingsEffectCoverageTest`, 28/28 GREEN).
3.6. GREEN: the TRUE HTTP integration test hits the endpoint and asserts a valid PDF.
3.7. PHPStan 0 errors; commit with `size:exception` recommendation.

## Test strategy (per PR, Strict TDD)

| PR | RED tests written | GREEN work | Verification |
|----|-------------------|------------|--------------|
| **PR-1** | `CezpdfRenderServiceTest` (60) + `AbstractPdfDocumentTest` (200) + `GoldenPdfFixtureTest` (50) | Vendor Cezpdf + `AbstractPdfDocument` + 3 small services + fixture generation script + fixture binary | `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml` — RED first (all 3 fail), GREEN after. PHPStan 0 errors. |
| **PR-2** | `AddressSplitTest` (80) + `CezpdfRenderFeatureTest` (400) | `PortedPdfDocument` (1117 LoC port) + `CezpdfRenderService` (100) + controller swap + Twig tree removal + interface extension | All RED tests pass. `GoldenPdfFixtureTest::testByteEquality` is GREEN (port produces same bytes as fixture). 19 feature scenarios GREEN. PHPStan 0 errors. |
| **PR-3** | REWRITE `SettingsEffectCoverageTest` (28 cases rewritten) + NEW `RealHttpEndpointTest` | None (test rewrites only; one new test; README + phpunit.xml updates) | All 28 rewritten cases GREEN. `RealHttpEndpointTest` GREEN (requires `ddev` running). PHPStan 0 errors. |

**TDD discipline**: every task in PR-1, PR-2, and PR-3 has its test written FIRST (RED) and the implementation SECOND (GREEN). No exceptions.

## Risk matrix

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| **Engine-swap bug surface** (1117 lines of layout math to port verbatim) | MEDIUM-HIGH | HIGH — any arithmetic mistake shifts the layout | Byte-equality test (`GoldenPdfFixtureTest::testByteEquality`) catches it immediately; PR-2 has a tight RED→GREEN cycle |
| **Byte-equality test fragility** (breaks on any Cezpdf version change, any seed-data tweak, any timestamp drift) | MEDIUM | MEDIUM — test goes RED on benign changes | `REGENERATE_FIXTURE=1` env var for intentional updates; documented workflow in the test; Cezpdf 0.11.6 is vendored so version is pinned |
| **Cezpdf unmaintained** (last release 0.11.6) | LOW (PHP 8.3 still works) | HIGH if a future PHP breaks it | Vendored with the plugin; any patches can be applied locally; AGENTS.md "vendor/ MUST be committed" makes patches part of the shipping artifact |
| **License compatibility** (Cezpdf public-domain + plugin LGPL-3.0 + upstream LGPL-3.0) | LOW | LOW | All permissive + LGPL; verified in the explore report §I |
| **Discarded work** (~1 000 LoC of Twig + 19 partials + macro + 3 tests) | HIGH (it WILL happen) | HIGH (opportunity cost) | Acknowledged in Engram #386; the user's explicit choice; PR-3 documents the discarded work in `archive-report.md` |
| **`data-*` token convention gone** (only the previous cycle's `SettingsEffectCoverageTest` was the consumer) | HIGH | LOW | Rewritten in PR-3 against Cezpdf output; old test is removed in PR-2 |
| **tpvmod URL contract regression** (`?page=factura_detallada&id=N`) | LOW | HIGH | `TpvmodUrlPinTest` already exists; PR-3 adds `RealHttpEndpointTest` (AD-19) for true HTTP coverage |
| **Multi-document regression** (4 doc types) | LOW | HIGH | `AdapterGettersTest` + `ClienteDocumentAdapterTest` + new `CezpdfRenderFeatureTest` cover the path |
| **Settings persistence regression** (`factura_pdf1_settings` table) | LOW | HIGH | `SettingsServiceTest` + `InitUpgradeTest` cover persistence; engine-independent |
| **PDF binary size / parse time** (Cezpdf PDFs are larger than mpdf HTML→PDF) | MEDIUM | LOW (admin-only feature) | Acceptable; a phpunit benchmark can be added if it becomes a concern |
| **The 5 SUGGESTIONs from the prior cycle's verify-report** | LOW | LOW | Carry-forward as SUGGESTIONs in the new verify-report |

## Supersession note

This design **SUPERSEDES AD-5, AD-6, AD-8, AD-9, AD-10, and AD-13** of the prior design at `plugins/factura_pdf1/openspec/changes/archive/2026-07-21-factura-pdf1-render-fidelity/design.md`. AD-1, AD-2, AD-3, AD-4, AD-7, AD-11, AD-12 of the prior design are **KEPT** (no change). The prior design stays as historical record. For render-fidelity decisions, this design is the authoritative document.

**Mapping summary:**

| Prior AD | New rule |
|----------|----------|
| AD-1, AD-2, AD-3, AD-4, AD-7, AD-11, AD-12 | KEPT (no change) |
| AD-5 (mpdf LOW fidelity) | SUPERSEDED by AD-14 + AD-15 + AD-16 (Cezpdf HIGH fidelity) |
| AD-6 (structural golden) | SUPERSEDED by AD-17 (byte-equality) |
| AD-8 (Twig template tree) | SUPERSEDED (template tree REMOVED; ported class + helper class) |
| AD-9 (`data-*` settings effect) | SUPERSEDED (rewritten in PR-3 against Cezpdf output) |
| AD-10 (`data-*` HTML tokens) | REMOVED (no consumer); replaced by AD-10-new (PDF signals) |
| AD-13 (14 text-block partials) | REMOVED (replaced by 14 Cezpdf draw branches) |

## Handoff to sdd-tasks

`sdd-tasks` will:

1. Translate the PR-1 (13 tasks), PR-2 (16 tasks), and PR-3 (7 tasks) lists above into **36 task rows** in `plugins/factura_pdf1/openspec/changes/factura-pdf1-czpdf-pixel-parity/tasks.md`.
2. Add a **TDD Cycle Evidence table** (RED / GREEN / TRIANGULATE / REFACTOR per task) — Strict TDD requirement.
3. Lock the chained-PR forecast with `size:exception` per PR (PR-1 in budget; PR-2 `size:exception` required; PR-3 `size:exception` recommended).
4. Forecast **~2 100 LoC** across 3 PRs (PR-1 ~400, PR-2 ~1 200, PR-3 ~400) + 850 KB vendored Cezpdf tree.
5. Pin the hybrid persistence: `tasks.md` lives at `plugins/factura_pdf1/openspec/changes/factura-pdf1-czpdf-pixel-parity/tasks.md`; engram `topic_key: sdd/factura-pdf1-czpdf-pixel-parity/tasks`.
6. Write the 5 SUGGESTIONs from the prior verify-report (not in scope here) as forward SUGGESTIONs in the new verify-report.
7. Confirm the `pipe()` no-op + `QRimg()` kept-on-disk + Verifactu deferred decision (AD-18) is recorded as an explicit design constraint in the tasks.
8. Add a `vendor/` commit checklist (per `fsframework-plugin-sdd` skill "Dependency Commits in Plugin SDDs"): PR-1 must `git add vendor/cezpdf/` and commit.

Implementation does not begin until the user explicitly approves the chained-PR strategy and the byte-equality strictness (locked in the explore round: STRICT byte-for-byte with `REGENERATE_FIXTURE=1` env var escape hatch).
