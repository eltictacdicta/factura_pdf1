# Delta for invoice-pdf-public-endpoint

## Purpose

This change closes SUGGESTION #2 from the prior
`adapt-factura-pdf1-to-fsframework` verify-report: pedido and
presupuesto renders via the public endpoint are now end-to-end
tested. The `PublicEndpointTest` previously only covered `factura`
and `albaran`; this delta adds the two missing scenarios (and
requires that any runtime defect they expose is fixed in the same
change, per the user's product decision).

## MODIFIED Requirements

_No existing requirement in the source-of-truth
`invoice-pdf-public-endpoint/spec.md` is being modified. The URL
contract, content-type requirement, 404 behavior, and tpvmod URL
pin all remain valid as written; the public endpoint's response
shape is unchanged. This change is a pure addition of test
coverage._

## ADDED Requirements

### Requirement: Pedido render via public endpoint returns a valid PDF

A request to the public endpoint with `?tipo=pedido` MUST resolve
to a `PedidoClienteAdapter`, render the pedido through the same
mpdf + Twig pipeline, and stream a valid PDF. The 2 scenario
"Successful factura render" and "Albaran render shares the same
content type" from the source-of-truth spec are extended to include
pedido and presupuesto (per the user's product decision in the
proposal round).

#### Scenario: Pedido render via `?tipo=pedido` returns a valid PDF

- GIVEN a `pedido_cliente` with `idpedido=1` is persisted
- WHEN the public endpoint is hit with `?page=factura_detallada&id=1&tipo=pedido`
- THEN the response MUST be HTTP 200
- AND `Content-Type` MUST be `application/pdf`
- AND the body MUST start with `%PDF-`
- AND the body length MUST be ≥ 1024 bytes
- AND `Content-Disposition` MUST equal `pedido-1.pdf`

#### Scenario: Pedido render uses the same content type as factura

- GIVEN a `pedido_cliente` with `idpedido=1` is persisted
- WHEN the endpoint is called with `id=1` and the request signals pedido
- THEN `Content-Type` MUST be `application/pdf`
- AND the body MUST start with `%PDF-`

### Requirement: Presupuesto render via public endpoint returns a valid PDF

A request to the public endpoint with `?tipo=presupuesto` MUST
resolve to a `PresupuestoClienteAdapter`, render the presupuesto
through the same mpdf + Twig pipeline, and stream a valid PDF.

#### Scenario: Presupuesto render via `?tipo=presupuesto` returns a valid PDF

- GIVEN a `presupuesto_cliente` with `idpresupuesto=1` is persisted
- WHEN the public endpoint is hit with `?page=factura_detallada&id=1&tipo=presupuesto`
- THEN the response MUST be HTTP 200
- AND `Content-Type` MUST be `application/pdf`
- AND the body MUST start with `%PDF-`
- AND the body length MUST be ≥ 1024 bytes
- AND `Content-Disposition` MUST equal `presupuesto-1.pdf`

#### Scenario: Presupuesto render uses the same content type as factura

- GIVEN a `presupuesto_cliente` with `idpresupuesto=1` is persisted
- WHEN the endpoint is called with `id=1` and the request signals presupuesto
- THEN `Content-Type` MUST be `application/pdf`
- AND the body MUST start with `%PDF-`

## REMOVED Requirements

_None. The existing 5 requirements of `invoice-pdf-public-endpoint`
remain valid; this change is a pure addition of 2 new test
scenarios (one per missing tipo)._
