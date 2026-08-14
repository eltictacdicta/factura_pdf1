<?php
/**
 * This file is part of factura_pdf1
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * @license LGPL-3.0-or-later
 */

declare(strict_types=1);

namespace FSFramework\Plugins\factura_pdf1\Services;

final class GestorProgramaRoleDefinition
{
    public const CODROL = 'gestor_programa';

    public const DESCRIPTION = 'Gestor del programa';

    /** @var list<string> */
    public const PAGE_MANIFEST = [
        // factura_pdf1
        'admin_factura_pdf1',
        'factura_detallada',
        // tpvmod
        'tpvmod',
        'tpvmod_facturas',
        'tpvmod_presupuestos',
        'tpvmod_pedidos',
        'tpvmod_albaranes',
        // clientes_core
        'ventas_clientes',
        'ventas_cliente',
        // clientes_facturacion
        'ventas_cliente_articulos',
        'ventas_maquetar',
        'ventas_clientes_opciones',
        // catalogo_core
        'ventas_articulos',
        'ventas_articulo',
        'ventas_familias',
        'ventas_familia',
        'ventas_fabricantes',
        'ventas_fabricante',
        'admin_almacenes',
        'admin_divisas',
        'admin_paises',
        'contabilidad_impuestos',
    ];

    /** @return list<string> */
    public static function pages(): array
    {
        return self::PAGE_MANIFEST;
    }
}
