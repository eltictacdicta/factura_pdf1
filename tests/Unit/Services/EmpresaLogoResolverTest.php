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

use FSFramework\Plugins\factura_pdf1\Services\EmpresaLogoResolver;
use PHPUnit\Framework\TestCase;

final class EmpresaLogoResolverTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/logo_resolver_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/images', 0755, true);

        unset($GLOBALS['config2']['system_logo']);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
        unset($GLOBALS['config2']['system_logo']);
    }

    public function testResolveSystemLogoReturnsNullWhenNoLogoExists(): void
    {
        $resolver = new EmpresaLogoResolver(null, $this->tmpDir);
        $this->assertNull($resolver->resolveSystemLogo());
    }

    public function testResolveSystemLogoFindsConfiguredLogo(): void
    {
        $relative = 'images/custom_logo.png';
        $absPath = $this->tmpDir . '/' . $relative;
        file_put_contents($absPath, 'fake-png');
        $GLOBALS['config2']['system_logo'] = $relative;

        $resolver = new EmpresaLogoResolver(null, $this->tmpDir);
        $this->assertSame($absPath, $resolver->resolveSystemLogo());
    }

    public function testResolveSystemLogoFindsSystemLogoPng(): void
    {
        $absPath = $this->tmpDir . '/images/system_logo.png';
        file_put_contents($absPath, 'fake-png');

        $resolver = new EmpresaLogoResolver(null, $this->tmpDir);
        $this->assertSame($absPath, $resolver->resolveSystemLogo());
    }

    public function testResolveSystemLogoFindsSystemLogoJpg(): void
    {
        $absPath = $this->tmpDir . '/images/system_logo.jpg';
        file_put_contents($absPath, 'fake-jpg');

        $resolver = new EmpresaLogoResolver(null, $this->tmpDir);
        $this->assertSame($absPath, $resolver->resolveSystemLogo());
    }

    public function testResolveSystemLogoFallsBackToLegacyLogo(): void
    {
        $absPath = $this->tmpDir . '/images/logo.png';
        file_put_contents($absPath, 'fake-png');

        $resolver = new EmpresaLogoResolver(null, $this->tmpDir);
        $this->assertSame($absPath, $resolver->resolveSystemLogo());
    }

    public function testResolveSystemLogoPriority(): void
    {
        $configured = $this->tmpDir . '/images/custom.png';
        file_put_contents($configured, 'fake-configured');

        $system = $this->tmpDir . '/images/system_logo.png';
        file_put_contents($system, 'fake-system');

        $legacy = $this->tmpDir . '/images/logo.png';
        file_put_contents($legacy, 'fake-legacy');

        $GLOBALS['config2']['system_logo'] = 'images/custom.png';

        $resolver = new EmpresaLogoResolver(null, $this->tmpDir);
        $this->assertSame($configured, $resolver->resolveSystemLogo(), 'config2 value should have highest priority');
    }

    public function testResolveSystemLogoSkipsConfiguredWhenFileMissing(): void
    {
        $GLOBALS['config2']['system_logo'] = 'images/missing.png';
        $system = $this->tmpDir . '/images/system_logo.jpg';
        file_put_contents($system, 'fake-jpg');

        $resolver = new EmpresaLogoResolver(null, $this->tmpDir);
        $this->assertSame($system, $resolver->resolveSystemLogo());
    }

    public function testResolveAbsolutePathStillWorks(): void
    {
        $absPath = $this->tmpDir . '/images/logo.png';
        file_put_contents($absPath, 'fake-png');

        $resolver = new EmpresaLogoResolver(null, $this->tmpDir);
        $this->assertSame($absPath, $resolver->resolveAbsolutePath());
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = glob($dir . '/*') ?: [];
        foreach ($items as $item) {
            is_dir($item) ? $this->removeDir($item) : unlink($item);
        }
        rmdir($dir);
    }
}
