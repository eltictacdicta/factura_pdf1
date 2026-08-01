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

use FSFramework\Plugins\factura_pdf1\Model\FacturaPdf1Setting;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use PHPUnit\Framework\TestCase;

final class SettingsCoverageTest extends TestCase
{
    private string $templatePath;

    protected function setUp(): void
    {
        $this->templatePath = FS_FOLDER . '/plugins/factura_pdf1/themes/AdminLTE/view/admin/factura_pdf1/settings.html.twig';
    }

    public function testKnownSettingsMatchUpstreamKeys(): void
    {
        $service = new SettingsService();
        $known = $service->knownSettingKeys();

        $this->assertSame(SettingsService::UPSTREAM_SETTING_KEYS, $known);
        $this->assertCount(29, $known);
    }

    public function testAdminTemplateRendersWidgetForEveryKnownSetting(): void
    {
        $this->assertFileExists($this->templatePath);
        $contents = (string) file_get_contents($this->templatePath);
        $missing = [];

        foreach (SettingsService::UPSTREAM_SETTING_KEYS as $key) {
            if (!preg_match('/name="' . preg_quote($key, '/') . '"/', $contents)) {
                $missing[] = $key;
            }
        }

        $this->assertSame([], $missing, 'Missing widgets for keys: ' . implode(', ', $missing));
    }

    public function testAdminTemplateContainsRequiredGroupHeadings(): void
    {
        $contents = (string) file_get_contents($this->templatePath);

        foreach (['print-options', 'logo', 'layout', 'lines', 'totals', 'footer'] as $group) {
            $this->assertStringContainsString("factura-pdf1.group.{$group}", $contents, "Missing group heading: {$group}");
        }
    }

    public function testUpstreamXmlFieldnamesMatchKnownSettings(): void
    {
        $upstreamPath = FS_FOLDER . '/plugins/FacturaPDF1/XMLView/SettingsInvoice.xml';
        if (!is_file($upstreamPath)) {
            $this->markTestSkipped('Upstream FacturaPDF1 plugin not present in workspace.');

            return;
        }

        $xml = (string) file_get_contents($upstreamPath);
        preg_match_all('/fieldname="([^"]+)"/', $xml, $matches);
        $fieldnames = array_values(array_filter(
            $matches[1],
            static fn (string $name): bool => $name !== 'name',
        ));

        $fsframeworkOnly = ['disposicion_cabecera'];
        $knownMinusFsOnly = array_values(array_diff(
            SettingsService::UPSTREAM_SETTING_KEYS,
            $fsframeworkOnly,
        ));
        $this->assertSame($knownMinusFsOnly, $fieldnames);
    }
}
