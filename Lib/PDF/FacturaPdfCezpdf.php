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

namespace FSFramework\Plugins\factura_pdf1\Lib\PDF;

/**
 * Cezpdf with strikethrough support for discounted list prices in ezTable cells.
 *
 * Usage: {@code <c:strike>575,74</c:strike>}
 */
final class FacturaPdfCezpdf extends \Cezpdf
{
    public function __construct($paper = 'a4', $orientation = 'portrait', $type = 'none', $options = [])
    {
        parent::__construct($paper, $orientation, $type, $options);
        $this->allowedTags .= '|strike';
    }

    /**
     * Draws a horizontal line through the tagged text (PVPR tachado).
     *
     * @param array<string, mixed> $info
     */
    public function strike(array $info): void
    {
        $lineFactor = 0.03;

        switch ($info['status']) {
            case 'start':
            case 'sol':
                if (!isset($this->ez['strikes'])) {
                    $this->ez['strikes'] = [];
                }

                $this->ez['strikes'][] = [
                    'x' => $info['x'],
                    'y' => $info['y'],
                    'angle' => $info['angle'],
                    'height' => $info['height'],
                ];
                $this->setLineStyle($info['height'] * $lineFactor);
                $this->saveState();
                break;

            case 'end':
            case 'eol':
                if (empty($this->ez['strikes'])) {
                    break;
                }

                $start = array_shift($this->ez['strikes']);
                // Through the x-height centre (uline uses y-drop below baseline).
                $mid = $start['height'] * 0.38;
                $a = deg2rad((float) $start['angle']);
                $ox = -sin($a) * $mid;
                $oy = cos($a) * $mid;
                $this->line(
                    $start['x'] + $ox,
                    $start['y'] + $oy,
                    $info['x'] + $ox,
                    $info['y'] + $oy
                );
                $this->restoreState();
                break;
        }
    }
}
