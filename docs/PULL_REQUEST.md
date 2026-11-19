# Borrador · Integrar las aplicaciones móviles de RSU

Integra `avance-06-api` desde el historial de RSU, incorpora dos variantes Expo y conecta la consulta ciudadana a la programación y a los recorridos existentes. La versión de conductor exige un token personal y valida asignación y asistencia en Laravel.

**Instalación y verificación** en [despliegue](DESPLIEGUE_GRATUITO.md), [pruebas](PRUEBAS.md) e [informe](../INFORME_FINAL.md). Las reglas cubiertas y sus componentes se enlazan en la [matriz](MATRIZ_DE_TRAZABILIDAD.md).

Base `main`, origen `feat/mobile-platform-integration`. Una vez cerrados los criterios críticos, publicar con `git push -u origin feat/mobile-platform-integration` y abrir el PR con `gh pr create --base main --head feat/mobile-platform-integration --title "Integrar las aplicaciones móviles de RSU" --body-file docs/PULL_REQUEST.md`. Para revisar los cambios específicos de la integración, comparar también con `842c295`.
