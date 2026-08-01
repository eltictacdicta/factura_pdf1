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

namespace FSFramework\Plugins\factura_pdf1\Controller\Admin;

require_once dirname(__DIR__, 4) . '/base/fs_controller.php';
require_once dirname(__DIR__, 4) . '/base/fs_db2.php';
require_once dirname(__DIR__, 4) . '/base/fs_core_log.php';
require_once dirname(__DIR__, 4) . '/base/fs_session_manager.php';

use FSFramework\Plugins\factura_pdf1\Services\SettingsFormBinder;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use FSFramework\Plugins\factura_pdf1\Services\SettingsValidator;
use FSFramework\Security\CsrfManager;
use FSFramework\Translation\FSTranslator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FacturaPdf1SettingsController extends \fs_controller
{
    public const PAGE_NAME = 'admin_factura_pdf1';

    /** @var array<string, mixed> */
    public array $settings = [];

    public ?string $validation_error = null;

    public ?string $flash_message = null;

    public ?string $flash_error = null;

    public ?string $last_redirect_url = null;

    public int $response_status_code = Response::HTTP_OK;

    private static ?SettingsService $settingsServiceForTests = null;

    private static bool $suppressRedirectForTests = false;

    public function __construct()
    {
        parent::__construct(self::PAGE_NAME, 'Factura PDF1', 'admin', false, false);
        $this->template = 'admin/factura_pdf1/settings';
    }

    public static function resetDependenciesForTests(): void
    {
        self::$settingsServiceForTests = null;
        self::$suppressRedirectForTests = false;
    }

    public static function setDependenciesForTests(
        ?SettingsService $settingsService = null,
        bool $suppressRedirect = false,
    ): void {
        self::$settingsServiceForTests = $settingsService;
        self::$suppressRedirectForTests = $suppressRedirect;
    }

    public function processAdmin(?Request $request = null): void
    {
        $request ??= $this->request;
        $service = $this->resolveSettingsService();
        $this->settings = $service->load();

        if (!$request->isMethod('POST')) {
            return;
        }

        if (!$this->assertPostCsrfValid($request)) {
            return;
        }

        if ($request->request->has('reset_defaults')) {
            $this->handleReset($service);

            return;
        }

        $this->handleSave($request, $service);
    }

    protected function private_core(): void
    {
        $this->processAdmin();
    }

    private function assertPostCsrfValid(Request $request): bool
    {
        $token = (string) $request->request->get('_token', '');
        $this->csrf_valid = $token !== '' && CsrfManager::isValid($token);
        if (!$this->isCsrfValid()) {
            $this->recordError(FSTranslator::trans('invalid-csrf-token'));
            $this->response_status_code = Response::HTTP_BAD_REQUEST;

            return false;
        }

        return true;
    }

    private function handleReset(SettingsService $service): void
    {
        $service->reset();
        $this->settings = $service->load();
        $this->recordMessage(FSTranslator::trans('factura-pdf1.admin.reset-done'));
        $this->redirectToSelf();
    }

    private function handleSave(Request $request, SettingsService $service): void
    {
        $bound = SettingsFormBinder::bindFromRequest($request, $service->defaults());
        $invalidColors = SettingsValidator::invalidColorKeys($bound);
        if ($invalidColors !== []) {
            $this->validation_error = FSTranslator::trans('factura-pdf1.admin.invalid-color');

            return;
        }

        $service->save($bound);
        $this->settings = $service->load();
        $this->recordMessage(FSTranslator::trans('factura-pdf1.admin.saved'));
        $this->redirectToSelf();
    }

    private function recordMessage(string $message): void
    {
        if (self::$suppressRedirectForTests) {
            $this->flash_message = $message;

            return;
        }

        $this->new_message($message);
    }

    private function recordError(string $message): void
    {
        if (self::$suppressRedirectForTests) {
            $this->flash_error = $message;

            return;
        }

        $this->new_error_msg($message);
    }

    private function redirectToSelf(): void
    {
        if (self::$suppressRedirectForTests) {
            $this->last_redirect_url = 'index.php?page=' . self::PAGE_NAME;
            $this->response_status_code = Response::HTTP_FOUND;

            return;
        }

        $this->last_redirect_url = $this->url();
        header('Location: ' . $this->last_redirect_url);
        exit;
    }

    private function resolveSettingsService(): SettingsService
    {
        return self::$settingsServiceForTests ?? new SettingsService();
    }
}
