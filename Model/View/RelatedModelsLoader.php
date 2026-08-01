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
    public static function load(object $document): array
    {
        self::requireRelatedModels();

        $empresa = (new \empresa())->get();
        if (!$empresa instanceof \empresa) {
            throw new \RuntimeException('Empresa no configurada.');
        }

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

    public static function requireRelatedModels(): void
    {
        if (!class_exists('empresa', false)) {
            require_once FS_FOLDER . '/plugins/business_data/model/empresa.php';
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
