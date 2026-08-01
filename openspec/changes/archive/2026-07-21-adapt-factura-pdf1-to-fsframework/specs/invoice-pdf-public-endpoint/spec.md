# invoice-pdf-public-endpoint Specification

## Purpose

Defines the public print endpoint of the `factura_pdf1` plugin, served
at the legacy URL `?page=factura_detallada&id=N`. This URL is
hardcoded in `plugins/tpvmod/controller/tpvmod.php:206` and is the
**only** public page name for the 4 client document types. The endpoint
resolves the id, picks the right `PrintableDocumentInterface` adapter,
and streams a PDF. Derives from the proposal §"Capabilities" →
"invoice-pdf-public-endpoint" and the §"Approach" decision to keep the
URL contract unchanged. Plugin is licensed LGPL-3.0-or-later.

## Requirements

### Requirement: URL contract preserved

The public endpoint MUST be served at `?page=factura_detallada&id={N}`.
No other public page name is exposed by the plugin. The id query
parameter MUST be read via `$this->request->query->getInt('id')`.

#### Scenario: GET with a numeric id reaches the endpoint

- GIVEN a logged-in user with read access
- WHEN `?page=factura_detallada&id=1` is requested
- THEN the public endpoint handler runs
- AND no other page name in the plugin responds to the same id

### Requirement: PDF content type and body

A successful response MUST set `Content-Type: application/pdf` and the
body MUST be non-empty and begin with the bytes `%PDF-`.

#### Scenario: Successful factura render

- GIVEN a `factura_cliente` with idfactura=1
- WHEN the endpoint is called with `id=1`
- THEN `Content-Type` is `application/pdf`
- AND the body starts with `%PDF-`
- AND the body length is ≥ 1024 bytes

#### Scenario: Albaran render shares the same content type

- GIVEN an `albaran_cliente` with idalbaran=1
- WHEN the endpoint is called with `id=1` and the request signals albaran
- THEN `Content-Type` is `application/pdf`
- AND the body starts with `%PDF-`

### Requirement: 404 on missing or non-numeric id

A request whose `id` is missing, non-numeric, or non-positive MUST
return HTTP 404. The id MUST be validated before any model lookup.

#### Scenario: Missing id returns 404

- GIVEN `?page=factura_detallada` with no `id` parameter
- WHEN the request is made
- THEN the response is HTTP 404

#### Scenario: Non-numeric id returns 404

- GIVEN `?page=factura_detallada&id=abc`
- WHEN the request is made
- THEN the response is HTTP 404
- AND no `PrintableDocumentInterface::fromId()` call is attempted

#### Scenario: Zero or negative id returns 404

- GIVEN `?page=factura_detallada&id=0` (or a negative value)
- WHEN the request is made
- THEN the response is HTTP 404

### Requirement: 404 on valid id without a document

A request with a valid (numeric, positive) id whose document does not
exist MUST return HTTP 404 with a JSON body `{"error":"not_found"}`.
The 404 MUST be produced by catching
`PrintableDocumentNotFoundException` (per the
`invoice-pdf-adapters` spec).

#### Scenario: Missing factura returns 404 JSON

- GIVEN no `factura_cliente` with idfactura=999999
- WHEN the endpoint is called with `id=999999`
- THEN the response is HTTP 404
- AND the body is `{"error":"not_found"}`

#### Scenario: Missing albaran returns 404 JSON

- GIVEN no `albaran_cliente` with idalbaran=999999
- WHEN the endpoint is called with `id=999999` (albaran)
- THEN the response is HTTP 404
- AND the body is `{"error":"not_found"}`

### Requirement: tpvmod URL contract pin

An integration test MUST read
`plugins/tpvmod/controller/tpvmod.php` as text and assert the literal
substring `'./index.php?page=factura_detallada&id='` is present. The
test MUST skip via `markTestSkipped()` when the file is missing.

#### Scenario: Hardcoded URL literal is present

- GIVEN `plugins/tpvmod/controller/tpvmod.php` exists
- WHEN the test reads the file
- THEN the literal `'./index.php?page=factura_detallada&id='` is present

#### Scenario: Missing tpvmod file skips the test

- GIVEN `plugins/tpvmod/controller/tpvmod.php` does not exist
- WHEN the test runs
- THEN `markTestSkipped('tpvmod not installed')` is called
