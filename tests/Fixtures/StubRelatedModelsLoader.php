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

namespace FacturaPdf1\Tests\Fixtures;

use FSFramework\Plugins\factura_pdf1\Services\RelatedModelsLoader;

/**
 * Test-only `RelatedModelsLoader` that lets the test inject a fixed
 * value for each `load*()` method. Used by the `RenderFeatureTest`
 * for scenarios that need a non-null IBAN, a known `Contacto`, an
 * `Almacen`, etc., without requiring DB seed data.
 */
final class StubRelatedModelsLoader extends RelatedModelsLoader
{
    public string $iban = '';

    public ?object $contactoEnvio = null;

    public ?object $almacen = null;

    public array $agenciaTransporte = ['nombre' => '', 'tracking' => ''];

    /** @var list<object> */
    public array $recibos = [];

    public function loadContactoEnvio(int $idcontactoenv): ?object
    {
        return $this->contactoEnvio;
    }

    public function loadAlmacen(string $codalmacen): ?object
    {
        return $this->almacen;
    }

    public function loadCuentaBancaria(string $codcliente, string $codcuenta): string
    {
        return $this->iban;
    }

    /**
     * @return array{nombre: string, tracking: string}
     */
    public function loadAgenciaTransporte(string $codtrans, ?string $codigoenv): array
    {
        return $this->agenciaTransporte;
    }

    /**
     * @return list<object>
     */
    public function loadRecibos(string $modelClass, string $idDoc): array
    {
        return $this->recibos;
    }
}
