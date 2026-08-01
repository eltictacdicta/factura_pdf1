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

use FSFramework\Plugins\factura_pdf1\Model\PrintableDocumentInterface;
use FSFramework\Plugins\factura_pdf1\Model\View\ClientDocumentPrintViewInterface;

/**
 * Minimal stub for the `ClientDocumentPrintViewInterface`
 * used by tests that need to exercise the
 * `AbstractClienteDocumentAdapter` without going through a
 * real `FacturaPrintView` / `AlbaranPrintView` / etc.
 *
 * The stub's `getDocument()` returns an anonymous model that
 * carries empty/null fields; tests that need a value override
 * the relevant getter directly on a `StubAdapter` subclass.
 *
 * The class also implements `PrintableDocumentInterface` so
 * it can be used as the `view` argument of `PortedPdfDocument`
 * in unit tests that need a printer doc without going through
 * the full adapter stack.
 */
final class StubView implements ClientDocumentPrintViewInterface, PrintableDocumentInterface
{
    public function getId(): int
    {
        return 0;
    }

    public function getModelClassName(): string
    {
        return self::class;
    }

    public function getCodigoRect(): ?string
    {
        return null;
    }

    public function getCodigo(): string
    {
        return '';
    }

    public function getFecha(): string
    {
        return '';
    }

    public function getLineas(): array
    {
        return [];
    }

    public function getTotales(): array
    {
        return [];
    }

    public function getVencimiento(): ?string
    {
        return null;
    }

    public function getRelatedDocuments(): array
    {
        return [];
    }

    public function getIban(): ?string
    {
        return null;
    }

    public function getCarrier(): ?array
    {
        return null;
    }

    public function getPaymentBreakdown(): array
    {
        return [];
    }

    public function getAlmacen(): ?object
    {
        return null;
    }

    public function getContactoEnvio(): ?object
    {
        return null;
    }

    public function getCuentaBancaria(): string
    {
        return '';
    }

    public function getAgenciaTransporte(): array
    {
        return ['nombre' => '', 'tracking' => ''];
    }

    public function getRecibos(): array
    {
        return [];
    }

    public function getObservaciones(): ?string
    {
        return null;
    }

    public function getLines(): iterable
    {
        return [];
    }

    public function getDocument(): object
    {
        return new class {
            public ?string $codigo = '';
            public ?string $codigorect = null;
            public ?string $observaciones = '';
            public ?string $fecha = '';
            public ?string $vencimiento = null;
            public ?string $nombre = '';
            public ?string $nombrecliente = '';
            public ?string $cifnif = '';
            public ?string $codcliente = '';
            public ?string $coddivisa = 'EUR';
            public ?string $codpago = 'CONT';
            public ?string $codtrans = null;
            public ?string $codigoenv = null;
            public ?int $idcontactoenv = null;
            public ?int $idcontactofact = null;
            public ?string $codalmacen = '';
            public ?int $idalbaran = 0;
            public ?int $idfactura = 0;
            public ?int $idpedido = 0;
            public ?int $idpresupuesto = 0;
            public float $total = 0.0;
            public float $neto = 0.0;
            public float $totaliva = 0.0;
            public float $totalirpf = 0.0;
            public float $totalrecargo = 0.0;
            public float $totalsuplidos = 0.0;
            public float $netosindto = 0.0;
            public float $dtopor1 = 0.0;
            public float $dtopor2 = 0.0;
            public float $tasaconv = 1.0;
            public bool $debaja = false;
        };
    }

    public function getDocumentId(): int
    {
        return 0;
    }

    public function getDocumentTypeLabel(): string
    {
        return 'Stub';
    }

    public function getEmpresa(): object
    {
        return new \stdClass();
    }

    public function getCliente(): object
    {
        return new \stdClass();
    }

    public function getLineasIva(): array
    {
        return [];
    }

    public function getDivisa(): object
    {
        return new \stdClass();
    }

    public function getFormaPago(): object
    {
        return new \stdClass();
    }

    public function getPais(): object
    {
        return new \stdClass();
    }

    public function getTotalFormatted(): string
    {
        return '0,00';
    }

    public function getSubtotalFormatted(): string
    {
        return '0,00';
    }

    public function getTaxTotalsFormatted(): array
    {
        return [];
    }
}
