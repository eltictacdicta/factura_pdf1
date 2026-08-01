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

use FSFramework\Plugins\factura_pdf1\Services\FormatoDocumento;
use PHPUnit\Framework\TestCase;

final class FormatoDocumentoTest extends TestCase
{
    public function testDefaultConstructorYieldsEmptyProperties(): void
    {
        $format = new FormatoDocumento();

        $this->assertSame('', $format->idlogo);
        $this->assertSame('', $format->titulo);
        $this->assertSame('', $format->texto);
    }

    public function testConstructorPopulatesAllThreeProperties(): void
    {
        $format = new FormatoDocumento('logo-1', 'My title', 'Body text');

        $this->assertSame('logo-1', $format->idlogo);
        $this->assertSame('My title', $format->titulo);
        $this->assertSame('Body text', $format->texto);
    }

    public function testPropertiesAreAssignableAfterConstruction(): void
    {
        $format = new FormatoDocumento();
        $format->idlogo = 'logo-2';
        $format->titulo = 'Updated title';
        $format->texto = 'Updated body';

        $this->assertSame('logo-2', $format->idlogo);
        $this->assertSame('Updated title', $format->titulo);
        $this->assertSame('Updated body', $format->texto);
    }
}
