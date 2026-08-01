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

use FacturaPdf1\Tests\Fixtures\SeedInvoiceFakt20260001;
use FSFramework\Plugins\factura_pdf1\Model\View\FacturaPrintView;
use FSFramework\Plugins\factura_pdf1\Services\CezpdfRenderService;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The previous (Twig-era) test asserted `data-text-block-{N}-position={P}`
 * HTML tokens and `text-block-{N}-pos-{P}` CSS classes in the rendered
 * HTML. PR-2 of `factura-pdf1-czpdf-pixel-parity` removes the Twig
 * template (the `data-*` token convention is gone), so those assertions
 * no longer apply. PR-3 will rewrite this test to assert the text
 * position via Cezpdf PDF signals (extracted text + coordinate
 * inspection). For PR-2 the test is reduced to a smoke test that
 * proves the renderer accepts the position settings without crashing
 * (the assertion is on the output being a non-empty PDF binary).
 */
final class TextBlockPositionTest extends TestCase
{
    protected function setUp(): void
    {
        SeedInvoiceFakt20260001::configureResolvers();
    }

    protected function tearDown(): void
    {
        FacturaPrintView::resetResolversForTests();
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function textBlockPositionProvider(): array
    {
        $cases = [];
        for ($block = 1; $block <= 2; $block++) {
            for ($position = 1; $position <= 7; $position++) {
                $cases['text' . $block . '_pos' . $position] = [$block, $position];
            }
        }

        return $cases;
    }

    #[DataProvider('textBlockPositionProvider')]
    public function testTextBlockPositionAcceptsAllPositions(int $block, int $position): void
    {
        $adapter = SeedInvoiceFakt20260001::buildAdapter();
        $service = new CezpdfRenderService();

        $settings = (new SettingsService())->defaults();
        $settings['posiciontexto' . $block] = $position;
        if ($block === 2) {
            $settings['texto2'] = 'Custom text block content';
        }

        $pdf = $service->render($adapter, $settings);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThanOrEqual(1024, strlen($pdf));
    }
}
