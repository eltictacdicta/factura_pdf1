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

use Symfony\Component\HttpFoundation\Request;

final class SettingsFormBinder
{
    /**
     * @param array<string, mixed> $defaults
     *
     * @return array<string, mixed>
     */
    public static function bindFromRequest(Request $request, array $defaults): array
    {
        $bound = [];
        foreach ($defaults as $key => $default) {
            if (is_bool($default)) {
                $bound[$key] = $request->request->has($key)
                    && filter_var($request->request->get($key), FILTER_VALIDATE_BOOLEAN);

                continue;
            }

            if (!is_int($default)) {
                $bound[$key] = trim((string) $request->request->get($key, (string) $default));

                continue;
            }

            $bound[$key] = (int) $request->request->get($key, $default);
        }

        return $bound;
    }
}
