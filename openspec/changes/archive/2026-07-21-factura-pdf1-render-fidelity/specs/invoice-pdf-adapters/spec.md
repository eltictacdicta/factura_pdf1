# Delta for invoice-pdf-adapters

## Purpose

This change extends `ClientDocumentPrintViewInterface` with 5 new
getters that surface the data loaded by `RelatedModelsLoader`. The
adapters (`AbstractClienteDocumentAdapter` and its 4 concrete
subclasses) MUST populate these getters so the renderer can read
them via the interface alone, preserving the polymorphic shape that
the source-of-truth spec requires. This change also adds a
`parentDocuments()` walk with dedup logic for the
`documentosrelacionados` setting (modes 0/1/2).

## MODIFIED Requirements

### Requirement: PrintableDocumentInterface contract

A `PrintableDocumentInterface` MUST be the only shape the PDF
renderer sees. The renderer MUST NOT import any concrete `*_cliente`
class or any of the per-doc line/iva tables. All four adapter types
MUST implement this interface. The interface MUST expose 5
additional getters: `getAlmacen()` (`Almacen|null`),
`getContactoEnvio()` (`Contacto|null`), `getCuentaBancaria()`
(`string` IBAN), `getAgenciaTransporte()` (`array{nombre: string,
tracking: string}`), and `getRecibos()` (`ReciboCliente[]`).

#### Scenario: Renderer depends only on the interface

- GIVEN the renderer source
- WHEN a grep for `factura_cliente|albaran_cliente|pedido_cliente|presupuesto_cliente|linea_` runs
- THEN no matches appear outside the adapter namespace

#### Scenario: All four adapters implement the interface

- GIVEN the four adapter classes
- WHEN `instanceof PrintableDocumentInterface` is checked
- THEN all four return `true`

#### Scenario: All four adapters expose the 5 new getters

- GIVEN the four adapter classes
- WHEN the 5 new getter methods are checked
- THEN each adapter defines all 5 methods with the documented return types

## ADDED Requirements

### Requirement: `getAlmacen()` returns the `Almacen` linked to the document

`getAlmacen()` MUST return the `Almacen` row whose `codalmacen`
matches the source document's `codalmacen`, loaded by
`RelatedModelsLoader::loadAlmacen()`. When the source document has
no `codalmacen` (or the join resolves to no row), the getter MUST
return `null`.

#### Scenario: Adapter returns the linked `Almacen`

- GIVEN a `factura_cliente` with `codalmacen='ALM-1'` and an `almacen` row keyed `ALM-1` with `nombre='Almacén Central'`
- WHEN `FacturaClienteAdapter::getAlmacen()` is called
- THEN it MUST return an `Almacen` instance with `nombre='Almacén Central'`

#### Scenario: Missing `codalmacen` returns `null`

- GIVEN a `factura_cliente` with `codalmacen=''`
- WHEN `FacturaClienteAdapter::getAlmacen()` is called
- THEN it MUST return `null` (not throw)

### Requirement: `getContactoEnvio()` returns the `Contacto` linked to the document

`getContactoEnvio()` MUST return the `Contacto` row whose
`idcontacto` matches the source document's `idcontactoenv`, loaded by
`RelatedModelsLoader::loadContactoEnvio()`. When `idcontactoenv` is
`null` or 0, the getter MUST return `null`.

#### Scenario: Adapter returns the linked `Contacto`

- GIVEN a `factura_cliente` with `idcontactoenv=5` and a `contacto` row with `idcontacto=5` and `direccion='Polígono Sur, Nave 12'`
- WHEN `FacturaClienteAdapter::getContactoEnvio()` is called
- THEN it MUST return a `Contacto` instance with `direccion='Polígono Sur, Nave 12'`

#### Scenario: Null `idcontactoenv` returns `null`

- GIVEN a `factura_cliente` with `idcontactoenv=0`
- WHEN `FacturaClienteAdapter::getContactoEnvio()` is called
- THEN it MUST return `null` (not throw)

### Requirement: `getCuentaBancaria()` returns the IBAN to render

`getCuentaBancaria()` MUST return the IBAN string to render in the
payment footer. Resolution rules (in order): (1) when
`traducirformaspago=true` AND the cliente has a `cuenta_banco_cliente`
row, return the cliente IBAN; (2) otherwise, return the empresa's
`cuenta_banco.iban`; (3) when neither is available, return the empty
string `''` (the renderer hides the IBAN block when the value is
empty).

#### Scenario: Cliente IBAN wins when `traducirformaspago=true`

- GIVEN a settings row with `traducirformaspago=true` AND a cliente with `cuenta_banco_cliente.iban='ES7621000000000000000000'`
- WHEN `FacturaClienteAdapter::getCuentaBancaria()` is called
- THEN it MUST return `ES7621000000000000000000`

#### Scenario: Empresa IBAN is the fallback

- GIVEN a settings row with `traducirformaspago=false`
- WHEN `FacturaClienteAdapter::getCuentaBancaria()` is called
- THEN it MUST return the empresa's `cuenta_banco.iban`

#### Scenario: Missing IBAN returns empty string

- GIVEN a settings row with `traducirformaspago=true` AND a cliente with no `cuenta_banco_cliente` AND an empresa with no `cuenta_banco`
- WHEN `FacturaClienteAdapter::getCuentaBancaria()` is called
- THEN it MUST return `''` (not throw)

### Requirement: `getAgenciaTransporte()` returns the carrier block data

`getAgenciaTransporte()` MUST return an associative array of shape
`['nombre' => string, 'tracking' => string]`, where `nombre` is the
`agencia_transporte.nombre` joined on `codtrans` and `tracking` is
the source document's `codigoenv`. When the source document has no
`codtrans` (or the join resolves to no row), the getter MUST return
`['nombre' => '', 'tracking' => '']`.

#### Scenario: Adapter returns the linked agency + tracking code

- GIVEN a `factura_cliente` with `codtrans='ASM'`, `codigoenv='TRK-2026-0001'`, and an `agencia_transporte` row keyed `codtrans='ASM'` with `nombre='ASM Transporte Urgente'`
- WHEN `FacturaClienteAdapter::getAgenciaTransporte()` is called
- THEN it MUST return `['nombre' => 'ASM Transporte Urgente', 'tracking' => 'TRK-2026-0001']`

#### Scenario: Missing `codtrans` returns the empty shape

- GIVEN a `factura_cliente` with `codtrans=''`
- WHEN `FacturaClienteAdapter::getAgenciaTransporte()` is called
- THEN it MUST return `['nombre' => '', 'tracking' => '']`

### Requirement: `getRecibos()` returns the `ReciboCliente` collection

`getRecibos()` MUST return the `ReciboCliente[]` collection for the
source document (loaded by `RelatedModelsLoader::loadRecibos()`). The
collection MUST be ordered by `vencimiento` ASC. When the document
has no receipts, the getter MUST return `[]` (not `null`).

#### Scenario: Adapter returns the receipts in ascending due-date order

- GIVEN a `factura_cliente` with three `recibo_cliente` rows with `vencimiento` values `2026-09-15`, `2026-08-01`, and `2026-10-30`
- WHEN `FacturaClienteAdapter::getRecibos()` is called
- THEN it MUST return a 3-element array ordered as `2026-08-01, 2026-09-15, 2026-10-30`

#### Scenario: Document with no receipts returns `[]`

- GIVEN a `pedido_cliente` with no `recibo_cliente` rows
- WHEN `PedidoClienteAdapter::getRecibos()` is called
- THEN it MUST return `[]`

### Requirement: `parentDocuments()` walk with dedup

`getRelatedDocuments()` (or its equivalent) MUST walk the parent
chain of the source document. The walk MUST deduplicate: a document
that appears as both a parent and a child MUST be returned exactly
once. The walk is driven by the `documentosrelacionados` setting
mode (0=off, 1=parents only, 2=parents+children).

#### Scenario: Mode 1 returns only the parent chain

- GIVEN a `factura_cliente` with parent `albaran_cliente` rows A and B
- WHEN the adapter is read with `documentosrelacionados=1`
- THEN the returned collection MUST contain A and B
- AND the collection MUST NOT contain any `factura_cliente` rows that are children of A or B

#### Scenario: Mode 2 returns the full graph (parents + children), deduped

- GIVEN a document chain A → B → A (A is a parent of B and B is a parent of A) and no other related rows
- WHEN the adapter is read with `documentosrelacionados=2`
- THEN the returned collection MUST contain A exactly once
- AND the collection MUST contain B exactly once
- AND the total count MUST be 2 (asserted by `assertCount(2, $collection)`)

#### Scenario: Mode 0 returns an empty collection

- GIVEN a `factura_cliente` with parent rows
- WHEN the adapter is read with `documentosrelacionados=0`
- THEN the returned collection MUST be `[]`

### Requirement: `getDocumentTypeLabel()` per-tipo `FormatoDocumento` override

Each `*PrintView` subclass MUST override `getDocumentTypeLabel()` to
read `formato_documento->titulo` (resolved via the source document's
`idformato`) first, falling back to the current hardcoded literal
(`'Factura'`, `'Albarán'`, `'Pedido'`, `'Presupuesto'`) when the
formato row is `null` or has no `titulo`.

#### Scenario: Formato titulo overrides the hardcoded literal

- GIVEN a `factura_cliente` with `idformato=3` and a `formato_documento` row with `id=3` and `titulo='Factura Proforma'`
- WHEN `FacturaPrintView::getDocumentTypeLabel()` is called
- THEN it MUST return `Factura Proforma`

#### Scenario: Null formato falls back to the hardcoded literal

- GIVEN a `factura_cliente` with `idformato=999999` (no matching `formato_documento`)
- WHEN `FacturaPrintView::getDocumentTypeLabel()` is called
- THEN it MUST return `Factura`

#### Scenario: Each `*PrintView` has its own hardcoded fallback

- GIVEN a `albaran_cliente` with no formato
- WHEN `AlbaranPrintView::getDocumentTypeLabel()` is called
- THEN it MUST return `Albarán`

- GIVEN a `pedido_cliente` with no formato
- WHEN `PedidoPrintView::getDocumentTypeLabel()` is called
- THEN it MUST return `Pedido`

- GIVEN a `presupuesto_cliente` with no formato
- WHEN `PresupuestoPrintView::getDocumentTypeLabel()` is called
- THEN it MUST return `Presupuesto`

## REMOVED Requirements

_None. The existing 6 requirements of `invoice-pdf-adapters` remain
valid; the contract requirement is EXTENDED (in the MODIFIED section)
and 7 new getter/override requirements are added._
