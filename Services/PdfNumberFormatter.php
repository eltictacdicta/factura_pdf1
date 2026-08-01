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
 * Locale-aware number formatter used by the Cezpdf port to
 * render monetary and quantity values on the printed document.
 *
 * Replaces the upstream `Tools::number()` call in the parent
 * `PDFDocument` class. The default (`','` decimal + `'.'`
 * thousands) is the Spanish (`es_ES`) convention used by
 * FacturaScripts; the override path is exercised by the
 * English (`en_EN`) locale and any future locale variant.
 */
class PdfNumberFormatter
{
    public static function format(
        float $n,
        string $decimalSep = ',',
        string $thousandsSep = '.',
        int $decimals = 2,
    ): string {
        return number_format($n, $decimals, $decimalSep, $thousandsSep);
    }
}
