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

/**
 * Builds stub legacy models for print view / adapter unit tests (no database).
 */
final class DocumentPrintViewFixture
{
    /**
     * @return array{
     *     factura: \FSFramework\model\factura_cliente,
     *     empresa: \empresa,
     *     cliente: \FSFramework\model\cliente,
     *     divisa: \divisa,
     *     formaPago: \forma_pago,
     *     pais: \pais,
     *     lineas: list<\FSFramework\model\linea_factura_cliente>,
     *     lineasIva: list<\FSFramework\model\linea_iva_factura_cliente>
     * }
     */
    public static function buildFacturaPayload(
        string $currency = 'EUR',
        string $locale = 'es_ES',
        float $total = 1234.56,
        float $neto = 1000.00,
        int $lineCount = 2,
    ): array {
        self::requireModels();

        $factura = new \FSFramework\model\factura_cliente(self::minimalFacturaRow($total, $neto, $currency));
        $factura->idfactura = 1;

        putenv('FS_LANG=' . $locale);

        return [
            'factura' => $factura,
            'empresa' => new \empresa(self::minimalEmpresaRow($currency)),
            'cliente' => new \FSFramework\model\cliente(self::minimalClienteRow($currency)),
            'divisa' => new \divisa(self::minimalDivisaRow($currency)),
            'formaPago' => new \forma_pago(self::minimalFormaPagoRow()),
            'pais' => new \pais(self::minimalPaisRow()),
            'lineas' => self::buildFacturaLineas($lineCount),
            'lineasIva' => [new \FSFramework\model\linea_iva_factura_cliente(self::minimalLineaIvaRow())],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildGenericDocumentRow(string $codigo, float $total, float $neto): array
    {
        $row = self::minimalFacturaRow($total, $neto, 'EUR');
        $row['codigo'] = $codigo;

        return $row;
    }

    /** @return list<\FSFramework\model\linea_factura_cliente> */
    private static function buildFacturaLineas(int $lineCount): array
    {
        $lineas = [];
        for ($i = 0; $i < $lineCount; $i++) {
            $lineas[] = new \FSFramework\model\linea_factura_cliente(self::minimalLineaRow($i + 1));
        }

        return $lineas;
    }

    /** @return array<string, mixed> */
    private static function minimalFacturaRow(float $total, float $neto, string $currency): array
    {
        return [
            'idfactura' => 1,
            'codigo' => 'FAC-TEST-001',
            'codcliente' => 'C001',
            'coddivisa' => $currency,
            'codpago' => 'CONT',
            'total' => $total,
            'neto' => $neto,
            'totaliva' => $total - $neto,
            'totalirpf' => 0,
            'totalrecargo' => 0,
            'codserie' => 'A',
            'codejercicio' => '2026',
            'numero' => '1',
            'numero2' => '',
            'fecha' => '2026-01-01',
            'hora' => '10:00:00',
            'codalmacen' => 'ALG',
            'codvendedor' => null,
            'codagente' => null,
            'coddir' => null,
            'nombrecliente' => 'Cliente Test SA',
            'cifnif' => 'A12345678',
            'porcomision' => 0,
            'observaciones' => '',
            'codtrans' => null,
            'codigoenv' => null,
            'idcontactoenv' => null,
            'nombreenv' => null,
            'apellidosenv' => null,
            'apartadoenv' => null,
            'direccionenv' => null,
            'codpostalenv' => null,
            'ciudadenv' => null,
            'provinciaenv' => null,
            'codpaisenv' => null,
            'numdocs' => 0,
            'dtopor1' => 0,
            'dtopor2' => 0,
            'dtopor3' => 0,
            'dtopor4' => 0,
            'dtopor5' => 0,
            'netosindto' => $neto,
            'totalsuplidos' => 0,
            'totaleuros' => $total,
            'codpais' => 'ESP',
            'codpostal' => '28001',
            'apartado' => '',
            'provincia' => 'Madrid',
            'ciudad' => 'Madrid',
            'direccion' => 'Calle Test 1',
            'irpf' => 0,
            'tasaconv' => 1,
            'pagada' => false,
            'anulada' => false,
            'idasiento' => null,
            'idasientop' => null,
            'idfacturarect' => null,
            'codigorect' => null,
            'vencimiento' => '2026-02-01',
            'idimprenta' => null,
            'femail' => null,
        ];
    }

    /** @return array<string, mixed> */
    private static function minimalEmpresaRow(string $currency): array
    {
        return [
            'id' => 1,
            'nombre' => 'Empresa Test SL',
            'nombrecorto' => 'Empresa Test',
            'cifnif' => 'B12345678',
            'administrador' => 'Admin',
            'administrador2' => '',
            'codserie' => 'A',
            'codalmacen' => 'ALG',
            'codpago' => 'CONT',
            'coddivisa' => $currency,
            'codpais' => 'ESP',
            'codpostal' => '28001',
            'provincia' => 'Madrid',
            'ciudad' => 'Madrid',
            'direccion' => 'Calle Empresa 1',
            'apartado' => '',
            'telefono' => '910000000',
            'fax' => '',
            'email' => 'info@empresa.test',
            'web' => 'https://empresa.test',
            'horario' => '',
            'contintegrada' => false,
            'codejercicio' => '2026',
            'recequiv' => false,
            'lema' => '',
            'observaciones' => '',
        ];
    }

    /** @return array<string, mixed> */
    private static function minimalClienteRow(string $currency): array
    {
        return [
            'codcliente' => 'C001',
            'nombre' => 'Cliente Test SA',
            'razonsocial' => 'Cliente Test SA',
            'nombrecomercial' => 'Cliente Test SA',
            'tipoidfiscal' => 'CIF/NIF',
            'cifnif' => 'A12345678',
            'telefono1' => '910000001',
            'telefono2' => '',
            'fax' => '',
            'email' => 'cliente@test.com',
            'web' => '',
            'codserie' => 'A',
            'coddivisa' => $currency,
            'codpago' => 'CONT',
            'codagente' => null,
            'codgrupo' => null,
            'debaja' => false,
            'fechabaja' => null,
            'fechaalta' => '2026-01-01',
            'observaciones' => '',
            'regimeniva' => 'General',
            'recargo' => false,
            'personafisica' => false,
            'diaspago' => '',
            'codproveedor' => null,
            'codtarifa' => null,
            'codpais' => 'ESP',
        ];
    }

    /** @return array<string, mixed> */
    private static function minimalDivisaRow(string $currency): array
    {
        return [
            'coddivisa' => $currency,
            'descripcion' => 'Euros',
            'codiso' => $currency,
            'simbolo' => '€',
            'tasaconv' => 1,
            'tasaconv_compra' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private static function minimalFormaPagoRow(): array
    {
        return [
            'codpago' => 'CONT',
            'descripcion' => 'Contado',
            'genrecibos' => 'Pagados',
            'codcuenta' => '',
            'domiciliado' => false,
            'imprimir' => true,
            'ventas' => true,
            'compras' => true,
            'numdias' => 0,
            'calendario' => '',
        ];
    }

    /** @return array<string, mixed> */
    private static function minimalPaisRow(): array
    {
        return [
            'codpais' => 'ESP',
            'nombre' => 'España',
            'codiso' => 'ES',
            'iso3' => 'ESP',
            'codtelefono' => '34',
        ];
    }

    /** @return array<string, mixed> */
    private static function minimalLineaRow(int $index): array
    {
        return [
            'idlinea' => $index,
            'idfactura' => 1,
            'idlineaalbaran' => null,
            'idalbaran' => null,
            'referencia' => 'REF-' . $index,
            'descripcion' => 'Linea ' . $index,
            'cantidad' => 1,
            'pvpunitario' => 100,
            'pvpsindto' => 100,
            'dtopor' => 0,
            'dtopor2' => 0,
            'dtopor3' => 0,
            'dtopor4' => 0,
            'pvptotal' => 100,
            'codimpuesto' => 'IVA21',
            'codcombinacion' => null,
            'iva' => 21,
            'recargo' => 0,
            'irpf' => 0,
            'orden' => $index,
            'mostrar_cantidad' => true,
            'mostrar_precio' => true,
        ];
    }

    /** @return array<string, mixed> */
    private static function minimalLineaIvaRow(): array
    {
        return [
            'idlinea' => 1,
            'idfactura' => 1,
            'totallinea' => 1000,
            'totalrecargo' => 0,
            'recargo' => 0,
            'totaliva' => 210,
            'iva' => 21,
            'codimpuesto' => 'IVA21',
            'neto' => 1000,
        ];
    }

    public static function requireModels(): void
    {
        if (!defined('FS_CIFNIF')) {
            define('FS_CIFNIF', 'CIF/NIF');
        }
        if (!defined('FS_FACTURA')) {
            define('FS_FACTURA', 'factura');
        }
        if (!defined('FS_IVA')) {
            define('FS_IVA', 'IVA');
        }

        require_once FS_FOLDER . '/plugins/clientes_facturacion/model/core/factura_cliente.php';
        require_once FS_FOLDER . '/plugins/clientes_facturacion/model/core/linea_factura_cliente.php';
        require_once FS_FOLDER . '/plugins/clientes_facturacion/model/core/linea_iva_factura_cliente.php';
        require_once FS_FOLDER . '/plugins/clientes_facturacion/model/core/albaran_cliente.php';
        require_once FS_FOLDER . '/plugins/clientes_facturacion/model/core/linea_albaran_cliente.php';
        require_once FS_FOLDER . '/plugins/clientes_facturacion/model/core/pedido_cliente.php';
        require_once FS_FOLDER . '/plugins/clientes_facturacion/model/core/linea_pedido_cliente.php';
        require_once FS_FOLDER . '/plugins/clientes_facturacion/model/core/presupuesto_cliente.php';
        require_once FS_FOLDER . '/plugins/clientes_facturacion/model/core/linea_presupuesto_cliente.php';
        require_once FS_FOLDER . '/plugins/business_data/model/empresa.php';
        require_once FS_FOLDER . '/plugins/clientes_core/model/core/cliente.php';
        require_once FS_FOLDER . '/plugins/catalogo_core/model/core/divisa.php';
        require_once FS_FOLDER . '/plugins/business_data/model/forma_pago.php';
        require_once FS_FOLDER . '/plugins/catalogo_core/model/core/pais.php';
    }

    /**
     * PR-2 row overrides. Keys prefixed with `__` (double-underscore) are
     * applied to the underlying minimalFacturaRow when
     * {@see applyFacturaRowOverrides()} is called. This lets the
     * RenderFeatureTest inject a non-default `codtrans`, `codigoenv`, or
     * `idcontactoenv` for a single test case without re-creating the
     * whole payload. Keys without the `__` prefix are settings (handled
     * separately by the test).
     *
     * @var array<string, mixed>
     */
    private static array $facturaRowOverrides = [];

    /**
     * @param array<string, mixed> $overrides
     */
    public static function applyFacturaRowOverrides(array $overrides): void
    {
        foreach ($overrides as $key => $value) {
            if (str_starts_with((string) $key, '__')) {
                self::$facturaRowOverrides[substr((string) $key, 2)] = $value;
            }
        }
    }

    public static function resetFacturaRowOverrides(): void
    {
        self::$facturaRowOverrides = [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function consumeFacturaRowOverrides(): array
    {
        $overrides = self::$facturaRowOverrides;
        self::$facturaRowOverrides = [];

        return $overrides;
    }
}
