# Documentación técnica — `acs-exam-web`

Contexto completo del sistema tal como está hoy, pensado para que
cualquiera del equipo pueda retomarlo sin tener que releer todo el código
ni el historial del chat.

1. **[01_ARQUITECTURA_GENERAL.md](01_ARQUITECTURA_GENERAL.md)** — stack
   real, los dos consumidores (panel web + API v1), cómo comparten lógica
   de negocio, estructura de carpetas, flujo de datos de punta a punta.
2. **[02_BASE_DE_DATOS.md](02_BASE_DE_DATOS.md)** — las 24 tablas,
   relaciones, el diseño polimórfico del banco de preguntas.
3. **[03_BACKEND_Y_FRONTEND.md](03_BACKEND_Y_FRONTEND.md)** — rutas,
   controladores, middleware, vistas Blade y el sistema de diseño
   `acode-*` con los valores exactos.
4. **[04_DECISIONES_ADR.md](04_DECISIONES_ADR.md)** y carpeta **[adrs/](adrs/README.md)** —
   registro completo de las 21 decisiones de arquitectura (ADR-001 al ADR-021),
   desviaciones justificadas respecto a los manuales, topología de subagentes
   y refactorización de seguridad, repositorios y paridad móvil.
5. **[05_MANUAL_SUBAGENTES_Y_FLUJO.md](05_MANUAL_SUBAGENTES_Y_FLUJO.md)** —
   arquitectura en 3 anillos de los 8 subagentes especializados, matriz de
   dominios de archivos y flujo operativo de auditoría, refactorización y QA.

Para levantar el proyecto localmente (con o sin Docker), credenciales de
demo y flujo sugerido de demostración, ver el `README.md` en la raíz del
repo.

Si algo de aquí ya no cuadra con el código, el código manda — actualiza
el documento correspondiente en el mismo cambio que lo desactualiza.
