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

namespace FSFramework\Plugins\factura_pdf1;

if (\class_exists(__NAMESPACE__ . '\\Init', false)) {
    return;
}

use FSFramework\Plugins\factura_pdf1\Services\GestorProgramaRoleDefinition;
use FSFramework\Plugins\factura_pdf1\Services\GestorProgramaRoleService;
use FSFramework\Plugins\factura_pdf1\Services\LegacyRolePermissionsGateway;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;

/**
 * Boot entry point for the factura_pdf1 plugin.
 */
class Init
{
    private static ?SettingsService $settingsServiceForTests = null;

    private static ?GestorProgramaRoleService $gestorRoleServiceForTests = null;

    public function init(): void
    {
        require_once __DIR__ . '/composer_autoload.php';
        $this->runSettingsUpgrade();
        $this->ensureGestorRoleIfMissing();
    }

    /**
     * Activation hook (PluginSchemaSynchronizer). Seeds the gestor role.
     */
    public static function upgrade(): void
    {
        self::runGestorRoleEnsure();
    }

    private function ensureGestorRoleIfMissing(): void
    {
        if (self::$gestorRoleServiceForTests !== null) {
            return;
        }

        try {
            $gateway = new LegacyRolePermissionsGateway();
            if (!$gateway->roleExists(GestorProgramaRoleDefinition::CODROL)) {
                self::runGestorRoleEnsure();
            }
        } catch (\Throwable $e) {
            error_log('[factura_pdf1] GestorProgramaRoleService: ' . $e->getMessage());
        }
    }

    private static function runGestorRoleEnsure(): void
    {
        try {
            $service = self::$gestorRoleServiceForTests ?? new GestorProgramaRoleService(new LegacyRolePermissionsGateway());
            $service->ensure();
        } catch (\Throwable $e) {
            error_log('[factura_pdf1] GestorProgramaRoleService: ' . $e->getMessage());
        }
    }

    /**
     * @internal Test seam.
     */
    public static function resetDependenciesForTests(): void
    {
        self::$settingsServiceForTests = null;
        self::$gestorRoleServiceForTests = null;
    }

    /**
     * @internal Test seam.
     */
    public static function setGestorRoleServiceForTests(?GestorProgramaRoleService $service): void
    {
        self::$gestorRoleServiceForTests = $service;
    }

    /**
     * @internal Test seam.
     */
    public static function setSettingsServiceForTests(?SettingsService $settingsService): void
    {
        self::$settingsServiceForTests = $settingsService;
    }

    private function runSettingsUpgrade(): void
    {
        $service = self::$settingsServiceForTests ?? new SettingsService();
        $service->load();
    }
}
