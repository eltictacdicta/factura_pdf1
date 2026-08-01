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

use FSFramework\Plugins\factura_pdf1\Init;
use FSFramework\Plugins\factura_pdf1\Model\FacturaPdf1Setting;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use PHPUnit\Framework\TestCase;

final class InitUpgradeTest extends TestCase
{
    protected function setUp(): void
    {
        Init::resetDependenciesForTests();
        FacturaPdf1Setting::enableTestStorage();
    }

    protected function tearDown(): void
    {
        Init::resetDependenciesForTests();
        FacturaPdf1Setting::disableTestStorage();
    }

    public function testInitLoadMigratesMostrarpaisToOcultarpais(): void
    {
        FacturaPdf1Setting::seedTestRow([
            'settings_json' => json_encode(['mostrarpais' => false], JSON_THROW_ON_ERROR),
            'current_version' => 1,
        ]);

        $service = new SettingsService();
        Init::setSettingsServiceForTests($service);
        (new Init())->init();

        $loaded = $service->load();
        $this->assertArrayNotHasKey('mostrarpais', $loaded);
        $this->assertTrue($loaded['ocultarpais']);

        $row = FacturaPdf1Setting::getTestRow();
        $this->assertSame(SettingsService::IN_CODE_VERSION, $row['current_version']);
    }

    public function testInitLoadMigratesOcultarreferenciasfactToDocumentosrelacionados(): void
    {
        FacturaPdf1Setting::seedTestRow([
            'settings_json' => json_encode(['ocultarreferenciasfact' => true], JSON_THROW_ON_ERROR),
            'current_version' => 1,
        ]);

        $service = new SettingsService();
        Init::setSettingsServiceForTests($service);
        (new Init())->init();

        $loaded = $service->load();
        $this->assertArrayNotHasKey('ocultarreferenciasfact', $loaded);
        $this->assertSame(0, $loaded['documentosrelacionados']);
    }

    public function testInitLoadIsNoOpWhenAlreadyAtCurrentVersion(): void
    {
        FacturaPdf1Setting::seedTestRow([
            'settings_json' => json_encode(['colorcabecera' => '#AABBCC'], JSON_THROW_ON_ERROR),
            'current_version' => SettingsService::IN_CODE_VERSION,
        ]);

        $service = new SettingsService();
        Init::setSettingsServiceForTests($service);
        (new Init())->init();

        $row = FacturaPdf1Setting::getTestRow();
        $this->assertSame(SettingsService::IN_CODE_VERSION, $row['current_version']);

        $decoded = json_decode((string) $row['settings_json'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('#AABBCC', $decoded['colorcabecera']);
        $this->assertArrayNotHasKey('mostrarpais', $decoded);
    }
}
