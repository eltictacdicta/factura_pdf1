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

final class SqlInjectionGrepTest extends TestCase
{
    public function testNoUnsafeSqlConcatenationInModernPluginCode(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $command = sprintf(
            "grep -rEn '(SELECT|INSERT|UPDATE|DELETE).*\\$.*(_GET|_POST|_REQUEST)' %s/Controller/ %s/Services/ %s/Model/ 2>/dev/null || true",
            escapeshellarg($pluginRoot),
            escapeshellarg($pluginRoot),
            escapeshellarg($pluginRoot),
        );

        $output = trim((string) shell_exec($command));
        $this->assertSame('', $output, 'Unsafe SQL patterns found: ' . $output);
    }
}
