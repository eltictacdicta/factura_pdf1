<?php
/**
 * This file is part of factura_pdf1
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * @license LGPL-3.0-or-later
 */

declare(strict_types=1);

namespace FacturaPdf1\Tests\Unit\Services;

use FSFramework\Plugins\factura_pdf1\Services\LegacyRolePermissionsGateway;
use PHPUnit\Framework\TestCase;

final class LegacyRolePermissionsGatewayTest extends TestCase
{
    private LegacyRolePermissionsGateway $gateway;

    private const TEST_CODROL = 'gestor_test';

    protected function setUp(): void
    {
        if (!defined('FS_FOLDER') || !is_file(FS_FOLDER . '/config.php')) {
            $this->markTestSkipped('Requiere entorno DDEV con config.php');
        }

        require_once FS_FOLDER . '/base/fs_model.php';
        require_once FS_FOLDER . '/model/fs_rol.php';
        require_once FS_FOLDER . '/model/fs_rol_access.php';

        $this->gateway = new LegacyRolePermissionsGateway();
        $this->cleanupTestRole();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestRole();
    }

    public function testRoleExistsUsesBooleanSemanticsForMissingRole(): void
    {
        $this->assertFalse($this->gateway->roleExists(self::TEST_CODROL));
    }

    public function testCreateRoleIsPersisted(): void
    {
        $this->assertTrue($this->gateway->createRole(self::TEST_CODROL, 'Rol de prueba gateway'));
        $this->assertTrue($this->gateway->roleExists(self::TEST_CODROL));
    }

    public function testRoleHasPageAccessUsesBooleanSemanticsWhenMissing(): void
    {
        $this->assertTrue($this->gateway->createRole(self::TEST_CODROL, 'Rol de prueba gateway'));
        $this->assertFalse($this->gateway->roleHasPageAccess(self::TEST_CODROL, 'pagina_inexistente_xyz'));
    }

    private function cleanupTestRole(): void
    {
        if (!class_exists('fs_rol', false)) {
            return;
        }

        $rol = new \fs_rol();
        $found = $rol->get(self::TEST_CODROL);
        if ($found instanceof \fs_rol) {
            $found->delete();
        }
    }
}
