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

/**
 * Grep test that enforces the Cezpdf port's surface in
 * PR-1 of `factura-pdf1-czpdf-pixel-parity`.
 *
 * The previous cycle's variant asserted "no Cezpdf references
 * in production code" because the engine was mpdf. PR-1
 * introduces Cezpdf as the new engine, so the assertion
 * evolves: the only places Cezpdf may be mentioned in
 * production code are the ported code paths (`Lib/PDF/` and
 * `Services/CezpdfRenderService.php`); all other code paths
 * (controllers, services other than the new Cezpdf service,
 * models, themes, Init) must remain Cezpdf-free. The upstream
 * `FacturaScripts\Core\Lib\PDF` import is still banned in all
 * locations because the port must stay standalone.
 */
final class CezpdfUsageGrepTest extends TestCase
{
    /**
     * Directories where Cezpdf references are EXPECTED (the
     * ported code paths). Any reference inside these
     * directories is allowed. PR-1 of the change introduces
     * Cezpdf-backed classes throughout `Lib/PDF/` plus the
     * service classes that the port consumes
     * (`CezpdfRenderService`, `FormatoDocumento`,
     * `PdfNumberFormatter`, `LocaleSettings`); the fixture
     * generation script also calls Cezpdf directly. PR-2
     * adds the `Controller/FacturaPdf1Controller.php` to
     * the allow-list because the controller now imports the
     * Cezpdf service for the engine-swap.
     *
     * @return list<string>
     */
    private function allowedDirectories(): array
    {
        $pluginRoot = dirname(__DIR__, 2);

        return [
            $pluginRoot . '/Lib/PDF',
            $pluginRoot . '/Services',
            $pluginRoot . '/Controller/FacturaPdf1Controller.php',
            $pluginRoot . '/tests/Fixtures/generate_legacy_fixture.php',
        ];
    }

    /**
     * @return list<string>
     */
    private function scannedPaths(): array
    {
        $pluginRoot = dirname(__DIR__, 2);

        return [
            $pluginRoot . '/Controller',
            $pluginRoot . '/Services',
            $pluginRoot . '/Model',
            $pluginRoot . '/controller',
            $pluginRoot . '/view',
            $pluginRoot . '/themes',
            $pluginRoot . '/Init.php',
        ];
    }

    public function testCezpdfReferencesOnlyInPortedCodePaths(): void
    {
        $allowed = $this->allowedDirectories();

        $violations = [];
        foreach ($this->scannedPaths() as $path) {
            if (!file_exists($path)) {
                continue;
            }

            $command = sprintf(
                "grep -rEn 'Cezpdf' %s 2>/dev/null || true",
                escapeshellarg($path),
            );
            $output = trim((string) shell_exec($command));
            if ($output === '') {
                continue;
            }

            foreach (explode("\n", $output) as $line) {
                if ($line === '') {
                    continue;
                }

                $matches = [];
                if (preg_match('/^([^:]+):/', $line, $matches) !== 1) {
                    continue;
                }

                $file = $matches[1];
                $allowedHere = false;
                foreach ($allowed as $allowedPath) {
                    if (str_starts_with($file, $allowedPath)) {
                        $allowedHere = true;
                        break;
                    }
                }

                if (!$allowedHere) {
                    $violations[] = $line;
                }
            }
        }

        $this->assertSame([], $violations, 'Cezpdf references found outside the ported code paths: ' . implode("\n", $violations));
    }

    public function testNoFacturaScriptsCoreLibPdfImports(): void
    {
        $violations = [];
        foreach ($this->scannedPaths() as $path) {
            if (!file_exists($path)) {
                continue;
            }

            $command = sprintf(
                "grep -rEn 'FacturaScripts\\\\\\\\Core\\\\\\\\Lib\\\\\\\\PDF' %s 2>/dev/null || true",
                escapeshellarg($path),
            );
            $output = trim((string) shell_exec($command));
            if ($output !== '') {
                $violations[] = $output;
            }
        }

        $this->assertSame([], $violations, 'Upstream FacturaScripts Core\\Lib\\PDF references found: ' . implode("\n", $violations));
    }
}
