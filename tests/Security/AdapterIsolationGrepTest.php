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

namespace FacturaPdf1\Tests\Security;

use PHPUnit\Framework\TestCase;

final class AdapterIsolationGrepTest extends TestCase
{
    /** @var array<string, list<string>> */
    private const FORBIDDEN_CROSS_REFERENCES = [
        'FacturaClienteAdapter.php' => ['AlbaranClienteAdapter', 'PedidoClienteAdapter', 'PresupuestoClienteAdapter', 'albaran_cliente', 'pedido_cliente', 'presupuesto_cliente'],
        'AlbaranClienteAdapter.php' => ['FacturaClienteAdapter', 'PedidoClienteAdapter', 'PresupuestoClienteAdapter', 'factura_cliente', 'pedido_cliente', 'presupuesto_cliente'],
        'PedidoClienteAdapter.php' => ['FacturaClienteAdapter', 'AlbaranClienteAdapter', 'PresupuestoClienteAdapter', 'factura_cliente', 'albaran_cliente', 'presupuesto_cliente'],
        'PresupuestoClienteAdapter.php' => ['FacturaClienteAdapter', 'AlbaranClienteAdapter', 'PedidoClienteAdapter', 'factura_cliente', 'albaran_cliente', 'pedido_cliente'],
    ];

    public function testAdaptersDoNotReferenceOtherDocumentTypes(): void
    {
        $adapterDir = dirname(__DIR__, 2) . '/Model/Adapters';
        $violations = [];

        foreach (self::FORBIDDEN_CROSS_REFERENCES as $file => $forbidden) {
            $path = $adapterDir . '/' . $file;
            $this->assertFileExists($path);
            $contents = (string) file_get_contents($path);

            foreach ($forbidden as $needle) {
                if (str_contains($contents, $needle)) {
                    $violations[] = $file . ' references ' . $needle;
                }
            }
        }

        $this->assertSame([], $violations, implode('; ', $violations));
    }
}
