# Archive Report: factura-pdf1-render-fidelity

```yaml
schema: gentle-ai.archive-result/v1
change: factura-pdf1-render-fidelity
project: fs-framework
plugin: plugins/factura_pdf1/
ownership: plugin-local
artifact_store: hybrid
archive_root: plugins/factura_pdf1/openspec/changes/archive/2026-07-21-factura-pdf1-render-fidelity/
specs_root: plugins/factura_pdf1/openspec/specs/
proposed: 2026-07-21
verified: 2026-07-21
archived: 2026-07-21
gating_verdict: pass_with_warnings
supersedes:
  - AD-5 of archive/2026-07-21-adapt-factura-pdf1-to-fsframework/design.md
  - AD-8 of archive/2026-07-21-adapt-factura-pdf1-to-fsframework/design.md
```

## Outcome

**Archived successfully with carry-forward warnings.** The change closes the SDD cycle for `factura-pdf1-render-fidelity` per the plugin-local OpenSpec convention declared in `plugins/factura_pdf1/openspec/config.yaml` (`ownership: plugin-local`, `artifact_store: hybrid`). The 5 delta specs under the change's `specs/` directory were merged into the plugin's source-of-truth `plugins/factura_pdf1/openspec/specs/{domain}/spec.md` (delta format stripped; canonical post-change spec retained). The active change folder has been moved into the dated archive directory per the `archive_root` template. No core `openspec/` entries were created; core `openspec/` does not see this change per AGENTS.md "OpenSpec per Plugin".

## Phase Gates Recap

| Phase | Date | Verdict / Status | Engram ref |
|-------|------|------------------|------------|
| Propose | 2026-07-21 | Approved (19 features + 2 endpoint tests + 3 dead-partial REMOVEs; 2-PR chained forecast) | obs #369 (proposal) |
| Spec | 2026-07-21 | 5 domain deltas published (3 MODIFIED + 31 ADDED scenarios) | obs #371 (settings) · #372 (adapters) · #373 (admin) · #374 (public-endpoint) + invoice-pdf-rendering |
| Design | 2026-07-21 | 5 new ADs (AD-9…AD-13) added; AD-5 and AD-8 of prior design SUPERSEDED; chained-PR plan locked | (filesystem `design.md`) |
| Tasks | 2026-07-21 | 21 tasks across PR-1 (8) + PR-2 (9) + Phase 3 cleanup (4); TDD Cycle Evidence table | obs #376 (tasks) |
| Apply | 2026-07-21 | PR-1 (8/8) + PR-2 (9/9) complete; 148 tests / 387 assertions / 0 PHPStan errors; 3 hallazgos resolved via path (a) | obs #377 (apply-progress) |
| Verify | 2026-07-21 | `pass_with_warnings` — 85/85 scenarios COMPLIANT; 3 WARNINGs + 5 SUGGESTIONs from prior cycle; 0 CRITICAL | obs #380 (verify-report) |
| **Archive** | **2026-07-21** | **This report** | (this observation) |

## Specs Synced (Source of Truth Updated)

The 5 delta specs were merged into the existing source-of-truth `plugins/factura_pdf1/openspec/specs/{domain}/spec.md` (the source-of-truth was created during the prior `adapt-factura-pdf1-to-fsframework` archive; this change extends it). Delta format (`## MODIFIED Requirements` / `## ADDED Requirements` / `## REMOVED Requirements`) was stripped from the merged output; the source-of-truth files are the canonical post-change spec.

| Domain | Action | ADDED | MODIFIED | REMOVED | Source-of-truth path |
|--------|--------|-------|----------|---------|----------------------|
| `invoice-pdf-rendering` | Extended | 17 | 0 | 0 | `plugins/factura_pdf1/openspec/specs/invoice-pdf-rendering/spec.md` (5 → 22 requirements) |
| `invoice-pdf-settings` | Modified + extended | 2 | 1 | 0 | `plugins/factura_pdf1/openspec/specs/invoice-pdf-settings/spec.md` (6 → 8 requirements) |
| `invoice-pdf-adapters` | Modified + extended | 7 | 1 | 0 | `plugins/factura_pdf1/openspec/specs/invoice-pdf-adapters/spec.md` (6 → 13 requirements) |
| `invoice-pdf-admin` | Modified + extended | 2 | 1 | 0 | `plugins/factura_pdf1/openspec/specs/invoice-pdf-admin/spec.md` (5 → 7 requirements) |
| `invoice-pdf-public-endpoint` | Extended | 2 | 0 | 0 | `plugins/factura_pdf1/openspec/specs/invoice-pdf-public-endpoint/spec.md` (5 → 7 requirements) |
| **TOTAL** | | **30 ADDED** | **3 MODIFIED** | **0 REMOVED** | **27 → 57 requirements (+111%)** |

The single source-of-truth path for each domain is now the canonical contract. The original delta specs are preserved verbatim under `archive/2026-07-21-factura-pdf1-render-fidelity/specs/{domain}/spec.md` for historical record.

## Supersession Note

This change SUPERSEDES two architectural decisions from the prior design at `plugins/factura_pdf1/openspec/changes/archive/2026-07-21-adapt-factura-pdf1-to-fsframework/design.md`:

| Prior | New |
|-------|-----|
| **AD-5** (mpdf LOW fidelity; only `colorcabecera` drives render) | **SUPERSEDED.** Every persisted setting MUST have a distinctive effect on the rendered PDF. The `SettingsEffectCoverageTest` (AD-9) is the enforcement mechanism. |
| **AD-8** (one `pdf.html.twig` + 9 partials) | **SUPERSEDED.** Final structure: `pdf.html.twig` + 6 partials (3 dead partials removed) + 14 text-block partials (7 positions × 2 text blocks) + 1 new macro file (`view/factura_pdf1/macro/address.html.twig`). The 3 dead partials (`_client_billing`, `_company_header`, `_invoice_number_date`) are REMOVED; their content is already inlined in `_parties_header.html.twig`. |

The prior design stays as historical record. For render-fidelity decisions, **this design is the authoritative document**. AD-1, AD-2, AD-3, AD-4, AD-6, AD-7 from the prior design are KEPT (no change). The 5 new ADs (AD-9 through AD-13) lock the test/contract/loader patterns that close the audit's gap.

## Lesson Locked

The most important non-obvious outcome of this change is a **spec lesson** that the audit surfaced:

> **"Every setting has a widget"** was a spec-too-narrow that allowed 27 of 28 persisted settings to ship without any effect on the rendered PDF. The new rule, enforced by `SettingsEffectCoverageTest` (AD-9), is:
>
> **"Every persisted setting has a distinctive effect on the rendered PDF."**

The enforcement mechanism is the `data-<key>="<value>"` token convention (AD-10): every setting that drives a render feature MUST emit a `data-<key>="<value>"` attribute on the relevant DOM element, and the test renders the same fixture 28 times (each with one key set to a sentinel value) and asserts each render contains its sentinel token. If a future contributor wires a new setting to the admin form but forgets to wire it to the renderer, the test fails.

The "every setting has a widget" scenario that the prior spec asserted is **kept** in the modified "Settings coverage test" requirement (as the first sub-scenario of the new effect-based requirement), so future regressions on the widget path are still caught; but the second sub-scenario ("Every widget has a render effect") is now the dominant contract.

## What Was Delivered

### 17 render features implemented (rows 1, 2, 3, 4, 5, 6, 7, 8, 11, 12, 13, 14, 15, 16, 17, 18, 19 of the proposal's feature table)

Each feature maps one or more previously-dead settings to a real render path that emits a `data-<key>="<value>"` token asserted by the regression suite:

1. Logo with 4-position selector + margin + measure
2. Color-coded header rows + alternating row shading
3. `pagoyvencimiento` mode selector (4 modes)
4. IBAN injection (cliente IBAN → empresa fallback)
5. Carrier block (codtrans + codigoenv)
6. Shipping address block (idcontactoenv + ocultardireccionenvio)
7. Related documents block (parentDocuments walk + dedup)
8. Warehouse block (mostraralmacen 4 modes + titulo + tel)
9–10. texto1 + texto2 blocks (7 position modes each; 14 partials total)
11. Hide-product-reference toggle
12. Auto-collapse tax table when 1 or 2 taxes share the net
13. Hide province / hide country toggles
14. `ref2` (custom second customer reference, 3 modes)
15. Max-company-width (espaciomaximoempresa)
16. Page numbering footer via mpdf `SetFooter`
17. Per-tipo titulo from `FormatoDocumento`
18. Address splitting at parens when over `PARTIR_DIR` width
19. Auto-shrink company name to fit width

### 2 missing integration tests added

`PublicEndpointTest::testEndpointStreamsPdfForSeededPedido` and `::testEndpointStreamsPdfForSeededPresupuesto` (SUGGESTION #2 from the prior verify-report, closed per the user's product decision). Both passed on the first run (no defect fix needed).

### 3 dead partials removed

- `view/factura_pdf1/partials/_client_billing.html.twig` (content inlined in `_parties_header.html.twig`)
- `view/factura_pdf1/partials/_company_header.html.twig` (same)
- `view/factura_pdf1/partials/_invoice_number_date.html.twig` (same)
- `view/factura_pdf1/pdf.html.twig` was updated to drop the 3 `{% block %}` stubs and the `_corporate_image` include.
- `_corporate_image.html.twig` is kept on disk as a 1-line empty Verifactu placeholder (locked out of scope per the design).

### 3 hallazgos from PR-1 audit resolved (all via path (a) — in-plugin models + fixture extension)

| Hallazgo | Resolution | Files |
|----------|-----------|-------|
| `\contacto` model missing | Created in-plugin `Model/Contacto.php` + `model/table/contacto.xml`; `RelatedModelsLoader::loadContactoEnvio()` returns a real `Contacto` instance | `plugins/factura_pdf1/Model/Contacto.php`, `model/table/contacto.xml`, `Services/RelatedModelsLoader.php` |
| `\recibo_cliente` model missing | Created in-plugin `Model/ReciboCliente.php` + `model/table/recibo_cliente.xml`; multi-FK design serves all 4 document types; `RelatedModelsLoader::loadRecibos()` returns ordered collection | `plugins/factura_pdf1/Model/ReciboCliente.php`, `model/table/recibo_cliente.xml`, `Services/RelatedModelsLoader.php` |
| `factura_cliente` columns `idcontactoenv` + `codigoenv` missing | Extended test fixtures (`DocumentPrintViewFixture`, `SeedInvoiceFakt20260001`) with the new columns (default null); production code reads via `property_exists` and falls back to null. Production migration is a follow-up (see WARNING #3 below). | `tests/Fixtures/DocumentPrintViewFixture.php`, `tests/Fixtures/SeedInvoiceFakt20260001.php` |

## Test & Build Summary

| Metric | PR-1 | PR-2 | Cumulative |
|--------|------|------|-----------|
| Tests written | 40 (28 SettingsEffectCoverage + 2 endpoint + 5 AdapterGetters + 5 RelatedModelsLoader) | 45 (25 RenderFeature + 14 TextBlockPosition + 2 AddressSplitMacro + 4 PrintViewDocumentTypeLabel) | **148** |
| Tests passing | 75/103 (28 RED by design for PR-2) | 148/148 | **148/148 (100%)** |
| Assertions | — | — | **387** |
| PHPUnit warnings | 18 (all locale-related, non-fatal) | same | 18 |
| PHPStan errors | 0 | 0 | **0 (27/27 files)** |
| `vendor/` changes | 0 (no new Composer deps) | 0 | 0 |
| `size:exception` per PR | Yes (~600 LoC) | Yes (~800 LoC) | Both justified in PR description |

### Spec compliance matrix (from verify-report)

| Domain | MODIFIED | ADDED | REMOVED | Scenarios | Status |
|--------|----------|-------|---------|-----------|--------|
| `invoice-pdf-adapters` | 1 | 8 | 0 | 20 | ✅ COMPLIANT |
| `invoice-pdf-admin` | 1 | 2 | 0 | 9 | ✅ COMPLIANT |
| `invoice-pdf-public-endpoint` | 0 | 2 | 0 | 4 | ✅ COMPLIANT |
| `invoice-pdf-rendering` | 0 | 17 | 0 | 31 | ✅ COMPLIANT |
| `invoice-pdf-settings` | 1 | 2 | 0 | 21 | ✅ COMPLIANT |
| **TOTAL** | **3** | **31** | **0** | **85** | **85/85 ✅** |

## Carry-Forward Warnings (Known Issues, Non-Blocking)

These are the 3 WARNINGs that the verify-report (Engram #380) carried forward. They are documented in the archive so the next session picks them up. **None are CRITICAL and none block this archive.**

1. **`vendor/` not git-tracked at parent-repo level.** The root `.gitignore` excludes `plugins/*` without an explicit `factura_pdf1` allow-rule. The plugin ships its own `vendor/` per the FSFramework plugin convention, but a fresh `git clone` of the parent repo will not see `plugins/factura_pdf1/vendor/` until a manual exception is added to the root `.gitignore`. This is a follow-up change to repo hygiene, not to the plugin itself. (Carried forward from prior change; same WARNING in both archives.)

2. **18 cosmetic test warnings.** The PHPUnit run reports 18 warnings (13 visible as per-test `⚠` markers, 5 are `no-assertions` notices inside container-only tests). These are pre-existing: the in-memory test harness does not have a fully seeded `fs_*_cliente` table, so `albaran/pedido/presupuesto` adapter paths and the `factura_detallada` integration scenarios pass the controller dispatch logic but fall back to "adapter returns empty shape" without crashing. All tests still pass (exit 0). Production behaviour is unaffected. (Carried forward from prior change; same WARNING in both archives.)

3. **NEW WARNING: production migration for `\contacto` + `\recibo_cliente` tables and `factura_cliente` columns is deferred.** The in-plugin model classes (`Model/Contacto.php`, `Model/ReciboCliente.php`) and the XML schemas in `plugins/factura_pdf1/model/table/` are in place, but production databases need a forward migration applied by `Init.php` on first activation. The current `Init.php` only registers migrations for `mostrarpais` and `ocultarreferenciasfact`. The schema-on-activate pathway (FSFramework's standard model-table discovery via the kernel plugin loader) will create the tables on first install, but upgrades from a pre-render-fidelity install will not auto-apply the new columns to existing `factura_cliente` rows. This is a follow-up change to add the `Init.php` schema-alter calls AND the `model/table/factura_cliente.xml` migration.

## Carry-Forward SUGGESTIONs (Optional, Non-Blocking)

These 5 SUGGESTIONs were carried forward from the prior `adapt-factura-pdf1-to-fsframework` verify-report. None of them are in scope for this change; they remain optional follow-ups:

1. **`RenderModuleIsolationGrepTest`** — A test that greps the renderer module (`PdfRenderService.php`) for any reference to `fs_var`, `Cezpdf`, or `FacturaScripts\Core\Lib\PDF\*`, and asserts zero matches. The production code path is already clean (manual `grep -rn "fs_var" plugins/factura_pdf1` returns 0 matches outside tests); the suggestion is to add an automated test that pins the invariant.
2. **Full-column schema assertion for `factura_pdf1_settings`** — A test that asserts the schema contains every required column (`id`, `name`, `settings_json`, `current_version`, `created_at`, `updated_at`) — not just the `UNIQUE (name)` constraint. The current `SettingsServiceTest::testSchemaDefinesUniqueConstraintOnDefaultRowName` only checks the unique constraint.
3. **`fs_var` render-path static test** — Same as suggestion #1 but scoped to the entire render path (renderer + adapters + Twig partials), not just the renderer module. This would be a wider net that catches any future contributor who reintroduces a `fs_var` read in a partial.
4. **NumberFormatter warning root cause fix** — The 18 PHPUnit warnings include `NumberFormatter::formatCurrency()` warnings triggered by `getLineas()` formatter paths. The pragmatic fix is to cast fixture `pvptotal: 400` to `400.0` or harden the `getLineas()` formatter to handle integer values. Cosmetic; no correctness impact.
5. **Pedido/presupuesto unit render test** — The 2 new integration tests (`testEndpointStreamsPdfForSeededPedido`, `testEndpointStreamsPdfForSeededPresupuesto`) test the full HTTP path. A parallel unit test that asserts the `*PrintView::build()` for pedido/presupuesto returns the expected view structure would catch view-build regressions without going through HTTP.

## Phase 3 Follow-Ups (Out of Scope, Tracked for Next Session)

The 4 Phase 3 follow-ups from `tasks.md` (intentionally not done as part of this change; recorded here for traceability):

1. **Production migration for `\contacto` + `\recibo_cliente` tables + `factura_cliente.idcontactoenv` + `factura_cliente.codigoenv` columns.** The in-plugin models are created but production DBs need migration. This is a separate follow-up SDD in the plugin's own `openspec/` (the work is plugin-internal: adding the `Init.php` schema-alter calls and the `model/table/factura_cliente.xml` migration). WARNING #3 above.

2. **Archive `plugins/factura_detallada/`** (the legacy plugin this one replaces). This is a follow-up SDD. Risk: any active operator running `factura_detallada` settings will lose them. The follow-up must include a smoke-test gate before merging.

3. **Remove `plugins/FacturaPDF1/`** (the upstream source). Same SDD as #2 above (the two operations are gated together — do not remove `FacturaPDF1/` before archiving `factura_detallada/`, or vice versa). The upstream plugin does not boot on FSFramework; its presence on disk is purely historical.

4. **Parent-repo `.gitignore` whitelist fix for `plugins/factura_pdf1/**`** (CORE concern, not this plugin's SDD). WARNING #1 above. This is a **core** `openspec/` change per AGENTS.md "OpenSpec per Plugin" routing rule; the plugin-local SDD cannot absorb it.

These 4 follow-ups are NOT part of this archive. Do not auto-trigger. The next session can open a new SDD for any of them as appropriate.

## Archive Contents (Moved from Active `changes/`)

```
plugins/factura_pdf1/openspec/changes/archive/2026-07-21-factura-pdf1-render-fidelity/
├── archive-report.md             (this file)
├── proposal.md                   (23,742 bytes)
├── design.md                     (17,972 bytes; AD-9…AD-13 + supersedes AD-5/AD-8)
├── tasks.md                      (12,458 bytes; 17/17 PR tasks [x]; 4 Phase 3 [ ])
├── apply-progress.md             (18,328 bytes; PR-1 + PR-2 complete; 3 hallazgos resolved)
├── verify-report.md              (8,896 bytes; verdict pass_with_warnings)
└── specs/                        (5 delta specs preserved for historical record)
    ├── invoice-pdf-rendering/spec.md     (17 ADDED requirements)
    ├── invoice-pdf-settings/spec.md      (1 MODIFIED + 2 ADDED requirements)
    ├── invoice-pdf-adapters/spec.md      (1 MODIFIED + 7 ADDED requirements)
    ├── invoice-pdf-admin/spec.md         (1 MODIFIED + 2 ADDED requirements)
    └── invoice-pdf-public-endpoint/spec.md (2 ADDED requirements)
```

The active `plugins/factura_pdf1/openspec/changes/factura-pdf1-render-fidelity/` directory has been removed (no leftover empty folder).

## Cross-References

- **Parent openspec (root)**: untouched. `openspec/changes/factura-pdf1-render-fidelity/` does NOT exist at the root. Per AGENTS.md "OpenSpec per Plugin" and `plugins/factura_pdf1/openspec/config.yaml: ownership: plugin-local`, this change is 100% plugin-internal and the core openspec/ tree does not see it.
- **Other plugins**: untouched (`factura_detallada` and `FacturaPDF1` are still on disk and are the targets of Phase 3 follow-ups #2 and #3).
- **Core (`base/`, `src/`, `controller/`, `model/`)**: untouched (per the proposal §"Out of Scope" and §"Affected Areas" `Untouched` rows).
- **tpvmod** (`plugins/tpvmod/controller/tpvmod.php:206`): untouched; the integration test (`TpvmodUrlPinTest`) pins the literal `'./index.php?page=factura_detallada&id='` and asserts the new plugin serves it.
- **Prior change archive** (`2026-07-21-adapt-factura-pdf1-to-fsframework/`): preserved. AD-5 and AD-8 of that design are SUPERSEDED; everything else still holds.

## Engram Observation Cross-Reference

| Engram ID | Topic | Type | Persisted in this archive? |
|-----------|-------|------|----------------------------|
| #369 | `sdd/factura-pdf1-render-fidelity/proposal` | architecture | preserved (filesystem + engram) |
| #371 | `sdd/factura-pdf1-render-fidelity/specs/invoice-pdf-settings` (delta) | architecture | preserved (filesystem + engram) |
| #372 | `sdd/factura-pdf1-render-fidelity/specs/invoice-pdf-adapters` (delta) | architecture | preserved (filesystem + engram) |
| #373 | `sdd/factura-pdf1-render-fidelity/specs/invoice-pdf-admin` (delta) | architecture | preserved (filesystem + engram) |
| #374 | `sdd/factura-pdf1-render-fidelity/specs/invoice-pdf-public-endpoint` (delta) | architecture | preserved (filesystem + engram) |
| #375 | `sdd/factura-pdf1-render-fidelity/specs/invoice-pdf-rendering` (delta) | architecture | preserved (filesystem + engram) |
| #376 | `sdd/factura-pdf1-render-fidelity/tasks` | architecture | preserved (filesystem + engram) |
| #377 | `sdd/factura-pdf1-render-fidelity/apply-progress` | architecture | preserved (filesystem + engram) |
| #380 | `sdd/factura-pdf1-render-fidelity/verify-report` | architecture | preserved (filesystem + engram) |
| **(this)** | **`sdd/factura-pdf1-render-fidelity/archive-report`** | **architecture** | **this observation + filesystem** |

## SDD Cycle Status

**CLOSED**. The change has been:

- ✅ Proposed (19 features + 2 endpoint tests + 3 dead-partial REMOVEs; 2-PR chained forecast; force-chained, size:exception per PR)
- ✅ Specified (5 delta specs; 3 MODIFIED + 31 ADDED scenarios)
- ✅ Designed (5 new ADs: AD-9 SettingsEffectCoverageTest, AD-10 data-* token convention, AD-11 adapter getter convention, AD-12 RelatedModelsLoader, AD-13 text-block partials; AD-5 and AD-8 of prior design SUPERSEDED)
- ✅ Tasked (21 tasks across 2 PRs + 4 Phase 3 follow-ups; TDD Cycle Evidence table)
- ✅ Applied (PR-1 8/8 + PR-2 9/9 complete; 148 tests / 387 assertions / 0 PHPStan errors; 3 hallazgos resolved via path (a))
- ✅ Verified (pass_with_warnings; 85/85 scenarios COMPLIANT; 3 WARNINGs + 5 SUGGESTIONs documented; 0 CRITICAL)
- ✅ **Archived (this report)**

The plugin `plugins/factura_pdf1/` is production-ready pending the 4 follow-ups documented above. The next session can open a new SDD for any of: (a) the production migration for `\contacto` + `\recibo_cliente` + `factura_cliente.idcontactoenv` + `factura_cliente.codigoenv`, (b) the archive of `factura_detallada` + removal of `FacturaPDF1/`, (c) the parent-repo `.gitignore` whitelist fix, or (d) any new feature work on the plugin.

## Handoff to Next Session

The SDD cycle for `factura-pdf1-render-fidelity` is **CLOSED**. The next session opens new SDDs as needed for the 4 Phase 3 follow-ups:

- **Follow-up #1 (plugin-local SDD)**: production migration for `contactos` + `reciboscli` tables + `factura_cliente.idcontactoenv` + `factura_cliente.codigoenv` columns via `Init.php` schema-alter + `model/table/factura_cliente.xml`.
- **Follow-up #2 + #3 (single plugin-local SDD)**: archive `plugins/factura_detallada/` + remove `plugins/FacturaPDF1/`. Gated together; smoke-test gate required.
- **Follow-up #4 (core openspec SDD)**: parent-repo `.gitignore` whitelist exception for `plugins/factura_pdf1/**`. Lives in the parent `openspec/`, not this plugin's.

Each follow-up is its own `propose → spec → design → tasks → apply → verify → archive` cycle. None auto-triggered.
