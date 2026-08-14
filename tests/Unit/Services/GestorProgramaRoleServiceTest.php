<?php
/**
 * This file is part of factura_pdf1
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * @license LGPL-3.0-or-later
 */

declare(strict_types=1);

namespace FacturaPdf1\Tests\Unit\Services;

use FacturaPdf1\Tests\Support\FakeRolePermissionsGateway;
use FSFramework\Plugins\factura_pdf1\Services\GestorProgramaRoleDefinition;
use FSFramework\Plugins\factura_pdf1\Services\GestorProgramaRoleService;
use PHPUnit\Framework\TestCase;

final class GestorProgramaRoleServiceTest extends TestCase
{
    private FakeRolePermissionsGateway $gateway;

    private GestorProgramaRoleService $service;

    protected function setUp(): void
    {
        $this->gateway = new FakeRolePermissionsGateway();
        $this->service = new GestorProgramaRoleService($this->gateway);
    }

    public function testCreatesRoleAndGrantsRegisteredManifestPages(): void
    {
        foreach (['tpvmod', 'ventas_clientes', 'ventas_articulos', 'admin_factura_pdf1'] as $page) {
            $this->gateway->registeredPages[$page] = true;
        }

        $result = $this->service->ensure();

        $this->assertTrue($result['created_role']);
        $this->assertSame(GestorProgramaRoleDefinition::DESCRIPTION, $this->gateway->roles[GestorProgramaRoleDefinition::CODROL]);
        $this->assertTrue($this->gateway->roleHasPageAccess(GestorProgramaRoleDefinition::CODROL, 'tpvmod'));
        $this->assertTrue($this->gateway->accesses[GestorProgramaRoleDefinition::CODROL]['tpvmod']);
        $this->assertFalse($this->gateway->roleHasPageAccess(GestorProgramaRoleDefinition::CODROL, 'tpvmod_settings'));
    }

    public function testSkipsPagesNotRegisteredInFsPages(): void
    {
        $this->gateway->registeredPages['tpvmod'] = true;

        $this->service->ensure();

        $this->assertTrue($this->gateway->roleHasPageAccess(GestorProgramaRoleDefinition::CODROL, 'tpvmod'));
        $this->assertFalse($this->gateway->roleHasPageAccess(GestorProgramaRoleDefinition::CODROL, 'ventas_clientes'));
    }

    public function testExistingRoleOnlyAddsMissingPageGrants(): void
    {
        $codrol = GestorProgramaRoleDefinition::CODROL;
        $this->gateway->roles[$codrol] = GestorProgramaRoleDefinition::DESCRIPTION;
        $this->gateway->accesses[$codrol]['tpvmod'] = true;
        $this->gateway->registeredPages['tpvmod'] = true;
        $this->gateway->registeredPages['ventas_clientes'] = true;

        $result = $this->service->ensure();

        $this->assertFalse($result['created_role']);
        $this->assertSame(1, $result['grants_added']);
        $this->assertTrue($this->gateway->roleHasPageAccess($codrol, 'ventas_clientes'));
    }

    public function testManifestDoesNotIncludeTpvmodSettings(): void
    {
        $this->assertNotContains('tpvmod_settings', GestorProgramaRoleDefinition::pages());
    }
}
