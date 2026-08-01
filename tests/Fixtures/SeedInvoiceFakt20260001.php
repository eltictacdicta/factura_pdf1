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

use FSFramework\Plugins\factura_pdf1\Model\Adapters\FacturaClienteAdapter;
use FSFramework\Plugins\factura_pdf1\Model\View\FacturaPrintView;

/**
 * Deterministic invoice payload for FAKT-2026-0001 (no database).
 */
final class SeedInvoiceFakt20260001
{
    public const INVOICE_CODE = 'FAKT-2026-0001';

    /** @var array<string, string> Section markers grep-able in PDF text extracts. */
    public const SECTION_MARKERS = [
        'company_header' => 'Empresa Demo SL',
        'invoice_number_date' => 'FAKT-2026-0001',
        'client_billing' => 'Cliente Demo SA',
        'line_items' => 'LINEAS DE FACTURA',
        'vat_breakdown' => 'DESGLOSE DE IVA',
        'totals' => 'TOTALES',
        'payment_footer' => 'FORMA DE PAGO',
    ];

    public static function buildAdapter(): FacturaClienteAdapter
    {
        self::configureResolvers();

        return FacturaClienteAdapter::fromId(1);
    }

    public static function configureResolvers(): void
    {
        $payload = self::buildPayload();

        FacturaPrintView::resetResolversForTests();
        FacturaPrintView::setResolversForTests(
            static fn (int $id): \FSFramework\model\factura_cliente|false => $id === 1 ? $payload['factura'] : false,
            static fn (\FSFramework\model\factura_cliente $factura): array => $payload,
        );
    }

    /** @return array<string, mixed> */
    public static function buildPayload(): array
    {
        DocumentPrintViewFixture::requireModels();

        $factura = new \FSFramework\model\factura_cliente(self::facturaRow());
        $factura->idfactura = 1;

        $empresa = new \empresa([
            'id' => 1,
            'nombre' => 'Empresa Demo SL',
            'nombrecorto' => 'Empresa Demo',
            'cifnif' => 'B99999999',
            'administrador' => 'Admin',
            'administrador2' => '',
            'codserie' => 'A',
            'codalmacen' => 'ALG',
            'codpago' => 'CONT',
            'coddivisa' => 'EUR',
            'codpais' => 'ESP',
            'codpostal' => '28001',
            'provincia' => 'Madrid',
            'ciudad' => 'Madrid',
            'direccion' => 'Calle Empresa 10',
            'apartado' => '',
            'telefono' => '910000000',
            'fax' => '',
            'email' => 'info@empresa-demo.test',
            'web' => 'https://empresa-demo.test',
            'horario' => '',
            'contintegrada' => false,
            'codejercicio' => '2026',
            'recequiv' => false,
            'lema' => '',
            'observaciones' => '',
        ]);

        $cliente = new \FSFramework\model\cliente([
            'codcliente' => 'C-DEMO',
            'nombre' => 'Cliente Demo SA',
            'razonsocial' => 'Cliente Demo SA',
            'nombrecomercial' => 'Cliente Demo SA',
            'tipoidfiscal' => 'CIF/NIF',
            'cifnif' => 'A88888888',
            'telefono1' => '910000001',
            'telefono2' => '',
            'fax' => '',
            'email' => 'cliente@demo.test',
            'web' => '',
            'codserie' => 'A',
            'coddivisa' => 'EUR',
            'codpago' => 'CONT',
            'codagente' => null,
            'codgrupo' => null,
            'debaja' => false,
            'fechabaja' => null,
            'fechaalta' => '2026-01-01',
            'observaciones' => '',
            'regimeniva' => 'General',
            'recargo' => true,
            'personafisica' => false,
            'diaspago' => '',
            'codproveedor' => null,
            'codtarifa' => null,
            'codpais' => 'ESP',
            'ref2' => 'PED-2026-001',
        ]);

        $divisa = new \divisa([
            'coddivisa' => 'EUR',
            'descripcion' => 'Euros',
            'codiso' => 'EUR',
            'simbolo' => '€',
            'tasaconv' => 1,
            'tasaconv_compra' => 1,
        ]);

        $formaPago = new \forma_pago([
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
        ]);

        $pais = new \pais([
            'codpais' => 'ESP',
            'nombre' => 'España',
            'codiso' => 'ES',
            'iso3' => 'ESP',
            'codtelefono' => '34',
        ]);

        $lineas = [];
        for ($i = 1; $i <= 3; $i++) {
            $lineas[] = new \FSFramework\model\linea_factura_cliente([
                'idlinea' => $i,
                'idfactura' => 1,
                'idlineaalbaran' => null,
                'idalbaran' => null,
                'referencia' => 'ART-' . $i,
                'descripcion' => 'Articulo demo ' . $i,
                'cantidad' => 1,
                'pvpunitario' => 100 * $i,
                'pvpsindto' => 100 * $i,
                'dtopor' => 0,
                'dtopor2' => 0,
                'dtopor3' => 0,
                'dtopor4' => 0,
                'pvptotal' => 100 * $i,
                'codimpuesto' => 'IVA21',
                'codcombinacion' => null,
                'iva' => 21,
                'recargo' => 5.2,
                'irpf' => 0,
                'orden' => $i,
                'mostrar_cantidad' => true,
                'mostrar_precio' => true,
            ]);
        }

        $lineasIva = [
            new \FSFramework\model\linea_iva_factura_cliente([
                'idlinea' => 1,
                'idfactura' => 1,
                'totallinea' => 600,
                'totalrecargo' => 31.2,
                'recargo' => 5.2,
                'totaliva' => 126,
                'iva' => 21,
                'codimpuesto' => 'IVA21',
                'neto' => 600,
            ]),
        ];

        putenv('FS_LANG=es_ES');

        return [
            'factura' => $factura,
            'empresa' => $empresa,
            'cliente' => $cliente,
            'divisa' => $divisa,
            'formaPago' => $formaPago,
            'pais' => $pais,
            'lineas' => $lineas,
            'lineasIva' => $lineasIva,
        ];
    }

    /** @return array<string, mixed> */
    private static function facturaRow(): array
    {
        $row = [
            'idfactura' => 1,
            'codigo' => self::INVOICE_CODE,
            'codcliente' => 'C-DEMO',
            'coddivisa' => 'EUR',
            'codpago' => 'CONT',
            'total' => 757.2,
            'neto' => 600.0,
            'totaliva' => 126.0,
            'totalirpf' => 0,
            'totalrecargo' => 31.2,
            'codserie' => 'A',
            'codejercicio' => '2026',
            'numero' => '1',
            'numero2' => '',
            'fecha' => '2026-01-15',
            'hora' => '10:30:00',
            'codalmacen' => 'ALG',
            'codvendedor' => null,
            'codagente' => null,
            'coddir' => null,
            'nombrecliente' => 'Cliente Demo SA',
            'cifnif' => 'A88888888',
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
            'netosindto' => 600.0,
            'totalsuplidos' => 0,
            'totaleuros' => 757.2,
            'codpais' => 'ESP',
            'codpostal' => '28002',
            'apartado' => '',
            'provincia' => 'Madrid',
            'ciudad' => 'Madrid',
            'direccion' => 'Calle Cliente 20',
            'irpf' => 0,
            'tasaconv' => 1,
            'pagada' => false,
            'anulada' => false,
            'idasiento' => null,
            'idasientop' => null,
            'idfacturarect' => null,
            'codigorect' => null,
            'vencimiento' => '2026-02-15',
            'idimprenta' => null,
            'femail' => null,
        ];

        $overrides = DocumentPrintViewFixture::consumeFacturaRowOverrides();
        foreach ($overrides as $key => $value) {
            $row[$key] = $value;
        }

        return $row;
    }
}
