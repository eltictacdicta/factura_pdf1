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
 * Per AD-12, centralizes the 5 cross-model joins that the new adapter
 * getters expose. Each load*() method is null-safe: when the joined
 * model class is not present in the environment, or when the join
 * resolves to no row, the method returns null / [] / empty string.
 *
 * PR-2 wires the joins to live data:
 *   - `Contacto` and `ReciboCliente` are now in-plugin models (PR-2
 *     Hallazgos 1 and 2 close the upstream gaps with self-contained
 *     `fs_model` subclasses).
 *   - The remaining model classes (`Almacen`, `cuenta_banco_cliente`,
 *     `cuenta_banco`, `agencia_transporte`) live in `business_data`
 *     and `facturacion_base`; the loader is null-safe against them so
 *     the plugin still renders when they are absent (e.g. in tests).
 *
 * This class is intentionally distinct from
 * {@see \FSFramework\Plugins\factura_pdf1\Model\View\RelatedModelsLoader}
 * (which loads the shared related models: empresa, cliente, divisa,
 * forma_pago, pais). The two classes serve different layers; this one
 * loads the per-document related models and is consumed by the adapter
 * getters added in PR-1.
 */
class RelatedModelsLoader
{
    public function loadAlmacen(string $codalmacen): ?object
    {
        if ($codalmacen === '' || !self::almacenClassAvailable()) {
            return null;
        }

        $row = (new \almacen())->get($codalmacen);
        if (!$row instanceof \almacen) {
            return null;
        }

        return $row;
    }

    public function loadContactoEnvio(int $idcontactoenv): ?object
    {
        if ($idcontactoenv <= 0) {
            return null;
        }

        // Per PR-2 Hallazgo 1: the \contacto class is now an in-plugin
        // model (plugins/factura_pdf1/Model/Contacto.php). The join
        // runs unconditionally; the model returns false when no row
        // exists, which we translate to null.
        $model = new \FSFramework\Plugins\factura_pdf1\Model\Contacto();
        $row = $model->get($idcontactoenv);

        return $row instanceof \FSFramework\Plugins\factura_pdf1\Model\Contacto ? $row : null;
    }

    public function loadCuentaBancaria(string $codcliente, string $codcuenta): string
    {
        if ($codcliente === '' && $codcuenta === '') {
            return '';
        }

        // Try the customer's bank account first (cuenta_banco_cliente).
        if ($codcliente !== '' && self::cuentaBancoClienteClassAvailable()) {
            $clienteIban = $this->resolveClienteIban($codcliente);
            if ($clienteIban !== '') {
                return $clienteIban;
            }
        }

        // Fallback to the empresa's main cuenta_banco.
        if ($codcuenta !== '' && self::cuentaBancoClassAvailable()) {
            $empresaIban = $this->resolveEmpresaIban($codcuenta);
            if ($empresaIban !== '') {
                return $empresaIban;
            }
        }

        return '';
    }

    /**
     * @return array{nombre: string, tracking: string}
     */
    public function loadAgenciaTransporte(string $codtrans, ?string $codigoenv): array
    {
        $tracking = is_string($codigoenv) ? $codigoenv : '';

        if ($codtrans === '') {
            return ['nombre' => '', 'tracking' => $tracking];
        }

        if (!self::agenciaTransporteClassAvailable()) {
            return ['nombre' => '', 'tracking' => $tracking];
        }

        $row = (new \agencia_transporte())->get($codtrans);
        if (!$row instanceof \agencia_transporte) {
            return ['nombre' => '', 'tracking' => $tracking];
        }

        $nombre = trim((string) $row->nombre);

        return ['nombre' => $nombre, 'tracking' => $tracking];
    }

    /**
     * @return list<object>
     */
    public function loadRecibos(string $modelClass, string $idDoc): array
    {
        if ($idDoc === '' || $idDoc === '0') {
            return [];
        }

        // Per PR-2 Hallazgo 2: the \recibo_cliente class is now an
        // in-plugin model (plugins/factura_pdf1/Model/ReciboCliente.php).
        // The join runs unconditionally; the model returns [] when the
        // foreign key has no rows. We rely on the model's own
        // `all_from()` ordering by `vencimiento` ASC.
        /** @var list<\FSFramework\Plugins\factura_pdf1\Model\ReciboCliente> $rows */
        $rows = (new \FSFramework\Plugins\factura_pdf1\Model\ReciboCliente())
            ->all_from($modelClass, (int) $idDoc);

        if (!is_array($rows)) {
            return [];
        }

        // The model already sorts by vencimiento; we still re-apply a
        // stable sort so the test contract is robust to future schema
        // changes.
        usort($rows, static function (object $a, object $b): int {
            $va = property_exists($a, 'vencimiento') ? (string) $a->vencimiento : '';
            $vb = property_exists($b, 'vencimiento') ? (string) $b->vencimiento : '';

            return strcmp($va, $vb);
        });

        return $rows;
    }

    private function resolveClienteIban(string $codcliente): string
    {
        /** @var list<object> $rows */
        $rows = (new \cuenta_banco_cliente())->all_from_cliente($codcliente);
        if ($rows === []) {
            return '';
        }

        $first = $rows[0];
        if (!property_exists($first, 'iban')) {
            return '';
        }

        return trim((string) $first->iban);
    }

    private function resolveEmpresaIban(string $codcuenta): string
    {
        $row = (new \cuenta_banco())->get($codcuenta);
        if (!$row instanceof \cuenta_banco) {
            return '';
        }

        // Direct access is safe after `instanceof` narrowing; we still
        // keep the defensive `property_exists` check so a downstream
        // plugin that narrows the type does not break the call site.
        if (!property_exists($row, 'iban')) {
            return '';
        }

        return trim((string) $row->iban);
    }

    private static function almacenClassAvailable(): bool
    {
        return self::tryRequireClass('almacen', 'business_data');
    }

    private static function cuentaBancoClienteClassAvailable(): bool
    {
        return self::tryRequireClass('cuenta_banco_cliente', 'clientes_core');
    }

    private static function cuentaBancoClassAvailable(): bool
    {
        return self::tryRequireClass('cuenta_banco', 'business_data');
    }

    private static function agenciaTransporteClassAvailable(): bool
    {
        return self::tryRequireClass('agencia_transporte', 'facturacion_base');
    }

    /**
     * Best-effort class availability check. When the model file is not
     * present in the current plugin set, the load*() method that depends
     * on it returns its documented default (null / [] / ''). The check
     * delegates to the autoloader first, and only attempts to require the
     * conventional path under FS_FOLDER/plugins/{owner} when the class is
     * not yet known to PHP.
     */
    private static function tryRequireClass(string $className, string $pluginOwner): bool
    {
        if (class_exists($className, false)) {
            return true;
        }

        if (!defined('FS_FOLDER')) {
            return false;
        }

        foreach (self::candidatePaths($className, $pluginOwner) as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }
            require_once $candidate;
            // The class_exists check below is the canonical "did the
            // require actually load the class?" probe; the surrounding
            // is_file() guard can return false in environments where
            // the plugin file is missing, so this branch is rarely
            // reached in tests but is the right runtime guard for
            // production.
            if (class_exists($className, false)) {
                return true;
            }
        }

        return class_exists($className, false);
    }

    /**
     * @return list<string>
     */
    private static function candidatePaths(string $className, string $pluginOwner): array
    {
        if (!defined('FS_FOLDER')) {
            return [];
        }

        return [
            FS_FOLDER . '/plugins/' . $pluginOwner . '/model/' . $className . '.php',
            FS_FOLDER . '/plugins/' . $pluginOwner . '/model/core/' . $className . '.php',
        ];
    }
}
