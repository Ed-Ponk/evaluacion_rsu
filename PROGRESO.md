# Progreso RSU

- Clonado, estado limpio inicial en `ed1812f`, rama principal `main`, escritura en GitHub verificada.
- Línea base con SQLite y PHPUnit completada. Ver [auditoría](docs/AUDITORIA_INICIAL.md).
- Rama local `feat/mobile-platform-integration` avanzó mediante fast-forward a `842c295`. Aplicadas 16 migraciones sobre la base aislada de main.
- Aplicaciones Expo y API de consulta implementadas. Flujo ligado a recorridos existentes y token de conductor.
- Pruebas de regresión corregidas y prueba integrada incorporada. Ver [informe](INFORME_FINAL.md) para estado de entrega.
- Builds e instalación Android validados en API 36. Recorrido iniciado, recuperado, registrado con GPS ficticio, cerrado y reflejado en la consulta ciudadana.
- Rama publicada y [PR #1](https://github.com/Ed-Ponk/rsu-proyecto/pull/1) abierto como borrador.
- Próximo punto de continuidad para ampliar el piloto: teléfonos físicos, origen HTTPS, MySQL y actualización de dependencias móviles.
