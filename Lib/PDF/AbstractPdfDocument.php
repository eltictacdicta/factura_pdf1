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

namespace FSFramework\Plugins\factura_pdf1\Lib\PDF;

use Cezpdf;
use FSFramework\Plugins\factura_pdf1\Services\FormatoDocumento;
use FSFramework\Plugins\factura_pdf1\Services\LocaleSettings;
use FSFramework\Plugins\factura_pdf1\Services\PdfNumberFormatter;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use FSFramework\Translation\FSTranslator;

/**
 * Missing-parent shim for the Cezpdf port of the upstream
 * `FacturaPDF1\Lib\PDF\PDFDocument` class.
 *
 * The original `PDFDocument` extends the FS2025 core
 * `FacturaScripts\Core\Lib\PDF\PDFDocument`, which provides a
 * rich surface (`i18n->trans`, `format->idlogo/titulo/texto`,
 * `tableWidth`, `getTaxesRows`, `getCountryName`, etc.) the
 * port calls without thinking. We do not have that core
 * surface in the FSFramework target stack, so this class
 * provides a minimal, testable replacement.
 *
 * PR-1 ships the constructor, the easy helpers
 * (`noHtml`, `formatNumber`, `getFileName`, `newLine`,
 * `pipe`) and PR-2-style stubs for the seven methods the
 * port calls into (`getTaxesRows`, `getCountryName`,
 * `getDivisaName`, `removeEmptyCols`, `addImageFromFile`,
 * `addImageFromAttachedFile`, `pipe`). PR-2 will replace
 * the stubs with the verbatim port logic once the upstream
 * port is wired in.
 */
abstract class AbstractPdfDocument
{
    protected Cezpdf $pdf;

    /** @var array<string, mixed> */
    protected array $settings;

    protected FSTranslator $translator;

    protected FormatoDocumento $format;

    protected PdfNumberFormatter $numberFormatter;

    protected LocaleSettings $locale;

    protected float $tableWidth = 480.0;

    protected bool $insertedHeader = false;

    /**
     * Coordinate constants from the upstream FS2025 parent
     * PDFDocument. The vertical content starts at `pageHeight -
     * topMargin - CONTENT_X` (yes, the upstream uses `CONTENT_X`
     * for the top spacing) and the page-number footer sits at
     * `FOOTER_Y` units from the bottom. The default font size
     * is `FONT_SIZE` points.
     */
    public const CONTENT_X = 30;

    public const FOOTER_Y = 20;

    public const FONT_SIZE = 9;

    public function __construct(
        Cezpdf $pdf,
        SettingsService $settings,
        FSTranslator $translator,
        FormatoDocumento $format,
        PdfNumberFormatter $numberFormatter,
        LocaleSettings $locale,
    ) {
        $this->pdf = $pdf;
        $this->settings = $settings->load();
        $this->translator = $translator;
        $this->format = $format;
        $this->numberFormatter = $numberFormatter;
        $this->locale = $locale;
    }

    abstract public function render(): void;

    public function getPdf(): Cezpdf
    {
        return $this->pdf;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getTranslator(): FSTranslator
    {
        return $this->translator;
    }

    public function getFormat(): FormatoDocumento
    {
        return $this->format;
    }

    public function getNumberFormatter(): PdfNumberFormatter
    {
        return $this->numberFormatter;
    }

    public function getLocale(): LocaleSettings
    {
        return $this->locale;
    }

    public function getTableWidth(): float
    {
        return $this->tableWidth;
    }

    public function setTableWidth(float $width): void
    {
        $this->tableWidth = $width;
    }

    public function isInsertedHeader(): bool
    {
        return $this->insertedHeader;
    }

    public function setInsertedHeader(bool $inserted): void
    {
        $this->insertedHeader = $inserted;
    }

    /**
     * Strip HTML and decode HTML entities from a string. Mirrors
     * the upstream `Tools::fixHtml()` semantics: tags are
     * removed and `&amp;`/`&quot;`/`&#039;` are decoded so the
     * resulting string is safe to drop into a Cezpdf text
     * operation.
     */
    /**
     * Strip HTML and decode HTML entities from a string. Mirrors
     * the upstream `Tools::fixHtml()` semantics: tags are
     * removed and `&amp;`/`&quot;`/`&#039;` are decoded so the
     * resulting string is safe to drop into a Cezpdf text
     * operation.
     */
    public function noHtml(string $str): string
    {
        return strip_tags(html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Normalize a currency symbol for Cezpdf output.
     *
     * Cezpdf {@see Cpdf::filterText()} expects UTF-8 and converts to
     * Windows-1252 internally. Pre-converting to single-byte sequences
     * (e.g. chr(128) for euro) produces invalid UTF-8 and the symbol is
     * dropped or replaced in the PDF.
     */
    public function formatPdfCurrencySymbol(string $symbol, string $codiso = ''): string
    {
        $symbol = trim($symbol);
        $codiso = strtoupper(trim($codiso));

        if ($symbol === '€' || $codiso === '978' || $codiso === 'EUR') {
            return '€';
        }

        if ($symbol === '') {
            return '';
        }

        return $symbol;
    }

    /**
     * Translate a key via the injected FSTranslator, forwarding
     * the optional placeholders. Mirrors the upstream
     * `$this->i18n->trans()` call site. The translator is the
     * FSFramework `FSTranslator` (Symfony Translation under the
     * hood); the port forwards the placeholders as-is.
     *
     * @param array<string, string> $params
     */
    public function trans(string $key, array $params = []): string
    {
        return $this->translator->trans($key, $params);
    }

    /**
     * Format a float using the active locale's decimal and
     * thousands separators. Replaces the upstream
     * `Tools::number()` call.
     */
    public function formatNumber(float $n): string
    {
        return $this->numberFormatter->format(
            $n,
            $this->locale->getDecimalSeparator(),
            $this->locale->getThousandsSeparator(),
        );
    }

    /**
     * PR-1 stub. PR-2 replaces this with the verbatim port of
     * the upstream `PDFDocument::getTaxesRows()` which reads
     * the `lineasiva` rows from the loaded `FacturaScripts`
     * `lineaiva_*_cliente` tables.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTaxesRows(object $model): array
    {
        return [];
    }

    /**
     * PR-1 stub. PR-2 replaces this with a real lookup against
     * the `pais` table; the upstream `getCountryName()` returns
     * the `nombre` field for a given `codpais` code.
     */
    public function getCountryName(string $code): string
    {
        return $code;
    }

    /**
     * PR-1 stub. PR-2 replaces this with a real lookup against
     * the `divisa` table; the upstream `getDivisaName()` returns
     * the `descripcion` field for a given `coddivisa` code.
     */
    public function getDivisaName(string $code): string
    {
        return $code;
    }

    /**
     * PR-1 stub. PR-2 replaces this with the verbatim port of
     * the upstream `removeEmptyCols()` which scans rows for
     * columns that are equal to the supplied zero-string and
     * drops both the column and the header entry.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string>                $headers
     */
    public function removeEmptyCols(array &$rows, array &$headers, string $zeroStr): void
    {
    }

    /**
     * PR-1 stub. PR-2 replaces this with a real Cezpdf image
     * placement call using `$this->pdf->ezImage()`.
     */
    public function addImageFromFile(string $path, float $x, float $y, float $w, float $h): void
    {
    }

    /**
     * PR-1 stub. PR-2 replaces this with the verbatim port of
     * the upstream `addImageFromAttachedFile()` which reads the
     * `path` property from the supplied `AttachedFile`-shaped
     * model and forwards to `addImageFromFile()`.
     */
    public function addImageFromAttachedFile(object $attachedFile, float $x, float $y, float $w, float $h): void
    {
    }

    /**
     * Default filename for the rendered PDF document.
     * PR-2 lets the concrete subclass override this to return
     * a more descriptive name (e.g. `factura-123.pdf`).
     */
    public function getFileName(): string
    {
        return 'document.pdf';
    }

    /**
     * Drop the cursor by one logical line. Mirrors the upstream
     * `PDFDocument::newLine()` and the small helpers that just
     * need to advance the Cezpdf cursor without drawing.
     */
    public function newLine(): void
    {
        $this->pdf->ezSetDy(-8);
    }

    /**
     * PR-1 stub for the `pipe()` calls the upstream inherited
     * from `ExtensionsTrait`. The original returns a string
     * fragment injected by a registered extension (e.g. a QR
     * provider). Verifactu is deferred to a follow-up SDD so
     * the port is given an empty string for now.
     */
    public function pipe(string $hook, object $model): string
    {
        return '';
    }
}
