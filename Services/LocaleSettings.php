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
 * Lightweight locale-aware value object read by the Cezpdf port
 * when rendering numbers and resolving company-specific settings.
 *
 * Replaces the upstream `Tools::settings('default', ...)` calls
 * scattered through `PDFDocument::render()`. The runtime source of
 * truth for the default company and the locale defaults is the
 * `factura_pdf1_settings` table (see {@see SettingsService::load()}),
 * but the port only needs the resolved values: the constructor
 * takes them directly so the port is testable without a real
 * database row.
 *
 * PR-1 ships the static defaults (`es_ES`); a follow-up SDD will
 * lift the values from the `factura_pdf1_settings` JSON payload.
 */
class LocaleSettings
{
    private string $decimalSeparator;

    private string $thousandsSeparator;

    private ?int $idempresa;

    public function __construct(
        string $decimalSeparator = ',',
        string $thousandsSeparator = '.',
        ?int $idempresa = null,
    ) {
        $this->decimalSeparator = $decimalSeparator;
        $this->thousandsSeparator = $thousandsSeparator;
        $this->idempresa = $idempresa;
    }

    public function getDecimalSeparator(): string
    {
        return $this->decimalSeparator;
    }

    public function getThousandsSeparator(): string
    {
        return $this->thousandsSeparator;
    }

    public function getIdempresa(): ?int
    {
        return $this->idempresa;
    }
}
