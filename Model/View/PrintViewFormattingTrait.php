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

namespace FSFramework\Plugins\factura_pdf1\Model\View;

trait PrintViewFormattingTrait
{
    private static function resolveLocale(object $empresa): string
    {
        unset($empresa);

        $lang = getenv('FS_LANG');
        if (is_string($lang) && $lang !== '') {
            return $lang;
        }

        return 'es_ES';
    }

    private static function formatMoney(object $divisa, string $locale, float $amount): string
    {
        $currency = property_exists($divisa, 'codiso') && is_string($divisa->codiso) && $divisa->codiso !== ''
            ? $divisa->codiso
            : 'EUR';

        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency($amount, $currency);

        return $formatted !== false ? $formatted : number_format($amount, FS_NF0, FS_NF1, FS_NF2);
    }

    /**
     * @param list<object> $lineasIva
     *
     * @return array<string, string>
     */
    private static function formatTaxTotals(object $divisa, string $locale, array $lineasIva): array
    {
        $totals = [];
        foreach ($lineasIva as $lineaIva) {
            $key = number_format((float) ($lineaIva->iva ?? 0), 2, '.', '');
            $totals[$key] = self::formatMoney($divisa, $locale, (float) ($lineaIva->totaliva ?? 0));
        }

        return $totals;
    }

    /**
     * @param list<object> $lineas
     *
     * @return list<object>
     */
    public static function aggregateLineasIvaFromLineas(array $lineas): array
    {
        /** @var array<string, object> $buckets */
        $buckets = [];
        foreach ($lineas as $linea) {
            $iva = (float) ($linea->iva ?? 0);
            $key = number_format($iva, 2, '.', '');
            if (!isset($buckets[$key])) {
                $buckets[$key] = (object) [
                    'iva' => $iva,
                    'neto' => 0.0,
                    'totaliva' => 0.0,
                    'recargo' => (float) ($linea->recargo ?? 0),
                    'totalrecargo' => 0.0,
                ];
            }

            $net = (float) ($linea->pvptotal ?? 0);
            $buckets[$key]->neto += $net;
            $buckets[$key]->totaliva += $net * $iva / 100;
            $buckets[$key]->totalrecargo += $net * ((float) ($linea->recargo ?? 0)) / 100;
        }

        return array_values($buckets);
    }
}
