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

namespace FacturaPdf1\Tests\Integration;

use FSFramework\Plugins\factura_pdf1\Controller\Admin\FacturaPdf1SettingsController;
use FSFramework\Plugins\factura_pdf1\Model\FacturaPdf1Setting;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use FSFramework\Security\CsrfManager;
use FSFramework\Translation\FSTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        FacturaPdf1SettingsController::resetDependenciesForTests();
        FacturaPdf1Setting::enableTestStorage();
        FSTranslator::reset();
        FSTranslator::initialize(FS_FOLDER);
        FSTranslator::loadPluginTranslations('factura_pdf1', FS_FOLDER . '/plugins/factura_pdf1');
    }

    protected function tearDown(): void
    {
        FacturaPdf1SettingsController::resetDependenciesForTests();
        FacturaPdf1Setting::disableTestStorage();
    }

    public function testAdminSaveRoundTripPersistsToDatabaseRow(): void
    {
        FacturaPdf1SettingsController::setDependenciesForTests(new SettingsService(), true);
        $controller = $this->createController();

        $payload = (new SettingsService())->defaults();
        $payload['colorcabecera'] = '#445566';
        $payload['_token'] = CsrfManager::generateToken();

        $controller->processAdmin(Request::create('/index.php?page=admin_factura_pdf1', 'POST', $payload));

        $this->assertSame(Response::HTTP_FOUND, $controller->response_status_code);
        $row = FacturaPdf1Setting::getTestRow();
        $this->assertNotNull($row);
        $decoded = json_decode((string) $row['settings_json'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('#445566', $decoded['colorcabecera']);
    }

    public function testAdminPostWithoutCsrfDoesNotPersist(): void
    {
        FacturaPdf1SettingsController::setDependenciesForTests(new SettingsService(), true);
        $controller = $this->createController();

        $payload = (new SettingsService())->defaults();
        $payload['colorcabecera'] = '#445566';

        $controller->processAdmin(Request::create('/index.php?page=admin_factura_pdf1', 'POST', $payload));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $controller->response_status_code);
        $this->assertNull(FacturaPdf1Setting::getTestRow());
    }

    public function testLegacyShimFileExists(): void
    {
        $path = FS_FOLDER . '/plugins/factura_pdf1/controller/admin_factura_pdf1.php';
        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('admin_factura_pdf1 extends', $contents);
    }

    private function createController(): FacturaPdf1SettingsController
    {
        $reflection = new \ReflectionClass(FacturaPdf1SettingsController::class);

        /** @var FacturaPdf1SettingsController $controller */
        return $reflection->newInstanceWithoutConstructor();
    }
}
