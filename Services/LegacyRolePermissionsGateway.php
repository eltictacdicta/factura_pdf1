<?php
/**
 * This file is part of factura_pdf1
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * @license LGPL-3.0-or-later
 */

declare(strict_types=1);

namespace FSFramework\Plugins\factura_pdf1\Services;

final class LegacyRolePermissionsGateway implements RolePermissionsGateway
{
    public function roleExists(string $codrol): bool
    {
        $this->bootLegacyModels();

        $rol = new \fs_rol();

        return $rol->get($codrol) !== false;
    }

    public function createRole(string $codrol, string $description): bool
    {
        $this->bootLegacyModels();

        $rol = new \fs_rol();
        $rol->codrol = $codrol;
        $rol->descripcion = $description;

        return $rol->save();
    }

    public function pageIsRegistered(string $pageName): bool
    {
        $this->bootLegacyModels();

        $page = new \fs_page();

        return $page->get($pageName) !== false;
    }

    public function roleHasPageAccess(string $codrol, string $pageName): bool
    {
        $this->bootLegacyModels();

        $access = new \fs_rol_access([
            'codrol' => $codrol,
            'fs_page' => $pageName,
        ]);

        return $this->legacySelectHasRows($access->exists());
    }

    public function grantPageAccess(string $codrol, string $pageName, bool $allowDelete): bool
    {
        $this->bootLegacyModels();

        $access = new \fs_rol_access([
            'codrol' => $codrol,
            'fs_page' => $pageName,
            'allow_delete' => $allowDelete,
        ]);

        return $access->save();
    }

    /**
     * Legacy fs_model::exists() devuelve array|false, no bool.
     */
    private function legacySelectHasRows(mixed $result): bool
    {
        return is_array($result) && $result !== [];
    }

    private function bootLegacyModels(): void
    {
        static $booted = false;
        if ($booted) {
            return;
        }

        $root = defined('FS_FOLDER') ? FS_FOLDER : dirname(__DIR__, 3);
        require_once $root . '/model/fs_rol.php';
        require_once $root . '/model/fs_rol_access.php';
        require_once $root . '/model/fs_page.php';

        $booted = true;
    }
}
