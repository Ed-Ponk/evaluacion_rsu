# Registro de incidencias V3

| Incidencia | Evidencia y corrección | Verificación |
| --- | --- | --- |
| Contrato GPS legado | El caso automatizado de recorrido completo falló al recibir un campo nuevo. `repetidos` se emite para lotes con identificador de operación. | Suite de regresión posterior en ambos motores |
| Límite compartido de autenticación | Registro y login competían por el mismo límite numérico/IP y la segunda alta legítima recibió 429 durante la integración. | Se separaron las cuotas para cada operación ciudadana y se repitió la integración |
| Carpeta nativa Windows bloqueada | Expo no pudo eliminar un archivo DEX en el directorio anterior. | Se compiló desde una copia nueva de ruta corta |
| Diferencia cartográfica | El mapa vectorial representa el polígono y la trayectoria persistida. Quedan pendientes el fondo vial, los controles de mapa y la comparación visual exhaustiva. | Véase la matriz de pantallas |

Los fallos del APK y los intentos de ejecución no concluyentes se registran por caso en el CSV, sin sustituir comprobaciones móviles por pruebas de API.
