# factura_pdf1

FSFramework port of the upstream FacturaScripts **FacturaPDF1** plugin. Replaces `factura_detallada` with multi-document PDF rendering (factura, albarán, pedido, presupuesto) via Cezpdf (the upstream `FacturaPDF1` engine) — no Twig, no mpdf.

## Status (PR-3)

- **PR-1**: bootstrap, `factura_pdf1_settings` table, `SettingsService`, adapter stubs, vendored Cezpdf (860 KB, 14 files).
- **PR-2**: PDF pipeline, public endpoint `?page=factura_detallada&id=N`, golden PDF regression (byte-equality, 2413 bytes).
- **PR-3**: per-setting coverage test against the Cezpdf-output signal convention (28/28 cases GREEN); TRUE HTTP integration test for the public endpoint; README + phpunit + PHPStan updates.

## Engine

The PDF engine is **Cezpdf** (vendored at `plugins/factura_pdf1/vendor/cezpdf/`). The previous mpdf + Twig pipeline was removed in PR-2. The byte-equality regression test (`tests/Regression/GoldenPdfTest.php`) renders the `SeedInvoiceFakt20260001` fixture and asserts the result is byte-identical to `tests/Fixtures/legacy_invoice_FACT20260001.pdf`.

### Regenerating the byte-equality fixture

If the Cezpdf port changes in a way that intentionally shifts the byte signature (e.g. font metric adjustments), regenerate the fixture with:

```bash
ddev exec php plugins/factura_pdf1/tests/Fixtures/generate_legacy_fixture.php
```

The script overwrites `tests/Fixtures/legacy_invoice_FACT20260001.pdf` with the current `CezpdfRenderService` output. The `GoldenPdfTest` also accepts the `REGENERATE_FIXTURE=1` env var to regenerate the fixture as a side-effect of the test run (handy for one-shot fixture refreshes during development):

```bash
ddev exec REGENERATE_FIXTURE=1 php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml --filter GoldenPdfTest
```

## Dependencies

- Core plugins: `clientes_facturacion`, `catalogo_core`, `business_data`, `clientes_core`
- PHP >= 8.2, Cezpdf (vendored under `plugins/factura_pdf1/vendor/cezpdf/`)

## Install / dev

```bash
ddev start
ddev exec composer install --working-dir=plugins/factura_pdf1
ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml
ddev exec php -d memory_limit=512M vendor/dev-tools/bin/phpstan analyse -c plugins/factura_pdf1/phpstan.neon
```

Activate the plugin from the admin plugin manager after install.

### Integration tests (opt-in)

The TRUE HTTP integration test (`tests/Integration/RealHttpEndpointTest.php`) hits `index.php?page=factura_detallada&id=N` via the ddev router. It is marked `@group integration` and excluded from the default phpunit run. Opt in with:

```bash
ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml --group integration
```

The integration tests skip when `ddev` is not running or the endpoint requires authentication (HTTP 302 to login). Authenticated integration tests are a follow-up SDD.

## Operator runbook

### Public PDF

```
GET /index.php?page=factura_detallada&id={idfactura}
GET /index.php?page=factura_detallada&id={id}&tipo=albaran|pedido|presupuesto
```

Expect HTTP 200, `Content-Type: application/pdf`, body starting with `%PDF-`.

### Admin settings

```
GET  /index.php?page=admin_factura_pdf1
POST /index.php?page=admin_factura_pdf1  (requires CSRF token from form)
```

- **Save**: POST all 28 upstream settings widgets; persists atomically to `factura_pdf1_settings.settings_json`.
- **Reset**: POST with `reset_defaults=1` and valid CSRF; restores documented defaults.

Smoke (with session cookie after login):

```bash
# Save round-trip (replace TOKEN and cookies from browser)
curl -sL -X POST 'https://<project>.ddev.site/index.php?page=admin_factura_pdf1' \
  -H 'Cookie: ...' \
  -d '_token=TOKEN&colorcabecera=%23FF0000&...' -w '%{http_code}\n' -o /dev/null
# Expect 302
```

Settings migrations run on plugin init (`mostrarpais` → `ocultarpais`, `ocultarreferenciasfact` → `documentosrelacionados`).

## Rollback

### PR-3 boundary (admin only)

1. Deactivate `factura_pdf1` or revert PR-3 files under `Controller/Admin/`, `controller/admin_factura_pdf1.php`, `themes/AdminLTE/view/admin/factura_pdf1/`, `translations/`, and related tests.
2. Public PDF endpoint from PR-2 continues to work with last saved settings.

### Full plugin removal

1. Deactivate `factura_pdf1` in the plugin manager.
2. `DROP TABLE factura_pdf1_settings;`
3. Re-enable `factura_detallada` if still installed.

## Sub-repo warning

This plugin is **parent-tracked** in the FSFramework monorepo. Do not initialize a nested `.git` directory here.

## License

LGPL-3.0-or-later — see [LICENSE](LICENSE).
