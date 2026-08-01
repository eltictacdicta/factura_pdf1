# OpenSpec — `plugins/factura_pdf1/`

Source of truth for the SDD of the **factura_pdf1** plugin (LGPL-3.0
inherited from upstream FacturaPDF1, applied to all newly authored
files; upstream legacy `Lib/PDF/PDFDocument.php` is not copied into
this plugin — the new code re-implements the layout from scratch).

## Canonical paths

- `plugins/factura_pdf1/openspec/config.yaml` — this plugin's OpenSpec config (`ownership: plugin-local`).
- `plugins/factura_pdf1/openspec/specs/` — canonical source of truth for plugin-local capabilities.
- `plugins/factura_pdf1/openspec/changes/{name}/` — active SDD changes for this plugin.
- `plugins/factura_pdf1/openspec/changes/archive/YYYY-MM-DD-{name}/` — archived (closed) SDD changes.

## Boundary

- This plugin is **parent-tracked**: the parent repo at `/home/javier/proyectos/panel-ab/` is the source of truth. Do **NOT** initialize a sub-repo here (mirror `clientes_facturacion`, not `factura_detallada`).
- Core `openspec/` does **NOT** carry entries for this change. Per `AGENTS.md` → "OpenSpec per Plugin", this change is 100% internal to the plugin and the SDD lives here exclusively.
- Parent `git add` from `/home/javier/proyectos/panel-ab/` is the valid commit path for everything under `plugins/factura_pdf1/`.
- `vendor/` MUST be committed alongside `composer.json` / `composer.lock` per `AGENTS.md` → "Plugin Composer Dependencies". `/vendor/` stays un-ignored in the plugin-local `.gitignore`; the only Composer line that stays ignored is `/composer.phar`.

## Active changes

- `adapt-factura-pdf1-to-fsframework/` — bootstrap and full port.
