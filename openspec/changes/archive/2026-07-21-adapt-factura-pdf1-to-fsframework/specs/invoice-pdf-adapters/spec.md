# invoice-pdf-adapters Specification

## Purpose

Defines the four client-document adapters and the
`PrintableDocumentInterface` that is the only shape the PDF renderer
sees. The adapters map the four upstream client document types
(`FacturaCliente`, `AlbaranCliente`, `PedidoCliente`,
`PresupuestoCliente`) onto a single common value shape, enabling the
renderer and the public endpoint (`?page=factura_detallada&id=N`) to be
fully polymorphic. Derives from the proposal §"Capabilities" →
"invoice-pdf-adapters" and the §"Approach" decision to keep the
renderer decoupled from the 4 different FS2025 line/iva/IRPF tables.
Plugin is licensed LGPL-3.0-or-later.

## Requirements

### Requirement: PrintableDocumentInterface contract

A `PrintableDocumentInterface` MUST be the only shape the PDF renderer
sees. The renderer MUST NOT import any concrete `*_cliente` class or
any of the per-doc line/iva tables. All four adapter types MUST
implement this interface.

#### Scenario: Renderer depends only on the interface

- GIVEN the renderer source
- WHEN a grep for `factura_cliente|albaran_cliente|pedido_cliente|presupuesto_cliente|linea_` runs
- THEN no matches appear outside the adapter namespace

#### Scenario: All four adapters implement the interface

- GIVEN the four adapter classes
- WHEN `instanceof PrintableDocumentInterface` is checked
- THEN all four return `true`

### Requirement: FacturaClienteAdapter shape

`FacturaClienteAdapter` MUST expose: `id`, `code` (=`numero`),
`date` (=`fecha`), `cliente`, `lineas` (each with `codigo`,
`descripcion`, `cantidad`, `pvpunitario`, `pvptotal`, `iva`, `total`),
`total`, `totaliva`, `totales`, `netosindto`, `dtopor1`, `dtopor2`,
`totalirpf`, `totalsuplidos`, `forma_pago`, `vencimiento`,
`related_documents`, `iban`, `carrier` (with `codtrans`, `codigoenv`),
`payment_breakdown`, `observaciones`. The mapping MUST source from
`factura_cliente` + `linea_factura_cliente` +
`linea_iva_factura_cliente`.

#### Scenario: Adapter exposes the canonical shape

- GIVEN a `factura_cliente` with idfactura=1
- WHEN `FacturaClienteAdapter::fromId(1)` returns
- THEN every field listed above is non-null and equals the source row value

#### Scenario: Empty related_documents is a non-null array

- GIVEN a factura with no related documents
- WHEN the adapter is read
- THEN `related_documents` is `[]` (not `null`)

### Requirement: AlbaranClienteAdapter shape

`AlbaranClienteAdapter` MUST expose the same shape as
`FacturaClienteAdapter`, mapped from `albaran_cliente` +
`linea_albaran_cliente` + `linea_iva_albaran_cliente`. Fields that do
not exist on an albarán (e.g. `totalirpf`) MUST fall back to a typed
zero/empty value.

#### Scenario: Adapter maps from albaran tables

- GIVEN an `albaran_cliente` with idalbaran=1
- WHEN `AlbaranClienteAdapter::fromId(1)` returns
- THEN `id`, `code`, `date`, `cliente`, `lineas`, and `total` equal the source row values

### Requirement: PedidoClienteAdapter shape

`PedidoClienteAdapter` MUST expose the same shape, mapped from
`pedido_cliente` + `linea_pedido_cliente` +
`linea_iva_pedido_cliente`.

#### Scenario: Adapter maps from pedido tables

- GIVEN a `pedido_cliente` with idpedido=1
- WHEN `PedidoClienteAdapter::fromId(1)` returns
- THEN `code`, `date`, `cliente`, `lineas`, and `total` match the pedido source

### Requirement: PresupuestoClienteAdapter shape

`PresupuestoClienteAdapter` MUST expose the same shape, mapped from
`presupuesto_cliente` + `linea_presupuesto_cliente` +
`linea_iva_presupuesto_cliente`.

#### Scenario: Adapter maps from presupuesto tables

- GIVEN a `presupuesto_cliente` with idpresupuesto=1
- WHEN `PresupuestoClienteAdapter::fromId(1)` returns
- THEN `code`, `date`, `cliente`, `lineas`, and `total` match the presupuesto source

### Requirement: fromId factory and not-found semantics

Each adapter MUST expose a static `fromId(int $id)` factory. When the
id does not resolve to a row, the factory MUST throw
`PrintableDocumentNotFoundException`, which the public endpoint maps
to HTTP 404.

#### Scenario: Existing id returns the adapter

- GIVEN a persisted `factura_cliente` with idfactura=1
- WHEN `FacturaClienteAdapter::fromId(1)` is called
- THEN it returns a `FacturaClienteAdapter` instance (no exception)

#### Scenario: Missing id throws PrintableDocumentNotFoundException

- GIVEN no `factura_cliente` with idfactura=999999
- WHEN `FacturaClienteAdapter::fromId(999999)` is called
- THEN it throws `PrintableDocumentNotFoundException`

#### Scenario: Same exception type across all 4 adapters

- GIVEN an `AlbaranClienteAdapter`, `PedidoClienteAdapter`, and `PresupuestoClienteAdapter`
- WHEN each `fromId(999999)` is called
- THEN all three throw `PrintableDocumentNotFoundException`
