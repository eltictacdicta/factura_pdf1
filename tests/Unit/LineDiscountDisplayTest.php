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

namespace FacturaPdf1\Tests\Unit;

use FacturaPdf1\Tests\Fixtures\DocumentPrintViewFixture;
use FSFramework\Plugins\factura_pdf1\Model\Adapters\FacturaClienteAdapter;
use FSFramework\Plugins\factura_pdf1\Model\View\FacturaPrintView;
use FSFramework\Plugins\factura_pdf1\Services\ImpresionSettingsReader;
use FSFramework\Plugins\factura_pdf1\Services\LineDiscountFormatter;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use PHPUnit\Framework\TestCase;

final class LineDiscountDisplayTest extends TestCase
{
    protected function tearDown(): void
    {
        FacturaPrintView::resetResolversForTests();
        ImpresionSettingsReader::setResolverForTests(null);
    }

    public function testAdapterLineShapeIncludesDiscountFields(): void
    {
        DocumentPrintViewFixture::requireModels();

        $line = new \FSFramework\model\linea_factura_cliente([
            'idlinea' => 1,
            'idfactura' => 1,
            'referencia' => 'REF-DTO',
            'descripcion' => 'Linea con dto',
            'cantidad' => 2,
            'pvpunitario' => 100,
            'pvpsindto' => 200,
            'dtopor' => 10,
            'dtopor2' => 0,
            'dtopor3' => 0,
            'dtopor4' => 0,
            'pvptotal' => 180,
            'codimpuesto' => 'IVA21',
            'codcombinacion' => null,
            'iva' => 21,
            'recargo' => 0,
            'irpf' => 0,
            'orden' => 1,
            'mostrar_cantidad' => true,
            'mostrar_precio' => true,
        ]);

        $payload = DocumentPrintViewFixture::buildFacturaPayload();
        $payload['lineas'] = [$line];

        FacturaPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\factura_cliente|false => $id === 1 ? $payload['factura'] : false,
            static fn (\FSFramework\model\factura_cliente $factura): array => $payload,
        );

        $linea = FacturaClienteAdapter::fromId(1)->getLineas()[0];

        $this->assertSame(10.0, $linea['dtopor']);
        $this->assertSame(0.0, $linea['dtopor2']);
        $this->assertSame(0.0, $linea['dtopor3']);
        $this->assertSame(0.0, $linea['dtopor4']);
        $this->assertSame(200.0, $linea['pvpsindto']);
        $this->assertSame(100.0, $linea['pvpunitario']);
    }

    public function testAdapterFillsDiscountsFromClienteWhenLineHasNone(): void
    {
        DocumentPrintViewFixture::requireModels();

        $line = new \FSFramework\model\linea_factura_cliente([
            'idlinea' => 1,
            'idfactura' => 1,
            'referencia' => 'REF-OLD',
            'descripcion' => 'Linea legacy sin dto en BD',
            'cantidad' => 1,
            'pvpunitario' => 100,
            'pvpsindto' => 0,
            'dtopor' => 0,
            'dtopor2' => 0,
            'dtopor3' => 0,
            'dtopor4' => 0,
            'pvptotal' => 90,
            'codimpuesto' => 'IVA21',
            'codcombinacion' => null,
            'iva' => 21,
            'recargo' => 0,
            'irpf' => 0,
            'orden' => 1,
            'mostrar_cantidad' => true,
            'mostrar_precio' => true,
        ]);

        $payload = DocumentPrintViewFixture::buildFacturaPayload();
        $payload['lineas'] = [$line];
        $payload['cliente']->d1 = 10.0;
        $payload['cliente']->d2 = 0.0;
        $payload['cliente']->d3 = 0.0;
        $payload['cliente']->d4 = 0.0;

        FacturaPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\factura_cliente|false => $id === 1 ? $payload['factura'] : false,
            static fn (\FSFramework\model\factura_cliente $factura): array => $payload,
        );

        $linea = FacturaClienteAdapter::fromId(1)->getLineas()[0];

        $this->assertSame(10.0, $linea['dtopor']);
        $this->assertSame(100.0, $linea['pvpsindto']);
        $this->assertSame(90.0, $linea['pvptotal']);
    }

    public function testCalcPvptotalAppliesCascadingDiscounts(): void
    {
        $this->assertSame(0.6, LineDiscountFormatter::calcPvptotal(1.0, 1.0, 40.0, 0.0, 0.0, 0.0));
    }

    public function testComputeDocumentTotalsFromLinesUsesDiscountedNeto(): void
    {
        $totals = LineDiscountFormatter::computeDocumentTotalsFromLines([
            [
                'pvptotal' => 0.6,
                'iva' => 21.0,
                'recargo' => 0.0,
                'irpf' => 0.0,
            ],
        ]);

        $this->assertSame(0.6, $totals['neto']);
        $this->assertEqualsWithDelta(0.126, $totals['totaliva'], 0.0001);
        $this->assertEqualsWithDelta(0.726, $totals['total'], 0.0001);
    }

    public function testGetTotalesRecomputesWhenLegacyLineUsesClienteDiscounts(): void
    {
        DocumentPrintViewFixture::requireModels();

        $line = new \FSFramework\model\linea_factura_cliente([
            'idlinea' => 1,
            'idfactura' => 1,
            'referencia' => 'REF-OLD',
            'descripcion' => 'Linea legacy sin dto en BD',
            'cantidad' => 1,
            'pvpunitario' => 1,
            'pvpsindto' => 0,
            'dtopor' => 0,
            'dtopor2' => 0,
            'dtopor3' => 0,
            'dtopor4' => 0,
            'pvptotal' => 1,
            'codimpuesto' => 'IVA21',
            'codcombinacion' => null,
            'iva' => 21,
            'recargo' => 0,
            'irpf' => 0,
            'orden' => 1,
            'mostrar_cantidad' => true,
            'mostrar_precio' => true,
        ]);

        $payload = DocumentPrintViewFixture::buildFacturaPayload();
        $payload['lineas'] = [$line];
        $payload['cliente']->d1 = 40.0;
        $payload['cliente']->d2 = 0.0;
        $payload['cliente']->d3 = 0.0;
        $payload['cliente']->d4 = 0.0;
        $payload['factura']->neto = 1.0;
        $payload['factura']->totaliva = 0.21;
        $payload['factura']->total = 1.21;

        FacturaPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\factura_cliente|false => $id === 1 ? $payload['factura'] : false,
            static fn (\FSFramework\model\factura_cliente $factura): array => $payload,
        );

        $totales = FacturaClienteAdapter::fromId(1)->getTotales();

        $this->assertSame(0.6, $totales['neto']);
        $this->assertEqualsWithDelta(0.126, $totales['totaliva'], 0.0001);
        $this->assertEqualsWithDelta(0.726, $totales['total'], 0.0001);
    }

    public function testResolveLineDiscountsUsesClienteWhenLineIsLegacy(): void
    {
        $cliente = new class {
            public function getEffectiveDiscounts(): array
            {
                return ['d1' => 15.0, 'd2' => 5.0, 'd3' => 0.0, 'd4' => 0.0];
            }
        };

        $resolved = LineDiscountFormatter::resolveLineDiscounts((object) [
            'dtopor' => 0,
            'dtopor2' => 0,
            'dtopor3' => 0,
            'dtopor4' => 0,
        ], $cliente);

        $this->assertSame(15.0, $resolved['dtopor']);
        $this->assertSame(5.0, $resolved['dtopor2']);
    }

    public function testFormatUnitPriceCellShowsStrikethroughWhenPrintDtoEnabled(): void
    {
        $cell = LineDiscountFormatter::formatUnitPriceCell(
            true,
            100.0,
            10.0,
            0.0,
            0.0,
            0.0,
            '100,00',
            '90,00',
        );

        $this->assertStringContainsString('<c:color:0.45,0.45,0.45><c:strike>100,00</c:strike></c:color>', $cell);
        $this->assertStringContainsString("\n90,00", $cell);
    }

    public function testFormatUnitPriceCellKeepsSinglePriceWhenPrintDtoDisabled(): void
    {
        $cell = LineDiscountFormatter::formatUnitPriceCell(
            false,
            100.0,
            10.0,
            0.0,
            0.0,
            0.0,
            '100,00',
            '90,00',
        );

        $this->assertSame('100,00', $cell);
    }

    public function testSettingsServiceLoadsPrintDtoFromImpresionReader(): void
    {
        ImpresionSettingsReader::setResolverForTests(static fn (): bool => false);

        $settings = (new SettingsService())->load();

        $this->assertArrayHasKey('print_dto', $settings);
        $this->assertFalse($settings['print_dto']);
    }
}
