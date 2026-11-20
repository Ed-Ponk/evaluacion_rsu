# Cierre V4.2

Referencia V4.1: `cfb0f0a`. Ancestro común con Avance 07: `842c295`. Revisión paralela: `7809486`.

| Área | Archivos o entidades | Conciliación |
| --- | --- | --- |
| Sectores y fotografía | ZonaController, Zona, SectorZona, formularios, zone_sectors y zones.photo_path | Se recuperó el módulo del Avance 07. Una edición de un cliente anterior conserva sectores si omite el campo. |
| Ciudadanía | Citizen, Ciudadano, SoloCiudadano, citizens | Se mantiene el contrato de los APK existentes. Se conserva el estado activo de la rama paralela y se rechazan cuentas desactivadas. |
| Votos | trip_ratings, CalificacionRecorrido, CiudadanoController | La columna paralela rating se adapta a stars sin recrear la tabla. Se conservan identificadores, comentarios y unicidad. |
| Historial y GPS | citizen_zone_periods, trip_points | Se conserva el historial V4.1. Las cuentas importadas inician su primer periodo en la fecha de conciliación. La trayectoria GPS conserva los UUID existentes. |
| Tokens | Sanctum | Los tokens de Ciudadano de Avance 07 se revocan. Los usuarios deben iniciar sesión de nuevo. Los tokens canónicos mantienen su modelo y ciclo de vida. |
| API | routes/api.php y controladores ciudadanos | Se mantiene la ruta canónica V4.1 y se añaden sectores y foto. La API alternativa requiere revisión de equivalencia antes de certificar clientes que la usaban. |

## Pendientes de cierre

- Confirmar instalación limpia y migraciones con copias V4.1 y Avance 07. Conciliar dos bases con identidades duplicadas requiere un plan de correspondencia por DNI, correo, zona y recorrido.
- Implementar y probar cierre offline durable. Incorporar cartografía vial legalmente utilizable.
- Registrar la ejecución acumulada de los 129 casos y las operaciones del panel. Repetir GPS e integración sobre la misma compilación candidata.
- Validar LAN con otro cliente. Para las pruebas físicas, disponer de un Android autorizado y una red privada. Para HTTPS, habilitar acceso autorizado desde otra red con una cuenta del responsable.
- Congelar compilaciones, ejecutar dos ensayos integrales y actualizar el PR con un resultado respaldado por evidencia.
