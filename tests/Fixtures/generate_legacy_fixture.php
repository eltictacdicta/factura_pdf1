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

/**
 * One-off CLI script that produces the byte-equality fixture PDF
 * used by `tests/Regression/GoldenPdfTest`.
 *
 * PR-2 of `factura-pdf1-czpdf-pixel-parity` regenerates the
 * fixture by running the real `CezpdfRenderService` against the
 * `SeedInvoiceFakt20260001` payload. The output is the
 * "ground truth" PDF that the `testByteEquality()` regression
 * test compares against a fresh render.
 *
 * Usage:
 *
 *     ddev exec REGENERATE_FIXTURE=1 php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml --filter GoldenPdfTest
 *
 * Or, to call this script directly:
 *
 *     ddev exec php tests/Fixtures/generate_legacy_fixture.php
 *
 * The script exits with code 0 on success and writes
 * `tests/Fixtures/legacy_invoice_FACT20260001.pdf`.
 */
require_once __DIR__ . '/../../composer_autoload.php';
require_once __DIR__ . '/../../../../tests/bootstrap.php';
require_once __DIR__ . '/../../vendor/cezpdf/Cezpdf.php';

// Cezpdf needs `tmp/<FS_TMP_NAME>pdf/` for the font cache.
$tmpName = defined('FS_TMP_NAME') ? FS_TMP_NAME : 'test_';
$tmpPath = 'tmp/' . $tmpName . 'pdf';
if (!is_dir($tmpPath)) {
    @mkdir($tmpPath, 0777, true);
}

require_once __DIR__ . '/../Fixtures/StubView.php';
require_once __DIR__ . '/../Fixtures/SeedInvoiceFakt20260001.php';
require_once __DIR__ . '/../Fixtures/DocumentPrintViewFixture.php';

$adapter = \FacturaPdf1\Tests\Fixtures\SeedInvoiceFakt20260001::buildAdapter();
$service = new \FSFramework\Plugins\factura_pdf1\Services\CezpdfRenderService();
$settings = (new \FSFramework\Plugins\factura_pdf1\Services\SettingsService())->defaults();

$bytes = $service->render($adapter, $settings);

$outputPath = __DIR__ . '/legacy_invoice_FACT20260001.pdf';
if (file_put_contents($outputPath, $bytes) === false) {
    fwrite(STDERR, "Failed to write fixture to {$outputPath}\n");
    exit(1);
}

fwrite(STDOUT, "Wrote " . strlen($bytes) . " bytes to {$outputPath}\n");
exit(0);
