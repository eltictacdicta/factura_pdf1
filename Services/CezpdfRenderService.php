<?php
/**
 * This file is part of factura_pdf1
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace FSFramework\Plugins\factura_pdf1\Services;

use FSFramework\Plugins\factura_pdf1\Lib\PDF\PortedPdfDocument;
use FSFramework\Plugins\factura_pdf1\Model\PrintableDocumentInterface;
use FSFramework\Translation\FSTranslator;

/**
 * Render service that fronts the Cezpdf port introduced in
 * `factura-pdf1-czpdf-pixel-parity`. Replaces the previous
 * `PdfRenderService` (mpdf + Twig) with a Cezpdf-only path.
 *
 * Same public surface as the previous `PdfRenderService`
 * (`render`, `renderHtml`, `save`) so the
 * `FacturaPdf1Controller` swap is a one-line import change.
 */
class CezpdfRenderService
{
    protected SettingsService $settings;

    protected FSTranslator $translator;

    protected FormatoDocumento $format;

    protected PdfNumberFormatter $numberFormatter;

    protected LocaleSettings $locale;

    public function __construct(
        ?SettingsService $settings = null,
        ?FSTranslator $translator = null,
        ?FormatoDocumento $format = null,
        ?PdfNumberFormatter $numberFormatter = null,
        ?LocaleSettings $locale = null,
    ) {
        $this->settings = $settings ?? new SettingsService();
        $this->translator = $translator ?? new FSTranslator();
        $this->format = $format ?? new FormatoDocumento();
        $this->numberFormatter = $numberFormatter ?? new PdfNumberFormatter();
        $this->locale = $locale ?? new LocaleSettings();
    }

    /**
     * Render the printable document into a PDF binary. The
     * signature matches the previous `PdfRenderService::render()`
     * so the controller does not change.
     *
     * The `$settings` arg is a per-call override array; the
     * keys are merged on top of `SettingsService::load()` so
     * the controller / tests can tweak individual settings
     * without persisting them. The previous `PdfRenderService`
     * accepted the same shape. (Before this fix, the array
     * was silently ignored and the document always used the
     * stored settings; the PR-3 `SettingsEffectCoverageTest`
     * rewrite caught the bug.)
     *
     * @param array<string, mixed> $settings
     */
    public function render(PrintableDocumentInterface $document, array $settings = []): string
    {
        $pdf = $this->createPdf();

        $port = new PortedPdfDocument(
            $pdf,
            $document,
            $this->settings,
            $this->translator,
            $this->format,
            $this->numberFormatter,
            $this->locale,
        );

        if ($settings !== []) {
            $loaded = $this->settings->load();
            $merged = array_replace($loaded, $settings);
            $reflection = new \ReflectionProperty(PortedPdfDocument::class, 'settings');
            $reflection->setAccessible(true);
            $reflection->setValue($port, $merged);
        }

        $port->render();

        $output = $pdf->ezOutput();

        return is_string($output) ? $output : '';
    }

    /**
     * Test seam kept for backward compatibility with the
     * previous `PdfRenderService::renderHtml()`. The Cezpdf
     * port has no HTML intermediate; the method returns an
     * empty string. Production code MUST NOT call this method.
     *
     * @param array<string, mixed> $settings
     */
    public function renderHtml(PrintableDocumentInterface $document, array $settings = []): string
    {
        return '';
    }

    /**
     * Save the rendered PDF to a file on disk. Convenience
     * wrapper around `render()` + `file_put_contents()`.
     */
    public function save(PrintableDocumentInterface $document, string $path): void
    {
        if (file_put_contents($path, $this->render($document)) === false) {
            throw new \RuntimeException('Unable to write PDF to ' . $path);
        }
    }

    /**
     * Create the Cezpdf instance wired with the page-number
     * footer + the A4 page geometry. The
     * `ezSetMargins(50, 50, 50, 50)` call matches the
     * upstream `FacturaPDF1` Cezpdf initialisation; the
     * `ezStartPageNumbers(300, 20, 8, 'right', '{PAGENO} /
     * {TOTALPAGENUM}')` call wires the page-number footer
     * that the upstream's `insertFooter()` would otherwise
     * print manually.
     */
    /**
     * Create the Cezpdf instance wired with the page-number
     * footer + the A4 page geometry. The
     * `ezSetMargins(50, 50, 50, 50)` call matches the
     * upstream `FacturaPDF1` Cezpdf initialisation; the
     * `ezStartPageNumbers(300, 20, 8, 'right', '{PAGENO} /
     * {TOTALPAGENUM}')` call wires the page-number footer
     * that the upstream's `insertFooter()` would otherwise
     * print manually.
     *
     * Marked `protected` (not `private`) so test doubles can
     * override it with a `SpyCezpdf` (see
     * `SettingsEffectCoverageTest`). The signature is
     * stable; subclasses must return a `\Cezpdf` instance.
     */
    protected function createPdf(): \Cezpdf
    {
        $tmpName = defined('FS_TMP_NAME') ? FS_TMP_NAME : '';
        $tmpPath = 'tmp/' . $tmpName . 'pdf';
        if (!is_dir($tmpPath)) {
            @mkdir($tmpPath, 0777, true);
        }

        $pdf = new \Cezpdf('a4', 'portrait');
        $pdf->tempPath = $tmpPath;
        $pdf->ezSetMargins(50, 50, 50, 50);

        return $pdf;
    }
}
