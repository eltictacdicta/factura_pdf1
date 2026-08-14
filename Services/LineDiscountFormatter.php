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
 * Cascading line discount helpers for PDF price cells (D1–D4).
 */
final class LineDiscountFormatter
{
    public static function dueMultiplier(float $d1, float $d2, float $d3, float $d4): float
    {
        return (1 - $d1 / 100)
            * (1 - $d2 / 100)
            * (1 - $d3 / 100)
            * (1 - $d4 / 100);
    }

    public static function hasDiscount(float $d1, float $d2, float $d3, float $d4): bool
    {
        return $d1 > 0 || $d2 > 0 || $d3 > 0 || $d4 > 0;
    }

    public static function discountedUnitPrice(float $pvpUnitario, float $d1, float $d2, float $d3, float $d4): float
    {
        return $pvpUnitario * self::dueMultiplier($d1, $d2, $d3, $d4);
    }

    public static function calcPvptotal(
        float $cantidad,
        float $pvpUnitario,
        float $d1,
        float $d2,
        float $d3,
        float $d4,
    ): float {
        return $cantidad * $pvpUnitario * self::dueMultiplier($d1, $d2, $d3, $d4);
    }

    /**
     * @param list<array<string, mixed>> $lines
     *
     * @return array{
     *     neto: float,
     *     totaliva: float,
     *     totalirpf: float,
     *     totalrecargo: float,
     *     total: float
     * }
     */
    public static function computeDocumentTotalsFromLines(array $lines, float $totalsuplidos = 0.0): array
    {
        $neto = 0.0;
        $totaliva = 0.0;
        $totalirpf = 0.0;
        $totalrecargo = 0.0;

        foreach ($lines as $line) {
            $pvptotal = (float) ($line['pvptotal'] ?? 0);
            $iva = (float) ($line['iva'] ?? 0);
            $recargo = (float) ($line['recargo'] ?? 0);
            $irpf = (float) ($line['irpf'] ?? 0);
            $neto += $pvptotal;
            $totaliva += $pvptotal * $iva / 100;
            $totalrecargo += $pvptotal * $recargo / 100;
            $totalirpf += $pvptotal * $irpf / 100;
        }

        $total = $neto + $totaliva - $totalirpf + $totalrecargo + $totalsuplidos;

        return [
            'neto' => $neto,
            'totaliva' => $totaliva,
            'totalirpf' => $totalirpf,
            'totalrecargo' => $totalrecargo,
            'total' => $total,
        ];
    }

    public static function lineHadStoredDiscounts(object $linea): bool
    {
        return self::hasDiscount(
            (float) ($linea->dtopor ?? 0),
            (float) ($linea->dtopor2 ?? 0),
            (float) ($linea->dtopor3 ?? 0),
            (float) ($linea->dtopor4 ?? 0),
        );
    }

    public static function lineNeedsDiscountEnrichment(object $linea, object $cliente): bool
    {
        if (self::lineHadStoredDiscounts($linea)) {
            return false;
        }

        $resolved = self::resolveLineDiscounts($linea, $cliente);

        return self::hasDiscount(
            $resolved['dtopor'],
            $resolved['dtopor2'],
            $resolved['dtopor3'],
            $resolved['dtopor4'],
        );
    }

    /**
     * Returns line D1–D4, falling back to the document cliente when the line
     * was persisted before tpvmod started storing per-line discounts.
     *
     * @return array{dtopor: float, dtopor2: float, dtopor3: float, dtopor4: float}
     */
    public static function resolveLineDiscounts(object $linea, object $cliente): array
    {
        $d1 = (float) ($linea->dtopor ?? 0);
        $d2 = (float) ($linea->dtopor2 ?? 0);
        $d3 = (float) ($linea->dtopor3 ?? 0);
        $d4 = (float) ($linea->dtopor4 ?? 0);

        if (!self::hasDiscount($d1, $d2, $d3, $d4)) {
            $fromCliente = self::readClienteDiscounts($cliente);
            $d1 = $fromCliente['dtopor'];
            $d2 = $fromCliente['dtopor2'];
            $d3 = $fromCliente['dtopor3'];
            $d4 = $fromCliente['dtopor4'];
        }

        return [
            'dtopor' => $d1,
            'dtopor2' => $d2,
            'dtopor3' => $d3,
            'dtopor4' => $d4,
        ];
    }

    /**
     * @return array{dtopor: float, dtopor2: float, dtopor3: float, dtopor4: float}
     */
    private static function readClienteDiscounts(object $cliente): array
    {
        $d1 = 0.0;
        $d2 = 0.0;
        $d3 = 0.0;
        $d4 = 0.0;

        if (method_exists($cliente, 'getEffectiveDiscounts')) {
            $raw = $cliente->getEffectiveDiscounts();
            $d1 = (float) ($raw['d1'] ?? 0);
            $d2 = (float) ($raw['d2'] ?? 0);
            $d3 = (float) ($raw['d3'] ?? 0);
            $d4 = (float) ($raw['d4'] ?? 0);
        }

        if (!self::hasDiscount($d1, $d2, $d3, $d4)) {
            $d1 = (float) ($cliente->d1 ?? 0);
            $d2 = (float) ($cliente->d2 ?? 0);
            $d3 = (float) ($cliente->d3 ?? 0);
            $d4 = (float) ($cliente->d4 ?? 0);
        }

        return [
            'dtopor' => $d1,
            'dtopor2' => $d2,
            'dtopor3' => $d3,
            'dtopor4' => $d4,
        ];
    }

    public static function formatListPriceStrikethrough(string $formattedListPrice): string
    {
        return '<c:color:0.45,0.45,0.45><c:strike>'
            . $formattedListPrice
            . '</c:strike></c:color>';
    }

    public static function formatUnitPriceCell(
        bool $printDto,
        float $pvpUnitario,
        float $d1,
        float $d2,
        float $d3,
        float $d4,
        string $formattedListPrice,
        string $formattedNetPrice,
    ): string {
        if (!$printDto || !self::hasDiscount($d1, $d2, $d3, $d4)) {
            return $formattedListPrice;
        }

        $discounted = self::discountedUnitPrice($pvpUnitario, $d1, $d2, $d3, $d4);
        if (abs($pvpUnitario - $discounted) < 0.0001) {
            return $formattedNetPrice;
        }

        return self::formatListPriceStrikethrough($formattedListPrice) . "\n" . $formattedNetPrice;
    }
}
