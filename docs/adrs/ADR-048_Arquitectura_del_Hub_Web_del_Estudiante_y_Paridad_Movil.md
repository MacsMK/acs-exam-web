# ADR-048 — Arquitectura del Hub Web del Estudiante y Paridad 1:1 con Móvil

**Estado:** Aceptado  
**Fecha:** 2026-11-09  
**Autores:** Equipo de Arquitectura y Normativa (A-Code Solutions)  
**Requerimientos Relacionados:** P-17, P-19, P-21, P-23, P-81, P-83, P-84, P-174, P-175, P-180, P-181, P-186, MPW-V2 §7.2, §8.10.4  

---

## Contexto

El sistema web `acs-exam-web` presentaba una severa disparidad visual y arquitectónica entre los módulos docentes y el entorno estudiantil:
1. El estudiante estaba confinado a una barra superior plana heredada de Sprint 1, excluido del sidebar institucional retráctil (`w-64 bg-acode-primary`), del header fijo con avatar e iniciales, y del pie de página institucional de dos secciones (`MPW-V2 §8.10.7`).
2. Existían servicios de dominio (`GroupEnrollmentService`, `StudentResultService`) y contratos REST para Flutter, pero el portal web carecía de controladores y vistas para:
   - Consulta de materias inscritas y abandono voluntario (`RF-GRU-010`, `RF-GRU-012`).
   - Consulta de calificaciones por periodo y promedio final (`RF-REP-007`, `RF-REP-008`).
   - Pantalla de instrucciones previas y confirmación de inicio de examen (`RF-EXA-028`, `RF-EXA-029`).

## Decisión

1. **Unificación del Layout Maestro (`resources/views/layouts/app.blade.php`):** Se suprime la bifurcación excluyente de rol y se homologa la barra lateral para docentes, administradores y estudiantes. El estudiante cuenta con 5 módulos normativos: Inicio (`home`), Mis Grupos (`groups`), Mis Exámenes (`exams`), Mis Calificaciones (`grades`) y Unirme a un grupo (`add`).
2. **Creación de `StudentGroupController`:** Provee `index()` para listar materias matriculadas activas y `leave()` para abandono voluntario con diálogo SweetAlert2 destructivo, validando que no existan exámenes en progreso (`RF-GRU-017`).
3. **Creación de `StudentGradeController`:** Provee `index()` y `show()` para consultar evaluaciones, rubros ponderados de periodos oficiales y promedios finales.
4. **Prevención de Inscripciones Duplicadas:** Se actualiza `GroupEnrollmentService::joinByFolio()` para rechazar re-inscripciones al mismo grupo con un mensaje explicativo claro (`P-19`).
5. **Filtrado Estricto de Grupos Cerrados (`P-186`):** Todas las consultas del estudiante filtran `is_closed = false`.

## Consecuencias

- **Positivas:**
  - Paridad visual y funcional del 100% entre docentes y estudiantes en el sistema web.
  - Autosuficiencia web completa (el alumno puede operar íntegramente sin depender de la aplicación móvil).
  - Cobertura de pruebas automatizadas certificando los requerimientos de la Lista Maestra.
- **Negativas:**
  - Incremento en la cantidad de rutas web registradas, requiriendo aislamiento estricto por middleware `role:student`.
