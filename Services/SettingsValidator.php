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

final class SettingsValidator
{
    /** @var list<string> */
    public const COLOR_KEYS = ['colorcabecera', 'colorfilas', 'colortexto1', 'colortexto2'];

    public static function isValidHexColor(string $color): bool
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return list<string> Invalid setting keys
     */
    public static function invalidColorKeys(array $settings): array
    {
        $invalid = [];
        foreach (self::COLOR_KEYS as $key) {
            if (!array_key_exists($key, $settings)) {
                continue;
            }

            $value = $settings[$key];
            if (!is_string($value) || !self::isValidHexColor($value)) {
                $invalid[] = $key;
            }
        }

        return $invalid;
    }
}
