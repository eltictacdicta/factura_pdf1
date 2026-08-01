# invoice-pdf-rendering Specification

## Purpose

Defines the PDF rendering pipeline of the `factura_pdf1` plugin: an mpdf +
Twig service that turns any `PrintableDocumentInterface` plus the persisted
settings into a PDF binary. Consumed by the public endpoint
(`?page=factura_detallada&id=N`, locked across all 5 specs) and by future
follow-up changes (email, batch print). Derives from the proposal §
"Capabilities" → "invoice-pdf-rendering" and the §"Approach" PDF rendering
decision. Plugin is licensed LGPL-3.0-or-later; the new code does not copy
the upstream `Lib/PDF/PDFDocument.php`.

## Requirements

### Requirement: PDF binary validity

The renderer MUST produce a PDF binary whose first bytes (offset 0) are
the literal ASCII string `%PDF-` (the ISO 32000 magic) and whose length
is at least 1 KB. The same property MUST hold for every
`PrintableDocumentInterface` adapter (`FacturaClienteAdapter`,
`AlbaranClienteAdapter`, `PedidoClienteAdapter`,
`PresupuestoClienteAdapter`).

#### Scenario: FacturaClienteAdapter render

- GIVEN a `FacturaClienteAdapter` constructed from a seeded `factura_cliente`
- WHEN the renderer is invoked
- THEN the returned string starts with `%PDF-`
- AND its length is ≥ 1024 bytes

#### Scenario: AlbaranClienteAdapter render

- GIVEN a `AlbaranClienteAdapter` constructed from a seeded `albaran_cliente`
- WHEN the renderer is invoked
- THEN the returned string starts with `%PDF-`

### Requirement: Twig template resolution

The renderer MUST resolve the body template at
`plugins/factura_pdf1/view/factura_pdf1/pdf.html.twig` and MUST
include the section partials colocated in
`plugins/factura_pdf1/view/factura_pdf1/partials/`.

#### Scenario: Template path is the plugin-local view

- GIVEN a `PrintableDocumentInterface` and settings
- WHEN the renderer resolves the template
- THEN the resolved path is `plugins/factura_pdf1/view/factura_pdf1/pdf.html.twig`
- AND each section partial under `partials/` is loaded by the render

#### Scenario: Missing template fails fast

- GIVEN the body template file does not exist
- WHEN the renderer is invoked
- THEN the renderer raises a template-not-found error and does NOT return `%PDF-`

### Requirement: Public service contract

The renderer MUST accept a `PrintableDocumentInterface` and a settings
array, and MUST return a PDF binary. The signature SHALL be stable
across the 4 adapter types so the public endpoint, admin, and any future
caller can call it without type-casting.

#### Scenario: Single signature across adapters

- GIVEN any of the 4 adapter types
- WHEN `render($document, $settings)` is called
- THEN the return is a `string` beginning with `%PDF-`

### Requirement: Dependency isolation from upstream

The renderer MUST NOT depend on `Cezpdf`,
`FacturaScripts\Core\Lib\PDF\*`, or any FS2025-only class. The only PDF
backend dependency permitted is `mpdf/mpdf` (^8.0, vendored).

#### Scenario: No forbidden imports

- GIVEN the renderer source code
- WHEN a static grep for `Cezpdf|FacturaScripts\\Core\\Lib\\PDF` runs
- THEN no matches are found in the renderer module

#### Scenario: mpdf is the only PDF backend

- GIVEN `plugins/factura_pdf1/composer.lock`
- WHEN the lockfile is inspected
- THEN `mpdf/mpdf` is the only PDF library listed

### Requirement: Golden PDF structural-fidelity regression

A regression test MUST render a fixed `factura_cliente` fixture
(e.g. `FAKT-2026-0001`) and assert: page count is 1, page width is 595
pt (A4), and the PDF text extract contains the invoice number, the
cliente name, and the total. The test MUST NOT assert byte equality
with the legacy golden file.

#### Scenario: Structural assertions pass

- GIVEN the seed fixture and the golden assertions
- WHEN the renderer runs against the fixture
- THEN page count, page width, and required text content all match
- AND the test does not compare raw bytes

#### Scenario: Magic bytes precheck

- GIVEN the rendered PDF for the fixture
- WHEN the test inspects the first 5 bytes
- THEN they equal the literal `%PDF-`

### Requirement: Logo position selector renders at the selected position

The Twig template MUST render the company logo at the position
selected by the `posicionlogo` setting (0=auto, 1=left, 2=up, 9=none).
The `margenlogo` setting (px) MUST set a CSS top/left offset, and the
`medidalogo` setting (px) MUST set the rendered width.

#### Scenario: `posicionlogo=1` renders the logo in the left column

- GIVEN a settings row with `posicionlogo=1`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain a `data-logo-position="left"` attribute on the logo element
- AND the logo element MUST be inside a `.party-col.party-col--left` container
- AND the rendered width MUST equal `medidalogo` pixels

#### Scenario: `posicionlogo=2` renders the logo above the header

- GIVEN a settings row with `posicionlogo=2`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain a `data-logo-position="up"` attribute on the logo element
- AND the logo element MUST be inside `.corporate-banner` and appear before the parties row

#### Scenario: `posicionlogo=9` omits the logo entirely

- GIVEN a settings row with `posicionlogo=9`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain a `data-logo-position="none"` attribute
- AND the logo `<img>` element MUST NOT appear in the rendered DOM

### Requirement: Color-coded header rows and alternating row shading

The line-items partial MUST read the `colorfilas` setting as a CSS
custom property and the `espaciofilas` setting as a `padding` value
applied to each line row.

#### Scenario: `colorfilas=#FF0000` produces a red row background token

- GIVEN a settings row with `colorfilas=#FF0000`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain a `data-color-filas="#FF0000"` attribute on the line-items table
- AND the CSS variable `--color-filas` on `.line-items` MUST equal `#FF0000`

#### Scenario: `espaciofilas=12` produces a 12px row padding

- GIVEN a settings row with `espaciofilas=12`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN each rendered line row MUST carry a `data-row-padding="12"` attribute
- AND inline CSS MUST set `padding: 12px` on `.line-items tbody tr`

### Requirement: `pagoyvencimiento` mode selector renders the right footer

The `pagoyvencimiento` setting (0=both, 1=only pay, 2=only expiry,
3=bank receipt) MUST drive which blocks appear in the payment footer.

#### Scenario: `pagoyvencimiento=1` shows only the payment form

- GIVEN a settings row with `pagoyvencimiento=1` and a `ReciboCliente` collection
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain a `data-pagoyvencimiento-mode="1"` attribute on the payment footer
- AND the footer MUST contain a `.payment-form` block
- AND the footer MUST NOT contain a `.payment-due` block

#### Scenario: `pagoyvencimiento=3` shows the bank receipt list

- GIVEN a settings row with `pagoyvencimiento=3` and seeded `ReciboCliente` rows
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-pagoyvencimiento-mode="3"`
- AND the footer MUST contain a `.payment-receipts` table listing each `ReciboCliente` (`numero`, `importe`, `vencimiento`)

### Requirement: IBAN injection driven by `traducirformaspago`

When `traducirformaspago=true` AND the cliente has a `cuenta_banco_cliente`
row, the payment footer MUST render the cliente's IBAN; otherwise it
MUST fall back to the empresa's `cuenta_banco`.

#### Scenario: `traducirformaspago=true` with cliente IBAN injects cliente IBAN

- GIVEN a settings row with `traducirformaspago=true` AND a cliente with `cuenta_banco_cliente.iban='ES7621000000000000000000'`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain a `data-iban-source="cliente"` attribute
- AND the visible IBAN text MUST equal `ES7621000000000000000000`

#### Scenario: `traducirformaspago=false` falls back to empresa IBAN

- GIVEN a settings row with `traducirformaspago=false`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain a `data-iban-source="empresa"` attribute
- AND the visible IBAN MUST equal the empresa's `cuenta_banco.iban`

### Requirement: Carrier block from `AgenciaTransporte` + `codigoenv`

The renderer MUST read `agencia_transporte` (joined on `codtrans`) and
`codigoenv` from the source document and render a carrier block with
the agency name and the shipment tracking code.

#### Scenario: Carrier block is rendered when codtrans and codigoenv are set

- GIVEN an adapter whose source document has `codtrans='ASM'`, `codigoenv='TRK-2026-0001'`, and an `agencia_transporte` row keyed `codtrans='ASM'` with `nombre='ASM Transporte Urgente'`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-carrier-present="true"`
- AND a `.carrier-block` element MUST be present
- AND the rendered text MUST contain both `ASM Transporte Urgente` and `TRK-2026-0001`

#### Scenario: Carrier block is omitted when codtrans is empty

- GIVEN an adapter whose source document has `codtrans=''`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST NOT contain `.carrier-block`

### Requirement: Shipping address block driven by `ocultardireccionenvio`

The renderer MUST read `ocultardireccionenvio` AND the source
document's `idcontactoenv` (a `Contacto` row). When the setting is
`false` AND `idcontactoenv` is set, the shipping address block MUST
render; otherwise it MUST be omitted.

#### Scenario: `ocultardireccionenvio=false` with idcontactoenv renders the shipping block

- GIVEN a settings row with `ocultardireccionenvio=false` AND a source document with `idcontactoenv=5`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-shipping-address-present="true"`
- AND a `.shipping-address` block MUST appear with the `Contacto` direccion and codpostal

#### Scenario: `ocultardireccionenvio=true` suppresses the shipping block

- GIVEN a settings row with `ocultardireccionenvio=true`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-shipping-address-present="false"`
- AND the `.shipping-address` block MUST NOT appear in the rendered DOM

### Requirement: Related documents block with `parentDocuments()` walk and dedup

The `documentosrelacionados` setting (0=off, 1=parents, 2=parents+children)
MUST drive a `parentDocuments()` walk on the adapter. The walk MUST
deduplicate documents (a document that is both a parent and a child
appears exactly once).

#### Scenario: `documentosrelacionados=1` lists parent documents

- GIVEN a settings row with `documentosrelacionados=1` AND a `factura_cliente` with two parent `albaran_cliente` rows
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-documentosrelacionados-mode="1"`
- AND a `.related-documents` block MUST list each parent's `code` and `date`
- AND no `factura_cliente` children of the parents MUST appear

#### Scenario: Dedup collapses a parent that is also a child

- GIVEN a document chain A → B → A (A is a parent of B and B is a parent of A)
- WHEN the renderer renders B with `documentosrelacionados=2`
- THEN the rendered HTML MUST list A exactly once
- AND the rendered HTML MUST contain `data-related-deduped-count="1"` (asserts the dedup token)

### Requirement: Warehouse block driven by `mostraralmacen`

The `mostraralmacen` setting (0=off, 1=name, 2=name+phone, 3=name+phone+title)
MUST drive a warehouse block sourced from the `Almacen` model loaded
via `RelatedModelsLoader::loadAlmacen()`.

#### Scenario: `mostraralmacen=3` shows name + phone + custom title

- GIVEN a settings row with `mostraralmacen=3` and `tituloalmacen='Centro logístico Madrid'` and `mostraralmacentel=true`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-warehouse-mode="3"`
- AND a `.warehouse-block` MUST contain the `Almacen.nombre`, the `Almacen.telefono`, and the literal `Centro logístico Madrid` as a heading

#### Scenario: `mostraralmacen=0` omits the warehouse block

- GIVEN a settings row with `mostraralmacen=0`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-warehouse-mode="0"`
- AND the `.warehouse-block` MUST NOT appear

### Requirement: Hide-product-reference toggle

The `ocultarreferenciaprod` setting MUST conditionally render the
`codigo` column of each line in the line-items table.

#### Scenario: `ocultarreferenciaprod=true` suppresses the codigo column

- GIVEN a settings row with `ocultarreferenciaprod=true`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-hide-reference="true"`
- AND no `th.line-ref` header MUST be rendered
- AND no `td.line-ref` cell MUST appear in any line row

### Requirement: Auto-collapse tax table when 1 or 2 taxes share the net

The `ocultartablaimpuestos` setting MUST cause the VAT breakdown
partial to inline-collapse when the document's IVA breakdown contains
1 or 2 distinct tax rates that share the same base (`neto`).

#### Scenario: Single-rate invoice collapses the tax table

- GIVEN a settings row with `ocultartablaimpuestos=true` AND a factura with a single 21% tax line
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-vat-table-collapsed="true"`
- AND no `.vat-breakdown` table MUST be rendered
- AND the rendered HTML MUST inline a `data-vat-collapsed-summary="21%: 100.00"` token

#### Scenario: Two-rate invoice still collapses

- GIVEN a settings row with `ocultartablaimpuestos=true` AND a factura with two tax lines (21% and 10%) sharing the same `neto`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-vat-table-collapsed="true"`
- AND a `data-vat-collapsed-summary="21%: 50.00; 10%: 50.00"` token MUST be present

#### Scenario: Two distinct netos keep the table expanded

- GIVEN a settings row with `ocultartablaimpuestos=true` AND a factura with two 21% lines on different `neto` values
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-vat-table-collapsed="false"`
- AND a `.vat-breakdown` table MUST be rendered with one row per `neto`

### Requirement: Hide province / hide country toggles

`ocultarprovincia` and `ocultarpais` MUST each conditionally render the
`provincia` and `pais` components of every address in the parties
header.

#### Scenario: `ocultarprovincia=true` suppresses the province

- GIVEN a settings row with `ocultarprovincia=true`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-hide-provincia="true"`
- AND no `.address-provincia` element MUST be rendered

#### Scenario: `ocultarpais=true` suppresses the country

- GIVEN a settings row with `ocultarpais=true`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-hide-pais="true"`
- AND no `.address-pais` element MUST be rendered

### Requirement: `ref2` custom customer reference (3 modes)

The `ref2` setting (0=off, 1=cliente ref2 column, 2=cliente ref2 + custom label)
MUST drive a secondary customer reference line under the cliente name.

#### Scenario: `ref2=1` renders the cliente's ref2 field

- GIVEN a settings row with `ref2=1` AND a cliente with `ref2='PED-2026-001'`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-ref2-mode="1"`
- AND a `.cliente-ref2` element MUST contain the literal `PED-2026-001`

#### Scenario: `ref2=0` omits the ref2 line

- GIVEN a settings row with `ref2=0`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-ref2-mode="0"`
- AND no `.cliente-ref2` element MUST be rendered

### Requirement: `espaciomaximoempresa` clamps the company block width

The `espaciomaximoempresa` setting (px) MUST set a `max-width` on the
company-info block of the parties header.

#### Scenario: `espaciomaximoempresa=240` clamps the company block

- GIVEN a settings row with `espaciomaximoempresa=240`
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-company-max-width="240"`
- AND the `.company-info` block MUST carry `style="max-width: 240px"`

### Requirement: Page numbering footer via mpdf `SetFooter`

`PdfRenderService::render()` MUST call `mpdf->SetFooter('{PAGENO} / {nbpg}')`
before `WriteHTML()`. The rendered PDF MUST carry a footer with the
literal page-number format on every page of a multi-page document.

#### Scenario: Multi-page document carries the page-number footer

- GIVEN a `PrintableDocumentInterface` that produces a 2+ page PDF
- WHEN the renderer finishes `WriteHTML()`
- THEN the rendered PDF MUST contain the literal `/ 2` substring in the page footer of page 1
- AND the second page MUST contain the literal `2 / 2`

### Requirement: Per-tipo titulo from `FormatoDocumento`

The 4 `*PrintView` classes MUST override `getDocumentTypeLabel()` to
read `formato_documento->titulo` first, falling back to the current
hardcoded literal when the formato row is `null` or has no `titulo`.

#### Scenario: `formato_documento->titulo` overrides the hardcoded literal

- GIVEN a `factura_cliente` whose `idformato` resolves to a `formato_documento` with `titulo='Factura Proforma'`
- WHEN the renderer renders the `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain a `data-document-type-label="Factura Proforma"` attribute
- AND the visible type heading MUST equal `Factura Proforma` (not the legacy literal `Factura`)

#### Scenario: Missing formato falls back to the hardcoded literal

- GIVEN a `factura_cliente` whose `idformato` does not resolve to any `formato_documento`
- WHEN the renderer renders the `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-document-type-label="Factura"`
- AND the visible type heading MUST equal `Factura`

### Requirement: Address splitting at parens when over `PARTIR_DIR` width

A Twig macro `_address_split` MUST split an address string at the
first `(` when its rendered width would exceed `PARTIR_DIR`
(constant) px, putting the parenthetical on a new line.

#### Scenario: Long address with parens splits

- GIVEN an address `Calle Larga 123, Piso 4 (Edificio Norte, Escalera B, Puerta 12)`
- WHEN the macro runs against the address
- THEN the rendered HTML MUST contain a `data-address-split="true"` attribute on the address element
- AND the rendered text MUST contain `Calle Larga 123, Piso 4` followed by a line break before `Edificio Norte`

#### Scenario: Short address does not split

- GIVEN an address `Calle Mayor 1`
- WHEN the macro runs against the address
- THEN the rendered HTML MUST contain `data-address-split="false"`
- AND the rendered text MUST NOT contain any line break inside the address

### Requirement: Auto-shrink company name to fit the company block width

The company name rendering MUST apply CSS that prevents overflow
when the name exceeds the available width. The mechanism MUST be
`text-overflow: ellipsis` + `white-space: nowrap` (mpdf-safe fallback
for `clamp()`).

#### Scenario: Long company name is ellipsised

- GIVEN a company whose `nombre` is longer than `espaciomaximoempresa` allows
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-company-name-overflow="true"`
- AND the `.company-name` element MUST carry `style="text-overflow: ellipsis; white-space: nowrap; overflow: hidden"`

#### Scenario: Short company name is rendered as-is

- GIVEN a company whose `nombre` fits in the available width
- WHEN the renderer renders any `PrintableDocumentInterface`
- THEN the rendered HTML MUST contain `data-company-name-overflow="false"`
- AND no `text-overflow: ellipsis` style MUST be present
