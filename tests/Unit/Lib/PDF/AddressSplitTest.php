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

namespace FacturaPdf1\Tests\Unit\Lib\PDF;

use Cezpdf;
use FSFramework\Plugins\factura_pdf1\Lib\PDF\PortedPdfDocument;
use FSFramework\Plugins\factura_pdf1\Services\FormatoDocumento;
use FSFramework\Plugins\factura_pdf1\Services\LocaleSettings;
use FSFramework\Plugins\factura_pdf1\Services\PdfNumberFormatter;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use FSFramework\Translation\FSTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Per PR-2 of `factura-pdf1-czpdf-pixel-parity` (spec H.4
 * requirement "Address splitting at parens"): the renderer
 * splits an address at the first `(` when its rendered width
 * would exceed `PARTIR_DIR` (170) and the parens are past
 * column 15. This test asserts the three scenarios from the
 * spec.
 *
 * Implementation note: the `combineAddress` method is
 * `protected` in `PortedPdfDocument`; we expose it via a
 * tiny test-only subclass that calls into the parent.
 */
final class AddressSplitTest extends TestCase
{
    public function testAddressWithoutParensIsNotSplit(): void
    {
        $exposed = $this->buildExposedDocument();

        $result = $exposed->exposeCombineAddress([
            'direccion' => 'Calle Mayor 1',
            'codpostal' => '28001',
            'ciudad' => 'Madrid',
            'provincia' => 'Madrid',
            'codpais' => 'ESP',
        ]);

        $this->assertStringContainsString('Calle Mayor 1', $result);
        $this->assertStringNotContainsString("\n(", $result);
    }

    public function testAddressWithParensWithinWidthIsNotSplit(): void
    {
        $exposed = $this->buildExposedDocument();

        $result = $exposed->exposeCombineAddress([
            'direccion' => 'Calle Larga 12',
            'codpostal' => '28001',
            'ciudad' => 'Madrid',
            'provincia' => 'Madrid',
            'codpais' => 'ESP',
        ]);

        $this->assertStringContainsString('Calle Larga 12', $result);
        // No parens in the address → no split introduced.
        $this->assertStringNotContainsString("\n(", $result);
    }

    public function testLongAddressWithParensIsSplitAtParens(): void
    {
        $exposed = $this->buildExposedDocument();

        $result = $exposed->exposeCombineAddress([
            'direccion' => 'Calle Larga 123, Piso 4 (Edificio Norte, Escalera B, Puerta 12)',
            'codpostal' => '28001',
            'ciudad' => 'Madrid',
            'provincia' => 'Madrid',
            'codpais' => 'ESP',
        ]);

        // The parens content lands on its own line.
        $this->assertStringContainsString('Calle Larga 123, Piso 4', $result);
        $this->assertMatchesRegularExpression('/\(Edificio Norte/', $result);
        // The opening paren and the following text are on a new line.
        $this->assertMatchesRegularExpression('/\n\(Edificio Norte/', $result);
    }

    private function buildExposedDocument(): ExposeCombineAddressDocument
    {
        $tmpName = defined('FS_TMP_NAME') ? FS_TMP_NAME : '';
        $tmpPath = 'tmp/' . $tmpName . 'pdf';
        if (!is_dir($tmpPath)) {
            @mkdir($tmpPath, 0777, true);
        }

        $pdf = new Cezpdf('a4', 'portrait');
        $pdf->tempPath = $tmpPath;
        $pdf->selectFont(__DIR__ . '/../../../vendor/cezpdf/fonts/Helvetica');

        // A no-op view: the address-split logic reads from a
        // stdClass shape that the protected `combineAddress`
        // inspects via `property_exists`. The actual
        // `PrintableDocumentInterface` instance is irrelevant
        // for this test.
        $view = new \FacturaPdf1\Tests\Fixtures\StubView();

        return new ExposeCombineAddressDocument(
            $pdf,
            $view,
            new SettingsService(),
            new FSTranslator(),
            new FormatoDocumento(),
            new PdfNumberFormatter(),
            new LocaleSettings(),
        );
    }
}

/**
 * Test-only subclass of `PortedPdfDocument` that exposes
 * the `combineAddress` method so the test can assert its
 * return value.
 */
final class ExposeCombineAddressDocument extends PortedPdfDocument
{
    public function exposeCombineAddress(object|array $model): string
    {
        $obj = is_array($model) ? (object) $model : $model;

        return $this->combineAddress($obj);
    }
}
