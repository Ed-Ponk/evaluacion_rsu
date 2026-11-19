# API RSU

Usar `Accept: application/json`. Las mutaciones reciben JSON. Los recursos del conductor exigen `Authorization: Bearer <token>`. En caso de fallo Laravel devuelve `message` y, si corresponde, `errors`.

| Método y ruta | Datos y respuesta |
| --- | --- |
| GET `/up` | Disponibilidad del proceso Laravel |
| GET `/api/v1/recoleccion` | `zonas` activas, `programaciones` y `actualizado`; pasar `?zona=<id>` para consultar turnos |
| POST `/api/conductor/login` | `email`, `password`, `dispositivo?`; devuelve `token`, `conductor` y `config` |
| GET `/api/conductor/perfil` | Perfil del conductor propio |
| POST `/api/conductor/logout` | Revoca el token actual |
| GET `/api/conductor/programaciones[/hoy|/{id}]` | Lista, turno actual/relevante o detalle; incluye asistencia y motivo de bloqueo en el detalle |
| POST `/api/conductor/programaciones/{id}/recorrido` | Inicia y devuelve `201` y `data` |
| GET `/api/conductor/recorridos[/actual|/{id}]` | Historial paginado por `page`, recorrido activo o detalle |
| POST `/api/conductor/recorridos/{id}/puntos` | `puntos` con `lat`, `lng`, `momento` ISO 8601 y `precision?`; devuelve `registrados` y `descartados` |
| POST `/api/conductor/recorridos/{id}/finalizar` | `observacion` de 3 a 500 caracteres con configuración predeterminada |

Estados de consulta `programado`, `reprogramado`, `en_ruta`, `completado` y `sin_confirmacion`. En ruta informa que el conductor inició el recorrido. `inicio` y `fin` del turno son horas programadas en ISO 8601. Se consulta desde ayer hasta siete días hacia adelante, además de recorridos todavía activos. Errores frecuentes 401 sesión inválida, 403 rol, 404 recurso, 422 validación y 429 cuota.
