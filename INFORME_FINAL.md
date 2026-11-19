# Informe de entrega RSU

**Estado general · APTO CON LIMITACIONES para laboratorio de servidor.** El flujo servidor de programación, recorrido y consulta ciudadana funciona y tiene prueba automatizada. La publicación y el piloto en dispositivos requieren cerrar los criterios pendientes de esta tabla.

## Evidencias

- `main` partía de `ed1812f`. La suite Feature original pasó 36 pruebas con 98 aserciones y 6 omisiones. Se integraron los avances existentes hasta `842c295` mediante fast-forward y se aplicaron 16 migraciones a la base SQLite creada desde main.
- La suite de la integración registró 183 casos, 176 aprobados, 6 omitidos por configuración y 1 incompleto por KPI del panel. Total 1 757 aserciones, sin errores ni fallos. Pint y Vite pasaron. `composer audit --locked` quedó sin avisos después de actualizar 7 dependencias.
- La prueba de integración certifica el flujo programado → en ruta → completado con persistencia en SQLite y sin datos de personal o GPS en la respuesta pública.
- Laravel en `http://127.0.0.1:8102` respondió a salud y consulta con 200, a panel sin sesión con 302 y a API de conductor sin token con 401. En una base separada con cinco zonas de ejemplo se verificó el ciclo real por HTTP de la programación 29, recorrido 1 y un punto GPS.
- El acceso web con sesión llegó al listado de programaciones y mostró el recorrido Completado en su historial. Una segunda instancia PHP recuperó el mismo estado desde SQLite. En 20 consultas locales por zona, la mediana fue 24,6 ms y el percentil 95 fue 27,5 ms. La prueba fue secuencial y con datos de ejemplo.
- Expo validó dependencias y compiló bundles Android e iOS por variante. Los bundles Hermes fueron aproximadamente 1,79 MB Android y 1,78 MB iOS. El tamaño de instalación se mide en el APK, y el arranque, batería y accesibilidad se ensayan en dispositivo.
- La aplicación de cliente se interpreta como consulta vecinal de la recolección municipal. Se añadieron horarios por zona y estado operativo sin registro ciudadano. La app del conductor reutiliza cuenta, propiedad, asignación y asistencia existentes. El GPS se registra manualmente en primer plano. El cambio de estado se consulta en REST cada 30 segundos en la app vecinal.

## Criterios

| Criterio | Estado | Evidencia o dependencia |
| --- | --- | --- |
| Repositorio original clonado y auditado | APROBADO | Referencias, arquitectura y [auditoría](docs/AUDITORIA_INICIAL.md) |
| Sistema original evaluado antes del cambio | APROBADO | Migraciones, seed y 36 pruebas Feature |
| Reglas de negocio identificadas y documentadas | PARCIAL | Catálogo por flujo. Falta inventario campo por campo de todos los módulos |
| Validaciones existentes y faltantes analizadas | PARCIAL | [Matriz](docs/MATRIZ_DE_VALIDACIONES.md), queda concurrencia y validación exhaustiva por módulo |
| Matriz de trazabilidad | APROBADO | [Regla, ruta y prueba](docs/MATRIZ_DE_TRAZABILIDAD.md) |
| Arquitectura móvil seleccionada y justificada | APROBADO | [ADR](docs/DECISIONES_TECNICAS.md) |
| Servidor principal funcional | APROBADO | HTTP y PHPUnit en SQLite |
| API documentada y probada | APROBADO | [Contrato](docs/API.md) y pruebas de API |
| App cliente implementada | PARCIAL | Código y bundle, sujeto a prueba instalada |
| App conductor implementada | PARCIAL | Código y bundle. GPS manual y validación instalada pendiente |
| Apps conectadas al servidor real | PARCIAL | Usan rutas verificadas. Falta comprobar desde ambas instalaciones |
| Roles y permisos diferenciados | APROBADO | Pruebas de acceso y propiedad |
| Base de datos integrada | APROBADO | SQLite desde cero y actualización desde main. MySQL pendiente de validación |
| Comunicación y sincronización | PARCIAL | Servidor integrado. Seguimiento instalado y redes degradadas pendientes |
| Flujo crítico extremo a extremo | PARCIAL | Flujo API/BD cubierto, caso completo desde dispositivos pendiente |
| Funcionamiento web preservado | PARCIAL | Pruebas de regresión y build web. MySQL y recorrido visual pendientes |
| Entorno gratuito operativo | PARCIAL | Loopback comprobado. HTTPS y despliegue entre dispositivos pendientes |
| APK | PARCIAL | Compilación nativa en verificación |
| Pruebas en dispositivos o limitación documentada | PARCIAL | Procedimiento manual, instalación en verificación |
| Seguridad revisada | PARCIAL | Expiración, alcance y dependencias PHP. Auditoría npm móvil y pruebas de despliegue pendientes |
| Instalación reproducible | PARCIAL | Instalación PHP/SQLite y bundles reproducidos. APK pendiente |
| Cambios registrados mediante Git | APROBADO | Rama `feat/mobile-platform-integration` |
| Rama publicada con permisos | BLOQUEADO | Publicación condicionada a controles críticos instalados |
| Pull Request preparado o creado | APROBADO | [Borrador local](docs/PULL_REQUEST.md) |
| Informe con evidencias | APROBADO | Este documento y [pruebas](docs/PRUEBAS.md) |

Consulta la [instalación](docs/DESPLIEGUE_GRATUITO.md) y el [plan manual](docs/PRUEBAS.md). Coste contratado S/ 0.
