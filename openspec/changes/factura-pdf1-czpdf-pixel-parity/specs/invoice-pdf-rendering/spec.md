# Delta for invoice-pdf-rendering

## Purpose

This change is a **major engine swap** for `plugins/factura_pdf1/`: the
mpdf + Twig pipeline shipped in the archived
`factura-pdf1-render-fidelity` cycle is replaced with a Cezpdf
pipeline that is a verbatim port of the upstream `FacturaPDF1`
`PDFDocument.php` (CamelCase). The 17 feature-level requirements
written in the previous cycle still describe what the rendered PDF
**looks like**, but the assertion mechanism changes: the
`data-*` HTML token convention (previous AD-10) is **obliterated**.
Each feature is now asserted against the Cezpdf-rendered PDF bytes
(text extraction via `smalot/pdfparser`, raw-byte inspection for
color hex, and per-feature Cezpdf draw-call invocation). The
structural-fidelity golden PDF test is REPLACED by a strict
byte-equality regression against a pre-generated Cezpdf fixture
(`REGENERATE_FIXTURE=1` env var is the operator's escape hatch for
intentional fixture updates).

This delta SUPERSEDES AD-5 of the previous design (LOW fidelity →
HIGH fidelity). AD-1, AD-2, AD-3, AD-4, AD-6, AD-7, AD-8, AD-9,
AD-10 (removed), AD-11, AD-12, AD-13 from the prior design are
replaced or re-mapped in this delta.

## MODIFIED Requirements

### Requirement: PDF binary validity

The `CezpdfRenderService` MUST produce a PDF binary whose first
bytes (offset 0) are the literal ASCII string `%PDF-` (the ISO
32000 magic) and whose length is at least 1 KB. The same property
MUST hold for every `PrintableDocumentInterface` adapter
(`FacturaClienteAdapter`, `AlbaranClienteAdapter`,
`PedidoClienteAdapter`, `PresupuestoClienteAdapter`).

(Previously: the same property was asserted against an mpdf
binary; the engine name is now Cezpdf.)

#### Scenario: FacturaClienteAdapter render via Cezpdf

- GIVEN a `FacturaClienteAdapter` constructed from a seeded `factura_cliente`
- WHEN `CezpdfRenderService::render()` is invoked
- THEN the returned string starts with `%PDF-`
- AND its length is ≥ 1024 bytes

#### Scenario: AlbaranClienteAdapter render via Cezpdf

- GIVEN a `AlbaranClienteAdapter` constructed from a seeded `albaran_cliente`
- WHEN `CezpdfRenderService::render()` is invoked
- THEN the returned string starts with `%PDF-`

### Requirement: Cezpdf draw-pipeline resolution

The `CezpdfRenderService` MUST resolve the draw path through
`plugins/factura_pdf1/Lib/PDF/PortedPdfDocument.php` and
`AbstractPdfDocument.php` (the ported upstream `PDFDocument.php`).
The renderer MUST NOT read or depend on any file under
`plugins/factura_pdf1/view/factura_pdf1/` (the Twig template tree
is removed by this change). The Cezpdf library MUST be loaded from
`plugins/factura_pdf1/vendor/cezpdf/Cezpdf.php` (vendored locally
per AGENTS.md "Plugin Composer Dependencies").

(Previously: the renderer resolved
`plugins/factura_pdf1/view/factura_pdf1/pdf.html.twig` and the
`partials/` directory; both are removed by this change.)

#### Scenario: Cezpdf draw path is the plugin-local port

- GIVEN a `PrintableDocumentInterface` and settings
- WHEN the renderer resolves the draw path
- THEN the resolved class is `plugins/factura_pdf1/Lib/PDF/PortedPdfDocument`
- AND the vendored Cezpdf class is `plugins/factura_pdf1/vendor/cezpdf/Cezpdf.php`
- AND no file under `plugins/factura_pdf1/view/factura_pdf1/` is read at render time

#### Scenario: Missing Cezpdf vendor fails fast

- GIVEN the vendored Cezpdf class file does not exist
- WHEN the renderer is invoked
- THEN the renderer raises a Cezpdf-not-found error and does NOT return `%PDF-`

### Requirement: Public service contract

The `CezpdfRenderService` MUST accept a `PrintableDocumentInterface`
and a settings array, and MUST return a PDF binary. The signature
SHALL be stable across the 4 adapter types so the public endpoint,
admin, and any future caller can call it without type-casting.
The legacy `renderHtml()` method is kept as a test seam only and
MUST NOT be called from production code.

(Previously: the contract was held by `PdfRenderService`; the
class is renamed to `CezpdfRenderService` and the engine is
Cezpdf.)

#### Scenario: Single signature across adapters

- GIVEN any of the 4 adapter types
- WHEN `CezpdfRenderService::render($document, $settings)` is called
- THEN the return is a `string` beginning with `%PDF-`

### Requirement: Dependency isolation from upstream

The renderer MUST NOT depend on
`FacturaScripts\Core\Lib\PDF\*`, `mpdf`, or any FS2025-only class.
The only PDF backend dependency permitted is the vendored Cezpdf
0.11.6 at `plugins/factura_pdf1/vendor/cezpdf/`. The renderer MUST
NOT load Twig, the upstream `PDFDocument` parent class, or
`ExtensionsTrait`.

(Previously: mpdf was the only PDF backend; it is removed by this
change. The previous cycle forbade Cezpdf; the new cycle requires
it as the local vendor.)

#### Scenario: No forbidden imports

- GIVEN the renderer source code
- WHEN a static grep for `mpdf|FacturaScripts\\Core\\Lib\\PDF|Twig` runs
- THEN no matches are found in the renderer module

#### Scenario: Cezpdf is the only PDF backend

- GIVEN `plugins/factura_pdf1/composer.json`
- WHEN the manifest is inspected
- THEN `mpdf/mpdf` is NOT listed
- AND `plugins/factura_pdf1/vendor/cezpdf/Cezpdf.php` exists
- AND `git ls-files plugins/factura_pdf1/vendor/cezpdf/` is non-empty

### Requirement: Golden PDF byte-equality regression

A regression test (`tests/Regression/GoldenPdfTest.php`) MUST render
the `SeedInvoiceFakt20260001` fixture and assert byte-for-byte
equality against the pre-generated fixture PDF at
`tests/Fixtures/legacy_invoice_FACT20260001.pdf`. The test MUST be
deterministic (no timestamp drift, no random data, no env-variable
leakage). The `REGENERATE_FIXTURE=1` environment variable MUST
provide an operator escape hatch: when set, the test rewrites the
fixture with the current Cezpdf output and reports the rewrite to
the operator (a follow-up commit is expected).

(Previously: the regression test asserted structural properties
only — page count, page width, text content — and explicitly did
NOT compare raw bytes. The new test is STRICT byte-equality,
locked as a user product decision in the explore round.)

#### Scenario: Same seed data + same settings produces identical PDF bytes

- GIVEN the `SeedInvoiceFakt20260001` fixture
- WHEN `CezpdfRenderService::render()` is invoked with the default settings
- THEN the produced PDF bytes MUST equal the contents of `tests/Fixtures/legacy_invoice_FACT20260001.pdf`
- AND the test MUST be deterministic (no timestamp drift, no random data, no env-variable leakage)

#### Scenario: `REGENERATE_FIXTURE=1` env var updates the fixture intentionally

- GIVEN the operator runs `REGENERATE_FIXTURE=1 ddev exec php vendor/bin/phpunit --filter GoldenPdfTest`
- WHEN the test runs
- THEN the test rewrites the fixture PDF at `tests/Fixtures/legacy_invoice_FACT20260001.pdf` with the current Cezpdf output
- AND the test is marked as "expected to pass" after the rewrite
- AND the test reports a notice instructing the operator to commit the updated fixture

### Requirement: Logo position selector renders at the selected position

The `CezpdfRenderService` MUST render the company logo at the
position selected by the `posicionlogo` setting (0=auto, 1=left,
2=up, 9=none) via the upstream's `Cezpdf::ezImage()` call with
the corresponding coordinate adjustment. The `margenlogo` setting
(px) MUST set the Cezpdf top/left offset, and the `medidalogo`
setting (px) MUST set the rendered width.

(Previously: a Twig template asserted the logo position via
`data-logo-position="left|up|none"` HTML attributes; the Twig
template is removed and assertions move to byte-level PDF signals.)

#### Scenario: `posicionlogo=1` renders the logo in the left column

- GIVEN a settings row with `posicionlogo=1`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the Cezpdf-rendered PDF MUST contain a distinctive byte-level signal distinguishing it from `posicionlogo=2` and `posicionlogo=9` (asserted via the rewritten `SettingsEffectCoverageTest` + PDF text extraction)
- AND the underlying `Cezpdf::ezImage()` call MUST be invoked with the left-column coordinate adjustment
- AND the rendered width MUST equal `medidalogo` points

#### Scenario: `posicionlogo=2` renders the logo above the header

- GIVEN a settings row with `posicionlogo=2`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the Cezpdf-rendered PDF MUST contain a distinctive byte-level signal distinguishing it from `posicionlogo=1` and `posicionlogo=9`
- AND the underlying `Cezpdf::ezImage()` call MUST be invoked with the above-header coordinate adjustment

#### Scenario: `posicionlogo=9` omits the logo entirely

- GIVEN a settings row with `posicionlogo=9`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST NOT contain the embedded logo image stream (asserted via absence of the logo's distinctive byte sequence in the PDF body)
- AND the underlying `Cezpdf::ezImage()` call MUST NOT be invoked

### Requirement: Color-coded header rows and alternating row shading

The Cezpdf draw path MUST read the `colorfilas` setting as the
Cezpdf row fill color and the `espaciofilas` setting as the row
padding applied to each line row. The settings' values MUST be
reflected as raw color hex bytes in the PDF body (asserted via
byte-level inspection).

(Previously: the line-items Twig partial asserted
`data-color-filas="#FF0000"` and `data-row-padding="12"` HTML
attributes; the partial is removed and assertions move to PDF
color hex in the raw bytes.)

#### Scenario: `colorfilas=#FF0000` produces a red row fill

- GIVEN a settings row with `colorfilas=#FF0000`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the literal hex sequence for `#FF0000` (red, RGB 255/0/0) in a PDF graphics state operator
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the color hex presence

#### Scenario: `espaciofilas=12` produces a 12-pt row padding

- GIVEN a settings row with `espaciofilas=12`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain a distinctive byte-level signal (text-position offset) that distinguishes it from the default row padding
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the padding signal

### Requirement: `pagoyvencimiento` mode selector renders the right footer

The `pagoyvencimiento` setting (0=both, 1=only pay, 2=only expiry,
3=bank receipt) MUST drive which blocks appear in the payment
footer via Cezpdf draw calls. The mode value MUST be reflected in
the rendered PDF text (extracted via `smalot/pdfparser`).

(Previously: a Twig partial asserted
`data-pagoyvencimiento-mode="1|3"` HTML attributes; the partial
is removed and assertions move to PDF text content.)

#### Scenario: `pagoyvencimiento=1` shows only the payment form

- GIVEN a settings row with `pagoyvencimiento=1` and a `ReciboCliente` collection
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the literal payment-form text block
- AND the rendered PDF MUST NOT contain the payment-due (expiry) block
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the mode-1 text signature

#### Scenario: `pagoyvencimiento=3` shows the bank receipt list

- GIVEN a settings row with `pagoyvencimiento=3` and seeded `ReciboCliente` rows
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain each `ReciboCliente` row's `numero`, `importe`, and `vencimiento` (extracted via `smalot/pdfparser`)
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the mode-3 text signature

### Requirement: IBAN injection driven by `traducirformaspago`

When `traducirformaspago=true` AND the cliente has a
`cuenta_banco_cliente` row, the Cezpdf-rendered payment footer
MUST include the cliente's IBAN as a visible text string;
otherwise it MUST fall back to the empresa's `cuenta_banco.iban`.
The IBAN source MUST be reflected in the extracted PDF text.

(Previously: a Twig partial asserted `data-iban-source="cliente|empresa"`
HTML attributes; the partial is removed and assertions move to
extracted PDF text content.)

#### Scenario: `traducirformaspago=true` with cliente IBAN injects cliente IBAN

- GIVEN a settings row with `traducirformaspago=true` AND a cliente with `cuenta_banco_cliente.iban='ES7621000000000000000000'`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the literal `ES7621000000000000000000` as visible text
- AND the rendered PDF MUST NOT contain the empresa IBAN

#### Scenario: `traducirformaspago=false` falls back to empresa IBAN

- GIVEN a settings row with `traducirformaspago=false`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the empresa's `cuenta_banco.iban` as visible text
- AND the rendered PDF MUST NOT contain any cliente IBAN

### Requirement: Carrier block from `AgenciaTransporte` + `codigoenv`

The Cezpdf draw path MUST read `agencia_transporte` (joined on
`codtrans`) and `codigoenv` from the source document via
`PrintableDocumentInterface::getAgenciaTransporte()` and render a
carrier block via Cezpdf text draw calls with the agency name and
the shipment tracking code.

(Previously: a Twig partial asserted
`data-carrier-present="true"` and a `.carrier-block` element; the
partial is removed and assertions move to extracted PDF text.)

#### Scenario: Carrier block is rendered when codtrans and codigoenv are set

- GIVEN an adapter whose source document has `codtrans='ASM'`, `codigoenv='TRK-2026-0001'`, and an `agencia_transporte` row keyed `codtrans='ASM'` with `nombre='ASM Transporte Urgente'`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain both `ASM Transporte Urgente` and `TRK-2026-0001` as visible text (extracted via `smalot/pdfparser`)
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the carrier signal

#### Scenario: Carrier block is omitted when codtrans is empty

- GIVEN an adapter whose source document has `codtrans=''`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST NOT contain any carrier text

### Requirement: Shipping address block driven by `ocultardireccionenvio`

The Cezpdf draw path MUST read `ocultardireccionenvio` AND the
source document's `idcontactoenv` (a `Contacto` row) via
`PrintableDocumentInterface::getContactoEnvio()`. When the setting
is `false` AND `idcontactoenv` is set, the shipping address block
MUST be rendered via Cezpdf text draw calls; otherwise it MUST be
omitted.

(Previously: a Twig partial asserted
`data-shipping-address-present="true|false"` and a
`.shipping-address` element; the partial is removed and
assertions move to extracted PDF text.)

#### Scenario: `ocultardireccionenvio=false` with idcontactoenv renders the shipping block

- GIVEN a settings row with `ocultardireccionenvio=false` AND a source document with `idcontactoenv=5`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the `Contacto` `direccion` and `codpostal` as visible text
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the shipping signal

#### Scenario: `ocultardireccionenvio=true` suppresses the shipping block

- GIVEN a settings row with `ocultardireccionenvio=true`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST NOT contain the shipping address text

### Requirement: Related documents block with `parentDocuments()` walk and dedup

The `documentosrelacionados` setting (0=off, 1=parents,
2=parents+children) MUST drive a Cezpdf-rendered related-documents
block. The walk MUST deduplicate documents (a document that is
both a parent and a child appears exactly once). The mode and
deduped count MUST be reflected in the extracted PDF text.

(Previously: a Twig partial asserted
`data-documentosrelacionados-mode="1|2"` and
`data-related-deduped-count` HTML attributes; the partial is
removed and assertions move to PDF text content.)

#### Scenario: `documentosrelacionados=1` lists parent documents

- GIVEN a settings row with `documentosrelacionados=1` AND a `factura_cliente` with two parent `albaran_cliente` rows
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST list each parent's `code` and `date` as visible text
- AND no `factura_cliente` children of the parents MUST appear

#### Scenario: Dedup collapses a parent that is also a child

- GIVEN a document chain A → B → A (A is a parent of B and B is a parent of A)
- WHEN the renderer renders B with `documentosrelacionados=2`
- THEN the rendered PDF MUST list A exactly once
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the deduped count signature

### Requirement: Warehouse block driven by `mostraralmacen`

The `mostraralmacen` setting (0=off, 1=name, 2=name+phone,
3=name+phone+title) MUST drive a Cezpdf-rendered warehouse block
sourced from the `Almacen` model loaded via
`RelatedModelsLoader::loadAlmacen()` and exposed via
`PrintableDocumentInterface::getAlmacen()`.

(Previously: a Twig partial asserted `data-warehouse-mode="0|3"`
and a `.warehouse-block` element; the partial is removed and
assertions move to extracted PDF text.)

#### Scenario: `mostraralmacen=3` shows name + phone + custom title

- GIVEN a settings row with `mostraralmacen=3` and `tituloalmacen='Centro logístico Madrid'` and `mostraralmacentel=true`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the `Almacen.nombre`, the `Almacen.telefono`, and the literal `Centro logístico Madrid` as visible text
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the warehouse-3 text signature

#### Scenario: `mostraralmacen=0` omits the warehouse block

- GIVEN a settings row with `mostraralmacen=0`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST NOT contain any warehouse text

### Requirement: Hide-product-reference toggle

The `ocultarreferenciaprod` setting MUST cause the Cezpdf draw
path to omit the `codigo` column of each line in the line-items
table. The presence/absence MUST be reflected in the extracted
PDF text.

(Previously: a Twig partial asserted `data-hide-reference="true"`
and the absence of `th.line-ref` / `td.line-ref` HTML elements;
the partial is removed and assertions move to PDF text content.)

#### Scenario: `ocultarreferenciaprod=true` suppresses the codigo column

- GIVEN a settings row with `ocultarreferenciaprod=true`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST NOT contain the line's `codigo` as visible text
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the hide-reference signal

### Requirement: Auto-collapse tax table when 1 or 2 taxes share the net

The `ocultartablaimpuestos` setting MUST cause the Cezpdf draw
path to inline-collapse the VAT breakdown when the document's
IVA breakdown contains 1 or 2 distinct tax rates that share the
same base (`neto`). The collapsed/expanded state MUST be
reflected in the extracted PDF text.

(Previously: a Twig partial asserted
`data-vat-table-collapsed="true|false"` and
`data-vat-collapsed-summary` HTML attributes; the partial is
removed and assertions move to PDF text content.)

#### Scenario: Single-rate invoice collapses the tax table

- GIVEN a settings row with `ocultartablaimpuestos=true` AND a factura with a single 21% tax line
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the literal `21%` and the inline-collapsed summary
- AND the rendered PDF MUST NOT contain a separate VAT breakdown table
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the collapsed summary text

#### Scenario: Two-rate invoice still collapses

- GIVEN a settings row with `ocultartablaimpuestos=true` AND a factura with two tax lines (21% and 10%) sharing the same `neto`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the inline-collapsed summary with both `21%` and `10%` rates

#### Scenario: Two distinct netos keep the table expanded

- GIVEN a settings row with `ocultartablaimpuestos=true` AND a factura with two 21% lines on different `neto` values
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain a separate VAT breakdown table with one row per `neto`
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the expanded-table signal

### Requirement: Hide province / hide country toggles

`ocultarprovincia` and `ocultarpais` MUST each cause the Cezpdf
draw path to omit the `provincia` and `pais` components of every
address in the parties header. The presence/absence MUST be
reflected in the extracted PDF text.

(Previously: a Twig partial asserted
`data-hide-provincia="true"` and `data-hide-pais="true"` HTML
attributes; the partial is removed and assertions move to PDF
text content.)

#### Scenario: `ocultarprovincia=true` suppresses the province

- GIVEN a settings row with `ocultarprovincia=true`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST NOT contain the address `provincia` as visible text
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the hide-provincia signal

#### Scenario: `ocultarpais=true` suppresses the country

- GIVEN a settings row with `ocultarpais=true`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST NOT contain the address `pais` as visible text
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the hide-pais signal

### Requirement: `ref2` custom customer reference (3 modes)

The `ref2` setting (0=off, 1=cliente ref2 column, 2=cliente ref2 +
custom label) MUST cause the Cezpdf draw path to emit a secondary
customer reference line under the cliente name. The mode and
content MUST be reflected in the extracted PDF text.

(Previously: a Twig partial asserted `data-ref2-mode="0|1"`
and a `.cliente-ref2` element; the partial is removed and
assertions move to PDF text content.)

#### Scenario: `ref2=1` renders the cliente's ref2 field

- GIVEN a settings row with `ref2=1` AND a cliente with `ref2='PED-2026-001'`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the literal `PED-2026-001` as visible text
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the ref2-1 text signature

#### Scenario: `ref2=0` omits the ref2 line

- GIVEN a settings row with `ref2=0`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST NOT contain the cliente ref2 as visible text

### Requirement: `espaciomaximoempresa` clamps the company block width

The `espaciomaximoempresa` setting (px) MUST cause the Cezpdf
draw path to clamp the company-info block to the configured width
(via Cezpdf text-box sizing primitives). The width MUST be
reflected as a distinctive byte-level signal in the rendered PDF.

(Previously: a Twig template asserted
`data-company-max-width="240"` and a
`style="max-width: 240px"` CSS rule; the Twig template is
removed and assertions move to PDF byte-level signals.)

#### Scenario: `espaciomaximoempresa=240` clamps the company block

- GIVEN a settings row with `espaciomaximoempresa=240`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain a distinctive byte-level signal distinguishing it from the default company-block width
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the company-block width signal

### Requirement: Page numbering footer via Cezpdf text draw

`CezpdfRenderService::render()` MUST emit a Cezpdf text draw
with the literal page-number format (`{PAGENO} / {nbpg}`) on every
page of a multi-page document. The rendered PDF MUST carry a
footer with the page-number format on every page.

(Previously: `PdfRenderService::render()` called
`mpdf->SetFooter('{PAGENO} / {nbpg}')` before `WriteHTML()`; the
mechanism is now a Cezpdf text draw, not an mpdf footer call.)

#### Scenario: Multi-page document carries the page-number footer

- GIVEN a `PrintableDocumentInterface` that produces a 2+ page PDF
- WHEN the renderer finishes the Cezpdf draw path
- THEN the rendered PDF MUST contain the literal `/ 2` substring in the page footer of page 1
- AND the second page MUST contain the literal `2 / 2`

### Requirement: Per-tipo titulo from `FormatoDocumento`

The 4 `*PrintView` classes MUST continue to override
`getDocumentTypeLabel()` to read `formato_documento->titulo`
(via the `FormatoDocumento` value object in the Cezpdf path)
first, falling back to the current hardcoded literal when the
formato row is `null` or has no `titulo`. The titulo MUST be
reflected as visible text in the rendered PDF (extracted via
`smalot/pdfparser`).

(Previously: the titulo override was read in the Twig template
and asserted via `data-document-type-label` HTML attributes; the
Twig template is removed and assertions move to PDF text
content.)

#### Scenario: `formato_documento->titulo` overrides the hardcoded literal

- GIVEN a `factura_cliente` whose `idformato` resolves to a `formato_documento` with `titulo='Factura Proforma'`
- WHEN the renderer renders the `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the literal `Factura Proforma` as visible text (extracted via `smalot/pdfparser`)
- AND the visible type heading MUST equal `Factura Proforma` (not the legacy literal `Factura`)

#### Scenario: Missing formato falls back to the hardcoded literal

- GIVEN a `factura_cliente` whose `idformato` does not resolve to any `formato_documento`
- WHEN the renderer renders the `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the literal `Factura` as visible text
- AND the visible type heading MUST equal `Factura`

### Requirement: Address splitting at parens when over `PARTIR_DIR` width

The Cezpdf draw path MUST split an address string at the first
`(` when its rendered width would exceed `PARTIR_DIR` (constant)
points, putting the parenthetical on a new line. The split
MUST be reflected in the extracted PDF text.

(Previously: a Twig `_address_split` macro asserted
`data-address-split="true|false"` HTML attributes; the Twig
macro is removed and the split is now performed by Cezpdf
text-draw logic, asserted via PDF text content.)

#### Scenario: Long address with parens splits

- GIVEN an address `Calle Larga 123, Piso 4 (Edificio Norte, Escalera B, Puerta 12)`
- WHEN the Cezpdf draw path runs against the address
- THEN the rendered PDF MUST contain `Calle Larga 123, Piso 4` followed by a line break before `Edificio Norte`
- AND the rewritten `SettingsEffectCoverageTest` MUST pin the address-split signal

#### Scenario: Short address does not split

- GIVEN an address `Calle Mayor 1`
- WHEN the Cezpdf draw path runs against the address
- THEN the rendered PDF MUST contain `Calle Mayor 1` as a single text run

### Requirement: Auto-shrink company name to fit the company block width

The Cezpdf draw path MUST shrink the company name font to fit
the available width when the name exceeds the
`espaciomaximoempresa` value. The mechanism MUST be
Cezpdf's text-width measurement + font-size reduction
(equivalent to `text-overflow: ellipsis` for the visual
outcome, but implemented via Cezpdf font sizing, not CSS).

(Previously: the company name rendering used CSS
`text-overflow: ellipsis; white-space: nowrap; overflow: hidden`
on the `.company-name` element; the CSS rule is removed and the
shrink is now performed by Cezpdf font sizing.)

#### Scenario: Long company name is shrunk

- GIVEN a company whose `nombre` is longer than `espaciomaximoempresa` allows
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the company `nombre` as visible text
- AND the rendered PDF MUST contain a distinctive byte-level signal distinguishing it from the default font size (asserted via the rewritten `SettingsEffectCoverageTest`)

#### Scenario: Short company name is rendered at the default font size

- GIVEN a company whose `nombre` fits in the available width
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered PDF MUST contain the company `nombre` as visible text at the default font size
- AND no font-shrink signal MUST be present

## ADDED Requirements

### Requirement: Cezpdf draw-call invocation per feature

The `CezpdfRenderService` MUST invoke the underlying
`Cezpdf::ezImage()`, `Cezpdf::ezText()`, `Cezpdf::ezTable()`, and
`Cezpdf::line()` draw primitives in the documented order for
every feature requirement above. The draw-call sequence is the
deterministic contract that produces the byte-equal output. A
unit test (`CezpdfRenderFeatureTest`) MUST spy on the Cezpdf
instance and assert the expected call sequence for the
`SeedInvoiceFakt20260001` fixture.

#### Scenario: SeedInvoiceFakt20260001 draw-call sequence is recorded

- GIVEN a Cezpdf spy wrapping the real `Cezpdf` instance
- WHEN the `CezpdfRenderService` renders the `SeedInvoiceFakt20260001` fixture
- THEN the spy MUST record the documented sequence of `ezImage`, `ezText`, `ezTable`, and `line` calls
- AND the call order MUST match the ported upstream `PDFDocument.php` line-by-line

#### Scenario: Each draw-call position passes through the AbstractPdfDocument helper

- GIVEN any Cezpdf draw primitive
- WHEN the `CezpdfRenderService` renders a `PrintableDocumentInterface`
- THEN the draw primitive MUST be called via a method on `AbstractPdfDocument` (never directly on `$pdf`)
- AND the wrapper MUST apply the documented coordinate transformation per the active settings

## REMOVED Requirements

_None. The 22 existing requirements of `invoice-pdf-rendering`
are all preserved in the MODIFIED section (the Twig-template
requirement is renamed and rewritten as "Cezpdf draw-pipeline
resolution"; the engine-class references are updated from mpdf
to Cezpdf; the golden PDF regression is rewritten as the new
byte-equality contract; the 17 feature requirements are
re-asserted against the Cezpdf-rendered PDF output). The
Twig template tree at `plugins/factura_pdf1/view/factura_pdf1/`
is deleted as a side effect, but the requirement it served
("resolve a body template for the renderer") is **replaced** by
the MODIFIED Cezpdf draw-pipeline resolution requirement, not
removed._
