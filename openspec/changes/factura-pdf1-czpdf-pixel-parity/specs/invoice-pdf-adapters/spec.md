# Delta for invoice-pdf-adapters

## Purpose

This change is a **major engine swap** for `plugins/factura_pdf1/`:
the mpdf + Twig pipeline shipped in the archived
`factura-pdf1-render-fidelity` cycle is replaced with a Cezpdf
pipeline. The 4 client-document adapters and the
`PrintableDocumentInterface` are **engine-independent** and are
preserved unchanged. This delta adds 5 new getters to the
interface (per the explore round's B.2 list — the methods the
ported `PDFDocument.php` needs to read but that the FS2025 parent
provided via `BusinessDocument` + `ExtensionsTrait`): they let
the Cezpdf path consume the adapter data without going through
the FS2025 `BusinessDocument` contract.

The change also updates the "renderer depends only on the
interface" requirement to reflect the new render class
(`CezpdfRenderService`) and the new draw path
(`Lib/PDF/PortedPdfDocument`).

The 4-adapter polymorphism, the `fromId()` factory, the
`PrintableDocumentNotFoundException` contract, the related-models
loaders, the `parentDocuments()` walk with dedup, and the
`getDocumentTypeLabel()` per-tipo `FormatoDocumento` override
are **kept verbatim**.

## MODIFIED Requirements

### Requirement: PrintableDocumentInterface contract

A `PrintableDocumentInterface` MUST be the only shape the PDF
renderer sees. The renderer (`CezpdfRenderService` →
`Lib/PDF/PortedPdfDocument` → `AbstractPdfDocument`) MUST NOT
import any concrete `*_cliente` class or any of the per-doc
line/iva tables. All four adapter types MUST implement this
interface. The interface MUST expose 10 getters: the original 5
from the previous cycle (`getAlmacen`, `getContactoEnvio`,
`getCuentaBancaria`, `getAgenciaTransporte`, `getRecibos`) AND
the 5 new getters added by this change (`getModelClassName`,
`getCodigoRect`, `getObservaciones`, `getLines`, `getId`).

(Previously: the interface exposed 5 getters; the renderer was
`PdfRenderService` and the renderer route was
mpdf + Twig. The interface contract is extended with 5 more
getters so the Cezpdf path can read these fields without going
through the FS2025 `BusinessDocument` contract.)

#### Scenario: Renderer depends only on the interface (Cezpdf path)

- GIVEN the `CezpdfRenderService` source and the `PortedPdfDocument` source
- WHEN a grep for `factura_cliente|albaran_cliente|pedido_cliente|presupuesto_cliente|linea_` runs
- THEN no matches appear outside the adapter namespace
- AND the draw path goes through `Lib/PDF/PortedPdfDocument.php` and `AbstractPdfDocument.php`

#### Scenario: All four adapters implement the interface

- GIVEN the four adapter classes
- WHEN `instanceof PrintableDocumentInterface` is checked
- THEN all four return `true`

#### Scenario: All four adapters expose the 10 getters

- GIVEN the four adapter classes
- WHEN the 10 getter methods are checked
- THEN each adapter defines all 10 methods with the documented return types

## ADDED Requirements

### Requirement: `getModelClassName()` returns the adapter's source model FQCN

`getModelClassName(): string` MUST return the fully-qualified
class name (FQCN) of the underlying source model that the
adapter wraps (e.g. `FacturaScripts\Dinamic\Model\FacturaCliente`,
`\FacturaCliente`, etc., per the project's model-lookup
convention). The ported `PDFDocument.php` uses this value to
route per-model logic that the FS2025 parent exposed via
`$this->getModel()->modelClassName()`.

#### Scenario: Default returns the concrete model FQCN

- GIVEN a `FacturaClienteAdapter` constructed from a persisted `factura_cliente`
- WHEN `getModelClassName()` is called
- THEN it MUST return the FQCN string identifying the source model class

#### Scenario: Override in tests lets the adapter wrap a custom class

- GIVEN a test subclass of `FacturaClienteAdapter` that overrides `getModelClassName()` to return `'Tests\\Fixtures\\CustomModel'`
- WHEN `getModelClassName()` is called
- THEN it MUST return the override literal `'Tests\\Fixtures\\CustomModel'`

### Requirement: `getCodigoRect()` returns the document's "rectified" code

`getCodigoRect(): ?string` MUST return the source document's
rectified code (`codigorect`), or `null` when the source has no
rectified counterpart. The ported `PDFDocument.php` uses this
value in the document-code header line; the FS2025 parent
exposed it via `Tools::getRectifiedCode()`.

#### Scenario: Source with codigorect returns the literal value

- GIVEN a `factura_cliente` with `codigo='A/2026/0001'` and `codigorect='A/2026/0001-RECT'`
- WHEN `FacturaClienteAdapter::getCodigoRect()` is called
- THEN it MUST return `'A/2026/0001-RECT'`

#### Scenario: Source without codigorect returns null

- GIVEN a `factura_cliente` with `codigo='A/2026/0001'` and no `codigorect`
- WHEN `FacturaClienteAdapter::getCodigoRect()` is called
- THEN it MUST return `null` (not `''`)

### Requirement: `getObservaciones()` returns the document's observations

`getObservaciones(): ?string` MUST return the source document's
observations (`observaciones`) trimmed, or `null` when the
field is empty. The ported `PDFDocument.php` uses this value in
the "Observaciones" block above the payment footer.

#### Scenario: Source with observaciones returns the trimmed value

- GIVEN a `factura_cliente` with `observaciones='  Cliente VIP — entrega prioritaria  '`
- WHEN `FacturaClienteAdapter::getObservaciones()` is called
- THEN it MUST return the trimmed value `'Cliente VIP — entrega prioritaria'`

#### Scenario: Source with empty observaciones returns null

- GIVEN a `factura_cliente` with `observaciones=''`
- WHEN `FacturaClienteAdapter::getObservaciones()` is called
- THEN it MUST return `null` (not `''`)

### Requirement: `getLines()` returns the document's line collection

`getLines(): iterable` MUST return the source document's line
collection (one entry per line, each entry shaped as
`['codigo' => string, 'descripcion' => string, 'cantidad' =>
float, 'pvpunitario' => float, 'pvptotal' => float, 'iva' =>
float, 'total' => float]`). The ported `PDFDocument.php` uses
this iterable to feed the Cezpdf `ezTable()` line-items draw
call. The iterable MAY be a generator, an array, or an
`ArrayIterator` — the port consumes it via `foreach` only.

#### Scenario: Source with 3 lines returns a 3-element iterable

- GIVEN a `factura_cliente` with 3 `linea_factura_cliente` rows
- WHEN `FacturaClienteAdapter::getLines()` is iterated
- THEN it MUST yield 3 entries
- AND each entry MUST have the documented 7 keys with the source values

#### Scenario: Source with no lines returns an empty iterable

- GIVEN a `factura_cliente` with 0 `linea_factura_cliente` rows
- WHEN `FacturaClienteAdapter::getLines()` is iterated
- THEN it MUST yield 0 entries
- AND `iterator_to_array($adapter->getLines())` MUST equal `[]`

### Requirement: `getId()` returns the source document's primary key

`getId(): int` MUST return the source document's primary-key
column value (`idfactura`, `idalbaran`, `idpedido`,
`idpresupuesto`, etc.). The ported `PDFDocument.php` uses this
value to construct the output filename and to log the
render path.

#### Scenario: FacturaClienteAdapter returns idfactura

- GIVEN a `factura_cliente` with `idfactura=42`
- WHEN `FacturaClienteAdapter::getId()` is called
- THEN it MUST return `42`

#### Scenario: AlbaranClienteAdapter returns idalbaran

- GIVEN an `albaran_cliente` with `idalbaran=7`
- WHEN `AlbaranClienteAdapter::getId()` is called
- THEN it MUST return `7`

#### Scenario: PedidoClienteAdapter returns idpedido

- GIVEN a `pedido_cliente` with `idpedido=11`
- WHEN `PedidoClienteAdapter::getId()` is called
- THEN it MUST return `11`

#### Scenario: PresupuestoClienteAdapter returns idpresupuesto

- GIVEN a `presupuesto_cliente` with `idpresupuesto=3`
- WHEN `PresupuestoClienteAdapter::getId()` is called
- THEN it MUST return `3`

## REMOVED Requirements

_None. The 13 existing requirements of `invoice-pdf-adapters`
remain valid; the `PrintableDocumentInterface` requirement is
extended (in the MODIFIED section) with 5 new getters and 5 new
requirements are added._
