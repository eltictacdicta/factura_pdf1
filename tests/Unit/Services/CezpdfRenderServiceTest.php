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

use FacturaPdf1\Tests\Fixtures\SeedInvoiceFakt20260001;
use FSFramework\Plugins\factura_pdf1\Services\CezpdfRenderService;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use PHPUnit\Framework\TestCase;
use FSFramework\Plugins\factura_pdf1\Model\View\FacturaPrintView;

/**
 * The PR-2 of `factura-pdf1-czpdf-pixel-parity` wires the
 * `CezpdfRenderService` to the new Cezpdf path
 * (replaces the PR-1 stub). The tests assert:
 *  1. The class is autoloadable and has the public API.
 *  2. The render() method returns a Cezpdf binary (starts
 *     with `%PDF-`, length > 1 KB).
 *  3. The render() method accepts a `PrintableDocumentInterface`
 *     and the default settings array (the same API the
 *     previous `PdfRenderService` had).
 */
final class CezpdfRenderServiceTest extends TestCase
{
    protected function setUp(): void
    {
        FacturaPrintView::resetResolversForTests();
    }

    protected function tearDown(): void
    {
        FacturaPrintView::resetResolversForTests();
    }

    public function testServiceClassExists(): void
    {
        $this->assertTrue(class_exists(CezpdfRenderService::class));
    }

    public function testServiceHasPublicRenderMethod(): void
    {
        $this->assertTrue(method_exists(CezpdfRenderService::class, 'render'));
        $reflection = new \ReflectionMethod(CezpdfRenderService::class, 'render');
        $this->assertTrue($reflection->isPublic());
    }

    public function testRenderReturnsCezpdfBinaryForSeededInvoice(): void
    {
        SeedInvoiceFakt20260001::configureResolvers();
        $adapter = SeedInvoiceFakt20260001::buildAdapter();
        $service = new CezpdfRenderService();
        $settings = (new SettingsService())->defaults();

        $pdf = $service->render($adapter, $settings);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThanOrEqual(1024, strlen($pdf));
    }
}
