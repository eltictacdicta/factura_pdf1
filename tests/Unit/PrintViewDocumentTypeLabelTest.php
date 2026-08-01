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
use FSFramework\Plugins\factura_pdf1\Model\View\AlbaranPrintView;
use FSFramework\Plugins\factura_pdf1\Model\View\FacturaPrintView;
use FSFramework\Plugins\factura_pdf1\Model\View\PedidoPrintView;
use FSFramework\Plugins\factura_pdf1\Model\View\PresupuestoPrintView;
use PHPUnit\Framework\TestCase;

/**
 * Locks in feature 17: the 4 `*PrintView` classes override
 * `getDocumentTypeLabel()` to read `formato_documento->titulo` first,
 * falling back to the current hard-coded literal when the formato row
 * is `null` or has no `titulo` field.
 *
 * The `formato_documento` lookup is intentionally scoped to the
 * print view: the adapter does not know about it. Each `*PrintView`
 * exposes a static seam `setFormatoDocumentoResolverForTests()` (or
 * equivalent) that lets the test inject a stub without requiring a
 * DB. The default test seam is `null` (no formato) and the print
 * view falls back to the hard-coded literal.
 *
 * Four scenarios:
 *   - Factura: titulo='Factura Proforma' => 'Factura Proforma'
 *   - Albaran: no formato => 'Albarán'
 *   - Pedido: titulo='Pedido Urgente' => 'Pedido Urgente'
 *   - Presupuesto: no formato => 'Presupuesto'
 */
final class PrintViewDocumentTypeLabelTest extends TestCase
{
    protected function setUp(): void
    {
        DocumentPrintViewFixture::requireModels();
    }

    protected function tearDown(): void
    {
        FacturaPrintView::resetResolversForTests();
        AlbaranPrintView::resetResolversForTests();
        PedidoPrintView::resetResolversForTests();
        PresupuestoPrintView::resetResolversForTests();
    }

    public function testFacturaLabelUsesFormatoTituloWhenAvailable(): void
    {
        $this->configureFactura(formatoTitulo: 'Factura Proforma');

        $view = FacturaPrintView::fromId(1);
        $this->assertSame('Factura Proforma', $view->getDocumentTypeLabel());
    }

    public function testAlbaranLabelFallsBackToHardcodedLiteralWhenFormatoIsNull(): void
    {
        $this->configureAlbaran(formatoTitulo: null);

        $view = AlbaranPrintView::fromId(1);
        $this->assertSame('Albarán', $view->getDocumentTypeLabel());
    }

    public function testPedidoLabelUsesFormatoTituloWhenAvailable(): void
    {
        $this->configurePedido(formatoTitulo: 'Pedido Urgente');

        $view = PedidoPrintView::fromId(1);
        $this->assertSame('Pedido Urgente', $view->getDocumentTypeLabel());
    }

    public function testPresupuestoLabelFallsBackToHardcodedLiteralWhenFormatoIsNull(): void
    {
        $this->configurePresupuesto(formatoTitulo: null);

        $view = PresupuestoPrintView::fromId(1);
        $this->assertSame('Presupuesto', $view->getDocumentTypeLabel());
    }

    private function configureFactura(?string $formatoTitulo): void
    {
        $base = DocumentPrintViewFixture::buildFacturaPayload();
        $factura = $base['factura'];
        $factura->idfactura = 1;

        FacturaPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\factura_cliente|false => $id === 1 ? $factura : false,
            static fn (\FSFramework\model\factura_cliente $f): array => $base,
        );
        FacturaPrintView::setFormatoDocumentoResolverForTests(
            static fn (): ?string => $formatoTitulo,
        );
    }

    private function configureAlbaran(?string $formatoTitulo): void
    {
        $base = DocumentPrintViewFixture::buildFacturaPayload();
        $albaran = new \FSFramework\model\albaran_cliente(
            DocumentPrintViewFixture::buildGenericDocumentRow('ALB-TEST-001', 500.0, 400.0),
        );
        $albaran->idalbaran = 1;

        $line = new \FSFramework\model\linea_albaran_cliente([
            'idlinea' => 1,
            'idalbaran' => 1,
            'referencia' => 'REF-1',
            'descripcion' => 'Linea 1',
            'cantidad' => 1,
            'pvpunitario' => 400,
            'pvpsindto' => 400,
            'dtopor' => 0,
            'dtopor2' => 0,
            'dtopor3' => 0,
            'dtopor4' => 0,
            'pvptotal' => 400,
            'codimpuesto' => 'IVA21',
            'codcombinacion' => null,
            'iva' => 21,
            'recargo' => 0,
            'irpf' => 0,
            'orden' => 1,
            'mostrar_cantidad' => true,
            'mostrar_precio' => true,
        ]);

        $payload = [
            'empresa' => $base['empresa'],
            'cliente' => $base['cliente'],
            'divisa' => $base['divisa'],
            'formaPago' => $base['formaPago'],
            'pais' => $base['pais'],
            'lineas' => [$line],
            'lineasIva' => [],
        ];

        AlbaranPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\albaran_cliente|false => $id === 1 ? $albaran : false,
            static fn (): array => $payload,
        );
        AlbaranPrintView::setFormatoDocumentoResolverForTests(
            static fn (): ?string => null,
        );
    }

    private function configurePedido(?string $formatoTitulo): void
    {
        $base = DocumentPrintViewFixture::buildFacturaPayload();
        $pedido = new \FSFramework\model\pedido_cliente(
            DocumentPrintViewFixture::buildGenericDocumentRow('PED-TEST-001', 800.0, 700.0),
        );
        $pedido->idpedido = 1;

        $line = new \FSFramework\model\linea_pedido_cliente([
            'idlinea' => 1,
            'idpedido' => 1,
            'referencia' => 'REF-1',
            'descripcion' => 'Linea 1',
            'cantidad' => 1,
            'pvpunitario' => 700,
            'pvpsindto' => 700,
            'dtopor' => 0,
            'dtopor2' => 0,
            'dtopor3' => 0,
            'dtopor4' => 0,
            'pvptotal' => 700,
            'codimpuesto' => 'IVA21',
            'codcombinacion' => null,
            'iva' => 21,
            'recargo' => 0,
            'irpf' => 0,
            'orden' => 1,
            'mostrar_cantidad' => true,
            'mostrar_precio' => true,
        ]);

        $payload = [
            'empresa' => $base['empresa'],
            'cliente' => $base['cliente'],
            'divisa' => $base['divisa'],
            'formaPago' => $base['formaPago'],
            'pais' => $base['pais'],
            'lineas' => [$line],
            'lineasIva' => [],
        ];

        PedidoPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\pedido_cliente|false => $id === 1 ? $pedido : false,
            static fn (): array => $payload,
        );
        PedidoPrintView::setFormatoDocumentoResolverForTests(
            static fn (): ?string => $formatoTitulo,
        );
    }

    private function configurePresupuesto(?string $formatoTitulo): void
    {
        $base = DocumentPrintViewFixture::buildFacturaPayload();
        $presupuesto = new \FSFramework\model\presupuesto_cliente(
            DocumentPrintViewFixture::buildGenericDocumentRow('PRE-TEST-001', 200.0, 180.0),
        );
        $presupuesto->idpresupuesto = 1;

        $line = new \FSFramework\model\linea_presupuesto_cliente([
            'idlinea' => 1,
            'idpresupuesto' => 1,
            'referencia' => 'REF-1',
            'descripcion' => 'Linea 1',
            'cantidad' => 1,
            'pvpunitario' => 180,
            'pvpsindto' => 180,
            'dtopor' => 0,
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

        $payload = [
            'empresa' => $base['empresa'],
            'cliente' => $base['cliente'],
            'divisa' => $base['divisa'],
            'formaPago' => $base['formaPago'],
            'pais' => $base['pais'],
            'lineas' => [$line],
            'lineasIva' => [],
        ];

        PresupuestoPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\presupuesto_cliente|false => $id === 1 ? $presupuesto : false,
            static fn (): array => $payload,
        );
        PresupuestoPrintView::setFormatoDocumentoResolverForTests(
            static fn (): ?string => null,
        );
    }
}
