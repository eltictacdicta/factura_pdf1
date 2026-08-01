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

interface ClientDocumentPrintViewInterface
{
    public function getDocument(): object;

    public function getDocumentId(): int;

    public function getDocumentTypeLabel(): string;

    public function getEmpresa(): object;

    public function getCliente(): object;

    /** @return list<object> */
    public function getLineas(): array;

    /** @return list<object> */
    public function getLineasIva(): array;

    public function getDivisa(): object;

    public function getFormaPago(): object;

    public function getPais(): object;

    public function getTotalFormatted(): string;

    public function getSubtotalFormatted(): string;

    /**
     * @return array<string, string>
     */
    public function getTaxTotalsFormatted(): array;
}
