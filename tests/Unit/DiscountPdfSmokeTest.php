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
use FSFramework\Plugins\factura_pdf1\Model\Adapters\AlbaranClienteAdapter;
use FSFramework\Plugins\factura_pdf1\Model\Adapters\FacturaClienteAdapter;
use FSFramework\Plugins\factura_pdf1\Model\View\AlbaranPrintView;
use FSFramework\Plugins\factura_pdf1\Model\View\FacturaPrintView;
use FSFramework\Plugins\factura_pdf1\Services\CezpdfRenderService;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Parser;

/**
 * Automated smoke for tpvmod-descuentos-cliente / factura_pdf1 T21:
 * discounted document line + print_dto → PDF shows net unit price.
 */
final class DiscountPdfSmokeTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    protected function tearDown(): void
    {
        FacturaPrintView::resetResolversForTests();
        AlbaranPrintView::resetResolversForTests();
    }

    public function testFacturaPdfShowsNetUnitPriceWhenPrintDtoEnabled(): void
    {
        $this->configureFacturaWithDiscountedLine();
        $adapter = FacturaClienteAdapter::fromId(1);

        $pdf = $this->render($adapter, ['print_dto' => true]);
        $text = $this->extractText($pdf);

        $this->assertStringContainsString('90,00', $text);
        $this->assertStringContainsString('100,00', $text);
        $this->assertGreaterThan(1024, strlen($pdf));
    }

    public function testAlbaranPdfKeepsListUnitPriceWhenPrintDtoDisabled(): void
    {
        $this->configureAlbaranWithDiscountedLine();
        $adapter = AlbaranClienteAdapter::fromId(1);

        $pdf = $this->render($adapter, ['print_dto' => false]);
        $text = $this->extractText($pdf);

        $this->assertStringContainsString('100,00', $text);
        $this->assertStringNotContainsString("100,00\n90,00", $text);
    }

    public function testAlbaranPdfShowsNetUnitPriceWhenPrintDtoEnabled(): void
    {
        $this->configureAlbaranWithDiscountedLine();
        $adapter = AlbaranClienteAdapter::fromId(1);

        $pdf = $this->render($adapter, ['print_dto' => true]);
        $text = $this->extractText($pdf);

        $this->assertStringContainsString('90,00', $text);
    }

    private function configureFacturaWithDiscountedLine(): void
    {
        DocumentPrintViewFixture::requireModels();

        $payload = DocumentPrintViewFixture::buildFacturaPayload();
        $payload['lineas'] = [$this->discountedLine('factura', 1)];

        FacturaPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\factura_cliente|false => $id === 1 ? $payload['factura'] : false,
            static fn (\FSFramework\model\factura_cliente $factura): array => $payload,
        );
    }

    private function configureAlbaranWithDiscountedLine(): void
    {
        DocumentPrintViewFixture::requireModels();

        $base = DocumentPrintViewFixture::buildFacturaPayload();
        $albaran = new \FSFramework\model\albaran_cliente(
            DocumentPrintViewFixture::buildGenericDocumentRow('ALB-TEST-001', 90.0, 74.38)
        );
        $albaran->idalbaran = 1;

        $payload = [
            'albaran' => $albaran,
            'empresa' => $base['empresa'],
            'cliente' => $base['cliente'],
            'divisa' => $base['divisa'],
            'formaPago' => $base['formaPago'],
            'pais' => $base['pais'],
            'lineas' => [$this->discountedLine('albaran', 1)],
            'lineasIva' => [],
        ];

        AlbaranPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\albaran_cliente|false => $id === 1 ? $payload['albaran'] : false,
            static fn (\FSFramework\model\albaran_cliente $albaran): array => $payload,
        );
    }

    private function discountedLine(string $documentType, int $idLinea): object
    {
        $row = [
            'idlinea' => $idLinea,
            'referencia' => 'REF-DTO',
            'descripcion' => 'Articulo con dto cliente',
            'cantidad' => 1,
            'pvpunitario' => 100,
            'pvpsindto' => 100,
            'dtopor' => 10,
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
        ];

        if ($documentType === 'albaran') {
            $row['idalbaran'] = 1;

            return new \FSFramework\model\linea_albaran_cliente($row);
        }

        $row['idfactura'] = 1;

        return new \FSFramework\model\linea_factura_cliente($row);
    }

    /**
     * @param array<string, mixed> $settingsOverride
     */
    private function render(object $adapter, array $settingsOverride): string
    {
        $settings = array_replace((new SettingsService())->defaults(), $settingsOverride);

        return (new CezpdfRenderService())->render($adapter, $settings);
    }

    private function extractText(string $pdf): string
    {
        $document = $this->parser->parseContent($pdf);

        return $document->getText();
    }
}
