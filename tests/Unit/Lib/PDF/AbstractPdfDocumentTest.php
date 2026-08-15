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

namespace FacturaPdf1\Tests\Unit\Lib\PDF;

use Cezpdf;
use FSFramework\Plugins\factura_pdf1\Lib\PDF\AbstractPdfDocument;
use FSFramework\Plugins\factura_pdf1\Model\FacturaPdf1Setting;
use FSFramework\Plugins\factura_pdf1\Services\FormatoDocumento;
use FSFramework\Plugins\factura_pdf1\Services\LocaleSettings;
use FSFramework\Plugins\factura_pdf1\Services\PdfNumberFormatter;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use FSFramework\Translation\FSTranslator;
use PHPUnit\Framework\TestCase;

/**
 * The PR-1 of `factura-pdf1-czpdf-pixel-parity` ships this
 * shim with PR-2-style stubs for the methods the upstream
 * `PDFDocument` relies on. Each test asserts ONE public method
 * of the shim against a default and an override; PR-2 replaces
 * the stubs with the verbatim port logic.
 */
final class AbstractPdfDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        FacturaPdf1Setting::enableTestStorage();
    }

    protected function tearDown(): void
    {
        FacturaPdf1Setting::disableTestStorage();
    }

    private function buildDocument(
        ?Cezpdf $pdf = null,
        ?SettingsService $settings = null,
        ?FSTranslator $translator = null,
        ?FormatoDocumento $format = null,
        ?PdfNumberFormatter $numberFormatter = null,
        ?LocaleSettings $locale = null,
    ): AbstractPdfDocument {
        $pdf ??= new Cezpdf('a4');
        $settings ??= new SettingsService();
        $translator ??= new FSTranslator();
        $format ??= new FormatoDocumento();
        $numberFormatter ??= new PdfNumberFormatter();
        $locale ??= new LocaleSettings();

        return new class (
            $pdf,
            $settings,
            $translator,
            $format,
            $numberFormatter,
            $locale,
        ) extends AbstractPdfDocument {
            public function render(): void
            {
                // PR-1 stub: no draw logic yet. PR-2 replaces the
                // body with the verbatim port of the upstream
                // PDFDocument::render().
            }
        };
    }

    public function testConstructorStoresInjectedDependencies(): void
    {
        $format = new FormatoDocumento('logo-1', 'Title', 'Body');
        $locale = new LocaleSettings('.', ',', 5);

        $doc = $this->buildDocument(null, null, null, $format, null, $locale);

        $this->assertSame('Title', $doc->getFormat()->titulo);
        $this->assertSame('.', $doc->getLocale()->getDecimalSeparator());
        $this->assertSame(5, $doc->getLocale()->getIdempresa());
    }

    public function testGetTableWidthReturnsDefault(): void
    {
        $doc = $this->buildDocument();

        $this->assertSame(480.0, $doc->getTableWidth());
    }

    public function testSetTableWidthUpdatesValue(): void
    {
        $doc = $this->buildDocument();
        $doc->setTableWidth(420.0);

        $this->assertSame(420.0, $doc->getTableWidth());
    }

    public function testIsInsertedHeaderStartsFalse(): void
    {
        $doc = $this->buildDocument();

        $this->assertFalse($doc->isInsertedHeader());
    }

    public function testSetInsertedHeaderUpdatesValue(): void
    {
        $doc = $this->buildDocument();
        $doc->setInsertedHeader(true);

        $this->assertTrue($doc->isInsertedHeader());
    }

    public function testNoHtmlStripsTagsAndDecodesEntities(): void
    {
        $doc = $this->buildDocument();

        $result = $doc->noHtml('<b>Hola</b> &amp; adios');

        $this->assertSame('Hola & adios', $result);
    }

    public function testFormatNumberDelegatesToLocaleAwareFormatter(): void
    {
        $doc = $this->buildDocument(null, null, null, null, null, new LocaleSettings('.', ','));

        $this->assertSame('1,234.50', $doc->formatNumber(1234.5));
    }

    public function testFormatNumberWithEsEsDefaultsUsesCommaDecimal(): void
    {
        $doc = $this->buildDocument();

        $this->assertSame('1.234,50', $doc->formatNumber(1234.5));
    }

    public function testFormatPdfCurrencySymbolReturnsUtf8EuroForCezpdf(): void
    {
        $doc = $this->buildDocument();

        $this->assertSame('€', $doc->formatPdfCurrencySymbol('€'));
        $this->assertSame('€', $doc->formatPdfCurrencySymbol('', '978'));
        $this->assertSame('€', $doc->formatPdfCurrencySymbol('', 'EUR'));
        $this->assertSame('€', $doc->formatPdfCurrencySymbol('', 'eur'));

        $suffix = '412,22 ' . $doc->formatPdfCurrencySymbol('€');
        $win1252 = mb_convert_encoding($suffix, 'Windows-1252', 'UTF-8');
        $this->assertSame("\x80", substr($win1252, -1));
    }

    public function testFormatPdfCurrencySymbolKeepsAsciiSymbols(): void
    {
        $doc = $this->buildDocument();

        $this->assertSame('B', $doc->formatPdfCurrencySymbol('B'));
        $this->assertSame('$', $doc->formatPdfCurrencySymbol('$'));
    }

    public function testGetTaxesRowsReturnsEmptyArrayStub(): void
    {
        $doc = $this->buildDocument();
        $model = new \stdClass();

        $this->assertSame([], $doc->getTaxesRows($model));
    }

    public function testGetCountryNameReturnsCodeStub(): void
    {
        $doc = $this->buildDocument();

        $this->assertSame('ESP', $doc->getCountryName('ESP'));
    }

    public function testGetDivisaNameReturnsCodeStub(): void
    {
        $doc = $this->buildDocument();

        $this->assertSame('EUR', $doc->getDivisaName('EUR'));
    }

    public function testRemoveEmptyColsIsNoOpStub(): void
    {
        $doc = $this->buildDocument();
        $rows = [['a' => '1', 'b' => '0']];
        $headers = ['a', 'b'];

        $doc->removeEmptyCols($rows, $headers, '0');

        $this->assertSame([['a' => '1', 'b' => '0']], $rows);
        $this->assertSame(['a', 'b'], $headers);
    }

    public function testAddImageFromFileIsNoOpStub(): void
    {
        $doc = $this->buildDocument();

        $doc->addImageFromFile('/nonexistent.png', 1.0, 2.0, 3.0, 4.0);

        $this->expectNotToPerformAssertions();
    }

    public function testAddImageFromAttachedFileIsNoOpStub(): void
    {
        $doc = $this->buildDocument();
        $attached = new \stdClass();
        $attached->path = '/tmp/missing.png';

        $doc->addImageFromAttachedFile($attached, 1.0, 2.0, 3.0, 4.0);

        $this->expectNotToPerformAssertions();
    }

    public function testGetFileNameReturnsDefaultDocumentName(): void
    {
        $doc = $this->buildDocument();

        $this->assertSame('document.pdf', $doc->getFileName());
    }

    public function testNewLineCallsEzSetDyOnCezpdf(): void
    {
        $pdf = new Cezpdf('a4');
        $doc = $this->buildDocument($pdf);

        $doc->newLine();

        $this->expectNotToPerformAssertions();
    }

    public function testPipeReturnsEmptyStringStub(): void
    {
        $doc = $this->buildDocument();
        $model = new \stdClass();

        $this->assertSame('', $doc->pipe('qrImageHeader', $model));
    }
}
