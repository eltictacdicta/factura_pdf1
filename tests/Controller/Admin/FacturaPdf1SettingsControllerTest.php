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

namespace FacturaPdf1\Tests\Controller\Admin;

use FSFramework\Plugins\factura_pdf1\Controller\Admin\FacturaPdf1SettingsController;
use FSFramework\Plugins\factura_pdf1\Model\FacturaPdf1Setting;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use FSFramework\Security\CsrfManager;
use FSFramework\Translation\FSTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FacturaPdf1SettingsControllerTest extends TestCase
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

    public function testGetRendersCurrentSettings(): void
    {
        FacturaPdf1Setting::seedTestRow([
            'settings_json' => json_encode(['colorcabecera' => '#AABBCC'], JSON_THROW_ON_ERROR),
            'current_version' => SettingsService::IN_CODE_VERSION,
        ]);

        $controller = $this->createController();
        $controller->processAdmin(Request::create('/index.php', 'GET'));

        $this->assertSame('#AABBCC', $controller->settings['colorcabecera']);
        $this->assertSame('admin/factura_pdf1/settings', $controller->template);
    }

    public function testPostWithoutCsrfTokenRejectsSave(): void
    {
        $service = new SettingsService();
        FacturaPdf1SettingsController::setDependenciesForTests($service, true);

        $controller = $this->createController();
        $payload = $this->validPayload();
        $controller->processAdmin(Request::create('/index.php', 'POST', $payload));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $controller->response_status_code);
        $this->assertNull(FacturaPdf1Setting::getTestRow());
        $this->assertNotNull($controller->flash_error);
    }

    public function testPostWithValidCsrfPersistsAndRedirects(): void
    {
        FacturaPdf1SettingsController::setDependenciesForTests(new SettingsService(), true);

        $controller = $this->createController();
        $payload = $this->validPayload();
        $payload['colorcabecera'] = '#112233';
        $payload['_token'] = CsrfManager::generateToken();

        $controller->processAdmin(Request::create('/index.php', 'POST', $payload));

        $row = FacturaPdf1Setting::getTestRow();
        $this->assertNotNull($row);
        $decoded = json_decode((string) $row['settings_json'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('#112233', $decoded['colorcabecera']);
        $this->assertSame('index.php?page=admin_factura_pdf1', $controller->last_redirect_url);
        $this->assertSame(Response::HTTP_FOUND, $controller->response_status_code);
        $this->assertNotNull($controller->flash_message);
    }

    public function testPostWithMalformedColorDoesNotPersist(): void
    {
        FacturaPdf1Setting::seedTestRow([
            'settings_json' => json_encode(['colorcabecera' => '#E9E9E9'], JSON_THROW_ON_ERROR),
            'current_version' => SettingsService::IN_CODE_VERSION,
        ]);

        FacturaPdf1SettingsController::setDependenciesForTests(new SettingsService(), true);

        $controller = $this->createController();
        $payload = $this->validPayload();
        $payload['colorcabecera'] = '#GGG';
        $payload['_token'] = CsrfManager::generateToken();

        $controller->processAdmin(Request::create('/index.php', 'POST', $payload));

        $row = FacturaPdf1Setting::getTestRow();
        $this->assertNotNull($row);
        $decoded = json_decode((string) $row['settings_json'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('#E9E9E9', $decoded['colorcabecera']);
        $this->assertNotNull($controller->validation_error);
        $this->assertNull($controller->last_redirect_url);
    }

    public function testResetRestoresDefaultsAndRedirects(): void
    {
        FacturaPdf1Setting::seedTestRow([
            'settings_json' => json_encode(['colorcabecera' => '#999999'], JSON_THROW_ON_ERROR),
            'current_version' => 5,
        ]);

        FacturaPdf1SettingsController::setDependenciesForTests(new SettingsService(), true);

        $controller = $this->createController();
        $controller->processAdmin(Request::create('/index.php', 'POST', [
            'reset_defaults' => '1',
            '_token' => CsrfManager::generateToken(),
        ]));

        $loaded = (new SettingsService())->load();
        $this->assertSame('#555555', $loaded['colorcabecera']);
        $this->assertSame('index.php?page=admin_factura_pdf1', $controller->last_redirect_url);
        $this->assertSame(Response::HTTP_FOUND, $controller->response_status_code);
    }

    public function testAdminTemplateExistsWithCsrfField(): void
    {
        $path = FS_FOLDER . '/plugins/factura_pdf1/themes/AdminLTE/view/admin/factura_pdf1/settings.html.twig';
        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('csrf_field()', $contents);
        $this->assertStringNotContainsString('|raw', $contents);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return (new SettingsService())->defaults();
    }

    private function createController(): FacturaPdf1SettingsController
    {
        $reflection = new \ReflectionClass(FacturaPdf1SettingsController::class);

        /** @var FacturaPdf1SettingsController $controller */
        $controller = $reflection->newInstanceWithoutConstructor();
        $controller->template = 'admin/factura_pdf1/settings';

        return $controller;
    }
}
