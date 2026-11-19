# Progreso RSU

## V3

- Revisadas 19 páginas de los avances y del plan del docente. Conservados los 105 casos, con evidencia por entorno.
- Implementados registro y valoración ciudadana, pantallas de conductor, cola GPS, indicadores y detalle operativo del panel.
- Migraciones desde cero y desde la versión previa, respaldo, restauración y concurrencia comprobados en MySQL 8.4 aislado. Permanece la regresión SQLite.
- Generados APK Android para laboratorio con origen configurable. Quedan pendientes los casos que requieren teléfono físico, conectividad externa y la cobertura indicada en el informe.
- Continuidad en [informe V3](docs/aceptacion/INFORME_V3.md) y [matriz individual](docs/aceptacion/RESULTADOS_ACEPTACION.csv).

- Clonado, estado limpio inicial en `ed1812f`, rama principal `main`, escritura en GitHub verificada.
- Línea base con SQLite y PHPUnit completada. Ver [auditoría](docs/AUDITORIA_INICIAL.md).
- Rama local `feat/mobile-platform-integration` avanzó mediante fast-forward a `842c295`. Aplicadas 16 migraciones sobre la base aislada de main.
- Aplicaciones Expo y API de consulta implementadas. Flujo ligado a recorridos existentes y token de conductor.
- Pruebas de regresión corregidas y prueba integrada incorporada. Ver [informe](INFORME_FINAL.md) para estado de entrega.
- Builds e instalación Android validados en API 36. Recorrido iniciado, recuperado, registrado con GPS ficticio, cerrado y reflejado en la consulta ciudadana.
- Rama publicada y [PR #1](https://github.com/Ed-Ponk/rsu-proyecto/pull/1) abierto como borrador.
- Próximo punto de continuidad para ampliar el piloto: teléfonos físicos, origen HTTPS, MySQL y actualización de dependencias móviles.
