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

interface PrintableDocumentInterface
{
    public function getId(): int;

    /**
     * Returns the FQCN of the source model wrapped by the adapter
     * (e.g. `FacturaScripts\Dinamic\Model\FacturaCliente`,
     * `\\FacturaCliente`). The renderer uses this to drive
     * per-model branching (rectifying invoice, supplier vs
     * customer document, etc.) that the upstream
     * `BusinessDocument::modelClassName()` provided.
     */
    public function getModelClassName(): string;

    /**
     * Returns the source document's `codigorect` (rectifying
     * invoice original code) or `null` when the source has no
     * rectified counterpart. Replaces the upstream
     * `Tools::getRectifiedCode()` call.
     */
    public function getCodigoRect(): ?string;

    public function getCodigo(): string;

    public function getFecha(): string;

    public function getCliente(): object;

    /**
     * Returns the raw source-document model the adapter wraps
     * (e.g. `\\FSFramework\\model\\factura_cliente`). The
     * renderer reads `codigo`, `cifnif`, `codpostal`, etc.
     * from this object directly; upstream equivalent was
     * `BusinessDocument::getModel()`.
     */
    public function getDocument(): object;

    /**
     * Returns the company (`empresa`) that issued the document.
     * Replaces the upstream `new Empresa()` instantiation in
     * `PDFDocument::insertHeader()`.
     */
    public function getEmpresa(): object;

    /**
     * Returns the document's currency (`divisa`). Replaces the
     * upstream `new Divisa()` instantiation in
     * `PDFDocument::getDivisaSymbol()`.
     */
    public function getDivisa(): object;

    /**
     * Returns the document's payment-method row (`forma_pago`).
     * Replaces the upstream `new FormaPago()` instantiation
     * in `PDFDocument::getBankData()`.
     */
    public function getFormaPago(): object;

    /**
     * Alias of {@see self::getLineas()} kept for the renderer.
     * Returns the source document's line collection shaped for
     * `PDFDocument::insertBusinessDocBody()`'s `ezTable()` draw call.
     *
     * @return iterable<array{codigo: string, descripcion: string, cantidad: float, pvpunitario: float, pvptotal: float, iva: float, total: float}>
     */
    public function getLines(): iterable;

    /**
     * @return list<array{codigo: string, descripcion: string, cantidad: float, pvpunitario: float, pvptotal: float, iva: float, total: float}>
     */
    public function getLineas(): array;

    /**
     * @return array{total: float, totaliva: float, netosindto: float, dtopor1: float, dtopor2: float, totalirpf: float, totalsuplidos: float, totales: float}
     */
    public function getTotales(): array;

    public function getVencimiento(): ?string;

    /**
     * @return list<object>
     */
    public function getRelatedDocuments(): array;

    public function getIban(): ?string;

    /**
     * @return array{codtrans: ?string, codigoenv: ?string}|null
     */
    public function getCarrier(): ?array;

    /**
     * @return list<array{fecha: string, importe: float}>
     */
    public function getPaymentBreakdown(): array;

    public function getObservaciones(): ?string;

    /**
     * Returns the warehouse (Almacen) linked to the document, or null when
     * the document has no `codalmacen` or the join resolves to no row.
     * Per AD-12, the resolution is delegated to
     * {@see \FSFramework\Plugins\factura_pdf1\Services\RelatedModelsLoader::loadAlmacen()}.
     *
     * @return object|null
     */
    public function getAlmacen(): ?object;

    /**
     * Returns the shipping-address contact (Contacto) for the document,
     * or null when the document has no `idcontactoenv` (or the join
     * resolves to no row). Per AD-12, the resolution is delegated to
     * {@see \FSFramework\Plugins\factura_pdf1\Services\RelatedModelsLoader::loadContactoEnvio()}.
     *
     * @return object|null
     */
    public function getContactoEnvio(): ?object;

    /**
     * Returns the IBAN to render in the payment footer. Empty string when
     * no IBAN is resolvable. Per AD-12, the resolution is delegated to
     * {@see \FSFramework\Plugins\factura_pdf1\Services\RelatedModelsLoader::loadCuentaBancaria()}.
     */
    public function getCuentaBancaria(): string;

    /**
     * Returns the carrier block data (['nombre' => string, 'tracking' => string]).
     * Defaults to `['nombre' => '', 'tracking' => '']` when the document
     * has no `codtrans` or the join resolves to no row. Per AD-12, the
     * resolution is delegated to
     * {@see \FSFramework\Plugins\factura_pdf1\Services\RelatedModelsLoader::loadAgenciaTransporte()}.
     *
     * @return array{nombre: string, tracking: string}
     */
    public function getAgenciaTransporte(): array;

    /**
     * Returns the receipt (ReciboCliente) collection for the document,
     * ordered by `vencimiento` ASC. Empty array when the document has no
     * receipts. Per AD-12, the resolution is delegated to
     * {@see \FSFramework\Plugins\factura_pdf1\Services\RelatedModelsLoader::loadRecibos()}.
     *
     * @return list<object>
     */
    public function getRecibos(): array;
}
