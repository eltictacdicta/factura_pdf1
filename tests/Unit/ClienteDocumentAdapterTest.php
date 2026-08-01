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
use FSFramework\Plugins\factura_pdf1\Model\Adapters\PedidoClienteAdapter;
use FSFramework\Plugins\factura_pdf1\Model\Adapters\PresupuestoClienteAdapter;
use FSFramework\Plugins\factura_pdf1\Model\Exception\PrintableDocumentNotFoundException;
use FSFramework\Plugins\factura_pdf1\Model\PrintableDocumentInterface;
use FSFramework\Plugins\factura_pdf1\Model\View\AlbaranPrintView;
use FSFramework\Plugins\factura_pdf1\Model\View\FacturaPrintView;
use FSFramework\Plugins\factura_pdf1\Model\View\PedidoPrintView;
use FSFramework\Plugins\factura_pdf1\Model\View\PresupuestoPrintView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClienteDocumentAdapterTest extends TestCase
{
    protected function tearDown(): void
    {
        FacturaPrintView::resetResolversForTests();
        AlbaranPrintView::resetResolversForTests();
        PedidoPrintView::resetResolversForTests();
        PresupuestoPrintView::resetResolversForTests();
    }

    public function testFacturaAdapterExposesCanonicalShape(): void
    {
        $payload = DocumentPrintViewFixture::buildFacturaPayload();
        FacturaPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\factura_cliente|false => $id === 1 ? $payload['factura'] : false,
            static fn (\FSFramework\model\factura_cliente $factura): array => $payload,
        );

        $adapter = FacturaClienteAdapter::fromId(1);

        $this->assertInstanceOf(PrintableDocumentInterface::class, $adapter);
        $this->assertSame(1, $adapter->getId());
        $this->assertSame('FAC-TEST-001', $adapter->getCodigo());
        $this->assertSame('2026-01-01', $adapter->getFecha());
        $this->assertSame($payload['cliente'], $adapter->getCliente());
        $this->assertCount(2, $adapter->getLineas());
        $this->assertSame('REF-1', $adapter->getLineas()[0]['codigo']);
        $this->assertSame(1234.56, $adapter->getTotales()['total']);
        $this->assertSame([], $adapter->getRelatedDocuments());
        $this->assertSame($payload['formaPago'], $adapter->getFormaPago());
    }

    #[DataProvider('adapterProvider')]
    public function testAdapterFromIdReturnsInstance(string $adapterClass, string $viewClass, callable $configure): void
    {
        $configure();

        $adapter = $adapterClass::fromId(1);

        $this->assertInstanceOf(PrintableDocumentInterface::class, $adapter);
        $this->assertSame(1, $adapter->getId());
        $this->assertNotSame('', $adapter->getCodigo());
        $this->assertGreaterThan(0, $adapter->getTotales()['total']);
    }

    #[DataProvider('adapterProvider')]
    public function testMissingIdThrowsPrintableDocumentNotFound(string $adapterClass, string $viewClass, callable $configure): void
    {
        $configure();

        $this->expectException(PrintableDocumentNotFoundException::class);
        $adapterClass::fromId(999999);
    }

    public static function adapterProvider(): array
    {
        return [
            'factura' => [
                FacturaClienteAdapter::class,
                FacturaPrintView::class,
                static function (): void {
                    $payload = DocumentPrintViewFixture::buildFacturaPayload();
                    FacturaPrintView::setResolversForTests(
                        static fn (int $id) => $id === 1 ? $payload['factura'] : false,
                        static fn () => $payload,
                    );
                },
            ],
            'albaran' => [
                AlbaranClienteAdapter::class,
                AlbaranPrintView::class,
                static function (): void {
                    self::configureGenericView(AlbaranPrintView::class, 'albaran', 'ALB-TEST-001', 'idalbaran');
                },
            ],
            'pedido' => [
                PedidoClienteAdapter::class,
                PedidoPrintView::class,
                static function (): void {
                    self::configureGenericView(PedidoPrintView::class, 'pedido', 'PED-TEST-001', 'idpedido');
                },
            ],
            'presupuesto' => [
                PresupuestoClienteAdapter::class,
                PresupuestoPrintView::class,
                static function (): void {
                    self::configureGenericView(PresupuestoPrintView::class, 'presupuesto', 'PRE-TEST-001', 'idpresupuesto');
                },
            ],
        ];
    }

    /**
     * @param class-string $viewClass
     */
    private static function configureGenericView(string $viewClass, string $modelName, string $codigo, string $idField): void
    {
        DocumentPrintViewFixture::requireModels();
        $base = DocumentPrintViewFixture::buildFacturaPayload();
        $modelClass = '\\FSFramework\\model\\' . $modelName . '_cliente';
        $lineClass = '\\FSFramework\\model\\linea_' . $modelName . '_cliente';

        $document = new $modelClass(DocumentPrintViewFixture::buildGenericDocumentRow($codigo, 500.0, 400.0));
        $document->{$idField} = 1;

        $line = new $lineClass([
            'idlinea' => 1,
            $idField => 1,
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

        $viewClass::resetResolversForTests();
        $viewClass::setResolversForTests(
            static fn (int $id) => $id === 1 ? $document : false,
            static fn () => $payload,
        );
    }
}
