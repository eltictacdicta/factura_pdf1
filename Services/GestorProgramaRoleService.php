<?php
/**
 * This file is part of factura_pdf1
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * @license LGPL-3.0-or-later
 */

declare(strict_types=1);

namespace FSFramework\Plugins\factura_pdf1\Services;

final class GestorProgramaRoleService
{
    public function __construct(
        private readonly RolePermissionsGateway $gateway,
    ) {
    }

    /**
     * @return array{created_role: bool, grants_added: int}
     */
    public function ensure(): array
    {
        $codrol = GestorProgramaRoleDefinition::CODROL;
        $createdRole = false;

        if (!$this->gateway->roleExists($codrol)) {
            if (!$this->gateway->createRole($codrol, GestorProgramaRoleDefinition::DESCRIPTION)) {
                throw new \RuntimeException('No se pudo crear el rol ' . $codrol);
            }
            $createdRole = true;
        }

        $grantsAdded = 0;
        foreach (GestorProgramaRoleDefinition::pages() as $pageName) {
            if (!$this->gateway->pageIsRegistered($pageName)) {
                continue;
            }

            if ($this->gateway->roleHasPageAccess($codrol, $pageName)) {
                continue;
            }

            if ($this->gateway->grantPageAccess($codrol, $pageName, true)) {
                ++$grantsAdded;
            }
        }

        return [
            'created_role' => $createdRole,
            'grants_added' => $grantsAdded,
        ];
    }
}
