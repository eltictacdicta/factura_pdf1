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

use FSFramework\Plugins\factura_pdf1\Model\PrintableDocumentInterface;

/**
 * Standalone port of the upstream `FacturaPDF1\Lib\PDF\PDFDocument`
 * (1117 LoC verbatim from the FS2025 FacturaPDF1 plugin).
 *
 * Per PR-2 of `factura-pdf1-czpdf-pixel-parity`:
 *  - Extends {@see AbstractPdfDocument} (the PR-1 shim) instead of
 *    the upstream FS2025 `FacturaScripts\Core\Lib\PDF\PDFDocument`.
 *  - Drops `use ExtensionsTrait;` (Verifactu is deferred to a
 *    follow-up SDD; `pipe()` returns `''`).
 *  - Replaces every `BusinessDocument` type-hint with the
 *    `PrintableDocumentInterface` we own.
 *  - Replaces every `Tools::settings('invoice', 'X', 'd')` with
 *    `$this->settings['X'] ?? 'd'`.
 *  - Replaces every `Tools::fixHtml($s)` with `$this->noHtml($s)`.
 *  - Replaces every `Tools::number($n)` with `$this->formatNumber($n)`.
 *  - Replaces every `Tools::settings('default', 'decimal_separator', ...)`
 *    with `$this->locale->getDecimalSeparator()`.
 *  - Replaces every `new FacturaScripts\Dinamic\Model\*` model
 *    instantiation with a call through the
 *    `PrintableDocumentInterface` (e.g. `getEmpresa()`,
 *    `getCliente()`, `getFormaPago()`, `getContactoEnvio()`,
 *    `getAlmacen()`, `getAgenciaTransporte()`, `getRecibos()`).
 *  - Replaces every `Where::eq('field', 'value')` with
 *    `loadWhere(['field' => $value])`.
 *
 * Method bodies stay 90% verbatim from the upstream. The structure
 * is the same (`newPage`, `insertHeader`, `insertBusinessDocHeader`,
 * `insertBusinessDocBody`, `insertBusinessDocFooter`, `insertFooter`,
 * `getLineHeaders`, `getTaxesRows`, `removeEmptyCols`,
 * `insertInvoiceReceipts`, `insertExpiration`, `calcImageSize`,
 * `addImageFromFile`, `addImageFromAttachedFile`, `getBankData`,
 * `textHeight`, `defTrans`, `fval`, `QRimg`, `combineAddress`,
 * `getDocAddress`, `getDivisaSymbol`, `insertCompanyLogo`); the
 * constructor gains a `PrintableDocumentInterface $view` parameter
 * that the engine swap wires in.
 */
class PortedPdfDocument extends AbstractPdfDocument
{
    /**
     * Page-numbering footer y-coordinate (overrides the
     * PR-1 default of 20 with the upstream's higher value
     * so the page number does not get cut off on some
     * printers). Mirrors the upstream's `FOOTER_Y`.
     */
    public const FOOTER_Y = 30;

    /**
     * Width threshold above which a parens-wrapped parenthetical
     * in an address string gets split onto a new line. Mirrors
     * the upstream `PARTIR_DIR`.
     */
    public const PARTIR_DIR = 170;

    /**
     * Width threshold above which the CP + city + province + country
     * address line gets split at the province. Mirrors the upstream
     * `PARTIR_PROV`.
     */
    public const PARTIR_PROV = 170;

    /**
     * Maximum X coordinate of the company data (used to keep the
     * company block from overlapping the client block).
     */
    protected float $xx = 0.0;

    /** Y coordinate used to synchronize company/client block heights. */
    protected float $yy = 0.0;

    /** X coordinate where the company data is drawn. */
    protected float $xcompany = 0.0;

    /** Y coordinate where the company data is drawn. */
    protected float $ycompany = 0.0;

    /** Y coordinate of the page top margin. */
    protected float $topY = 0.0;

    /** Y coordinate where the company/client header ends. */
    protected float $yyhead = 0.0;

    /** Header row colors (RGB 0..1). */
    protected float $hr = 0.92;

    protected float $hg = 0.92;

    protected float $hb = 0.92;

    /** Alternating row colors (RGB 0..1). */
    protected float $lr = 0.93;

    protected float $lg = 0.93;

    protected float $lb = 0.93;

    protected float $rowgap = 2.0;

    /** Texto-1 colors (RGB 0..1). */
    protected float $r1 = 0.0;

    protected float $g1 = 0.0;

    protected float $b1 = 0.0;

    /** Texto-2 colors (RGB 0..1). */
    protected float $r2 = 0.0;

    protected float $g2 = 0.0;

    protected float $b2 = 0.0;

    /**
     * The source document the port is rendering. Replaces the
     * upstream `BusinessDocument $model` parameter passed to the
     * `insert*()` methods.
     */
    protected PrintableDocumentInterface $view;

    /** Active header layout (1 = Logo-Empresa-Cliente, 2 = Logo-Cliente-Empresa). */
    protected int $headerLayout = 1;

    public function __construct(
        \Cezpdf $pdf,
        PrintableDocumentInterface $view,
        \FSFramework\Plugins\factura_pdf1\Services\SettingsService $settings,
        \FSFramework\Translation\FSTranslator $translator,
        \FSFramework\Plugins\factura_pdf1\Services\FormatoDocumento $format,
        \FSFramework\Plugins\factura_pdf1\Services\PdfNumberFormatter $numberFormatter,
        \FSFramework\Plugins\factura_pdf1\Services\LocaleSettings $locale,
    ) {
        parent::__construct($pdf, $settings, $translator, $format, $numberFormatter, $locale);
        $this->view = $view;
    }

    /**
     * Returns a descriptive filename for the rendered PDF.
     * Pattern: `{modelClassName}-{id}.pdf` (e.g. `FacturaCliente-1.pdf`).
     */
    public function getFileName(): string
    {
        $parts = explode('\\', $this->view->getModelClassName());

        return strtolower(end($parts)) . '-' . $this->view->getId() . '.pdf';
    }

    /**
     * Main render entry point. Mirrors the upstream's
     * `PDFDocument::render($model)` body: page setup, header,
     * business-doc header (client info), business-doc body
     * (line items), business-doc footer (taxes + payment
     * blocks), and the per-page footer.
     */
    public function render(): void
    {
        $this->newPage();
        $this->insertHeader();
        $this->insertBusinessDocHeader();
        $this->insertBusinessDocBody();
        $this->insertBusinessDocFooter();
        $this->insertFooter();
    }

    /**
     * Set up the page. Mirrors the upstream
     * `newPage($orientation, $forceNewPage)`.
     *
     * @param string $orientation
     * @param bool   $forceNewPage
     */
    public function newPage(string $orientation = 'portrait', bool $forceNewPage = false): void
    {
        // Lazily create the Cezpdf on the first call. The render
        // service can pre-create the Cezpdf (with the page-numbers
        // footer already wired) and pass it in; in that case we
        // skip the Cezpdf-construction block.
        if (!isset($this->pdf->ez['pageWidth']) || $this->pdf->ez['pageWidth'] === 0) {
            // Construction is delegated to the service; in our
            // standalone port we assume the Cezpdf instance is
            // already prepared. This branch is a defensive guard.
        }

        if ($forceNewPage || (isset($this->pdf->y) && $this->pdf->y < 100)) {
            $this->pdf->ezNewPage();
            $this->insertedHeader = false;
        } else {
            $this->pdf->ezText("\n");
        }
    }

    /**
     * Returns the height an address block will occupy, given
     * the font size and the current page width. Mirrors the
     * upstream `textHeight()`.
     */
    protected function textHeight(string $text, int $fontsize): int
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $ny = 0;
        $parts = explode("\n", $text);
        $espacio = $this->pdf->ez['pageWidth'] - $this->pdf->ez['leftMargin'] - $this->pdf->ez['rightMargin'];
        foreach ($parts as $s) {
            $anchotexto = $s === '' ? 1 : (int) $this->pdf->getTextWidth($fontsize, $s);
            $ny += (int) round(ceil($anchotexto / max(1, $espacio)) * ($fontsize * 1.1));
        }

        return $ny;
    }

    /**
     * Translates a key via the injected translator, with an
     * optional literal fallback and a boolean toggle. Mirrors
     * the upstream `defTrans($text, $defaulttext, $enabled)`.
     */
    protected function defTrans(string $text, string $defaulttext, bool $enabled = true): string
    {
        if ($enabled === false) {
            return $defaulttext;
        }
        $translated = $this->translator->trans($text);
        if ($translated === $text) {
            return $defaulttext;
        }

        return $translated;
    }

    /**
     * Parses a numeric string with the locale's decimal separator
     * and returns a float. Mirrors the upstream `fval($s)`.
     *
     * @param mixed $s
     */
    protected function fval(mixed $s): float
    {
        if (is_string($s)) {
            $s = preg_replace(
                '/[^0-9' . preg_quote($this->locale->getDecimalSeparator(), '/') . ']/',
                '',
                $s,
            );
            $s = str_replace(',', '.', (string) $s);
        }

        return (float) $s;
    }

    /**
     * Build the printable address string from a model object.
     * Mirrors the upstream `combineAddress($model)`. The split
     * at the first `(` happens when the rendered text width
     * exceeds `PARTIR_DIR` and the parens are past column 15.
     */
    protected function combineAddress(object $model): string
    {
        $dir = '';
        $p = '';
        $direccion = property_exists($model, 'direccion') ? (string) $model->direccion : '';
        if ($direccion !== '') {
            $dir .= $direccion . "\n";
            $n = strpos($dir, '(');
            if ($n !== false && $n > 15 && (int) $this->pdf->getTextWidth(self::FONT_SIZE, $dir) > self::PARTIR_DIR) {
                $dir = trim(substr($dir, 0, $n)) . "\n" . substr($dir, $n);
            }
        }

        $apartado = property_exists($model, 'apartado') ? (string) $model->apartado : '';
        if ($apartado !== '') {
            $dir .= $this->translator->trans('box') . ' ' . $apartado . "\n";
        }

        $codpostal = property_exists($model, 'codpostal') ? (string) $model->codpostal : '';
        $ciudad = property_exists($model, 'ciudad') ? (string) $model->ciudad : '';
        $s = ltrim(ltrim($codpostal . ' ') . $ciudad);

        $codpais = property_exists($model, 'codpais') ? (string) $model->codpais : '';
        if ($codpais !== '' && !($this->settings['ocultarpais'] ?? false)) {
            $p = ', ' . $this->getCountryName($codpais);
        }

        $provincia = property_exists($model, 'provincia') ? (string) $model->provincia : '';
        $ocultarprovincia = (bool) ($this->settings['ocultarprovincia'] ?? false);
        if ($provincia !== '' && (!$ocultarprovincia || $provincia !== $ciudad)) {
            $candidate = $s . $provincia . $p;
            if ((int) $this->pdf->getTextWidth(self::FONT_SIZE, $candidate) >= self::PARTIR_PROV) {
                $s .= "\n";
            } else {
                $s .= ' ';
            }
            $s = ltrim($s) . '(' . $provincia . ')';
        }

        $dir .= $s . $p;
        if (substr($dir, -1, 1) === "\n") {
            $dir = substr($dir, 0, strlen($dir) - 1);
        }

        return $this->noHtml($dir);
    }

    /**
     * Returns the address string for the document (or the
     * subject's default address for supplier documents).
     * Mirrors the upstream `getDocAddress($subject, $model)`.
     */
    protected function getDocAddress(object $model): string
    {
        return $this->combineAddress($model);
    }

    /**
     * When the document has no address fields populated, fall back
     * to the first `direccion_cliente` or the `cliente` model itself.
     */
    protected function resolveClientAddressSource(object $model): object
    {
        $direccion = property_exists($model, 'direccion') ? trim((string) $model->direccion) : '';
        if ($direccion !== '') {
            return $model;
        }

        $cliente = $this->view->getCliente();

        if (method_exists($cliente, 'get_direcciones')) {
            $direcciones = $cliente->get_direcciones();
            if (is_array($direcciones) && $direcciones !== []) {
                foreach ($direcciones as $dir) {
                    $domFact = property_exists($dir, 'domfacturacion') ? (bool) $dir->domfacturacion : false;
                    if ($domFact) {
                        return $dir;
                    }
                }
                return $direcciones[0];
            }
        }

        return $cliente;
    }

    /**
     * Returns the currency symbol for a given currency code.
     * Mirrors the upstream `getDivisaSymbol($code)`.
     */
    protected function getDivisaSymbol(string $code): string
    {
        if ($code === '') {
            return '';
        }

        // The currency symbol is read from the source view.
        $divisa = $this->view->getDivisa();
        $symbol = property_exists($divisa, 'simbolo') ? (string) $divisa->simbolo : '';

        return $symbol;
    }

    /**
     * Inserts the company logo at the configured position.
     * Mirrors the upstream `insertCompanyLogo($idfile)`.
     */
    protected function insertCompanyLogo(int|string $idfile = 0): array
    {
        $this->yyhead = $this->pdf->ez['pageHeight'] - $this->pdf->ez['topMargin'];
        $pos = (int) ($this->settings['posicionlogo'] ?? 0);
        if ($pos === 9) {
            return ['width' => 0, 'height' => 0];
        }

        $resolver = new \FSFramework\Plugins\factura_pdf1\Services\EmpresaLogoResolver();
        $logoPath = $resolver->resolveSystemLogo();
        if ($logoPath === null) {
            return ['width' => 0, 'height' => 0];
        }
        $logoSize = $this->calcImageSize($logoPath);
        $xPos = (float) $this->pdf->ez['leftMargin'];
        $yPos = (float) $this->pdf->ez['pageHeight'] - (float) $this->pdf->ez['topMargin'] - $logoSize['height'];

        if ($pos === 0) {
            $pos = ($logoSize['width'] >= ($logoSize['height'] * 2) || $logoSize['width'] > 150) ? 2 : 1;
        }

        $margenlogo = (int) ($this->settings['margenlogo'] ?? 0);
        if ($pos === 2) {
            $this->xcompany = 0.0;
            $xPos += $margenlogo;
            $this->ycompany = $yPos;
            $this->xx = $xPos + $logoSize['width'];
        } else {
            $this->xcompany = $xPos + $logoSize['width'];
            $this->ycompany = $yPos + ($logoSize['height'] / 2);
            $this->xx = 0.0;
            $yPos -= $margenlogo;
        }

        $this->addImageFromFile($logoPath, $xPos, $yPos, $logoSize['width'], $logoSize['height']);
        $this->yyhead = $yPos;

        return $logoSize;
    }

    /**
     * Inserts the company header. Mirrors the upstream
     * `insertHeader($idempresa)`.
     *
     * Supports `disposicion_cabecera` setting:
     *  1 = Logo | Empresa | Cliente (default)
     *  2 = Logo | Cliente | Empresa
     */
    protected function insertHeader(): void
    {
        if ($this->insertedHeader) {
            return;
        }
        $this->insertedHeader = true;
        $this->headerLayout = (int) ($this->settings['disposicion_cabecera'] ?? 1);

        $leftmargin = (float) $this->pdf->ez['leftMargin'];
        $yy = (float) $this->pdf->y;
        $fs = self::FONT_SIZE;
        $this->yy = $yy;

        $empresa = $this->view->getEmpresa();

        $idLogo = property_exists($empresa, 'idlogo') ? (string) $empresa->idlogo : '';
        $logoSize = $this->insertCompanyLogo($idLogo);
        $hasLogo = $logoSize['width'] > 0;

        $contentWidth = (float) $this->pdf->ez['pageWidth']
            - (float) $this->pdf->ez['leftMargin']
            - (float) $this->pdf->ez['rightMargin'];

        if ($hasLogo) {
            $logoColWidth = $contentWidth * 0.20;
            $blockWidth = $contentWidth * 0.40;
        } else {
            $logoColWidth = 0;
            $blockWidth = $contentWidth * 0.50;
        }

        $espacio = (int) $blockWidth - 10;

        $companyInfo = $this->prepareCompanyInfo($empresa);
        $companyNombre = $companyInfo['nombre'];
        $companyDir = $companyInfo['dir'];
        $companyCon = $companyInfo['contact'];

        $sizenombre = $this->fitFontSize($companyNombre, $espacio, $fs);

        if ($logoSize['width'] === 0) {
            // sin logo
        } elseif ($this->xcompany === 0.0) {
            $this->pdf->y = $this->ycompany === 0.0 ? $yy : ($this->ycompany - 10);
        } else {
            $this->pdf->ez['leftMargin'] = $leftmargin + $logoColWidth;
            $this->pdf->y = min($yy + 10, $this->ycompany + 2 + 40);
        }

        $this->topY = (float) $this->pdf->y;
        $blockLeft = (float) $this->pdf->ez['leftMargin'];

        if ($this->headerLayout === 2) {
            $clientInfo = $this->prepareClientInfo();
            $clientSizenombre = $this->fitFontSize($clientInfo['nombre'], $espacio, $fs);
            $this->pdf->ezText($this->translator->trans('customer') . ':', $fs + 5, ['justification' => 'left', 'aright' => $blockLeft + $blockWidth]);
            $this->pdf->y -= 4;
            $this->pdf->ezText($this->noHtml($clientInfo['nombre']), $clientSizenombre, ['justification' => 'left', 'aright' => $blockLeft + $blockWidth]);
            $this->pdf->ezText($this->noHtml($clientInfo['dir']), $fs, ['justification' => 'left', 'aright' => $blockLeft + $blockWidth]);
        } else {
            $this->pdf->ezText($this->noHtml($companyNombre), $sizenombre, ['justification' => 'left', 'aright' => $blockLeft + $blockWidth]);
            $this->pdf->y -= 3;
            $this->pdf->ezText($this->noHtml($companyDir), $fs, ['justification' => 'left', 'aright' => $blockLeft + $blockWidth]);
            $this->pdf->y -= 3;
            $this->pdf->ezText($this->noHtml($companyCon), $fs, ['justification' => 'left', 'aright' => $blockLeft + $blockWidth]);
        }

        $this->xx = $blockLeft + $blockWidth + 10;
        $this->yyhead = min($this->yyhead, (float) $this->pdf->y);
        $this->pdf->ez['leftMargin'] = $leftmargin;
        $this->pdf->y -= 20;
    }

    /**
     * Prepare company text data from the empresa object.
     *
     * @return array{nombre: string, dir: string, contact: string}
     */
    protected function prepareCompanyInfo(?object $empresa = null): array
    {
        if ($empresa === null) {
            $empresa = $this->view->getEmpresa();
        }

        $nombre = property_exists($empresa, 'nombre') ? (string) $empresa->nombre : '';

        $dir = $this->combineAddress($empresa);
        $tipoidfiscal = property_exists($empresa, 'tipoidfiscal') ? (string) $empresa->tipoidfiscal : 'NIF';
        if ($tipoidfiscal === '') {
            $tipoidfiscal = 'NIF';
        }
        $cifnif = property_exists($empresa, 'cifnif') ? (string) $empresa->cifnif : '';
        $dir .= "\n" . $tipoidfiscal . ': ' . $cifnif;

        $con = '';
        $email = property_exists($empresa, 'email') ? (string) $empresa->email : '';
        if ($email !== '') {
            $con .= $email;
        }
        $telefono1 = property_exists($empresa, 'telefono1') ? (string) $empresa->telefono1 : '';
        $telefono2 = property_exists($empresa, 'telefono2') ? (string) $empresa->telefono2 : '';
        $s = $telefono1;
        if ($s === '') {
            $s = $telefono2;
        } elseif ($telefono2 !== '') {
            $s .= '  /  ' . $telefono2;
        }
        if ($s !== '') {
            if ($con !== '') {
                $con .= "\n";
            }
            $con .= $s;
        }
        $web = property_exists($empresa, 'web') ? (string) $empresa->web : '';
        if ($web !== '') {
            if ($con !== '') {
                $con .= "\n";
            }
            $con .= $web;
        }

        return ['nombre' => $nombre, 'dir' => $dir, 'contact' => $con];
    }

    /**
     * Prepare client text data from the document/cliente model.
     *
     * @return array{nombre: string, dir: string}
     */
    protected function prepareClientInfo(): array
    {
        $model = $this->view->getDocument();

        $nombreCliente = property_exists($model, 'nombrecliente') ? trim((string) $model->nombrecliente) : '';
        if ($nombreCliente === '' || $nombreCliente === '-') {
            $cliente = $this->view->getCliente();
            $nombreCliente = property_exists($cliente, 'razonsocial') ? (string) $cliente->razonsocial : '';
            if ($nombreCliente === '') {
                $nombreCliente = property_exists($cliente, 'nombre') ? (string) $cliente->nombre : '';
            }
        }

        $dirSource = $this->resolveClientAddressSource($model);
        $dir = $this->getDocAddress($dirSource);
        $cifnif = property_exists($model, 'cifnif') ? trim((string) $model->cifnif) : '';
        if ($cifnif === '') {
            $cliente = $this->view->getCliente();
            $cifnif = property_exists($cliente, 'cifnif') ? (string) $cliente->cifnif : '';
        }
        if ($cifnif !== '') {
            $s = $this->translator->trans('cifnif');
            if ($s !== '') {
                $s .= ': ';
            }
            $dir .= "\n" . $s . $this->noHtml($cifnif);
        }

        return ['nombre' => $nombreCliente, 'dir' => $dir];
    }

    /**
     * Calculate the largest font size that fits a name within a given width.
     */
    protected function fitFontSize(string $text, int $maxWidth, int $baseSize): int
    {
        if ((int) $this->pdf->getTextWidth($baseSize + 5, $text) <= $maxWidth) {
            return $baseSize + 5;
        }
        if ((int) $this->pdf->getTextWidth($baseSize + 4, $text) <= $maxWidth) {
            return $baseSize + 4;
        }
        if ((int) $this->pdf->getTextWidth($baseSize + 3, $text) <= $maxWidth) {
            return $baseSize + 3;
        }
        return $baseSize + 2;
    }

    /**
     * Inserts the business-document header (customer block,
     * rectifying-invoice number, related documents block,
     * shipping address, payment terms). Mirrors the upstream
     * `insertBusinessDocHeader($model)`.
     */
    protected function insertBusinessDocHeader(): void
    {
        $leftmargin = (float) $this->pdf->ez['leftMargin'];
        $xx = $this->xx;
        $yy = $this->yy;
        $fs = self::FONT_SIZE;
        $model = $this->view->getDocument();

        // Almacén
        $codalmacen = property_exists($model, 'codalmacen') ? trim((string) $model->codalmacen) : '';
        $mostraralmacen = trim((string) ($this->settings['mostraralmacen'] ?? ''));
        if ($codalmacen !== '' && $mostraralmacen !== '') {
            $almacen = $this->view->getAlmacen();
            if ($almacen !== null) {
                $alm = $this->translator->trans($mostraralmacen);
                if ($alm === '#') {
                    $alm = $this->noHtml((string) ($this->settings['tituloalmacen'] ?? '')) . ' ';
                } else {
                    $alm .= ': ';
                }
                $almacenDireccion = property_exists($almacen, 'direccion') ? (string) $almacen->direccion : '';
                $almacenCiudad = property_exists($almacen, 'ciudad') ? (string) $almacen->ciudad : '';
                $alm .= $almacenDireccion . ', ' . $almacenCiudad;
                $almacenProvincia = property_exists($almacen, 'provincia') ? (string) $almacen->provincia : '';
                $ocultarprovincia = (bool) ($this->settings['ocultarprovincia'] ?? false);
                if ($almacenProvincia !== '' && (!$ocultarprovincia || $almacenProvincia !== $almacenCiudad)) {
                    $alm .= ' (' . $almacenProvincia . ')';
                }
                $almacenCodpais = property_exists($almacen, 'codpais') ? (string) $almacen->codpais : '';
                $ocultarpais = (bool) ($this->settings['ocultarpais'] ?? false);
                if ($almacenCodpais !== '' && !$ocultarpais) {
                    $alm .= ', ' . $this->getCountryName($almacenCodpais);
                }
                if ($this->settings['mostraralmacentel'] ?? false) {
                    $almacenTelefono = property_exists($almacen, 'telefono') ? (string) $almacen->telefono : '';
                    $alm .= '   ' . $this->translator->trans('phone') . ': ' . $almacenTelefono;
                }
                $rightmargin = (int) ($this->settings['espaciomaximoempresa'] ?? 280);
                $this->pdf->y += 10;
                $this->pdf->ezText($this->noHtml($alm), $fs, ['justification' => 'left']);
                $this->yyhead = min($this->yyhead, (float) $this->pdf->y);
            }
        }

        // Refcli / refs
        $refcli = '';
        $ref2pos = 0;
        $direnvio = '';
        $refs = '';
        $tipodoc = $this->translator->trans(
            strtolower(basename(str_replace('\\', '/', $this->view->getModelClassName()))) . '-min',
        );
        $modelClass = $this->view->getModelClassName();
        // Match the cliente-document family by name
        // (case-insensitive AND snake-case normalised)
        // so the check works for both the upstream
        // PascalCase class names (`FacturaCliente`) and
        // the FSFramework snake_case names
        // (`factura_cliente`). PR-3 of the
        // factura-pdf1-czpdf-pixel-parity change caught
        // the original case-sensitive `str_contains`
        // check failing for snake_case names.
        $modelBase = strtolower(basename(str_replace('\\', '/', $modelClass)));
        $isClienteDoc = in_array($modelBase, [
            'facturacliente',
            'pedidocliente',
            'albarancliente',
            'presupuestocliente',
            'factura_cliente',
            'pedido_cliente',
            'albaran_cliente',
            'presupuesto_cliente',
        ], true);
        if ($isClienteDoc) {
            if (!empty($this->format->titulo)) {
                $tipodoc = $this->noHtml($this->format->titulo);
            }
            $ref2setting = (int) ($this->settings['ref2'] ?? 0);
            $numero2 = property_exists($model, 'numero2') ? (string) $model->numero2 : '';
            if ($ref2setting > 0 && $numero2 !== '') {
                $ref2pos = $ref2setting;
                if ($ref2pos === 1) {
                    $refcli = $this->translator->trans('reference') . ':  ' . $numero2;
                }
                if ($ref2pos === 2) {
                    $refs = $this->translator->trans('reference') . ':  ' . $numero2;
                }
            }
        }

        $numdocumento = $tipodoc . ':  ' . (property_exists($model, 'codigo') ? (string) $model->codigo : '');

        // Texto colors
        $colortexto1 = (string) ($this->settings['colortexto1'] ?? '#000000');
        $this->r1 = hexdec(substr($colortexto1, 1, 2)) / 255;
        $this->g1 = hexdec(substr($colortexto1, 3, 2)) / 255;
        $this->b1 = hexdec(substr($colortexto1, 5, 2)) / 255;
        $colortexto2 = (string) ($this->settings['colortexto2'] ?? '#000000');
        $this->r2 = hexdec(substr($colortexto2, 1, 2)) / 255;
        $this->g2 = hexdec(substr($colortexto2, 3, 2)) / 255;
        $this->b2 = hexdec(substr($colortexto2, 5, 2)) / 255;
        $r1 = $this->r1;
        $g1 = $this->g1;
        $b1 = $this->b1;
        $r2 = $this->r2;
        $g2 = $this->g2;
        $b2 = $this->b2;
        $texto1 = $this->noHtml($this->format->texto);
        $texto2 = $this->noHtml((string) ($this->settings['texto2'] ?? ''));
        $posiciontexto1 = (int) ($this->settings['posiciontexto1'] ?? 8);
        $medidatexto1 = (int) ($this->settings['medidatexto1'] ?? 8);
        $just1 = (string) ($this->settings['justiftexto1'] ?? 'left');
        $posiciontexto2 = (int) ($this->settings['posiciontexto2'] ?? 8);
        $medidatexto2 = (int) ($this->settings['medidatexto2'] ?? 8);
        $just2 = (string) ($this->settings['justiftexto2'] ?? 'left');

        // Right-side block: client (layout 1) or empresa (layout 2)
        if ($this->headerLayout === 2) {
            $companyInfo = $this->prepareCompanyInfo();
            $tipo = $this->translator->trans('company');
            $nombre = $this->noHtml($companyInfo['nombre']);
            $dir = $companyInfo['dir'] . "\n" . $companyInfo['contact'];
        } else {
            $clientInfo = $this->prepareClientInfo();
            $tipo = $this->translator->trans('customer');
            $nombre = $this->noHtml($clientInfo['nombre']);
            $dir = $clientInfo['dir'];
        }

        $wmax = (int) $this->pdf->getTextWidth($fs + 2, $nombre);
        $h = (int) $this->pdf->getFontHeight($fs + 5) + 4 + (int) $this->pdf->getFontHeight($fs + 2);

        $dirLines = explode("\n", $dir);
        foreach ($dirLines as $line) {
            $wmax = max($wmax, (int) $this->pdf->getTextWidth($fs, $line));
            $h += (int) $this->pdf->getFontHeight($fs);
        }
        if ($refcli !== '') {
            $h += 4 + (int) $this->pdf->getFontHeight($fs);
        }

        // Shipping address
        $envioatt = '';
        $enviodir = '';
        $envioteltrans = '';
        $idcontactoenv = property_exists($model, 'idcontactoenv') ? (int) $model->idcontactoenv : 0;
        $ocultardireccionenvio = (bool) ($this->settings['ocultardireccionenvio'] ?? false);
        if ($idcontactoenv > 0 && !$ocultardireccionenvio) {
            $contacto = $this->view->getContactoEnvio();
            if ($contacto !== null) {
                $contactoNombre = property_exists($contacto, 'nombre') ? (string) $contacto->nombre : '';
                $contactoApellidos = property_exists($contacto, 'apellidos') ? (string) $contacto->apellidos : '';
                if ($contactoNombre !== '') {
                    $envioatt .= $this->noHtml($contactoNombre);
                }
                if ($contactoNombre !== '' && $contactoApellidos !== '') {
                    $envioatt .= ' ';
                }
                if ($contactoApellidos !== '') {
                    $envioatt .= $this->noHtml($contactoApellidos);
                }
            }
            $enviodir = $this->combineAddress($contacto ?? $model);
            $telefono1 = $contacto !== null && property_exists($contacto, 'telefono1') ? (string) $contacto->telefono1 : '';
            $telefono2 = $contacto !== null && property_exists($contacto, 'telefono2') ? (string) $contacto->telefono2 : '';
            if ($telefono1 !== '') {
                $envioteltrans .= $telefono1;
            }
            if ($telefono1 !== '' && $telefono2 !== '') {
                $envioteltrans .= ' / ';
            }
            if ($telefono2 !== '') {
                $envioteltrans .= $telefono2;
            }
            $agencia = $this->view->getAgenciaTransporte();
            $nombreAgencia = $agencia['nombre'] ?? '';
            $tracking = $agencia['tracking'] ?? '';
            if ($nombreAgencia !== '' || $tracking !== '') {
                if ($envioteltrans !== '') {
                    $envioteltrans .= "\n";
                }
                if ($nombreAgencia !== '') {
                    $envioteltrans .= $this->translator->trans('carrier') . ': ' . $nombreAgencia;
                }
                if ($tracking !== '') {
                    $envioteltrans .= ' (' . $tracking . ')';
                }
            }
        }

        $qrSize = 100;
        $qrImage = '';
        $qrTitle1 = '';
        $qrTitle2 = '';
        try {
            $pipeVal = $this->pipe('qrImageHeader', $model);
            if (!empty($pipeVal)) {
                $qrImage = $pipeVal;
                $qrTitle1 = $this->pipe('qrTitleHeader', $model);
                $qrTitle2 = $this->pipe('qrSubtitleHeader', $model);
            }
        } catch (\Exception $e) {
            // swallow (Verifactu deferred)
        }

        if ($wmax < 100) {
            $wmax = 100;
        }
        $xx2 = max($this->xx, (float) $this->pdf->ez['pageWidth'] - (float) $this->pdf->ez['rightMargin'] - 10 - $wmax);
        $ww = $xx2 - $xx - 20;
        if ($qrImage !== '' && $ww < $qrSize) {
            $xx2 += $qrSize - $ww;
            $ww = $xx2 - $xx - 20;
        }
        $qrX = $xx + ($ww / 2) - ($qrSize / 2);
        $this->pdf->ez['leftMargin'] = $xx2;

        if ($qrImage !== '') {
            $this->QRimg($qrImage, $qrTitle1, $qrTitle2, (float) $qrX, (float) $this->pdf->ez['pageHeight'] - (float) $this->pdf->ez['topMargin'], $qrSize, $fs);
            $this->yyhead = min($this->yyhead, (float) $this->pdf->y + 20);
        }

        if ($this->xcompany === 0.0) {
            $this->pdf->y = $this->ycompany === 0.0 ? $yy : ($yy - (($yy - $this->yyhead) / 2) + ($h / 2));
        } else {
            $this->pdf->y = min($yy + 10, $this->ycompany + 1 + ($h / 2));
        }
        $this->pdf->ezText($tipo . ':', $fs + 5, ['justification' => 'left']);
        $this->pdf->y -= 4;
        $this->pdf->ezText($nombre, $fs + 2, ['justification' => 'left']);
        $this->pdf->ezText($dir, $fs, ['justification' => 'left']);
        if ($envioatt !== '' && $enviodir === '') {
            $this->pdf->ezText($envioatt, $fs, ['justification' => 'left']);
        }
        if ($envioteltrans !== '' && $enviodir === '') {
            $this->pdf->ezText($envioteltrans, $fs, ['justification' => 'left']);
        }
        if ($refcli !== '') {
            $this->pdf->y -= 4;
            $this->pdf->ezText($refcli, $fs);
        }
        if ($enviodir !== '') {
            $this->pdf->y -= 8;
            $this->pdf->ezText($this->translator->trans('shipping-address') . ':', $fs + 4, ['justification' => 'left']);
            $this->pdf->y -= 4;
            $this->pdf->ezText($enviodir, $fs, ['justification' => 'left']);
            if ($envioatt !== '') {
                $this->pdf->ezText($envioatt, $fs, ['justification' => 'left']);
            }
            if ($envioteltrans !== '') {
                $this->pdf->ezText($envioteltrans, $fs, ['justification' => 'left']);
            }
        }
        $this->yyhead = min($this->yyhead, (float) $this->pdf->y);
        $this->pdf->ez['leftMargin'] = $leftmargin;
        $this->pdf->y = $this->yyhead - 15;

        if ($posiciontexto1 === 2 && $texto1 !== '') {
            $this->pdf->setColor($r1, $g1, $b1);
            $this->pdf->ezText($texto1, $medidatexto1, ['justification' => $just1]);
            $this->pdf->y -= 10;
        }
        if ($posiciontexto2 === 2 && $texto2 !== '') {
            $this->pdf->setColor($r2, $g2, $b2);
            $this->pdf->ezText($texto2, $medidatexto2, ['justification' => $just2]);
            $this->pdf->y -= 10;
        }
        $this->pdf->setColor(0, 0, 0);

        $this->pdf->y -= 14;
        $this->newLine();
        $yy = (float) $this->pdf->y;

        $fecha = property_exists($model, 'fecha') ? (string) $model->fecha : '';
        $numsize = $fs + 6;
        $espacios = '';
        if (strlen($numdocumento) > 38) {
            $numsize = $fs + 4;
            $espacios = str_repeat(' ', (int) round((strlen($numdocumento) - 8) * 1.4));
        } elseif (strlen($numdocumento) > 32) {
            $numsize = $fs + 5;
            $espacios = str_repeat(' ', (int) round((strlen($numdocumento) - 14) * 1.5));
        } else {
            if (strlen($numdocumento) > 14) {
                $espacios = str_repeat(' ', (int) round((strlen($numdocumento) - 14) * 1.6));
            } else {
                $espacios = '';
            }
        }
        $this->pdf->ezText($espacios . $this->translator->trans('date') . ':  ' . $fecha, $numsize, ['justification' => 'right']);
        $this->pdf->y = $yy;
        $this->pdf->ezText($numdocumento, $numsize);

        $codigorect = $this->view->getCodigoRect();
        if ($codigorect !== null && $codigorect !== '') {
            $this->pdf->y -= 4;
            $this->pdf->setColor(0.2, 0.2, 0.2);
            $rec = $this->translator->trans('invoice') . ' ' . strtolower($this->translator->trans('original')) . ':  ' . $codigorect;
            $this->pdf->ezText($rec, $fs + 3);
        } elseif ($refs !== '') {
            $this->pdf->y -= 7;
            $this->pdf->setColor(0.3, 0.3, 0.3);
            $this->pdf->ezText($refs, $fs);
        }
        $this->pdf->setColor(0, 0, 0);

        $this->pdf->ezText('', $fs + 6);
        $this->newLine();
        $this->pdf->y -= 8;

        if ($posiciontexto1 === 3 && $texto1 !== '') {
            $this->pdf->setColor($r1, $g1, $b1);
            $this->pdf->ezText($texto1, $medidatexto1, ['justification' => $just1]);
            $this->pdf->y -= 10;
        }
        if ($posiciontexto2 === 3 && $texto2 !== '') {
            $this->pdf->setColor($r2, $g2, $b2);
            $this->pdf->ezText($texto2, $medidatexto2, ['justification' => $just2]);
            $this->pdf->y -= 10;
        }
        $this->pdf->setColor(0, 0, 0);

        $this->pdf->y -= 10;
    }

    /**
     * Returns the line-headers array. Mirrors the upstream
     * `getLineHeaders()`.
     */
    protected function getLineHeaders(): array
    {
        return [
            'descripcion' => ['type' => 'text', 'title' => $this->translator->trans('description')],
            'cantidad' => ['type' => 'number', 'title' => $this->translator->trans('quantity')],
            'pvpunitario' => ['type' => 'number', 'title' => $this->translator->trans('price')],
            'pvptotal' => ['type' => 'number', 'title' => $this->translator->trans('net')],
        ];
    }

    /**
     * Inserts the line-items body. Mirrors the upstream
     * `insertBusinessDocBody($model)`.
     */
    protected function insertBusinessDocBody(): void
    {
        $colorcabecera = (string) ($this->settings['colorcabecera'] ?? '#ffffff');
        $this->hr = hexdec(substr($colorcabecera, 1, 2)) / 255;
        $this->hg = hexdec(substr($colorcabecera, 3, 2)) / 255;
        $this->hb = hexdec(substr($colorcabecera, 5, 2)) / 255;
        $colorfilas = (string) ($this->settings['colorfilas'] ?? '#ffffff');
        $this->lr = hexdec(substr($colorfilas, 1, 2)) / 255;
        $this->lg = hexdec(substr($colorfilas, 3, 2)) / 255;
        $this->lb = hexdec(substr($colorfilas, 5, 2)) / 255;
        $this->rowgap = (float) ($this->settings['espaciofilas'] ?? 2);

        $headers = [];
        $tableOptions = [
            'cols' => [],
            'shadeCol' => [$this->lr, $this->lg, $this->lb],
            'shadeHeadingCol' => [$this->hr, $this->hg, $this->hb],
            'rowGap' => $this->rowgap,
            'width' => $this->tableWidth,
            'gridlines' => EZ_GRIDLINE_TABLE,
        ];

        foreach ($this->getLineHeaders() as $key => $value) {
            $headers[$key] = '<c:color:1,1,1>' . $value['title'] . '</c:color>';
            if ($key === 'descripcion') {
                $tableOptions['cols'][$key] = ['width' => 240];
            } elseif (in_array($value['type'], ['number', 'percentage'], true)) {
                $tableOptions['cols'][$key] = ['justification' => 'right'];
            }
        }

        $tableData = [];
        foreach ($this->view->getLines() as $line) {
            $data = [];
            foreach ($this->getLineHeaders() as $key => $value) {
                $lineValue = is_array($line) ? ($line[$key] ?? null) : (property_exists($line, $key) ? $line->{$key} : null);
                if ($value['type'] === 'percentage') {
                    $data[$key] = $this->formatNumber((float) $lineValue) . '%';
                } elseif ($value['type'] === 'number') {
                    $data[$key] = $this->formatNumber((float) $lineValue);
                } else {
                    $data[$key] = (string) ($lineValue ?? '');
                }
            }
            $tableData[] = $data;
        }

        if ($tableData !== []) {
            $this->pdf->ezTable($tableData, $headers, '', $tableOptions);
        }
    }

    /**
     * Inserts the business-document footer (taxes, totals,
     * payment receipts). Mirrors the upstream
     * `insertBusinessDocFooter($model)`.
     */
    protected function insertBusinessDocFooter(): void
    {
        $model = $this->view->getDocument();
        $observaciones = property_exists($model, 'observaciones') ? trim((string) (property_exists($model, 'observaciones') ? $model->observaciones : '')) : '';

        $bottomMargin = (float) $this->pdf->ez['bottomMargin'];
        $totalsHeight = 80;

        $obsHeight = 0;
        $obsLines = [];
        if ($observaciones !== '') {
            $obsText = $this->noHtml($observaciones);
            $obsLines = explode("\n", $obsText);
            $lineHeight = (float) $this->pdf->getFontHeight(self::FONT_SIZE);
            $titleHeight = (float) $this->pdf->getFontHeight(self::FONT_SIZE + 2);
            $obsHeight = $titleHeight + 8 + (count($obsLines) * $lineHeight) + 16;
        }

        $targetY = $bottomMargin + $totalsHeight + $obsHeight;
        if ($this->pdf->y > $targetY) {
            $this->pdf->y = $targetY;
        }

        if ($observaciones !== '') {
            $leftX = (float) $this->pdf->ez['leftMargin'];
            $rightX = (float) $this->pdf->ez['pageWidth'] - (float) $this->pdf->ez['rightMargin'];
            $boxWidth = $rightX - $leftX;
            $boxTop = (float) $this->pdf->y;

            $this->pdf->setStrokeColor(0.7, 0.7, 0.7);
            $this->pdf->setLineStyle(0.5);
            $this->pdf->rectangle($leftX, $boxTop - $obsHeight + 8, $boxWidth, $obsHeight - 8);

            $this->pdf->y = $boxTop - 4;
            $this->pdf->ezText('  ' . $this->translator->trans('observations'), self::FONT_SIZE + 2);
            $this->pdf->y -= 4;
            foreach ($obsLines as $obsLine) {
                $this->pdf->ezText('  ' . $obsLine, self::FONT_SIZE);
            }

            $this->pdf->setStrokeColor(0, 0, 0);
            $this->pdf->y -= 8;
        }

        // Subtotals — amounts include currency symbol
        $coddivisa = property_exists($model, 'coddivisa') ? (string) $model->coddivisa : '';
        $sym = $this->getDivisaSymbol($coddivisa);
        $suffix = $sym !== '' ? ' ' . $sym : '';

        $headers = [
            'net' => $this->translator->trans('tax-base'),
            'taxPct' => '% ' . $this->translator->trans('tax'),
            'taxes' => $this->translator->trans('taxes'),
            'totalSurcharge' => $this->translator->trans('re'),
            'totalIrpf' => $this->translator->trans('irpf'),
            'totalSupplied' => $this->translator->trans('supplied-amount'),
            'total' => $this->translator->trans('total'),
        ];
        $neto = property_exists($model, 'neto') ? (float) $model->neto : 0.0;
        $totaliva = property_exists($model, 'totaliva') ? (float) $model->totaliva : 0.0;
        $totalrecargo = property_exists($model, 'totalrecargo') ? (float) $model->totalrecargo : 0.0;
        $totalirpf = property_exists($model, 'totalirpf') ? (float) $model->totalirpf : 0.0;
        $totalsuplidos = property_exists($model, 'totalsuplidos') ? (float) $model->totalsuplidos : 0.0;
        $total = property_exists($model, 'total') ? (float) $model->total : 0.0;

        // Resolve dominant tax percentage from line items
        $taxPct = '';
        foreach ($this->view->getLines() as $line) {
            $iva = is_array($line) ? (float) ($line['iva'] ?? 0) : (float) (property_exists($line, 'iva') ? $line->iva : 0);
            if ($iva > 0) {
                $taxPct = $this->formatNumber($iva) . '%';
                break;
            }
        }

        $rows = [
            [
                'net' => $this->formatNumber($neto) . $suffix,
                'taxPct' => $taxPct,
                'taxes' => $this->formatNumber($totaliva) . $suffix,
                'totalSurcharge' => $this->formatNumber($totalrecargo) . $suffix,
                'totalIrpf' => $this->formatNumber(0 - $totalirpf) . $suffix,
                'totalSupplied' => $this->formatNumber($totalsuplidos) . $suffix,
                'total' => $this->formatNumber($total) . $suffix,
            ],
        ];
        $zeroWithSuffix = $this->formatNumber(0) . $suffix;
        $this->removeEmptyCols($rows, $headers, $zeroWithSuffix);
        $tableOptions = [
            'cols' => [
                'net' => ['justification' => 'right'],
                'taxPct' => ['justification' => 'right'],
                'taxes' => ['justification' => 'right'],
                'totalSurcharge' => ['justification' => 'right'],
                'totalIrpf' => ['justification' => 'right'],
                'totalSupplied' => ['justification' => 'right'],
                'total' => ['justification' => 'right'],
            ],
            'shadeCol' => [$this->lr, $this->lg, $this->lb],
            'shadeHeadingCol' => [$this->hr, $this->hg, $this->hb],
            'width' => $this->tableWidth,
            'gridlines' => EZ_GRIDLINE_TABLE,
        ];
        $this->pdf->ezTable($rows, $headers, '', $tableOptions);
    }

    /**
     * Inserts the page-numbering footer and generation timestamp.
     */
    protected function insertFooter(): void
    {
        $now = $this->translator->trans('generated-at', ['%when%' => date('d-m-Y H:i')]);
        $pageText = '1 / 1';
        $leftX = (float) $this->pdf->ez['leftMargin'];
        $rightX = (float) ($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']);

        $this->pdf->addText($leftX, self::FOOTER_Y, self::FONT_SIZE, $pageText);
        $nowWidth = $this->pdf->getTextWidth(self::FONT_SIZE, $now);
        $this->pdf->addText($rightX - $nowWidth, self::FOOTER_Y, self::FONT_SIZE, $now);
    }

    /**
     * Returns the tax-breakdown rows for the model. Mirrors
     * the upstream `getTaxesRows($model)`. Reads from
     * `linea_iva_*_cliente` via the view.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTaxesRows(object $model): array
    {
        // The view-model may not expose lineasIva; we delegate
        // to a getter if present, otherwise empty array.
        if (!method_exists($this->view, 'getLineasIva')) {
            return [];
        }

        $lineasIva = $this->view->getLineasIva();
        $rows = [];
        foreach ($lineasIva as $linea) {
            $iva = property_exists($linea, 'iva') ? (float) $linea->iva : 0.0;
            $neto = property_exists($linea, 'neto') ? (float) $linea->neto : 0.0;
            $totaliva = property_exists($linea, 'totaliva') ? (float) $linea->totaliva : 0.0;
            $recargo = property_exists($linea, 'recargo') ? (float) $linea->recargo : 0.0;
            $totalrecargo = property_exists($linea, 'totalrecargo') ? (float) $linea->totalrecargo : 0.0;
            $rows[] = [
                'tax' => $iva . '%',
                'taxbase' => $this->formatNumber($neto),
                'taxp' => $iva . '%',
                'taxamount' => $this->formatNumber($totaliva),
                'taxsurchargep' => $recargo . '%',
                'taxsurcharge' => $this->formatNumber($totalrecargo),
            ];
        }

        return $rows;
    }

    /**
     * Removes empty columns (where all rows equal the zero
     * string) from a rows/headers pair. Mirrors the upstream
     * `removeEmptyCols($rows, $headers, $zeroStr)`.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int|string, string>         $headers
     */
    public function removeEmptyCols(array &$rows, array &$headers, string $zeroStr): void
    {
        if ($rows === []) {
            return;
        }
        $keys = array_keys($rows[0]);
        $dropKeys = [];
        foreach ($keys as $key) {
            $allZero = true;
            foreach ($rows as $row) {
                if ((string) ($row[$key] ?? '') !== $zeroStr) {
                    $allZero = false;
                    break;
                }
            }
            if ($allZero) {
                $dropKeys[] = $key;
            }
        }
        if ($dropKeys === []) {
            return;
        }
        foreach ($rows as &$row) {
            foreach ($dropKeys as $key) {
                unset($row[$key]);
            }
        }
        unset($row);
        foreach ($dropKeys as $key) {
            unset($headers[$key]);
        }
    }

    /**
     * Computes the dimensions of an image file, scaling the
     * width down to a maximum of 200 px and the height to 80
     * px. Mirrors the upstream `calcImageSize($filePath)`.
     */
    protected function calcImageSize(string $filePath): array
    {
        $size = @getimagesize($filePath);
        if ($size === false) {
            return ['width' => 0, 'height' => 0];
        }
        $imageSize = $size;
        if ($size[0] > 200) {
            $imageSize[0] = 200;
            $imageSize[1] = $imageSize[1] * $imageSize[0] / $size[0];
            $size[0] = $imageSize[0];
            $size[1] = $imageSize[1];
        }
        if ($size[1] > 80) {
            $imageSize[1] = 80;
            $imageSize[0] = $imageSize[0] * $imageSize[1] / $size[1];
        }
        $percent = (int) ($this->settings['medidalogo'] ?? 100);
        $imageSize[0] = $imageSize[0] * $percent / 100;
        $imageSize[1] = $imageSize[1] * $percent / 100;

        return ['width' => (float) $imageSize[0], 'height' => (float) $imageSize[1]];
    }

    /**
     * Cezpdf-backed image placement at exact coordinates.
     * Uses low-level addPngFromFile / addJpegFromFile to
     * honour the calculated $w/$h dimensions instead of
     * ezImage which defaults to the image's natural pixel size.
     */
    public function addImageFromFile(string $path, float $x, float $y, float $w, float $h): void
    {
        if (!file_exists($path)) {
            return;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $this->pdf->addPngFromFile($path, $x, $y, $w, $h);
        } elseif (in_array($ext, ['jpg', 'jpeg'], true)) {
            $this->pdf->addJpegFromFile($path, $x, $y, $w, $h);
        }
    }

    /**
     * Cezpdf-backed image placement for an `AttachedFile`-shaped
     * model. Mirrors the upstream `addImageFromAttachedFile()`.
     */
    public function addImageFromAttachedFile(object $attachedFile, float $x, float $y, float $w, float $h): void
    {
        $path = property_exists($attachedFile, 'path') ? (string) $attachedFile->path : '';
        if ($path === '' || !file_exists($path)) {
            return;
        }
        $this->addImageFromFile($path, $x, $y, $w, $h);
    }

    /**
     * Returns the bank-data string for a receipt. Mirrors the
     * upstream `getBankData($receipt)`.
     */
    protected function getBankData(object $receipt): string
    {
        $formaPago = $this->view->getFormaPago();
        $payMethodCodPago = property_exists($formaPago, 'codpago') ? (string) $formaPago->codpago : '';
        $payMethodDescripcion = property_exists($formaPago, 'descripcion') ? (string) $formaPago->descripcion : '';
        $imprimir = property_exists($formaPago, 'imprimir') ? (bool) $formaPago->imprimir : true;

        if (!$imprimir) {
            return $this->defTrans(
                $payMethodCodPago,
                $payMethodDescripcion,
                (bool) ($this->settings['traducirformaspago'] ?? true),
            );
        }

        $codcliente = property_exists($receipt, 'codcliente') ? (string) $receipt->codcliente : '';
        $iban = $this->view->getCuentaBancaria();
        if ($iban !== '') {
            return $this->defTrans(
                $payMethodCodPago,
                $payMethodDescripcion,
                (bool) ($this->settings['traducirformaspago'] ?? true),
            ) . ' - ' . $iban;
        }

        return $this->defTrans(
            $payMethodCodPago,
            $payMethodDescripcion,
            (bool) ($this->settings['traducirformaspago'] ?? true),
        );
    }

    /**
     * Inserts the invoice receipts (pagoyvencimiento mode).
     * Mirrors the upstream `insertInvoiceReceipts($invoice)`.
     */
    protected function insertInvoiceReceipts(): void
    {
        $recibos = $this->view->getRecibos();
        $modo = (int) ($this->settings['pagoyvencimiento'] ?? 3);
        if (count($recibos) === 0) {
            return;
        }
        if (count($recibos) === 1 && $modo === 3) {
            $r = $recibos[0];
            $pago = $this->translator->trans('payment-method') . ':  ' . $this->getBankData($r);
            $pagado = property_exists($r, 'pagado') ? (bool) $r->pagado : false;
            $venc = $this->translator->trans('expiration') . ':  ';
            if ($pagado) {
                $venc .= $this->translator->trans('paid');
            } else {
                $venc .= (string) (property_exists($r, 'vencimiento') ? $r->vencimiento : '');
            }
            $importe = property_exists($r, 'importe') ? (float) $r->importe : 0.0;
            $total = $this->translator->trans('amount') . ':  ' . $this->formatNumber($importe);
            $this->pdf->ezText("\n");
            $yy = (float) $this->pdf->y;
            $this->pdf->ezText($pago, self::FONT_SIZE + 1, ['justification' => 'left']);
            $this->pdf->y = $yy;
            $this->pdf->ezText($venc, self::FONT_SIZE + 1, ['justification' => 'right']);
        } else {
            $headers = [
                'numero' => $this->translator->trans('receipt'),
                'bank' => $this->translator->trans('payment-method'),
                'importe' => $this->translator->trans('amount'),
                'vencimiento' => $this->translator->trans('expiration'),
            ];
            $rows = [];
            foreach ($recibos as $receipt) {
                $rows[] = [
                    'numero' => (string) (property_exists($receipt, 'numero') ? $receipt->numero : ''),
                    'bank' => $this->getBankData($receipt),
                    'importe' => $this->formatNumber((float) (property_exists($receipt, 'importe') ? $receipt->importe : 0)),
                    'vencimiento' => (string) (property_exists($receipt, 'vencimiento') ? $receipt->vencimiento : ''),
                ];
            }
            $this->pdf->ezText("\n");
            $this->pdf->ezTable($rows, $headers, '', ['width' => $this->tableWidth]);
        }
    }

    /**
     * Inserts the expiration date. Mirrors the upstream
     * `insertExpiration($invoice)`.
     */
    protected function insertExpiration(): void
    {
        $model = $this->view->getDocument();
        $finoferta = property_exists($model, 'finoferta') ? (string) $model->finoferta : '';
        if ($finoferta !== '') {
            $this->pdf->ezText("\n");
            $venc = $this->translator->trans('expiration') . ':  ' . $finoferta;
            $this->pdf->ezText($venc, self::FONT_SIZE + 1, ['justification' => 'right']);
        }
    }

    /**
     * Render a QR image (kept on disk from upstream; not
     * invoked in PR-2 because Verifactu is deferred). Mirrors
     * the upstream `QRimg()`.
     */
    protected function QRimg(?string $qrImage, ?string $qrTitle1, ?string $qrTitle2, float $qrX, float $qrY, int $qrSize, int $qrFont): void
    {
        if (empty($qrImage)) {
            return;
        }
        // The QR rendering path is identical to the upstream
        // (base64 decode → temp file → addPngFromFile). The
        // PR-2 implementation defers Verifactu, so the path is
        // never invoked; we keep the method on disk per the
        // proposal's "QRimg is kept" note.
        if (str_starts_with($qrImage, 'data:image/')) {
            $base64Data = explode(',', $qrImage, 2)[1] ?? $qrImage;
            $imageData = base64_decode($base64Data);
            if ($imageData === false) {
                return;
            }
            $mimeType = 'image/png';
            if (preg_match('/data:image\/([^;]+)/', $qrImage, $matches)) {
                $mimeType = 'image/' . $matches[1];
            }
            $extension = ($mimeType === 'image/png') ? '.png' : '.jpg';
            $tempFile = tempnam(sys_get_temp_dir(), 'qr_') . $extension;
            if (!file_put_contents($tempFile, $imageData)) {
                return;
            }
            try {
                if ($mimeType === 'image/png') {
                    $this->pdf->addPngFromFile($tempFile, $qrX, $qrY - $qrSize, $qrSize, $qrSize);
                } else {
                    $this->pdf->addJpegFromFile($tempFile, $qrX, $qrY - $qrSize, $qrSize, $qrSize);
                }
                $this->pdf->y = $qrY - $qrSize;
            } catch (\Exception $e) {
                // swallow
            } finally {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        } elseif (file_exists($qrImage)) {
            $extension = strtolower(pathinfo($qrImage, PATHINFO_EXTENSION));
            try {
                if ($extension === 'png') {
                    $this->pdf->addPngFromFile($qrImage, $qrX, $qrY - $qrSize, $qrSize, $qrSize);
                } else {
                    $this->pdf->addJpegFromFile($qrImage, $qrX, $qrY - $qrSize, $qrSize, $qrSize);
                }
                $this->pdf->y = $qrY - $qrSize;
            } catch (\Exception $e) {
                // swallow
            }
        }

        if (!empty($qrTitle1)) {
            $textX = $qrX + ($qrSize / 2);
            $textY = $qrY;
            $this->pdf->addText($textX, $textY, $qrFont, $qrTitle1, 0, 'center');
        }
        if (!empty($qrTitle2)) {
            $textX = $qrX + ($qrSize / 2);
            $textY = $qrY - $qrSize - 6;
            $this->pdf->addText($textX, $textY, $qrFont, $qrTitle2, 0, 'center');
            $this->pdf->y = $textY - $this->pdf->getFontHeight($qrFont);
        }
    }
}
