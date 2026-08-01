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

use FSFramework\Plugins\factura_pdf1\Model\Exception\PrintableDocumentNotFoundException;

/**
 * Self-contained FSFramework `fs_model` subclass that models a contact
 * belonging to a cliente. Created in PR-2 (Hallazgo 1) to close the
 * gap surfaced by the post-PR-1 audit: the upstream `contacto` class
 * does not exist in this repo's `clientes_core`, and PR-1's
 * `RelatedModelsLoader::loadContactoEnvio()` was forced into a
 * null-safe default. The new model gives the shipping-address block
 * (feature #6) a real, in-plugin target.
 *
 * The shape mirrors the upstream `FacturaPDF1` reference model:
 * a flat set of fields with a primary key `idcontacto` and a
 * `codcliente` FK. `idcontactoenv` is the recursive FK that lets a
 * cliente have multiple shipping contacts. The model is intentionally
 * minimal — it owns only the fields the renderer reads in the
 * shipping-address block.
 */
final class Contacto extends \fs_model
{
    public ?int $idcontacto = null;

    public ?string $codcliente = null;

    public ?string $nombre = null;

    public ?string $apellidos = null;

    public ?string $telefono1 = null;

    public ?string $telefono2 = null;

    public ?string $email = null;

    public ?string $codpais = null;

    public ?string $provincia = null;

    public ?string $ciudad = null;

    public ?string $direccion = null;

    public ?string $codpostal = null;

    public ?int $idcontactoenv = null;

    public ?string $observaciones = null;

    public function __construct($data = false)
    {
        parent::__construct('contactos');
        if ($data) {
            $this->hydrateFromArray(is_array($data) ? $data : (array) $data);
        }
    }

    /**
     * @return Contacto|false
     */
    public function get($id)
    {
        $id = is_string($id) ? (int) $id : $id;
        $data = $this->db->select(
            'SELECT * FROM ' . $this->table_name
            . ' WHERE idcontacto = ' . $this->var2str($id) . ';'
        );
        if ($data) {
            return new self($data[0]);
        }

        return false;
    }

    public function exists(): bool
    {
        if ($this->idcontacto === null) {
            return false;
        }

        return (bool) $this->db->select(
            'SELECT idcontacto FROM ' . $this->table_name
            . ' WHERE idcontacto = ' . $this->var2str($this->idcontacto) . ';'
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
                . 'codcliente = ' . $this->var2str($this->codcliente)
                . ', nombre = ' . $this->var2str($this->nombre)
                . ', apellidos = ' . $this->var2str($this->apellidos)
                . ', telefono1 = ' . $this->var2str($this->telefono1)
                . ', telefono2 = ' . $this->var2str($this->telefono2)
                . ', email = ' . $this->var2str($this->email)
                . ', codpais = ' . $this->var2str($this->codpais)
                . ', provincia = ' . $this->var2str($this->provincia)
                . ', ciudad = ' . $this->var2str($this->ciudad)
                . ', direccion = ' . $this->var2str($this->direccion)
                . ', codpostal = ' . $this->var2str($this->codpostal)
                . ', idcontactoenv = ' . $this->var2str($this->idcontactoenv)
                . ', observaciones = ' . $this->var2str($this->observaciones)
                . ' WHERE idcontacto = ' . $this->var2str($this->idcontacto) . ';';
        } else {
            $sql = 'INSERT INTO ' . $this->table_name
                . ' (codcliente, nombre, apellidos, telefono1, telefono2, email, codpais,'
                . ' provincia, ciudad, direccion, codpostal, idcontactoenv, observaciones) VALUES ('
                . $this->var2str($this->codcliente) . ','
                . $this->var2str($this->nombre) . ','
                . $this->var2str($this->apellidos) . ','
                . $this->var2str($this->telefono1) . ','
                . $this->var2str($this->telefono2) . ','
                . $this->var2str($this->email) . ','
                . $this->var2str($this->codpais) . ','
                . $this->var2str($this->provincia) . ','
                . $this->var2str($this->ciudad) . ','
                . $this->var2str($this->direccion) . ','
                . $this->var2str($this->codpostal) . ','
                . $this->var2str($this->idcontactoenv) . ','
                . $this->var2str($this->observaciones) . ');';
        }

        return $this->db->exec($sql);
    }

    public function delete(): bool
    {
        if ($this->idcontacto === null) {
            return false;
        }

        return $this->db->exec(
            'DELETE FROM ' . $this->table_name
            . ' WHERE idcontacto = ' . $this->var2str($this->idcontacto) . ';'
        );
    }

    protected function install(): string
    {
        return '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrateFromArray(array $data): void
    {
        if (array_key_exists('idcontacto', $data) && $data['idcontacto'] !== null) {
            $this->idcontacto = (int) $data['idcontacto'];
        }
        if (array_key_exists('codcliente', $data) && $data['codcliente'] !== null) {
            $this->codcliente = (string) $data['codcliente'];
        }
        if (array_key_exists('nombre', $data) && $data['nombre'] !== null) {
            $this->nombre = (string) $data['nombre'];
        }
        if (array_key_exists('apellidos', $data) && $data['apellidos'] !== null) {
            $this->apellidos = (string) $data['apellidos'];
        }
        if (array_key_exists('telefono1', $data) && $data['telefono1'] !== null) {
            $this->telefono1 = (string) $data['telefono1'];
        }
        if (array_key_exists('telefono2', $data) && $data['telefono2'] !== null) {
            $this->telefono2 = (string) $data['telefono2'];
        }
        if (array_key_exists('email', $data) && $data['email'] !== null) {
            $this->email = (string) $data['email'];
        }
        if (array_key_exists('codpais', $data) && $data['codpais'] !== null) {
            $this->codpais = (string) $data['codpais'];
        }
        if (array_key_exists('provincia', $data) && $data['provincia'] !== null) {
            $this->provincia = (string) $data['provincia'];
        }
        if (array_key_exists('ciudad', $data) && $data['ciudad'] !== null) {
            $this->ciudad = (string) $data['ciudad'];
        }
        if (array_key_exists('direccion', $data) && $data['direccion'] !== null) {
            $this->direccion = (string) $data['direccion'];
        }
        if (array_key_exists('codpostal', $data) && $data['codpostal'] !== null) {
            $this->codpostal = (string) $data['codpostal'];
        }
        if (array_key_exists('idcontactoenv', $data) && $data['idcontactoenv'] !== null) {
            $this->idcontactoenv = (int) $data['idcontactoenv'];
        }
        if (array_key_exists('observaciones', $data) && $data['observaciones'] !== null) {
            $this->observaciones = (string) $data['observaciones'];
        }
    }
}
