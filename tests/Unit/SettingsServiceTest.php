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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SettingsService::class)]
final class SettingsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FacturaPdf1Setting::enableTestStorage();
    }

    protected function tearDown(): void
    {
        FacturaPdf1Setting::disableTestStorage();
        parent::tearDown();
    }

    #[Test]
    public function defaultsContainDocumentedKnownSettings(): void
    {
        $service = new SettingsService();
        $defaults = $service->defaults();

        $this->assertSame(0, $defaults['posicionlogo']);
        $this->assertSame('#E9E9E9', $defaults['colorcabecera']);
        $this->assertSame(3, $defaults['pagoyvencimiento']);
        $this->assertSame('left', $defaults['justiftexto1']);
        $this->assertArrayHasKey('documentosrelacionados', $defaults);
    }

    #[Test]
    public function loadFillsMissingKeyFromDefaults(): void
    {
        FacturaPdf1Setting::seedTestRow([
            'settings_json' => json_encode(['colorcabecera' => '#FF0000'], JSON_THROW_ON_ERROR),
            'current_version' => SettingsService::IN_CODE_VERSION,
        ]);

        $loaded = (new SettingsService())->load();

        $this->assertSame('#FF0000', $loaded['colorcabecera']);
        $this->assertSame(0, $loaded['posicionlogo']);
    }

    #[Test]
    public function loadPreservesUnknownKeysForForwardCompatibility(): void
    {
        FacturaPdf1Setting::seedTestRow([
            'settings_json' => json_encode([
                'future_widget' => 'beta-value',
                'colorcabecera' => '#AABBCC',
            ], JSON_THROW_ON_ERROR),
            'current_version' => SettingsService::IN_CODE_VERSION,
        ]);

        $loaded = (new SettingsService())->load();

        $this->assertSame('beta-value', $loaded['future_widget']);
        $this->assertSame('#AABBCC', $loaded['colorcabecera']);
        $this->assertSame(0, $loaded['margenlogo']);
    }

    #[Test]
    public function saveRoundTripsSettingsJsonAndIncrementsVersion(): void
    {
        FacturaPdf1Setting::seedTestRow([
            'settings_json' => json_encode(['colorcabecera' => '#111111'], JSON_THROW_ON_ERROR),
            'current_version' => 1,
        ]);

        $service = new SettingsService();
        $payload = $service->defaults();
        $payload['colorcabecera'] = '#222222';
        $payload['future_widget'] = 'kept';

        $service->save($payload);

        $row = FacturaPdf1Setting::getTestRow();
        $this->assertNotNull($row);
        $this->assertSame(2, $row['current_version']);

        $decoded = json_decode((string) $row['settings_json'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('#222222', $decoded['colorcabecera']);
        $this->assertSame('kept', $decoded['future_widget']);

        $reloaded = $service->load();
        $this->assertSame('#222222', $reloaded['colorcabecera']);
        $this->assertSame('kept', $reloaded['future_widget']);
    }

    #[Test]
    public function resetWritesDefaultsAndBumpsVersion(): void
    {
        FacturaPdf1Setting::seedTestRow([
            'settings_json' => json_encode(['colorcabecera' => '#999999'], JSON_THROW_ON_ERROR),
            'current_version' => 5,
        ]);

        $service = new SettingsService();
        $service->reset();

        $row = FacturaPdf1Setting::getTestRow();
        $this->assertNotNull($row);
        $this->assertSame(SettingsService::IN_CODE_VERSION, $row['current_version']);

        $loaded = $service->load();
        $this->assertSame('#E9E9E9', $loaded['colorcabecera']);
        $this->assertSame(0, $loaded['posicionlogo']);
    }

    #[Test]
    public function applyMigrationsConvertsMostrarpaisToOcultarpais(): void
    {
        FacturaPdf1Setting::seedTestRow([
            'settings_json' => json_encode(['mostrarpais' => true], JSON_THROW_ON_ERROR),
            'current_version' => 1,
        ]);

        $service = new SettingsService();
        $loaded = $service->load();

        $this->assertArrayNotHasKey('mostrarpais', $loaded);
        $this->assertFalse($loaded['ocultarpais']);

        $row = FacturaPdf1Setting::getTestRow();
        $this->assertSame(SettingsService::IN_CODE_VERSION, $row['current_version']);
    }

    #[Test]
    public function currentVersionReturnsStoredVersion(): void
    {
        FacturaPdf1Setting::seedTestRow([
            'settings_json' => '{}',
            'current_version' => 7,
        ]);

        $this->assertSame(7, (new SettingsService())->currentVersion());
    }

    #[Test]
    public function schemaDefinesUniqueConstraintOnDefaultRowName(): void
    {
        $schemaPath = dirname(__DIR__, 2) . '/model/table/factura_pdf1_settings.xml';
        $xml = (string) file_get_contents($schemaPath);

        $this->assertStringContainsString('factura_pdf1_settings_name_key', $xml);
        $this->assertStringContainsString('UNIQUE (name)', $xml);
        $this->assertSame('default', FacturaPdf1Setting::DEFAULT_ROW_NAME);
        $this->assertSame(FacturaPdf1Setting::DEFAULT_ROW_NAME, SettingsService::ROW_NAME);
    }

    #[Test]
    public function saveThrowsAndLeavesRowUnchangedWhenAtomicSaveFails(): void
    {
        FacturaPdf1Setting::seedTestRow([
            'settings_json' => json_encode(['colorcabecera' => '#111111'], JSON_THROW_ON_ERROR),
            'current_version' => 3,
        ]);

        FacturaPdf1Setting::forceSaveAtomicFailure(true);

        try {
            $service = new SettingsService();

            try {
                $service->save(['colorcabecera' => '#333333']);
                $this->fail('Expected RuntimeException when atomic save fails.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('No se pudieron guardar', $exception->getMessage());
            }

            $row = FacturaPdf1Setting::getTestRow();
            $this->assertNotNull($row);
            $this->assertSame(3, $row['current_version']);

            $decoded = json_decode((string) $row['settings_json'], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('#111111', $decoded['colorcabecera']);
        } finally {
            FacturaPdf1Setting::forceSaveAtomicFailure(false);
        }
    }
}
