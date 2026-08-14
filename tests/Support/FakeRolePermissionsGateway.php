<?php
/**
 * This file is part of factura_pdf1
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * @license LGPL-3.0-or-later
 */

declare(strict_types=1);

namespace FacturaPdf1\Tests\Support;

use FSFramework\Plugins\factura_pdf1\Services\RolePermissionsGateway;

/**
 * @internal
 */
class FakeRolePermissionsGateway implements RolePermissionsGateway
{
    /** @var array<string, string> */
    public array $roles = [];

    /** @var array<string, true> */
    public array $registeredPages = [];

    /**
     * @var array<string, array<string, bool>> codrol => page => allow_delete
     */
    public array $accesses = [];

    public function roleExists(string $codrol): bool
    {
        return isset($this->roles[$codrol]);
    }

    public function createRole(string $codrol, string $description): bool
    {
        $this->roles[$codrol] = $description;

        return true;
    }

    public function pageIsRegistered(string $pageName): bool
    {
        return isset($this->registeredPages[$pageName]);
    }

    public function roleHasPageAccess(string $codrol, string $pageName): bool
    {
        return isset($this->accesses[$codrol][$pageName]);
    }

    public function grantPageAccess(string $codrol, string $pageName, bool $allowDelete): bool
    {
        $this->accesses[$codrol][$pageName] = $allowDelete;

        return true;
    }
}
