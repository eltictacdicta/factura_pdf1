# Delta for invoice-pdf-admin

## Purpose

This change is a **major engine swap** for `plugins/factura_pdf1/`:
the mpdf + Twig pipeline shipped in the archived
`factura-pdf1-render-fidelity` cycle is replaced with a Cezpdf
pipeline. The admin settings page is **engine-independent** and
is preserved as-is. The CSRF-protected POST handler, the widget
groups, the save/reset actions, the i18n (es_ES + en_EN), and
the text-block-1 / text-block-2 widget groups all continue to
work unchanged.

This delta updates the "every widget has a render effect"
requirement to reflect that the render effect is now a
**Cezpdf-rendered PDF** (not a Twig-rendered HTML body), and
adds an explicit assertion that the admin form's CSRF
protection and the `?page=admin_factura_pdf1` route are
unaffected by the engine swap.

## MODIFIED Requirements

### Requirement: All settings rendered, grouped logically

The form MUST render a widget for every known setting, grouped
under the following sections in this order: logo, layout (header
blocks), lines (table), totals, text-block-1, text-block-2,
footer. Each widget MUST carry an HTML `name` attribute matching
the setting key. Every widget MUST be effective: changing the
value and re-rendering any `PrintableDocumentInterface` MUST
produce a different rendered PDF body, attributable to the
changed widget (this is the lesson from Engram obs #367 —
"every setting has an effect", not just "every setting has a
widget"). The rendered PDF MUST be produced by
`CezpdfRenderService`.

(Previously: the "different body" assertion was for the rendered
HTML body produced by the Twig template. The Twig template is
removed; the assertion now targets the Cezpdf-rendered PDF
body, asserted via the rewritten `SettingsEffectCoverageTest`.)

#### Scenario: Every setting has a widget

- GIVEN the rendered form
- WHEN the test iterates the known settings list
- THEN for every key an input/select/checkbox with `name="<key>"` is present

#### Scenario: Group sections exist in the template

- GIVEN the admin template
- WHEN the template is parsed
- THEN group headings `logo`, `layout`, `lines`, `totals`, `text-block-1`, `text-block-2`, `footer` are present

#### Scenario: Every widget has a render effect on the Cezpdf PDF

- GIVEN a widget in the form
- WHEN the operator changes the widget's value and saves
- THEN a re-render of the seeded `PrintableDocumentInterface` fixture MUST produce a different Cezpdf-rendered PDF body
- AND the difference MUST be attributable to the changed widget (per the rewritten `SettingsEffectCoverageTest`)

## ADDED Requirements

### Requirement: CSRF protection and route contract survive the engine swap

The admin form's CSRF protection and the
`?page=admin_factura_pdf1` route contract MUST be unaffected by
the engine swap. The template MUST still emit `{{ csrf_field() }}`
on the POST form; the controller MUST still call
`$this->isCsrfValid()`; a missing or invalid token MUST still be
rejected before any `SettingsService::save()` call. The route
`?page=admin_factura_pdf1` MUST still respond (200 GET, 302
POST) and MUST NOT introduce any template-rendering side effect.

#### Scenario: `csrf_field()` is present in the form

- GIVEN the admin template
- WHEN the template is parsed
- THEN a `{{ csrf_field() }}` token output is present in the rendered HTML

#### Scenario: `?page=admin_factura_pdf1` GET renders the form after the engine swap

- GIVEN a logged-in admin with access to admin pages
- WHEN `?page=admin_factura_pdf1` is requested after the Cezpdf engine swap
- THEN the response is HTTP 200 and the body is the rendered form
- AND the form's widget layout matches the documented section order

#### Scenario: POST without CSRF token is rejected after the engine swap

- GIVEN a POST with a valid settings body but no CSRF token
- WHEN the controller runs after the Cezpdf engine swap
- THEN `$this->isCsrfValid()` returns `false`
- AND no `SettingsService::save()` call is made
- AND the response is a re-render of the form with an error

#### Scenario: POST with valid CSRF proceeds to `SettingsService::save()`

- GIVEN a POST with a valid CSRF token and a valid settings body
- WHEN the controller runs after the Cezpdf engine swap
- THEN `$this->isCsrfValid()` returns `true`
- AND the save path runs
- AND the response is a 302 to `?page=admin_factura_pdf1`

## REMOVED Requirements

_None. The 9 existing requirements of `invoice-pdf-admin` remain
valid; the "All settings rendered, grouped logically"
requirement is updated (in the MODIFIED section) to reflect the
Cezpdf-rendered PDF target, and a new CSRF/route contract
requirement is added._
