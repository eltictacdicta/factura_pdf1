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

namespace FacturaPdf1\Tests\Unit\Services;

use FSFramework\Plugins\factura_pdf1\Services\PdfNumberFormatter;
use PHPUnit\Framework\TestCase;

final class PdfNumberFormatterTest extends TestCase
{
    public function testEsEsDefaultUsesCommaAndDot(): void
    {
        $result = PdfNumberFormatter::format(1234.5);

        $this->assertSame('1.234,50', $result);
    }

    public function testEnEnUsesDotAndComma(): void
    {
        $result = PdfNumberFormatter::format(1234.5, '.', ',');

        $this->assertSame('1,234.50', $result);
    }

    public function testCustomDecimalsOverrideDefaultTwo(): void
    {
        $result = PdfNumberFormatter::format(7.5, ',', '.', 0);

        $this->assertSame('8', $result);
    }

    public function testNegativeNumberIsHandled(): void
    {
        $result = PdfNumberFormatter::format(-1234.5, '.', ',');

        $this->assertSame('-1,234.50', $result);
    }
}
