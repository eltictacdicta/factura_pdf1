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
use FSFramework\Plugins\factura_pdf1\Lib\PDF\PortedPdfDocument;
use FSFramework\Plugins\factura_pdf1\Services\FormatoDocumento;
use FSFramework\Plugins\factura_pdf1\Services\LocaleSettings;
use FSFramework\Plugins\factura_pdf1\Services\PdfNumberFormatter;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use FSFramework\Translation\FSTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TableHeadingColorTest extends TestCase
{
    #[DataProvider('headingColorProvider')]
    public function testApplyTableHeadingColors(string $hexColor, float $expectedR, float $expectedG, float $expectedB): void
    {
        $document = $this->buildExposedDocument();
        $document->exposeApplyTableHeadingColors($hexColor);

        $this->assertEqualsWithDelta($expectedR, $document->exposeHeadingRed(), 0.001);
        $this->assertEqualsWithDelta($expectedG, $document->exposeHeadingGreen(), 0.001);
        $this->assertEqualsWithDelta($expectedB, $document->exposeHeadingBlue(), 0.001);
    }

    /**
     * @return iterable<string, array{string, float, float, float}>
     */
    public static function headingColorProvider(): iterable
    {
        yield 'light default is darkened for white text' => ['#E9E9E9', 85 / 255, 85 / 255, 85 / 255];
        yield 'dark configured color is preserved' => ['#112233', 0x11 / 255, 0x22 / 255, 0x33 / 255];
        yield 'invalid hex falls back to readable gray' => ['#GGG', 85 / 255, 85 / 255, 85 / 255];
    }

    private function buildExposedDocument(): ExposeTableHeadingColorsDocument
    {
        $pdf = new Cezpdf('A4', 'portrait');
        $view = new \FacturaPdf1\Tests\Fixtures\StubView();

        return new ExposeTableHeadingColorsDocument(
            $pdf,
            $view,
            new SettingsService(),
            new FSTranslator(),
            new FormatoDocumento(),
            new PdfNumberFormatter(),
            new LocaleSettings(),
        );
    }
}

final class ExposeTableHeadingColorsDocument extends PortedPdfDocument
{
    public function exposeApplyTableHeadingColors(string $hexColor): void
    {
        $this->applyTableHeadingColors($hexColor);
    }

    public function exposeHeadingRed(): float
    {
        return $this->hr;
    }

    public function exposeHeadingGreen(): float
    {
        return $this->hg;
    }

    public function exposeHeadingBlue(): float
    {
        return $this->hb;
    }
}
