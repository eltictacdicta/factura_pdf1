<?php
/**
 * This file is part of factura_pdf1
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * @license LGPL-3.0-or-later
 */

declare(strict_types=1);

namespace FacturaPdf1\Tests\Unit;

use FacturaPdf1\Tests\Support\FakeRolePermissionsGateway;
use FSFramework\Plugins\factura_pdf1\Init;
use FSFramework\Plugins\factura_pdf1\Model\FacturaPdf1Setting;
use FSFramework\Plugins\factura_pdf1\Services\GestorProgramaRoleDefinition;
use FSFramework\Plugins\factura_pdf1\Services\GestorProgramaRoleService;
use FSFramework\Plugins\factura_pdf1\Services\SettingsService;
use PHPUnit\Framework\TestCase;

final class InitGestorRoleUpgradeTest extends TestCase
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

    public function testUpgradeSeedsGestorProgramaRole(): void
    {
        $gateway = new FakeRolePermissionsGateway();
        $gateway->registeredPages['tpvmod'] = true;
        $gateway->registeredPages['ventas_clientes'] = true;

        Init::setGestorRoleServiceForTests(new GestorProgramaRoleService($gateway));
        Init::upgrade();

        $this->assertTrue($gateway->roleExists(GestorProgramaRoleDefinition::CODROL));
        $this->assertTrue($gateway->roleHasPageAccess(GestorProgramaRoleDefinition::CODROL, 'tpvmod'));
        $this->assertTrue($gateway->roleHasPageAccess(GestorProgramaRoleDefinition::CODROL, 'ventas_clientes'));
    }

    public function testUpgradeDoesNotBreakWhenGatewayThrows(): void
    {
        $gateway = new class extends FakeRolePermissionsGateway {
            public function createRole(string $codrol, string $description): bool
            {
                throw new \RuntimeException('db down');
            }
        };

        Init::setGestorRoleServiceForTests(new GestorProgramaRoleService($gateway));

        Init::upgrade();

        $this->assertFalse($gateway->roleExists(GestorProgramaRoleDefinition::CODROL));
    }
}
