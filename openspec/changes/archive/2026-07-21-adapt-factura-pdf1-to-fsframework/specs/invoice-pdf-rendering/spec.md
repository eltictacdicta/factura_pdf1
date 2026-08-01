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
