# Spec: Rol Gestor del programa

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| GP-01 | `Init::upgrade()` MUST invoke role seeding | MUST |
| GP-02 | Role `codrol` MUST be `gestor_programa` | MUST |
| GP-03 | Role description MUST be «Gestor del programa» | MUST |
| GP-04 | Seeding MUST be idempotent (create-if-missing + add missing page grants) | MUST |
| GP-05 | Page grants MUST use `allow_delete = true` | MUST |
| GP-06 | MUST NOT grant `tpvmod_settings` | MUST |
| GP-07 | MUST grant `admin_factura_pdf1` and operational pages from dependency plugins | MUST |
| GP-08 | MUST skip pages not registered in `fs_pages` | MUST |
| GP-09 | Failures MUST NOT break plugin activation (log + swallow) | MUST |

## Page manifest

### factura_pdf1
- `admin_factura_pdf1`
- `factura_detallada`

### tpvmod
- `tpvmod`, `tpvmod_facturas`, `tpvmod_presupuestos`, `tpvmod_pedidos`, `tpvmod_albaranes`

### clientes_core
- `ventas_clientes`, `ventas_cliente`

### clientes_facturacion
- `ventas_cliente_articulos`, `ventas_maquetar`, `ventas_clientes_opciones`

### catalogo_core
- `ventas_articulos`, `ventas_articulo`, `ventas_familias`, `ventas_familia`
- `ventas_fabricantes`, `ventas_fabricante`
- `admin_almacenes`, `admin_divisas`, `admin_paises`, `contabilidad_impuestos`
