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
use PHPUnit\Framework\TestCase;

/**
 * Per AD-11 (adapter getter convention): the new getters on
 * PrintableDocumentInterface MUST have a default implementation in
 * AbstractClienteDocumentAdapter that returns null / [] / '' when the
 * underlying related-model join resolves to nothing. The tests in this
 * file pin that contract: every getter MUST exist on the concrete
 * adapter, MUST have the documented return type, and MUST return the
 * documented default for an empty fixture.
 */
final class AdapterGettersTest extends TestCase
{
    protected function tearDown(): void
    {
        FacturaPrintView::resetResolversForTests();
    }

    public function testGetAlmacenReturnsNullByDefault(): void
    {
        $adapter = $this->buildFacturaAdapter(codalmacen: 'NON-EXISTENT-CODE-9999');

        $this->assertTrue(method_exists($adapter, 'getAlmacen'), 'Adapter must expose getAlmacen()');
        $this->assertNull($adapter->getAlmacen());
    }

    public function testGetContactoEnvioReturnsNullByDefault(): void
    {
        $adapter = $this->buildFacturaAdapter();

        $this->assertTrue(method_exists($adapter, 'getContactoEnvio'), 'Adapter must expose getContactoEnvio()');
        $this->assertNull($adapter->getContactoEnvio());
    }

    public function testGetCuentaBancariaReturnsEmptyStringByDefault(): void
    {
        $adapter = $this->buildFacturaAdapter();

        $this->assertTrue(method_exists($adapter, 'getCuentaBancaria'), 'Adapter must expose getCuentaBancaria()');
        $this->assertSame('', $adapter->getCuentaBancaria());
    }

    public function testGetAgenciaTransporteReturnsEmptyShapeByDefault(): void
    {
        $adapter = $this->buildFacturaAdapter(codtrans: 'NON-EXISTENT-AGENCY-9999');

        $this->assertTrue(method_exists($adapter, 'getAgenciaTransporte'), 'Adapter must expose getAgenciaTransporte()');
        $this->assertSame(
            ['nombre' => '', 'tracking' => ''],
            $adapter->getAgenciaTransporte(),
        );
    }

    public function testGetRecibosReturnsEmptyArrayByDefault(): void
    {
        $adapter = $this->buildFacturaAdapter();

        $this->assertTrue(method_exists($adapter, 'getRecibos'), 'Adapter must expose getRecibos()');
        $this->assertSame([], $adapter->getRecibos());
    }

    private function buildFacturaAdapter(
        string $codalmacen = 'ALG',
        string $codtrans = '',
    ): FacturaClienteAdapter {
        $payload = DocumentPrintViewFixture::buildFacturaPayload();
        $payload['factura']->codalmacen = $codalmacen;
        $payload['factura']->codtrans = $codtrans === '' ? null : $codtrans;

        FacturaPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\factura_cliente|false => $id === 1 ? $payload['factura'] : false,
            static fn (\FSFramework\model\factura_cliente $factura): array => $payload,
        );

        return FacturaClienteAdapter::fromId(1);
    }
}
