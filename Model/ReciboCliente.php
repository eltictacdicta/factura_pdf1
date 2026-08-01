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

namespace FSFramework\Plugins\factura_pdf1\Model;

/**
 * Self-contained FSFramework `fs_model` subclass that models a customer
 * receipt (`ReciboCliente`). Created in PR-2 (Hallazgo 2) to close the
 * gap surfaced by the post-PR-1 audit: the upstream `recibo_cliente`
 * class does not exist in this repo's `clientes_facturacion`, and PR-1's
 * `RelatedModelsLoader::loadRecibos()` was forced into a null-safe
 * default. The new model gives the `pagoyvencimiento` mode 3
 * (feature #3) a real, in-plugin target.
 *
 * The table is intentionally minimal: a `idfactura` FK + the columns
 * the renderer needs in the payment-receipts table (fecha, importe,
 * vencimiento, estado, numero, observaciones). The four ID columns
 * (idfactura, idalbaran, idpedido, idpresupuesto) let the same model
 * serve all 4 document types PR-1 wired up.
 */
final class ReciboCliente extends \fs_model
{
    public ?int $idrecibo = null;

    public ?int $idfactura = null;

    public ?int $idalbaran = null;

    public ?int $idpedido = null;

    public ?int $idpresupuesto = null;

    public ?string $codcliente = null;

    public ?string $coddivisa = null;

    public ?string $codpago = null;

    public ?string $codcuenta = null;

    public ?string $fecha = null;

    public ?string $vencimiento = null;

    public ?float $importe = null;

    public ?string $estado = null;

    public ?string $numero = null;

    public ?string $observaciones = null;

    public function __construct($data = false)
    {
        parent::__construct('reciboscli');
        if ($data) {
            $this->hydrateFromArray(is_array($data) ? $data : (array) $data);
        }
    }

    /**
     * Return the receipt rows attached to a given foreign document id.
     * Mirrors the `all_from()` convention of upstream models.
     *
     * @return list<self>
     */
    public function all_from(string $modelClass, int $id): array
    {
        $column = $this->resolveForeignColumn($modelClass);
        if ($column === null) {
            return [];
        }

        $rows = $this->db->select(
            'SELECT * FROM ' . $this->table_name
            . ' WHERE ' . $column . ' = ' . $this->var2str($id)
            . ' ORDER BY vencimiento ASC, idrecibo ASC;'
        );
        if (!$rows) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[] = new self($row);
        }

        return $result;
    }

    /**
     * @return self|false
     */
    public function get($id)
    {
        $id = is_string($id) ? (int) $id : $id;
        $data = $this->db->select(
            'SELECT * FROM ' . $this->table_name
            . ' WHERE idrecibo = ' . $this->var2str($id) . ';'
        );
        if ($data) {
            return new self($data[0]);
        }

        return false;
    }

    public function exists(): bool
    {
        if ($this->idrecibo === null) {
            return false;
        }

        return (bool) $this->db->select(
            'SELECT idrecibo FROM ' . $this->table_name
            . ' WHERE idrecibo = ' . $this->var2str($this->idrecibo) . ';'
        );
    }

    public function test(): bool
    {
        return true;
    }

    public function save(): bool
    {
        if ($this->exists()) {
            $sql = 'UPDATE ' . $this->table_name . ' SET '
                . 'idfactura = ' . $this->var2str($this->idfactura)
                . ', idalbaran = ' . $this->var2str($this->idalbaran)
                . ', idpedido = ' . $this->var2str($this->idpedido)
                . ', idpresupuesto = ' . $this->var2str($this->idpresupuesto)
                . ', codcliente = ' . $this->var2str($this->codcliente)
                . ', coddivisa = ' . $this->var2str($this->coddivisa)
                . ', codpago = ' . $this->var2str($this->codpago)
                . ', codcuenta = ' . $this->var2str($this->codcuenta)
                . ', fecha = ' . $this->var2str($this->fecha)
                . ', vencimiento = ' . $this->var2str($this->vencimiento)
                . ', importe = ' . $this->var2str($this->importe)
                . ', estado = ' . $this->var2str($this->estado)
                . ', numero = ' . $this->var2str($this->numero)
                . ', observaciones = ' . $this->var2str($this->observaciones)
                . ' WHERE idrecibo = ' . $this->var2str($this->idrecibo) . ';';
        } else {
            $sql = 'INSERT INTO ' . $this->table_name
                . ' (idfactura, idalbaran, idpedido, idpresupuesto, codcliente, coddivisa,'
                . ' codpago, codcuenta, fecha, vencimiento, importe, estado, numero, observaciones) VALUES ('
                . $this->var2str($this->idfactura) . ','
                . $this->var2str($this->idalbaran) . ','
                . $this->var2str($this->idpedido) . ','
                . $this->var2str($this->idpresupuesto) . ','
                . $this->var2str($this->codcliente) . ','
                . $this->var2str($this->coddivisa) . ','
                . $this->var2str($this->codpago) . ','
                . $this->var2str($this->codcuenta) . ','
                . $this->var2str($this->fecha) . ','
                . $this->var2str($this->vencimiento) . ','
                . $this->var2str($this->importe) . ','
                . $this->var2str($this->estado) . ','
                . $this->var2str($this->numero) . ','
                . $this->var2str($this->observaciones) . ');';
        }

        return $this->db->exec($sql);
    }

    public function delete(): bool
    {
        if ($this->idrecibo === null) {
            return false;
        }

        return $this->db->exec(
            'DELETE FROM ' . $this->table_name
            . ' WHERE idrecibo = ' . $this->var2str($this->idrecibo) . ';'
        );
    }

    protected function install(): string
    {
        return '';
    }

    private function resolveForeignColumn(string $modelClass): ?string
    {
        $key = strtolower($modelClass);
        if (str_contains($key, 'factura')) {
            return 'idfactura';
        }
        if (str_contains($key, 'albaran')) {
            return 'idalbaran';
        }
        if (str_contains($key, 'pedido')) {
            return 'idpedido';
        }
        if (str_contains($key, 'presupuesto')) {
            return 'idpresupuesto';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrateFromArray(array $data): void
    {
        if (array_key_exists('idrecibo', $data) && $data['idrecibo'] !== null) {
            $this->idrecibo = (int) $data['idrecibo'];
        }
        if (array_key_exists('idfactura', $data) && $data['idfactura'] !== null) {
            $this->idfactura = (int) $data['idfactura'];
        }
        if (array_key_exists('idalbaran', $data) && $data['idalbaran'] !== null) {
            $this->idalbaran = (int) $data['idalbaran'];
        }
        if (array_key_exists('idpedido', $data) && $data['idpedido'] !== null) {
            $this->idpedido = (int) $data['idpedido'];
        }
        if (array_key_exists('idpresupuesto', $data) && $data['idpresupuesto'] !== null) {
            $this->idpresupuesto = (int) $data['idpresupuesto'];
        }
        if (array_key_exists('codcliente', $data) && $data['codcliente'] !== null) {
            $this->codcliente = (string) $data['codcliente'];
        }
        if (array_key_exists('coddivisa', $data) && $data['coddivisa'] !== null) {
            $this->coddivisa = (string) $data['coddivisa'];
        }
        if (array_key_exists('codpago', $data) && $data['codpago'] !== null) {
            $this->codpago = (string) $data['codpago'];
        }
        if (array_key_exists('codcuenta', $data) && $data['codcuenta'] !== null) {
            $this->codcuenta = (string) $data['codcuenta'];
        }
        if (array_key_exists('fecha', $data) && $data['fecha'] !== null) {
            $this->fecha = (string) $data['fecha'];
        }
        if (array_key_exists('vencimiento', $data) && $data['vencimiento'] !== null) {
            $this->vencimiento = (string) $data['vencimiento'];
        }
        if (array_key_exists('importe', $data) && $data['importe'] !== null) {
            $this->importe = (float) $data['importe'];
        }
        if (array_key_exists('estado', $data) && $data['estado'] !== null) {
            $this->estado = (string) $data['estado'];
        }
        if (array_key_exists('numero', $data) && $data['numero'] !== null) {
            $this->numero = (string) $data['numero'];
        }
        if (array_key_exists('observaciones', $data) && $data['observaciones'] !== null) {
            $this->observaciones = (string) $data['observaciones'];
        }
    }
}
