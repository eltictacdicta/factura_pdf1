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

/**
 * Loads shared related models (empresa, cliente, divisa, forma_pago, pais).
 */
final class RelatedModelsLoader
{
    /**
     * @return array{
     *     empresa: \empresa,
     *     cliente: \FSFramework\model\cliente,
     *     divisa: \divisa,
     *     formaPago: \forma_pago,
     *     pais: \pais
     * }
     */
    public static function load(object $document, ?string $documentType = null): array
    {
        self::requireRelatedModels();

        $empresa = self::resolveEmpresa((new \empresa())->get(), $documentType);

        $codcliente = property_exists($document, 'codcliente') ? (string) $document->codcliente : '';
        $cliente = (new \FSFramework\model\cliente())->get($codcliente);
        if (!$cliente instanceof \FSFramework\model\cliente) {
            throw new \RuntimeException('Cliente no encontrado para el documento.');
        }

        $coddivisa = property_exists($document, 'coddivisa') ? (string) $document->coddivisa : '';
        $divisa = (new \divisa())->get($coddivisa);
        if (!$divisa instanceof \divisa) {
            throw new \RuntimeException('Divisa no encontrada para el documento.');
        }

        $codpago = property_exists($document, 'codpago') ? (string) $document->codpago : '';
        $formaPago = (new \forma_pago())->get($codpago);
        if (!$formaPago instanceof \forma_pago) {
            throw new \RuntimeException('Forma de pago no encontrada para el documento.');
        }

        $codpais = $empresa->codpais !== '' ? $empresa->codpais : ($cliente->codpais ?? '');
        $pais = (new \pais())->get($codpais);
        if (!$pais instanceof \pais) {
            $pais = new \pais();
        }

        return [
            'empresa' => $empresa,
            'cliente' => $cliente,
            'divisa' => $divisa,
            'formaPago' => $formaPago,
            'pais' => $pais,
        ];
    }

    /**
     * Resolves the `\empresa` that must be printed for a document type.
     *
     * AD-13: this is the single interception point that adopts the sede mapped
     * by `empresa_sede`. It runs before the caller derives `$codpais`, so the
     * adopted sede's country is the one that selects the `pais` row.
     *
     * - Missing base row: `\RuntimeException('Empresa no configurada.')`, even
     *   when a sede is mapped (the contract is preserved verbatim).
     * - No type / unmapped type / dangling `codsede`: the very same base
     *   instance is returned, so zero-sede installs keep byte-identical output
     *   (apart from the deliberate base-company phone below).
     * - AD-4: whichever company wins has its `telefono` published as the
     *   dynamic `telefono1` the PDF reads. On the base path this is the single
     *   intentional, human-approved visible output change for existing installs;
     *   on the sede path `toEmpresa()` already published it, making this a no-op.
     *
     * `$sedeLoader` is the DB-free test seam forwarded to
     * `empresa_sede::resolveForDocumentType()`; production callers omit it.
     *
     * @param (callable(string): ?\empresa_sede)|null $sedeLoader
     */
    public static function resolveEmpresa(
        \empresa|false $base,
        ?string $documentType,
        ?callable $sedeLoader = null
    ): \empresa {
        self::requireRelatedModels();

        if (!$base instanceof \empresa) {
            throw new \RuntimeException('Empresa no configurada.');
        }

        // Sedes override (AD-13), before the caller's `$codpais` derivation.
        // `empresa_sede` ships in business_data; when it cannot be loaded the
        // loader degrades to the base company instead of fataling.
        $sede = null;
        if ($documentType !== null && $documentType !== '' && class_exists('empresa_sede', false)) {
            $sede = \empresa_sede::resolveForDocumentType($documentType, $base, $sedeLoader);
        }

        $empresa = $sede instanceof \empresa ? $sede : $base;

        if (class_exists('empresa_sede', false)) {
            return \empresa_sede::withPrintablePhone($empresa);
        }

        return $empresa;
    }

    public static function requireRelatedModels(): void
    {
        if (!class_exists('empresa', false)) {
            require_once FS_FOLDER . '/plugins/business_data/model/empresa.php';
        }
        // `empresa_sede` was added by business_data 1.1.0. Guarding on
        // `class_exists` matches this file's existing pattern; the extra
        // `file_exists` guard keeps an older business_data from fataling the
        // print path — the loader then degrades to the base company.
        $empresaSedeModel = FS_FOLDER . '/plugins/business_data/model/empresa_sede.php';
        if (!class_exists('empresa_sede', false) && file_exists($empresaSedeModel)) {
            require_once $empresaSedeModel;
        }
        if (!class_exists(\FSFramework\model\cliente::class, false)) {
            require_once FS_FOLDER . '/plugins/clientes_core/model/core/cliente.php';
        }
        if (!class_exists('divisa', false)) {
            require_once FS_FOLDER . '/plugins/catalogo_core/model/core/divisa.php';
        }
        if (!class_exists('forma_pago', false)) {
            require_once FS_FOLDER . '/plugins/business_data/model/forma_pago.php';
        }
        if (!class_exists('pais', false)) {
            require_once FS_FOLDER . '/plugins/catalogo_core/model/core/pais.php';
        }
    }
}
