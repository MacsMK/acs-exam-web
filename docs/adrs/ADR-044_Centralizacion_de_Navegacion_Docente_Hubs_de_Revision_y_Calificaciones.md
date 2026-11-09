# ADR-044 — Centralización de Navegación Docente: Hubs de Revisión y Calificaciones

**Estado:** Aceptado  
**Fecha:** 2026-11-09  
**Autores:** Equipo de Frontend y Backend (A-Code Solutions)  
**Requerimientos Relacionados:** RW-11, RW-28..31, RF-IAR-002, RF-CAL-001..005, MPW-V2 §7.1, §8.10  

---

## Contexto

En el layout maestro docente (`resources/views/layouts/app.blade.php`), los ítems del menú lateral *"Revisión"* y *"Calificaciones"* enlazaban a un closure comodín `teacher.coming-soon`, provocando una experiencia fragmentada y la falsa percepción de funcionalidades incompletas, a pesar de que el backend ya contaba con controladores plenamente operativos:
- `TeacherReviewController` para recotejar intentos de examen individuales.
- `EvaluationPeriodController` para configurar periodos ponderados y capturar sábanas de notas.

La causa de la desconexión era la ausencia de **páginas índice concentradoras (Hubs)** a nivel docente: las rutas existentes requerían obligatoriamente un parámetro de entidad específico en la URL (`exams/{exam}/revision` y `groups/{group}/periodos`).

## Decisión

1. **Creación del Hub Global de Revisiones (`GET /docente/revision`):** Se implementa el método `TeacherReviewController::globalIndex()`, el cual consulta todos los exámenes creados por el docente con conteo de intentos entregados y cantidad de reactivos de IA pendientes de recoteje (`pending_ai_count > 0`), enrutando al profesor directamente al examen seleccionado.
2. **Creación del Hub Global de Calificaciones (`GET /docente/calificaciones`):** Se implementa el método `EvaluationPeriodController::hub()`, que presenta tarjetas modulares de todos los grupos del maestro, detallando alumnos inscritos, periodos evaluativos dados de alta y enlaces de acceso directo a la sábana de notas.
3. **Erradicación de Enlaces Huérfanos:** Se reasignan los enlaces del menú lateral hacia estas dos rutas canónicas.

## Consecuencias

- **Positivas:**
  - Experiencia de usuario coherente y navegación en dos clics hacia cualquier intento pendiente de revisión o sábana de notas.
  - Se erradican dos marcadores `coming-soon` del panel del docente.
- **Negativas:**
  - Requiere mantener eager loading optimizado en ambas rutas índice para prevenir consultas N+1 en listados con múltiples grupos o exámenes.
