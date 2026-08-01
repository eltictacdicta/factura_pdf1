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

final class ComposerLockTest extends TestCase
{
    /**
     * PR-1 of `factura-pdf1-czpdf-pixel-parity` drops the mpdf
     * Composer dependency and uses the `rospdf/pdf-php` package
     * (Cezpdf 0.12+) as the PDF backend. mpdf is NOT a dependency.
     */
    public function testComposerLockUsesCezpdfAsPdfBackend(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $composer = json_decode((string) file_get_contents($pluginRoot . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('php', $composer['require']);
        $this->assertArrayHasKey('rospdf/pdf-php', $composer['require']);
        $this->assertArrayNotHasKey('mpdf/mpdf', $composer['require']);

        $this->assertFileExists($pluginRoot . '/vendor/rospdf/pdf-php/src/Cezpdf.php');
        $this->assertFileExists($pluginRoot . '/vendor/rospdf/pdf-php/src/Cpdf.php');
    }
}
