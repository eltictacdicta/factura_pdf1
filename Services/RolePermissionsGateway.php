<?php
/**
 * This file is part of factura_pdf1
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * @license LGPL-3.0-or-later
 */

declare(strict_types=1);

namespace FSFramework\Plugins\factura_pdf1\Services;

interface RolePermissionsGateway
{
    public function roleExists(string $codrol): bool;

    public function createRole(string $codrol, string $description): bool;

    public function pageIsRegistered(string $pageName): bool;

    public function roleHasPageAccess(string $codrol, string $pageName): bool;

    public function grantPageAccess(string $codrol, string $pageName, bool $allowDelete): bool;
}
