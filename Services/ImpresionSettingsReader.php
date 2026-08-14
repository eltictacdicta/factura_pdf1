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
 * Reads empresa impresión flags from fs_var (Admin → Empresa → Impresión).
 */
final class ImpresionSettingsReader
{
    /** @var (callable(): bool)|null */
    private static $resolverForTests = null;

    public static function setResolverForTests(?callable $resolver): void
    {
        self::$resolverForTests = $resolver;
    }

    public static function isPrintDtoEnabled(): bool
    {
        if (self::$resolverForTests !== null) {
            return (bool) (self::$resolverForTests)();
        }

        if (!class_exists('fs_var', false)) {
            $path = dirname(__DIR__, 3) . '/model/fs_var.php';
            if (is_file($path)) {
                require_once $path;
            }
        }

        if (!class_exists('fs_var', false)) {
            return true;
        }

        $fsvar = new \fs_var();
        $impresion = [
            'print_dto' => '1',
        ];
        $impresion = $fsvar->array_get($impresion, false);

        return self::isTruthy($impresion['print_dto'] ?? '0');
    }

    private static function isTruthy(mixed $value): bool
    {
        if ($value === false || $value === null || $value === '' || $value === '0' || $value === 0) {
            return false;
        }

        return true;
    }
}
