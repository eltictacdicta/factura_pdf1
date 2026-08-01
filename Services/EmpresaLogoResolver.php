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

namespace FSFramework\Plugins\factura_pdf1\Services;

/**
 * Resolves the company logo path using admin_system_branding (primary)
 * or admin_empresa (fallback).
 */
final class EmpresaLogoResolver
{
    private const LOGO_PNG = 'images/logo.png';

    private const LOGO_JPG = 'images/logo.jpg';

    private const SYSTEM_LOGO_PATH = 'images/system_logo';

    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg'];

    public function __construct(
        private readonly ?string $fsFolder = null,
        private readonly ?string $fsMydocs = null,
    ) {
    }

    /**
     * Resolve the system branding logo (admin_system_branding)
     * as an absolute path suitable for embedding in PDFs.
     *
     * Priority mirrors fs_settings::getSystemLogoUrl():
     *  1. config2['system_logo'] relative to FS_MYDOCS
     *  2. FS_MYDOCS/images/system_logo.{png|jpg|jpeg}
     *  3. FS_MYDOCS/images/logo.{png|jpg} (legacy fallback)
     */
    public function resolveSystemLogo(): ?string
    {
        $mydocsRoot = $this->resolveMydocsRoot();
        if ($mydocsRoot === '') {
            return null;
        }

        $configured = $GLOBALS['config2']['system_logo'] ?? null;
        if ($configured !== null && $configured !== '') {
            $abs = $mydocsRoot . '/' . ltrim((string) $configured, '/');
            if (is_file($abs)) {
                return $abs;
            }
        }

        foreach (self::ALLOWED_EXTENSIONS as $ext) {
            $abs = $mydocsRoot . '/' . self::SYSTEM_LOGO_PATH . '.' . $ext;
            if (is_file($abs)) {
                return $abs;
            }
        }

        foreach ([self::LOGO_PNG, self::LOGO_JPG] as $legacy) {
            $abs = $mydocsRoot . '/' . $legacy;
            if (is_file($abs)) {
                return $abs;
            }
        }

        return null;
    }

    /**
     * Resolve the company logo (admin_empresa) as an absolute path.
     */
    public function resolveAbsolutePath(?object $empresa = null): ?string
    {
        foreach ($this->candidatePaths($empresa) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function candidatePaths(?object $empresa): array
    {
        $paths = [];
        $mydocsRoot = $this->resolveMydocsRoot();

        if ($mydocsRoot !== '') {
            $paths[] = $mydocsRoot . '/' . self::LOGO_PNG;
            $paths[] = $mydocsRoot . '/' . self::LOGO_JPG;

            $logoField = is_object($empresa) && property_exists($empresa, 'logo')
                ? trim((string) $empresa->logo)
                : '';
            if ($logoField !== '') {
                $paths[] = $mydocsRoot . '/' . ltrim($logoField, '/');
            }
        }

        $folder = $this->resolveFsFolder();
        $tmpName = defined('FS_TMP_NAME') ? (string) FS_TMP_NAME : '';
        if ($folder !== '' && $tmpName !== '') {
            $paths[] = $folder . '/tmp/' . $tmpName . 'logo.png';
            $paths[] = $folder . '/tmp/' . $tmpName . 'logo.jpg';
        }

        return array_values(array_unique($paths));
    }

    private function resolveFsFolder(): string
    {
        if ($this->fsFolder !== null && $this->fsFolder !== '') {
            return rtrim($this->fsFolder, '/');
        }

        return defined('FS_FOLDER') ? rtrim((string) FS_FOLDER, '/') : '';
    }

    private function resolveMydocsRoot(): string
    {
        if ($this->fsMydocs !== null) {
            return $this->toAbsolutePath($this->fsMydocs);
        }

        if (defined('FS_MYDOCS') && (string) FS_MYDOCS !== '') {
            return $this->toAbsolutePath((string) FS_MYDOCS);
        }

        // FS_MYDOCS is empty: fs_settings stores paths relative to FS_FOLDER
        return $this->resolveFsFolder();
    }

    private function toAbsolutePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if ($path[0] === '/') {
            return rtrim($path, '/');
        }

        $folder = $this->resolveFsFolder();
        if ($folder === '') {
            return rtrim($path, '/');
        }

        return $folder . '/' . trim($path, '/');
    }
}
