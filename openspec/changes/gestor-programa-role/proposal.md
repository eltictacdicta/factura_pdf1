# Proposal: Rol «Gestor del programa» al activar factura_pdf1

## Problema

Al activar `factura_pdf1` (y su cadena de dependencias) no existe un rol preconfigurado para
asignar a un usuario operativo que gestione catálogo, clientes, documentos de venta e impresión
sin ser administrador del sistema.

## Solución

Al ejecutar `Init::upgrade()` (activación / sincronización de esquema de `factura_pdf1`):

1. Crear el rol `gestor_programa` («Gestor del programa») si no existe.
2. Sincronizar permisos idempotentes (`allow_delete = true`) sobre las páginas operativas de
   `catalogo_core`, `clientes_core`, `clientes_facturacion`, `tpvmod` y `factura_pdf1`.
3. Incluir `admin_factura_pdf1`; excluir `tpvmod_settings`.
4. Solo conceder páginas ya registradas en `fs_pages` (controladores activos).

## Alcance

- Plugin-local: `plugins/factura_pdf1/` únicamente.
- Sin asignación automática a usuarios (el admin asigna el rol desde Usuarios/Roles).

## Criterios de éxito

- Tras activar `factura_pdf1`, existe `fs_roles.codrol = gestor_programa`.
- El rol tiene acceso a las páginas del manifiesto que estén registradas.
- Re-activar o repetir `upgrade()` no duplica filas ni elimina permisos extra añadidos manualmente.
