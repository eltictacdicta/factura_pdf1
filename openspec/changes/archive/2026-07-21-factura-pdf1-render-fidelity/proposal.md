# Proposal: Bring upstream FacturaPDF1 render fidelity to `plugins/factura_pdf1/`

## Intent

The closed change `adapt-factura-pdf1-to-fsframework` (archived 2026-07-21;
verify-report verdict: `pass_with_warnings`, 62/62 tests, 205 assertions
GREEN; PHPStan 24/24 GREEN) shipped the **infrastructure** of the new
plugin: composer, `factura_pdf1_settings` JSON table, 4-adapter
polymorphism, mpdf + Twig pipeline, public endpoint, admin page, settings
coverage test, security greps, tpvmod URL pin, init-upgrade path.

It did **not** ship upstream render fidelity. The post-archive audit (Engram
obs #367, 2026-07-21) read the 4 adapters, 9 partials, `SettingsService`,
`PdfRenderService`, admin template, and the upstream
`plugins/FacturaPDF1/Lib/PDF/PDFDocument.php` (1117 LoC). Two structural
gaps surfaced:

1. **27 of 28 persisted settings are dead.** A grep across all templates and
   services shows exactly one match for any setting key
   (`colorcabecera` → `accent_color` in `_line_items.html.twig` line 5 and
   `_vat_breakdown.html.twig` line 5). The new template is a structural
   clone of the legacy `factura_detallada` shape; the user explicitly
   complained it "looks the same as factura_detallada" and "only prints
   invoices." The verify-report's spec ("every setting has a widget")
   passed, but the spec was too narrow — it required a UI, not an effect on
   the rendered PDF. **Lesson locked**: a render-fidelity spec must assert
   "setting X changes the rendered output," not just "admin has widget X."
2. **`pedido` and `presupuesto` are not covered end-to-end.** The prior
   `PublicEndpointTest` only asserts `factura` and `albaran`. The user's own
   verify-report flagged this as SUGGESTION #2 and the audit identified it
   as the most likely site of a runtime defect the unit-only coverage
   could not catch.

This change delivers a **real `FacturaPDF1` port**, not a wrapper: the 27
dead settings become live render knobs, the 2 missing endpoint tests are
added (and any defect they expose is fixed), and 3 dead partials are
removed.

## Scope

### In Scope (22 deliverables)

**A. 19 render features** (each maps one or more dead settings to a real
render path):

| # | Feature | Settings (currently dead) | New code path |
|---|---------|---------------------------|---------------|
| 1 | Logo with 4-position selector + margin + measure | `posicionlogo`, `margenlogo`, `medidalogo` | Twig selector in `_parties_header.html.twig`; `PdfRenderService` reads settings and passes to template |
| 2 | Color-coded header rows + alternating row shading | `colorfilas`, `espaciofilas` | CSS in `_line_items.html.twig`; `espaciofilas` as `padding` |
| 3 | `pagoyvencimiento` mode selector (4 modes) | `pagoyvencimiento` | `ReciboCliente` model load in adapter; Twig branching in `_payment_footer.html.twig` |
| 4 | IBAN injection (domiciled → cliente IBAN, else empresa) | `traducirformaspago` | `cuenta_banco_cliente` / `cuenta_banco` model load in adapter; Twig in `_payment_footer.html.twig` |
| 5 | Carrier block (codtrans + codigoenv) | (no setting) | `AgenciaTransporte` model load; Twig |
| 6 | Shipping address block (idcontactoenv) | `ocultardireccionenvio` | `Contacto` model load; Twig (gated by setting) |
| 7 | Related documents block (parentDocuments walk + dedup) | `documentosrelacionados` | `parentDocuments()` call in adapter; Twig; dedup logic |
| 8 | Warehouse block (mostraralmacen 4 modes + titulo + tel) | `mostraralmacen`, `tituloalmacen`, `mostraralmacentel` | `Almacen` model load; Twig |
| 9 | texto1 block (7 position modes + medida + color + justif) | `posiciontexto1`, `medidatexto1`, `colortexto1`, `justiftexto1` | Twig + CSS for 7 positions; content = `FormatoDocumento->texto` |
| 10 | texto2 block (7 position modes + medida + color + justif + free text) | `posiciontexto2`, `medidatexto2`, `colortexto2`, `justiftexto2`, `texto2` | Twig + CSS + admin textarea |
| 11 | Hide-product-reference toggle | `ocultarreferenciaprod` | Twig conditional in `_line_items.html.twig` |
| 12 | Auto-collapse tax table when 1 or 2 taxes share the net | `ocultartablaimpuestos` | PHP logic in `*PrintView::build()`; Twig conditional in `_vat_breakdown.html.twig` |
| 13 | Hide province / hide country | `ocultarprovincia`, `ocultarpais` | Twig conditional in `_parties_header.html.twig` |
| 14 | `ref2` (custom second customer reference, 3 modes) | `ref2` | Twig in `_parties_header.html.twig` (modes 0/1/2) |
| 15 | Max-company-width (espaciomaximoempresa) | `espaciomaximoempresa` | CSS in `_parties_header.html.twig` |
| 16 | Page numbering footer | (no setting) | mpdf `SetFooter('{PAGENO} / {nbpg}')` in `PdfRenderService` |
| 17 | Per-tipo titulo from `FormatoDocumento` | (no setting) | Override `getDocumentTypeLabel()` in `*PrintView` to read `formato_documento->titulo` (fallback to current literal) |
| 18 | Address splitting at parens when over `PARTIR_DIR` width | (no setting) | Twig macro `_address_split` in `view/factura_pdf1/macro/address.html.twig` (new) |
| 19 | Auto-shrink company name to fit width | (no setting) | CSS `font-size: clamp()` + `text-overflow` in `_parties_header.html.twig` |

**B. 2 missing integration tests** (SUGGESTION #2 from prior
verify-report, now in scope per user product decision):
- `PublicEndpointTest::testEndpointStreamsPdfForSeededPedido`
- `PublicEndpointTest::testEndpointStreamsPdfForSeededPresupuesto`
Tests are RED in PR-1; runtime defects they expose are fixed in PR-1
or PR-2 depending on size.

**C. 3 dead partials cleanup** (placeholders, included by `pdf.html.twig`):
- REMOVE `view/factura_pdf1/partials/_client_billing.html.twig` (content
  inlined in `_parties_header.html.twig`)
- REMOVE `view/factura_pdf1/partials/_company_header.html.twig` (same)
- REMOVE `view/factura_pdf1/partials/_invoice_number_date.html.twig` (same)
- EDIT `view/factura_pdf1/pdf.html.twig` to drop the 3 `{% block %}` stubs
  and the `{% include %}` of the empty `_corporate_image.html.twig`
  (kept on disk as Verifactu placeholder, per locked decision).

### Out of Scope

- **Verifactu QR block** (deferred to a separate `QrForVerifactuService`
  SDD; `_corporate_image.html.twig` stays as empty placeholder).
- **3 supplier document types** (`FacturaProveedor`, `AlbaranProveedor`,
  `PresupuestoProveedor` — not in upstream port scope).
- **22 non-ES/EN translation locales** (24-locale parity is a follow-up).
- **Physical removal of `plugins/FacturaPDF1/`** and **deprecation of
  `plugins/factura_detallada/`** (the 4.2 follow-up from the prior
  change; still deferred).
- **Parent-repo `.gitignore` whitelist fix** for `plugins/factura_pdf1/**`
  (a core concern; not in this plugin's SDD).
- **The other 4 SUGGESTIONs from the prior verify-report** (SUGGESTIONs
  #1, #3, #4, #5: render-module-isolation grep, full-column schema
  assertion, no-fs_var grep, NumberFormatter warning root cause). These
  become SUGGESTIONs in the new verify-report; the 4-of-5 SUGGESTION #2
  (pedido/presupuesto endpoint) is in scope per the user's decision.

## Capabilities (delta vs the 5 source-of-truth specs)

### Modified Capabilities
- **`invoice-pdf-rendering`**: 19 new spec scenarios, one per row of the
  features table above. Each scenario asserts (a) the setting is read
  from `SettingsService::load()`, (b) the rendered HTML contains a
  distinctive token (CSS class, text content, or mpdf metadata) that
  proves the setting had an effect. **Lesson applied from the audit**:
  the assertion is on render output, not on admin UI.
- **`invoice-pdf-settings`**: existing scenario "Every setting has a
  widget" is RETIRED in favor of "Every persisted setting has at least
  one effect on the rendered PDF" — asserted by a new
  `SettingsEffectCoverageTest` that iterates `UPSTREAM_SETTING_KEYS` and
  confirms each key appears in the rendered HTML of at least one
  scenario.
- **`invoice-pdf-adapters`**: 5 new spec scenarios for the new model
  loads: `Contacto`, `Almacen`, `cuenta_banco*`, `AgenciaTransporte`,
  `ReciboCliente`. Each adapter that touches the new model is asserted
  to load it and expose the data through a new
  `ClientDocumentPrintViewInterface` getter.
- **`invoice-pdf-public-endpoint`**: 2 new spec scenarios (pedido +
  presupuesto), parallel to the existing `testEndpointStreamsPdfForSeededAlbaran`.
- **`invoice-pdf-admin`**: 1 new spec scenario — the admin textarea for
  `texto2` is editable, persists, and round-trips through
  `factura_pdf1_settings`.

### New Capabilities
None. All five baseline specs exist and are extended; no new capability
file is needed.

## Approach

**Render features** (1–15, 17–19): Twig conditionals + CSS in the existing
9 partials; no new template files except a single shared macro
`view/factura_pdf1/macro/address.html.twig` (used by `feature #18`).
`PdfRenderService` reads the 28 settings and passes them as a single
`settings` array to Twig (already done for `colorcabecera`; extend to
the other 27).

**Model loads** (1, 3, 4, 5, 6, 8, 17): PHP service `RelatedModelsLoader`
(already exists at `Model/View/RelatedModelsLoader.php`) extended with
5 new `load*()` methods: `loadAlmacen`, `loadContactoEnvio`,
`loadCuentaBancaria`, `loadAgenciaTransporte`, `loadRecibos`. Each
adapter (`AbstractClienteDocumentAdapter`) calls the relevant
`load*()` in its constructor and exposes the result through a new
getter on the interface.

**Page numbering** (16): one line in `PdfRenderService::render()` —
`$mpdf->SetFooter('{PAGENO} / {nbpg}')` before `WriteHTML`.

**Per-tipo titulo** (17): override `getDocumentTypeLabel()` in
`FacturaPrintView`, `AlbaranPrintView`, `PedidoPrintView`,
`PresupuestoPrintView` to call `formato_documento->titulo` first
(returning `null` → fallback to current literal).

**TDD cycle** (Strict TDD, per `config.yaml: strict_tdd: true`): RED
test in PR-1 for each feature (asserting the rendered HTML contains a
distinctive token per setting), GREEN in PR-1 or PR-2 depending on
size, REFACTOR in PR-2.

**Chained PR split** (per cached preflight `delivery_strategy:
force-chained`, `review_budget_lines: 400`, `size:exception` per PR):

- **PR-1 — settings effects + pedido/presupuesto coverage**
  (~600 LoC, requires `size:exception`): `SettingsService` (no
  change), 19 new scenario tests in
  `tests/Unit/RenderFeatureTest.php` (RED); 2 new endpoint tests
  in `tests/Integration/PublicEndpointTest.php` (RED); minimal
  Twig + PHP edits to make them GREEN. Includes the 3 dead
  partial REMOVEs (small). **Standalone.**
- **PR-2 — full upstream fidelity pass** (~800 LoC, requires
  `size:exception`): `RelatedModelsLoader` extensions,
  `getDocumentTypeLabel()` overrides, address-splitting macro,
  `SetFooter`, the 2 dead-partial tests in PDF regression
  (`GoldenPdfTest` updated), 4 new `*PrintView` getters, full
  `tests/Unit/AdapterExtensionsTest.php` triangulation. **Requires
  PR-1 on the target branch.**

The orchestrator will confirm this 2-PR split (or re-forecast to 3
smaller PRs) before `sdd-tasks` locks the final shape. No
implementation begins until the user has explicitly chosen the
chained-PR strategy.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `plugins/factura_pdf1/view/factura_pdf1/partials/_parties_header.html.twig` | MODIFY | Logo (1), hide-province (13), ref2 (14), max-width (15), auto-shrink (19) — 7 new feature blocks |
| `plugins/factura_pdf1/view/factura_pdf1/partials/_line_items.html.twig` | MODIFY | colorfilas + espaciofilas (2), hide-reference (11) — 2 new feature blocks |
| `plugins/factura_pdf1/view/factura_pdf1/partials/_payment_footer.html.twig` | MODIFY | pagoyvencimiento (3), IBAN (4) — 2 new feature blocks |
| `plugins/factura_pdf1/view/factura_pdf1/partials/_vat_breakdown.html.twig` | MODIFY | ocultartablaimpuestos (12) — 1 new feature block |
| `plugins/factura_pdf1/view/factura_pdf1/partials/_corporate_image.html.twig` | KEEP (empty) | Verifactu placeholder, locked out of scope |
| `plugins/factura_pdf1/view/factura_pdf1/partials/_client_billing.html.twig` | REMOVE | Dead placeholder; content inlined in `_parties_header.html.twig` |
| `plugins/factura_pdf1/view/factura_pdf1/partials/_company_header.html.twig` | REMOVE | Dead placeholder; content inlined in `_parties_header.html.twig` |
| `plugins/factura_pdf1/view/factura_pdf1/partials/_invoice_number_date.html.twig` | REMOVE | Dead placeholder; content inlined in `_parties_header.html.twig` |
| `plugins/factura_pdf1/view/factura_pdf1/pdf.html.twig` | MODIFY | Drop 3 dead `{% block %}` stubs; drop `_corporate_image` include (kept on disk) |
| `plugins/factura_pdf1/view/factura_pdf1/macro/address.html.twig` (new) | NEW | `_address_split` macro (feature 18) |
| `plugins/factura_pdf1/Services/PdfRenderService.php` | MODIFY | Read all 28 settings (line 116 today reads only `colorcabecera`); add `SetFooter` (feature 16) |
| `plugins/factura_pdf1/Model/View/RelatedModelsLoader.php` | MODIFY | 5 new `load*()` methods (Almacen, ContactoEnvio, CuentaBancaria, AgenciaTransporte, Recibos) |
| `plugins/factura_pdf1/Model/View/{Factura,Albaran,Pedido,Presupuesto}PrintView.php` | MODIFY | Override `getDocumentTypeLabel()` to read `formato_documento->titulo` (feature 17) |
| `plugins/factura_pdf1/Model/View/ClientDocumentPrintViewInterface.php` | MODIFY | Add 5 new getters (getAlmacen, getContactoEnvio, getCuentaBancaria, getAgenciaTransporte, getRecibos) |
| `plugins/factura_pdf1/Model/Adapters/AbstractClienteDocumentAdapter.php` | MODIFY | Wire the 5 new getters; populate in constructor |
| `plugins/factura_pdf1/openspec/specs/{invoice-pdf-rendering,invoice-pdf-settings,invoice-pdf-adapters,invoice-pdf-public-endpoint,invoice-pdf-admin}/spec.md` | MODIFY | Source-of-truth updates; delta specs in this change |
| `plugins/factura_pdf1/openspec/changes/factura-pdf1-render-fidelity/specs/{invoice-pdf-rendering,invoice-pdf-settings,invoice-pdf-adapters,invoice-pdf-public-endpoint,invoice-pdf-admin}/spec.md` | NEW | Delta specs |
| `plugins/factura_pdf1/tests/Unit/RenderFeatureTest.php` | NEW | 19 setting-effect scenarios (RED first) |
| `plugins/factura_pdf1/tests/Unit/AdapterExtensionsTest.php` | NEW | 4 adapters × 5 new getters = 20 triangulation cases |
| `plugins/factura_pdf1/tests/Unit/SettingsEffectCoverageTest.php` | NEW | Replaces "every setting has a widget" with "every setting has an effect" |
| `plugins/factura_pdf1/tests/Integration/PublicEndpointTest.php` | MODIFY | +2 methods (pedido, presupuesto) |
| `plugins/factura_pdf1/tests/Regression/GoldenPdfTest.php` | MODIFY | Asserts the 19 new tokens are present in the rendered PDF for `FAKT-2026-0001` |
| `plugins/factura_pdf1/translations/messages.{es,en}.yaml` | MODIFY | +5 new strings per locale: `factura-pdf1.option.new-page-before-text-1`, etc. (for the 7 position modes of texto1/texto2) |
| `plugins/FacturaPDF1/`, `plugins/factura_detallada/`, `plugins/tpvmod/`, `base/`, `src/`, `controller/` (root), `model/` (root) | UNTOUCHED | Out of scope; no core change. |
| Parent `openspec/` tree | UNTOUCHED | Per `AGENTS.md` "OpenSpec per Plugin" — this is a 100% plugin-local change. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Pedido/presupuesto endpoint tests expose a real defect in `PedidoPrintView::build()` / `PresupuestoPrintView::build()` or the Twig render path. | Medium | Tests go RED in PR-1 (per user product decision); the fix is small and lands in PR-1 or PR-2. If the defect is large (>100 LoC), orchestrator re-splits PR-2. |
| Visual regression: 27 new render knobs may break the legacy `factura_detallada` look the user complained about. | Medium | Defaults match the upstream `XMLView/SettingsInvoice.xml` defaults (preserved in `SettingsService::defaults()`); the `_line_items.html.twig` accent color stays as `colorcabecera` fallback. `GoldenPdfTest` asserts structural tokens, not bytes. |
| `RelatedModelsLoader` extension grows past the 5-`load*()` plan: the audit lists 5 model families, but a missed edge case may surface during apply. | Low | `load*()` methods are private to `RelatedModelsLoader`; the public contract is the 5 new getters on `ClientDocumentPrintViewInterface`. If a 6th model surfaces, the orchestrator files it as a follow-up, not a silent addition. |
| `getDocumentTypeLabel()` per-tipo override breaks the existing `_parties_header.html.twig` template. | Low | Override falls back to the current hardcoded literal when `formato_documento->titulo` is `null`. `GoldenPdfTest` regression catches the no-titulo case. |
| Auto-shrink company name (feature 19) using `clamp()` CSS may not work in mpdf's HTML→PDF engine. | Low | Fallback to JS-free `text-overflow: ellipsis` + `white-space: nowrap` if mpdf strips `clamp()`. Triangulation test renders the page in both engines (Twig render + final mpdf render). |
| 3 dead-partial REMOVEs break any custom theme that overrides them. | Low | `themes/AdminLTE/` is the only theme in this repo; no plugin theme overrides `factura_pdf1/partials/`. `Plugin` README documents the removal. |
| The audit's "27 dead settings" claim is wrong (some are read in a path the grep didn't reach). | Low | Engram #367 is the authoritative source; the audit agent read the renderer module directly. The new `SettingsEffectCoverageTest` is the runtime confirmation. |

## Rollback Plan

This change is a **pure feature addition** to an already-shipped
plugin. No in-use code is removed except 3 empty placeholder partials
(their content is already inlined in `_parties_header.html.twig`, so
removing them is invisible to the rendered output). Rollback is
**plugin deactivation** + git revert: an operator who hits a blocker
disables `factura_pdf1` from the admin plugin manager. No data
migration is required because `factura_pdf1_settings.settings_json`
is the only persisted state and the new render features are pure
read paths over the same JSON keys (the 27 dead settings are already
in the JSON column; we're only wiring them to the renderer).

## Dependencies

Unchanged from the prior change:
- `mpdf/mpdf` ^8.0 (vendored).
- `clientes_facturacion` (provides `factura_cliente` + `linea_factura_cliente` + `linea_iva_factura_cliente` + `recibo_cliente`).
- `catalogo_core` (provides `articulo`, `familia`, `fabricante`, `impuesto`).
- `business_data` (provides `empresa`, `ejercicio`, `serie`, `divisa`, `forma_pago`, `almacen`).
- `clientes_core` (provides `cliente`, `direccion_cliente`, `pais`, `cuenta_banco_cliente`, `contacto`, `agencia_transporte`).
- `formatos` plugin (provides `formato_documento` for feature 17) — already a dependency of `clientes_facturacion`.

**No new Composer dependencies.** The 5 new model loads
(`Contacto`, `Almacen`, `cuenta_banco_cliente`, `cuenta_banco`,
`AgenciaTransporte`, `ReciboCliente`) are all provided by the existing
dependency chain. `formato_documento` is reached via
`clientes_facturacion`'s `require` on `formatos`.

## Success Criteria

- [ ] `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml` is GREEN, with new cases covering: 19 setting-effect scenarios, 5 adapter-extension getter triangulation cases, 2 new pedido/presupuesto endpoint cases, `SettingsEffectCoverageTest` asserting every key in `UPSTREAM_SETTING_KEYS` (28) has a render effect.
- [ ] `ddev exec php -d memory_limit=512M vendor/dev-tools/bin/phpstan analyse -c plugins/factura_pdf1/phpstan.neon` is GREEN (no new errors).
- [ ] `curl -sL "https://<project>.ddev.site/index.php?page=factura_detallada&id={N}&tipo=pedido"` and `&tipo=presupuesto` return HTTP 200, `Content-Type: application/pdf`, body begins with `%PDF-`. (Smoke: same as prior change, extended with the 2 new types.)
- [ ] `git grep -nE 'posiciontexto[12]|posicionlogo|pagoyvencimiento|mostraralmacen|documentosrelacionados|ocultarreferenciaprod|ref2|traducirformaspago|ocultartablaimpuestos' plugins/factura_pdf1/view/ plugins/factura_pdf1/Services/` returns ≥ 19 distinct references (the prior count was 1, for `colorcabecera`).
- [ ] The 3 dead partials (`_client_billing`, `_company_header`, `_invoice_number_date`) are removed from the file system AND from `pdf.html.twig`'s `{% include %}` list. `_corporate_image.html.twig` is kept on disk as a 1-line empty placeholder.
- [ ] Zero entries in `openspec/changes/factura-pdf1-render-fidelity/` at the parent-repo level (this is a plugin-local SDD; core `openspec/` does not see it).
- [ ] `git ls-files plugins/factura_pdf1/vendor/ | wc -l` is non-zero (the `vendor/` is still vendored; no new Composer deps means no new vendor changes).
- [ ] The 5 source-of-truth spec files under `plugins/factura_pdf1/openspec/specs/{invoice-pdf-rendering,invoice-pdf-settings,invoice-pdf-adapters,invoice-pdf-admin,invoice-pdf-public-endpoint}/spec.md` are updated with the new scenarios; the 5 delta specs under `plugins/factura_pdf1/openspec/changes/factura-pdf1-render-fidelity/specs/` are committed.
- [ ] User complaint is closed: a fresh-render of a real `factura_cliente` shows at least 5 of the 19 new render knobs producing observable output (visual smoke check; the user can confirm). The 3-partial cleanup is invisible to the rendered PDF (smoke: byte-compare of pre/post partial REMOVEs is allowed because the dead partials are empty).

## Chained PR Forecast

2-PR shape (provisional, re-confirmed in `sdd-tasks`):
- **PR-1 — settings effects + pedido/presupuesto coverage** (~600 LoC, requires `size:exception`): 19 new RED scenario tests, 2 RED endpoint tests, minimal Twig/PHP edits to make them GREEN, the 3 dead-partial REMOVEs. Standalone.
- **PR-2 — full upstream fidelity** (~800 LoC, requires `size:exception`): `RelatedModelsLoader` 5-method extension, `getDocumentTypeLabel()` overrides, address-splitting macro, `SetFooter`, 4 new `*PrintView` getters, full `AdapterExtensionsTest` triangulation, `SettingsEffectCoverageTest`, `GoldenPdfTest` regression update, +5 new translation strings. Requires PR-1 on the target branch.

The orchestrator will confirm this 2-PR split (or re-forecast to
3 smaller PRs) before `sdd-tasks` locks the final shape. No
implementation begins until the user has explicitly chosen the
chained-PR strategy.

## Open Questions

None at this time. The 3 user product decisions are locked
(Verifactu QR out; pedido/presupuesto tests in; texto1/texto2 full
fidelity). The 19 features are derived directly from the audit
(Engram #367, section B.2). The 5 source-of-truth spec files are
the contract for `sdd-spec`; the 5 delta specs to be written live
under `plugins/factura_pdf1/openspec/changes/factura-pdf1-render-fidelity/specs/`.

One **follow-up** (not blocking this change, recorded here for
traceability): if `sdd-apply` discovers that the 5 new model loads
need a missing core helper (e.g. an `IBANResolver` for
`cuenta_banco_cliente`, or a `FormAttachment` service for
`idcontactoenv`), the apply agent MUST surface it as a follow-up
change in the **core** `openspec/`, not absorb it into this
plugin-local SDD. The plugin-local rule in `AGENTS.md` is explicit
and not negotiable inside a single PR.

## Handoff to sdd-spec

This proposal defines the contract: 19 render features + 2 endpoint
tests + 3 dead-partial REMOVEs, with delta specs in 5 source-of-truth
files. `sdd-spec` writes the 5 delta specs (one per source-of-truth
file) under
`plugins/factura_pdf1/openspec/changes/factura-pdf1-render-fidelity/specs/`
and updates the 5 source-of-truth specs in
`plugins/factura_pdf1/openspec/specs/`. `sdd-design` then plans the
Twig + PHP edits and the chained-PR split. `sdd-tasks` locks the
final 1-2 PR shape. Implementation does not begin until the user
explicitly approves the chained-PR strategy.
