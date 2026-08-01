# Archive Report: adapt-factura-pdf1-to-fsframework

```yaml
schema: gentle-ai.archive-result/v1
change: adapt-factura-pdf1-to-fsframework
project: fs-framework
plugin: plugins/factura_pdf1/
ownership: plugin-local
artifact_store: hybrid
archive_root: plugins/factura_pdf1/openspec/changes/archive/2026-07-21-adapt-factura-pdf1-to-fsframework/
specs_root: plugins/factura_pdf1/openspec/specs/
proposed: 2026-07-20
verified: 2026-07-21
archived: 2026-07-21
gating_verdict: pass_with_warnings
```

## Outcome

**Archived successfully with carry-forward warnings.** The change closes the SDD cycle for `adapt-factura-pdf1-to-fsframework` per the plugin-local OpenSpec convention declared in `plugins/factura_pdf1/openspec/config.yaml` (`ownership: plugin-local`, `artifact_store: hybrid`). The five delta specs under the change's `specs/` directory were promoted (verbatim) into the plugin's source-of-truth `plugins/factura_pdf1/openspec/specs/{domain}/spec.md`, because the source-of-truth directory was empty before this archive (only a `.gitkeep` was present). The active change folder has been moved into the dated archive directory per the `archive_root` template. No core openspec/ entries were created; core `openspec/` does not see this change per AGENTS.md "OpenSpec per Plugin".

## Phase Gates Recap

| Phase | Date | Verdict / Status | Engram ref |
|-------|------|------------------|------------|
| Propose | 2026-07-20 | Approved (Approach B: full FSFramework port) | (intake) |
| Spec | 2026-07-20 | 5 domain specs published | (intake) |
| Design | 2026-07-20 | 8 ADs locked (AD-1 … AD-8) | (intake) |
| Tasks | 2026-07-20 | 21 tasks across 3 chained PRs + cleanup §4 | (intake) |
| Apply | 2026-07-20 → 2026-07-21 | Phases 1–3 + remediation batch complete; 62 tests, 205 assertions, PHPStan 24/24 OK | obs #362 (apply-progress) |
| Verify | 2026-07-21 | `pass_with_warnings` — re-verified, supersedes stale `fail` | obs #363 (verify-report) |
| **Archive** | **2026-07-21** | **This report** | (this observation) |

## Specs Synced (Source of Truth Updated)

The plugin's `plugins/factura_pdf1/openspec/specs/` was empty before this archive (only `.gitkeep`). The 5 delta specs were full specs (not deltas with `ADDED Requirements` / `MODIFIED Requirements` / `REMOVED Requirements` sections), so they were promoted verbatim per the sdd-archive skill's "If Main Spec Does NOT Exist" branch.

| Domain | Action | Source delta | Source-of-truth path |
|--------|--------|--------------|----------------------|
| `invoice-pdf-rendering` | **Created** (full spec) | `changes/.../specs/invoice-pdf-rendering/spec.md` | `plugins/factura_pdf1/openspec/specs/invoice-pdf-rendering/spec.md` |
| `invoice-pdf-settings` | **Created** (full spec) | `changes/.../specs/invoice-pdf-settings/spec.md` | `plugins/factura_pdf1/openspec/specs/invoice-pdf-settings/spec.md` |
| `invoice-pdf-adapters` | **Created** (full spec) | `changes/.../specs/invoice-pdf-adapters/spec.md` | `plugins/factura_pdf1/openspec/specs/invoice-pdf-adapters/spec.md` |
| `invoice-pdf-admin` | **Created** (full spec) | `changes/.../specs/invoice-pdf-admin/spec.md` | `plugins/factura_pdf1/openspec/specs/invoice-pdf-admin/spec.md` |
| `invoice-pdf-public-endpoint` | **Created** (full spec) | `changes/.../specs/invoice-pdf-public-endpoint/spec.md` | `plugins/factura_pdf1/openspec/specs/invoice-pdf-public-endpoint/spec.md` |

Byte-identity check (`cmp -s`) confirms each main spec is identical to its delta counterpart.

## Spec Compliance Summary

| Spec | Requirements | Scenarios | Coverage |
|------|--------------|-----------|----------|
| `invoice-pdf-rendering` | 5 | 9 | 9/9 COMPLIANT |
| `invoice-pdf-settings` | 6 | 10 | 9 COMPLIANT + 1 PARTIAL |
| `invoice-pdf-adapters` | 6 | 10 | 9 COMPLIANT + 1 PARTIAL |
| `invoice-pdf-admin` | 5 | 11 | 11/11 COMPLIANT |
| `invoice-pdf-public-endpoint` | 5 | 10 | 10/10 COMPLIANT |
| **Total** | **27 requirements** | **50 scenarios** | **47 COMPLIANT + 2 PARTIAL + 1 dual-PARTIAL** |

(The "3 PARTIAL" count in the verify-report collapses the 2 settings-schema and 1 renderer-isolation gaps; the per-spec rows above resolve them to the right specs.)

## Carry-Forward Warnings (Known Issues, Non-Blocking)

These are the 5 WARNINGs that the verify-report (Engram #363) carried forward. They are documented in the archive so the next session picks them up. **None are CRITICAL and none block this archive.**

1. **`vendor/` not git-tracked at parent-repo level.** The parent `.gitignore` excludes `plugins/*` without a `factura_pdf1` exception; 753 vendor files (96M) live on disk and the plugin's own `.gitignore` correctly does NOT exclude `vendor/`, but the parent-repo policy still takes effect. AGENTS.md "Plugin Composer Dependencies" rule is not enforceable in this repository layout. The on-disk `vendor/` is the runnable artifact. **Resolution**: requires either a parent `.gitignore` whitelist exception (`!plugins/factura_pdf1/**`) or a separate sub-repo delivery. This is a follow-up, not part of this archive.
2. **18 PHPUnit `NumberFormatter` locale warnings** in `ClienteDocumentAdapterTest` (albaran/pedido/presupuesto data-provider cases) and `PdfRenderServiceTest::testRenderAlbaranAdapterReturnsValidPdfBinary`. Non-fatal; all 205 assertions pass. Source: a `getLineas()` formatter path triggered by integer `pvptotal: 400` in fixture rows. Cosmetic; no correctness impact. **Resolution suggestion**: cast fixture `pvptotal: 400` to `400.0` or harden the `getLineas()` formatter.
3. **Settings count is 28, not 30.** Upstream `XMLView/SettingsInvoice.xml` defines 28 fieldnames (after excluding `name`); `UPSTREAM_SETTING_KEYS` pins 28; `SettingsCoverageTest::testKnownSettingsMatchUpstreamKeys` asserts `assertCount(28, $known)`. Proposal/design referenced "30 settings" as an aspirational count. **Locked deviation**, asserted against upstream XML.
4. **`?tipo=` doc-type selector added** (`FacturaPdf1Controller::resolveAdapter()` matches `factura|albaran|pedido|presupuesto`; default `factura`). Not in the original `design.md` data-flow diagram. Default behavior preserves the tpvmod URL contract (`?page=factura_detallada&id=N` still resolves to `FacturaClienteAdapter`). **Locked deviation**.
5. **3 PARTIAL spec coverage cases** (per Strict TDD audit, 2 settings-related + 1 adapters-related):
   - `invoice-pdf-settings#Renderer reads from dedicated table` — no explicit "no `fs_var` in render path" grep test. Behavior is enforced by the production code path; `grep -rn "fs_var" plugins/factura_pdf1` returns 0 matches outside the test file, but no automated assertion enforces it.
   - `invoice-pdf-settings#Schema is parseable` — `SettingsServiceTest::testSchemaDefinesUniqueConstraintOnDefaultRowName` only verifies the `UNIQUE (name)` constraint, not the full column list (`id`, `name`, `settings_json`, `current_version`, `created_at`, `updated_at`).
   - `invoice-pdf-adapters#Renderer depends only on the interface` — `AdapterIsolationGrepTest` scans only the 4 adapter files; the renderer module (`PdfRenderService.php`) is verified by static inspection only.
   - **Resolution suggestions** (already in verify-report SUGGESTION §): add `RenderModuleIsolationGrepTest`, add a full-column presence test, add an `fs_var` absence test in render path.

## Follow-Up SDD (Out of Scope, Tracked for Next Session)

**Task 4.2: archive `factura_detallada` + remove `plugins/FacturaPDF1/`** — explicitly out of scope for this change per the proposal §"Out of Scope" and `tasks.md` §4.2. The follow-up SDD will:

- Remove `plugins/factura_detallada/` (the in-house 1-setting, FacturaCliente-only predecessor; the new plugin supersedes it).
- Remove `plugins/FacturaPDF1/` (the upstream FacturaScripts 2025 plugin that does not boot on FSFramework — `Init.php` extends a non-existent class; `Lib/PDF/PDFDocument.php` extends a non-existent class; references 10 non-existent classes; uses Cezpdf; etc.).
- Gate on a user-confirmed smoke test before merging (per proposal §"Rollback Plan").

**The follow-up SDD will be its own `propose → spec → design → tasks → apply → verify → archive` cycle.** It is NOT part of this archive. Do not auto-trigger.

A separate `parent-repo .gitignore whitelist fix for plugins/factura_pdf1/**` (to close WARNING #1) is also a candidate for a follow-up, but it touches the parent repo, not the plugin, so it belongs in the **core** `openspec/` (per AGENTS.md "OpenSpec per Plugin" routing rule). Also NOT part of this archive.

## Archive Contents (Moved from Active `changes/`)

```
plugins/factura_pdf1/openspec/changes/archive/2026-07-21-adapt-factura-pdf1-to-fsframework/
├── archive-report.md             (this file)
├── proposal.md                   (14,660 bytes)
├── design.md                     (19,545 bytes)
├── tasks.md                      (4,583 bytes) — 1.1–4.1 [x]; 4.2 [ ] (out of scope)
├── verify-report.md              (26,410 bytes; verdict pass_with_warnings)
└── specs/
    ├── invoice-pdf-rendering/spec.md
    ├── invoice-pdf-settings/spec.md
    ├── invoice-pdf-adapters/spec.md
    ├── invoice-pdf-admin/spec.md
    └── invoice-pdf-public-endpoint/spec.md
```

The active `plugins/factura_pdf1/openspec/changes/adapt-factura-pdf1-to-fsframework/` directory has been removed (no leftover empty folder).

## Cross-References

- **Parent openspec (root)**: untouched. `openspec/changes/adapt-factura-pdf1-to-fsframework/` does NOT exist at the root. Per AGENTS.md "OpenSpec per Plugin" and `plugins/factura_pdf1/openspec/config.yaml: ownership: plugin-local`, this change is 100% plugin-internal and the core openspec/ tree does not see it.
- **Other plugins**: untouched (`factura_detallada` is still installed and is the target of the 4.2 follow-up; `FacturaPDF1` is still on disk and is also a 4.2 target).
- **Core (`base/`, `src/`, `controller/`, `model/`)**: untouched (per the proposal §"Out of Scope" and §"Affected Areas" `Untouched` rows).
- **tpvmod** (`plugins/tpvmod/controller/tpvmod.php:206`): untouched; the integration test (`TpvmodUrlPinTest`) pins the literal `'./index.php?page=factura_detallada&id='` and asserts the new plugin serves it.

## SDD Cycle Status

**CLOSED**. The change has been:

- ✅ Proposed (Approach B: full FSFramework port)
- ✅ Specified (5 delta specs)
- ✅ Designed (8 ADs locked)
- ✅ Tasked (21 tasks across 3 chained PRs + cleanup §4)
- ✅ Applied (Phases 1–3 + remediation batch, 62 tests, 205 assertions, PHPStan OK)
- ✅ Verified (pass_with_warnings, 5 warnings documented)
- ✅ **Archived (this report)**

The plugin `plugins/factura_pdf1/` is production-ready pending the two follow-ups documented above. The next session can open a new SDD for any of: (a) the 4.2 archive of `factura_detallada` + `FacturaPDF1/`, (b) the parent-repo `.gitignore` whitelist fix, or (c) any new feature work on the plugin.
