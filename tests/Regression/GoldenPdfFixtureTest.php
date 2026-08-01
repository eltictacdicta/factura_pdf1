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

namespace FacturaPdf1\Tests\Regression;

use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Parser;

/**
 * Regression test for the PR-1 byte-equality fixture
 * `tests/Fixtures/legacy_invoice_FACT20260001.pdf`.
 *
 * PR-1 of `factura-pdf1-czpdf-pixel-parity` ships a minimal
 * fixture (a Cezpdf-rendered PDF with the invoice text but
 * without the full upstream port). This test asserts the
 * fixture is a valid, parseable PDF that the regression net
 * can rely on. PR-2 regenerates the fixture with the verbatim
 * port of the upstream `PDFDocument::render()` and adds
 * `testByteEquality()` that compares a freshly rendered
 * `factura_detallada` to the fixture byte-for-byte.
 */
final class GoldenPdfFixtureTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__ . '/../Fixtures/legacy_invoice_FACT20260001.pdf';

    public function testFixtureFileExists(): void
    {
        $this->assertFileExists(self::FIXTURE_PATH);
    }

    public function testFixtureStartsWithPdfMagic(): void
    {
        $bytes = (string) file_get_contents(self::FIXTURE_PATH);

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function testFixtureIsParseableBySmalot(): void
    {
        $parser = new Parser();
        $bytes = (string) file_get_contents(self::FIXTURE_PATH);

        $doc = $parser->parseContent($bytes);

        $this->assertGreaterThanOrEqual(1, count($doc->getPages()));
    }
}
