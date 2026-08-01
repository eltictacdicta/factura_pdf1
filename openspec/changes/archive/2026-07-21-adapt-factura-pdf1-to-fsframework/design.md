# Design: Adapt FacturaPDF1 to FSFramework (replaces `factura_detallada`)

## Technical Approach

`plugins/factura_pdf1/` is a **new** plugin that mirrors the `plugins/factura_detallada/` skeleton (Composer + vendored `vendor/` + `composer_autoload.php` + `Init.php` + lowercase `controller/` shim + PSR-4 `Controller/`/`Services/`/`Model/` + mpdf + Twig) and extends it to cover the 4 client document types and 30 settings that the upstream FacturaScripts `FacturaPDF1` plugin provides. The renderer is decoupled from the 4 different `*_cliente` line/iva tables by a `PrintableDocumentInterface` that all four adapters implement. Settings live in a dedicated `factura_pdf1_settings` table (JSON column + `current_version`) rather than `fs_var` because 30 keys exceed the latter's ergonomics. The public URL `?page=factura_detallada&id=N` is preserved verbatim because `plugins/tpvmod/controller/tpvmod.php:206` hardcodes it as a fallback. The mpdf HTML/CSS layout replaces Cezpdf pixel parity (LOW visual fidelity is the accepted trade-off; the regression test asserts magic bytes + page count + key text, not byte equality).

## Architecture Decisions

| # | Title | Choice | Alternatives considered | Rationale |
|---|-------|--------|------------------------|-----------|
| **AD-1** | Skeleton source | Reuse `factura_detallada/` skeleton (composer, Init, shim, mpdf, PSR-4). | Fork from scratch; thin shim over upstream `FacturaPDF1/`. | Skeleton is proven; fork would duplicate ~80% of stable plumbing; shim is bigger than the port. |
| **AD-2** | Settings storage | Dedicated `factura_pdf1_settings` table (JSON column). | `fs_var`; one-row-per-key EAV; YAML file. | 30 keys + JSON column + `current_version` migrations beat `fs_var` (no JSON, no version); EAV explodes row count; YAML forbids atomic save. |
| **AD-3** | Renderer polymorphism | `PrintableDocumentInterface` + 4 adapters (Factura/Albaran/Pedido/Presupuesto). | One mega-class with `match($docType)`; per-doc-type controllers. | Interface keeps the renderer and Twig template single-path; mega-class breaks OCP and amplifies test surface. |
| **AD-4** | Public URL name | Preserve `?page=factura_detallada` (lowercase shim). | Switch to `?page=factura_pdf1`. | `plugins/tpvmod/controller/tpvmod.php:206` hardcodes the literal; an integration test pins it. |
| **AD-5** | PDF layout engine | mpdf HTML/CSS (Twig template). | Preserve Cezpdf pixel parity. | Cezpdf is not vendored; mpdf is the only PDF backend approved; user accepted LOW visual fidelity; golden-PDF regression test asserts structural fidelity only. |
| **AD-6** | PDF regression test | Structural fidelity: `%PDF-` magic + page count + key text per adapter. | Byte equality; visual diff. | mpdf timestamps/fonts drift; byte equality is brittle; visual diff needs headless browser infra we don't have. |
| **AD-7** | Settings persistence shape | Single-row singleton (`name='default'`) with JSON column + `current_version` integer. | One row per key; multi-row (one per scope). | Single-row is the upstream's pattern; one-row-per-key inflates joins; multi-scope is not in scope. |
| **AD-8** | Twig template organization | One `pdf.html.twig` + 9 partials (per block). | One monolithic template. | Mirrors `plugins/factura_detallada/view/factura_detallada/pdf.html.twig` + 9 `partials/_*.html.twig` already in production; the 4 adapters render the same template. |

## Data Flow

**Public endpoint (PDF render):**

```
HTTP GET /index.php?page=factura_detallada&id=N
        |
        v
controller/factura_detallada.php   (legacy shim, 29 lines)
        | extends
        v
Controller\FacturaPdf1Controller::processRequest()
        |
        |-- validate id (getInt, <= 0 -> 404)
        |
        v
Services\SettingsService::load()    (reads factura_pdf1_settings row)
        |
        v
Model\Adapters\XxxAdapter::fromId(N)  (throws PrintableDocumentNotFoundException -> 404)
        |
        v
Model\View\XxxPrintView             (joins lineas + iva + IRPF + RE + empresa + cliente + ...)
        |
        v
Services\PdfRenderService::render(view, settings)
        |   Twig render(view/factura_pdf1/pdf.html.twig + 9 partials)
        |   mpdf WriteHTML -> Output('S') -> binary string
        v
Symfony Response(application/pdf, body=%PDF-...)
```

**Admin POST (settings save):**

```
HTTP POST /index.php?page=admin_factura_pdf1
        |
        v
Controller\Admin\FacturaPdf1SettingsController::private_core()
        |
        |-- $this->isCsrfValid() == false -> 403 (no save)
        |
        |-- bind form (Symfony Request -> 30 keys)
        |-- validate (color hex regex etc.)
        |-- if 'reset' action -> defaults()
        |
        v
Services\SettingsService::save(array, currentVersion+1)  (atomic: BEGIN; UPDATE row; COMMIT)
        |
        v
Response::redirect('?page=admin_factura_pdf1')  (302 + success flash)
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `plugins/factura_pdf1/composer.json` | Create | Mirror `plugins/factura_detallada/composer.json`; `mpdf/mpdf` ^8.0; no phpmailer (out of scope). |
| `plugins/factura_pdf1/composer.lock` | Create | Lockfile for the vendored deps. |
| `plugins/factura_pdf1/vendor/**` | Create | `git add plugins/factura_pdf1/vendor/` per `AGENTS.md` plugin-vendor rule. `/vendor/` is NOT in `.gitignore`. |
| `plugins/factura_pdf1/composer_autoload.php` | Create | Vendor-load shim mirroring `plugins/factura_detallada/composer_autoload.php`. |
| `plugins/factura_pdf1/fsframework.ini` | Create | `name=factura_pdf1`; `version=1`; `min_version=0.14`; `require=clientes_facturacion,catalogo_core,business_data,clientes_core`. |
| `plugins/factura_pdf1/facturascripts.ini` | Create | Legacy compat for the upstream installer; same shape as `plugins/factura_detallada/facturascripts.ini`. |
| `plugins/factura_pdf1/Init.php` | Create | Namespaced `FSFramework\Plugins\factura_pdf1\Init`; `registerTwigPaths()` mirrors `plugins/factura_detallada/Init.php`. |
| `plugins/factura_pdf1/Controller/FacturaPdf1Controller.php` | Create | Public endpoint; `processRequest()` returns `Response`; calls `XxxAdapter::fromId()`; `private_core()` checks `?page=factura_detallada`. |
| `plugins/factura_pdf1/Controller/Admin/FacturaPdf1SettingsController.php` | Create | Admin page; CSRF check, form bind, `SettingsService::save()` (atomic), `defaults()` on reset, redirect. |
| `plugins/factura_pdf1/controller/factura_detallada.php` | Create | 29-line legacy shim extending `FacturaPdf1Controller` (mirrors `plugins/factura_detallada/controller/factura_detallada.php`). |
| `plugins/factura_pdf1/controller/admin_factura_pdf1.php` | Create | 29-line legacy shim extending `FacturaPdf1SettingsController`. |
| `plugins/factura_pdf1/Services/PdfRenderService.php` | Create | mpdf + Twig wrapper; `render(PrintableDocumentInterface, array): string`; mirrors `plugins/factura_detallada/Services/PdfRenderService.php` but typed against the interface. |
| `plugins/factura_pdf1/Services/SettingsService.php` | Create | `load(): array` (default merge + forward-compat), `save(array, int): void` (atomic), `defaults(): array`, `currentVersion(): int`, `applyMigrations(int): void`, `reset(): void`. |
| `plugins/factura_pdf1/Services/EmpresaLogoResolver.php` | Create | Mirror `plugins/factura_detallada/Services/EmpresaLogoResolver.php`. |
| `plugins/factura_pdf1/Model/PrintableDocumentInterface.php` | Create | The interface contract (see §Interfaces). |
| `plugins/factura_pdf1/Model/FacturaPdf1Setting.php` | Create | `fs_model` subclass; columns: `id`, `name`, `settings_json`, `current_version`, `created_at`, `updated_at`. |
| `plugins/factura_pdf1/Model/Adapters/FacturaClienteAdapter.php` | Create | `static fromId(int): self`; maps `factura_cliente` + `linea_factura_cliente` + `linea_iva_factura_cliente`. |
| `plugins/factura_pdf1/Model/Adapters/AlbaranClienteAdapter.php` | Create | Same shape, maps `albaran_cliente` tables. |
| `plugins/factura_pdf1/Model/Adapters/PedidoClienteAdapter.php` | Create | Same shape, maps `pedido_cliente` tables. |
| `plugins/factura_pdf1/Model/Adapters/PresupuestoClienteAdapter.php` | Create | Same shape, maps `presupuesto_cliente` tables. |
| `plugins/factura_pdf1/Model/View/FacturaPrintView.php` | Create | Read-only view-model joining factura + lineas + iva + RE/IRPF + empresa + cliente + divisa + formaPago + pais. |
| `plugins/factura_pdf1/Model/View/AlbaranPrintView.php` | Create | Same shape, albaran tables; IRPF falls back to 0. |
| `plugins/factura_pdf1/Model/View/PedidoPrintView.php` | Create | Same shape, pedido tables. |
| `plugins/factura_pdf1/Model/View/PresupuestoPrintView.php` | Create | Same shape, presupuesto tables. |
| `plugins/factura_pdf1/Model/Exception/PrintableDocumentNotFoundException.php` | Create | Single exception type across all 4 adapters (per `invoice-pdf-adapters` spec). |
| `plugins/factura_pdf1/model/table/factura_pdf1_settings.xml` | Create | XML schema: `id`, `name` (unique), `settings_json` (JSON), `current_version` (integer), `created_at`, `updated_at`. |
| `plugins/factura_pdf1/view/factura_pdf1/pdf.html.twig` | Create | Body template; CSS variables for the 30 settings; `{% include 'factura_pdf1/partials/_*.html.twig' %}`. |
| `plugins/factura_pdf1/view/factura_pdf1/partials/_*.html.twig` (×9) | Create | `_company_header`, `_corporate_image`, `_parties_header`, `_invoice_number_date`, `_line_items`, `_vat_breakdown`, `_totals`, `_payment_footer`, `_client_billing` — mirrors `plugins/factura_detallada/view/factura_detallada/partials/`. |
| `plugins/factura_pdf1/themes/AdminLTE/view/admin/factura_pdf1/settings.html.twig` | Create | Admin form; 30 widgets grouped `logo / layout / lines / totals / footer`; `{{ csrf_field() }}`; reset button. |
| `plugins/factura_pdf1/translations/messages.es.yaml` | Create | es_ES baseline; `factura-pdf1.*` key prefix. |
| `plugins/factura_pdf1/translations/messages.en.yaml` | Create | en_EN baseline. |
| `plugins/factura_pdf1/tests/Unit/...` | Create | Per-adapter `fromId` tests; `SettingsService` JSON round-trip; init-upgrade migration tests; `current_version` no-op. |
| `plugins/factura_pdf1/tests/Integration/...` | Create | Public endpoint: `Content-Type: application/pdf`, `%PDF-` magic, 404 on missing/non-numeric id, tpvmod URL literal pinned. |
| `plugins/factura_pdf1/tests/Regression/GoldenPdfTest.php` | Create | Structural-fidelity assertions for fixture `FAKT-2026-0001` (1 page, A4 595pt, contains numero + cliente + total). |
| `plugins/factura_pdf1/tests/Security/...` | Create | SQL-injection grep; Twig `|raw` grep on user data; CSRF reject on admin POST. |
| `plugins/factura_pdf1/tests/Controller/Admin/...` | Create | CSRF happy/sad path, save redirect, reset-to-defaults, es_ES/en_EN render. |
| `plugins/factura_pdf1/tests/Fixtures/factura_cliente.seed.php` | Create | Deterministic seed for the golden PDF. |
| `plugins/factura_pdf1/phpunit.xml` | Create | Bootstrap mirror; `tests/` discovery. |
| `plugins/factura_pdf1/phpstan.neon` | Create | Level project; paths to the plugin's `Controller/`, `Services/`, `Model/`. |
| `plugins/factura_pdf1/README.md` | Create | Operator runbook; sub-repo boundary warning; rollback note. |
| `plugins/factura_pdf1/LICENSE` | Create | LGPL-3.0-or-later. |
| `plugins/FacturaPDF1/**` | (no edit) | Untouched. Removal deferred to follow-up archive change. |
| `plugins/factura_detallada/**` | (no edit) | Untouched. Deprecation deferred to follow-up archive change. |
| `plugins/tpvmod/controller/tpvmod.php` | (no edit) | Line 206 URL literal is the contract; pinned by integration test. |
| `base/`, `src/`, `controller/` (root), `model/` (root) | (no edit) | Core is not touched. Plugin-local SDD per `AGENTS.md`. |

## Interfaces / Contracts

```php
namespace FSFramework\Plugins\factura_pdf1\Model;

interface PrintableDocumentInterface
{
    public function getId(): int;
    public function getCodigo(): string;
    public function getFecha(): string;             // ISO yyyy-mm-dd
    public function getCliente(): \FSFramework\model\cliente;
    /** @return list<array{codigo:string,descripcion:string,cantidad:float,pvpunitario:float,pvptotal:float,iva:float,total:float}> */
    public function getLineas(): array;
    /** @return array{total:float,totaliva:float,netosindto:float,dtopor1:float,dtopor2:float,totalirpf:float,totalsuplidos:float,totales:float} */
    public function getTotales(): array;
    public function getFormaPago(): ?\forma_pago;
    public function getVencimiento(): ?string;
    /** @return list<\FSFramework\model\factura_cliente|\FSFramework\model\albaran_cliente> */
    public function getRelatedDocuments(): array;
    public function getIban(): ?string;
    /** @return array{codtrans:?string,codigoenv:?string}|null */
    public function getCarrier(): ?array;
    /** @return list<array{fecha:string,importe:float}> */
    public function getPaymentBreakdown(): array;
    public function getObservaciones(): ?string;
}
```

```php
namespace FSFramework\Plugins\factura_pdf1\Services;

final class SettingsService
{
    /** @return array<string,mixed> */
    public function load(): array;
    /** @param array<string,mixed> $settings */
    public function save(array $settings): void;          // atomic, bumps current_version
    /** @return array<string,mixed> */
    public function defaults(): array;
    public function currentVersion(): int;
    public function applyMigrations(int $fromVersion): void;
    public function reset(): void;                        // writes defaults + bump version
}
```

```php
namespace FSFramework\Plugins\factura_pdf1\Services;

final class PdfRenderService
{
    public function render(\FSFramework\Plugins\factura_pdf1\Model\PrintableDocumentInterface $doc, array $settings): string;
    // returns PDF binary starting with '%PDF-'; throws on missing template
}
```

```php
namespace FSFramework\Plugins\factura_pdf1\Model;

final class FacturaPdf1Setting extends \fs_model
{
    public string $name = 'default';
    public string $settings_json = '{}';
    public int $current_version = 0;
    public string $created_at;
    public string $updated_at;
    // test() validates JSON + version; save() runs the atomic UPDATE in a transaction
}
```

Each adapter: `public static function fromId(int $id): self` (throws `PrintableDocumentNotFoundException` on miss); constructor `private`; public getters implement `PrintableDocumentInterface`.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|--------------|----------|
| Unit (adapters) | 4 `fromId()` paths + not-found | Mock `fs_db2` (anonymously subclass `fs_model` for fixtures, per `plugins/factura_detallada/tests/` pattern); assert `PrintableDocumentInterface` shape and `PrintableDocumentNotFoundException`. |
| Unit (settings) | JSON round-trip; `defaults()`; missing-key fallback; unknown-key forward-compat | In-memory `FacturaPdf1Setting` with mock `fs_db2`; assert `array_key_exists` for every known + an injected unknown. |
| Unit (init-upgrade) | `current_version` migration: `mostrarpais→ocultarpais`, `ocultarreferenciasfact→documentosrelacionados`; no-op at parity | Seed row at `current_version=1`; run `applyMigrations(1)`; assert new shape + bumped version. |
| Unit (CSRF) | Admin POST without token | Reuse `plugins/factura_detallada/tests/Security/` style: assert `$this->isCsrfValid()===false` and zero `save()` calls. |
| Integration (public endpoint) | `?page=factura_detallada&id=N` returns `application/pdf` + `%PDF-`; 404 on missing/non-numeric/zero/negative id; 404 JSON on valid-but-missing id | PHPUnit `Integration/` boot against DDEV; curl-or-invoke the controller; assert headers and body. |
| Integration (tpvmod pin) | `plugins/tpvmod/controller/tpvmod.php` still contains literal `'./index.php?page=factura_detallada&id='`; skip via `markTestSkipped` if missing | Read the file as text, `assertStringContainsString`. |
| Regression (golden PDF) | Structural fidelity per adapter | Render fixed fixture; assert page count=1, page width=595pt, text extract contains numero+cliente+total. NOT byte equality. Use `smalot/pdfparser` (already in `factura_detallada/composer.json` require-dev). |
| Security (grep) | No `Cezpdf|FacturaScripts\\Core\\Lib\\PDF` outside vendor; no `|raw` on user-data Twig expressions; no SQL concatenation with `$_GET`/`$_POST` | Static regex over the plugin's PHP + Twig tree. |
| E2E (smoke) | Real DDEV: `curl /index.php?page=factura_detallada&id=N` returns 200 + PDF for each of the 4 doc types | Manual or scripted, listed in the proposal's success criteria. |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary is touched. The plugin is HTTP-in / PDF-out only; the PDF engine (mpdf) is a vendored library with its own threat model. The admin endpoint reuses the existing CSRF + Symfony `Request`/`Response` patterns; no new boundary is introduced.

## Migration / Rollout

The change ships across 3 chained PRs (orchestrator will re-confirm the split with the user per `ask-always`):

- **PR-1 — bootstrap + data** (~350 LoC, within budget): `composer.json` + `composer.lock` + `vendor/` (committed per the plugin-vendor rule), `fsframework.ini`, `facturascripts.ini`, `Init.php`, `composer_autoload.php`, the `factura_pdf1_settings` table + XML + `Model/FacturaPdf1Setting.php` + `Services/SettingsService.php`, `Model/PrintableDocumentInterface.php` + 4 empty adapter stubs, 4 `*PrintView.php` skeletons, `Model/Exception/PrintableDocumentNotFoundException.php`, `phpunit.xml`, `phpstan.neon`, `README.md`, `LICENSE`, PHPUnit scaffolding. **Standalone.** No controller or template yet.
- **PR-2 — PDF rendering + endpoint** (~550 LoC, requires `size:exception` in PR body): `Services/PdfRenderService.php`, full `view/factura_pdf1/pdf.html.twig` + 9 partials, `Controller/FacturaPdf1Controller.php`, legacy `controller/factura_detallada.php` shim, all 4 adapter implementations, `GoldenPdfTest` regression, `tests/Integration/` for the public endpoint. Requires PR-1 on the target branch.
- **PR-3 — admin + i18n + tests** (~600 LoC, requires `size:exception`): `Controller/Admin/FacturaPdf1SettingsController.php`, `themes/AdminLTE/view/admin/factura_pdf1/settings.html.twig` (30 widgets + CSRF + reset), `translations/messages.{es,en}.yaml`, full `tests/Security/` and `tests/Controller/Admin/` suites, `init-upgrade` migration tests, settings-coverage test, phpstan. Requires PR-1 and PR-2.

Operator rollout: ship PR-1 → PR-2 (verify smoke `curl /index.php?page=factura_detallada&id=N` returns 200 + `%PDF-` for a real id) → PR-3 (admin save round-trip; verify one widget changes the rendered PDF) → follow-up archive change deprecates `factura_detallada/` and removes `plugins/FacturaPDF1/`.

## Open Questions

None at this time. The 5 critical user decisions are locked (REPLACE `factura_detallada`; lowercase `factura_pdf1`; LOW visual fidelity; dedicated `factura_pdf1_settings` table; 4 client doc types behind `PrintableDocumentInterface`). The 3-PR split is forecast and will be re-confirmed with the user before `sdd-tasks` locks the final shape. If `sdd-apply` discovers a missing core helper (e.g. an `IBANResolver` for `cuenta_banco_cliente`, or a generic `PrintableDocument` interface in `src/`), the apply agent MUST surface it as a **follow-up core change** per `AGENTS.md` "OpenSpec per Plugin" — not absorb it into this plugin-local SDD.
