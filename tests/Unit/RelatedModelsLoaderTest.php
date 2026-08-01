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

use FSFramework\Plugins\factura_pdf1\Services\RelatedModelsLoader;
use PHPUnit\Framework\TestCase;

/**
 * Per AD-12, RelatedModelsLoader centralizes the 5 cross-model joins that
 * the new adapter getters expose. Each load*() method MUST be null-safe
 * (returns null / [] / empty string when the joined model is missing or
 * the underlying class is not present in this environment).
 *
 * The 5 tests in this file pin that contract. PR-1 ships the contract;
 * PR-2 wires the joins to live data (when more model classes are added to
 * the repo) and to the adapters' `fromId()` path.
 */
final class RelatedModelsLoaderTest extends TestCase
{
    public function testLoadAlmacenReturnsNullWhenClassMissing(): void
    {
        $loader = new RelatedModelsLoader();

        $this->assertNull($loader->loadAlmacen(''));
        $this->assertNull($loader->loadAlmacen('NONEXISTENT-CODE-XYZ'));
    }

    public function testLoadContactoEnvioReturnsNullWhenIdIsZeroOrNegative(): void
    {
        $loader = new RelatedModelsLoader();

        $this->assertNull($loader->loadContactoEnvio(0));
        $this->assertNull($loader->loadContactoEnvio(-1));
    }

    public function testLoadCuentaBancariaReturnsEmptyStringWhenNoIbanIsResolvable(): void
    {
        $loader = new RelatedModelsLoader();

        $this->assertSame('', $loader->loadCuentaBancaria('', ''));
        $this->assertSame('', $loader->loadCuentaBancaria('C001', 'CB-NONEXISTENT'));
    }

    public function testLoadAgenciaTransporteReturnsEmptyShapeWhenCodtransIsEmpty(): void
    {
        $loader = new RelatedModelsLoader();

        $this->assertSame(
            ['nombre' => '', 'tracking' => ''],
            $loader->loadAgenciaTransporte('', null),
        );
        $this->assertSame(
            ['nombre' => '', 'tracking' => 'TRK-2026-0001'],
            $loader->loadAgenciaTransporte('', 'TRK-2026-0001'),
        );
    }

    public function testLoadRecibosReturnsEmptyArrayWhenNoRecibosExist(): void
    {
        $loader = new RelatedModelsLoader();

        $this->assertSame([], $loader->loadRecibos('factura_cliente', '999999'));
        $this->assertSame([], $loader->loadRecibos('pedido_cliente', '1'));
    }
}
