# Delta for invoice-pdf-public-endpoint

## Purpose

This change is a **major engine swap** for `plugins/factura_pdf1/`:
the mpdf + Twig pipeline shipped in the archived
`factura-pdf1-render-fidelity` cycle is replaced with a Cezpdf
pipeline. The public endpoint URL contract
(`?page=factura_detallada&id=N`) is **preserved unchanged** — the
endpoint resolves the id, picks the right
`PrintableDocumentInterface` adapter, and now streams a Cezpdf
PDF (not an mpdf-rendered PDF).

This delta closes the **test-bypass gap** that let the tpvmod
URL helper issue slip through in the prior change: the new
`RealHttpEndpointTest` exercises the **real HTTP stack** (not
`Request::create()`) and asserts a valid PDF response for the
two highest-risk doc types. The existing
`Request::create()`-based `PublicEndpointTest` is kept (it
covers pedido and presupuesto) but is no longer the only
integration test path.

The pedido/presupuesto scenarios added by the previous cycle
are updated to reference the Cezpdf pipeline (not the
"mpdf + Twig pipeline" string in the previous delta).

## MODIFIED Requirements

### Requirement: Pedido render via public endpoint returns a valid PDF

A request to the public endpoint with `?tipo=pedido` MUST resolve
to a `PedidoClienteAdapter`, render the pedido through the
**Cezpdf pipeline** (`CezpdfRenderService` →
`PortedPdfDocument`), and stream a valid PDF. The
`?page=factura_detallada&id=1&tipo=pedido` URL MUST keep
working end-to-end.

(Previously: the scenario asserted the render went through "the
same mpdf + Twig pipeline"; that pipeline is removed. The Cezpdf
pipeline replaces it.)

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
through the **Cezpdf pipeline** (`CezpdfRenderService` →
`PortedPdfDocument`), and stream a valid PDF.

(Previously: the scenario asserted the render went through "the
same mpdf + Twig pipeline"; that pipeline is removed. The Cezpdf
pipeline replaces it.)

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

## ADDED Requirements

### Requirement: TRUE HTTP integration test exercises the real index.php

The plugin MUST include a true integration test
(`tests/Integration/RealHttpEndpointTest.php`) that exercises
the **real HTTP stack** (not a programmatic `Request::create()`)
to catch issues that the unit-level `Request::create()` tests
cannot. This closes the test-bypass gap that let the tpvmod URL
helper issue slip through in the previous change: when
`Request::create()` is used, the URL contract is not actually
exercised against the framework router, and helper functions
like `tpvmod`'s URL generator can drift from the endpoint's
actual route without any test failure. A real HTTP round-trip
catches this class of regression.

The test MUST hit `http://localhost/index.php?page=factura_detallada&id=N`
(or the equivalent ddev URL) via curl or any equivalent real
HTTP client, and MUST assert the response is a valid Cezpdf PDF.
The test MUST skip via `markTestSkipped('ddev not running')` when
the ddev web server is unreachable.

#### Scenario: `?page=factura_detallada&id=1` via real HTTP returns a valid PDF

- GIVEN the ddev web server is running on `http://localhost`
- AND a `factura_cliente` with `idfactura=1` is persisted
- AND a logged-in session is established (test logs in first or seeds the auth cookie)
- WHEN the test sends a real HTTP GET to `http://localhost/index.php?page=factura_detallada&id=1`
- THEN the response MUST be HTTP 200
- AND `Content-Type` MUST be `application/pdf`
- AND the body MUST start with `%PDF-`
- AND the body MUST match the byte-equality fixture at `tests/Fixtures/legacy_invoice_FACT20260001.pdf` for the same seed data
- AND the test MUST be deterministic

#### Scenario: `?page=factura_detallada&id=1&tipo=albaran` via real HTTP returns a valid PDF

- GIVEN the ddev web server is running on `http://localhost`
- AND an `albaran_cliente` with `idalbaran=1` is persisted
- AND a logged-in session is established
- WHEN the test sends a real HTTP GET to `http://localhost/index.php?page=factura_detallada&id=1&tipo=albaran`
- THEN the response MUST be HTTP 200
- AND `Content-Type` MUST be `application/pdf`
- AND the body MUST start with `%PDF-`
- AND the rendered PDF MUST contain albaran-specific data (not factura data) — asserted by extracting the albaran `codigo` and `fecha` via `smalot/pdfparser`

#### Scenario: Ddev not running skips the test gracefully

- GIVEN the ddev web server is NOT running on `http://localhost`
- WHEN the test runs
- THEN `markTestSkipped('ddev not running; integration test requires ddev')` is called
- AND no failure is reported

#### Scenario: Real HTTP failure surfaces a meaningful error message

- GIVEN the ddev web server is running
- WHEN the test sends a real HTTP GET and the response is NOT HTTP 200 (e.g. 500 from a server error)
- THEN the test MUST fail with a message that includes the response body (so the operator can debug the Cezpdf failure)

## REMOVED Requirements

_None. The 6 existing requirements of `invoice-pdf-public-endpoint`
remain valid; the pedido/presupuesto requirements are updated
(in the MODIFIED section) to reference the Cezpdf pipeline, and
a new TRUE HTTP integration test requirement is added._
