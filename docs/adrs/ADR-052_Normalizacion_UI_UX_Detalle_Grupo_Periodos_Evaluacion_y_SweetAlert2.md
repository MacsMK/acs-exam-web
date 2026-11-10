# ADR-052 — Normalización UI/UX del Detalle de Grupo, Periodos de Evaluación y Adopción de SweetAlert2

- **Fecha:** 10 de noviembre de 2026
- **Estado:** Aceptado
- **Autores:** Equipo 4 (A-Code Solutions)
- **Dictamen Normativo:** `Dictamen B` (Auditoría Normativa `normative-compliance-auditor`)
- **Afecta a:** `resources/views/groups/show.blade.php`, `resources/views/evaluation-periods/index.blade.php`, `app/Models/EvaluationPeriod.php`, `app/Http/Controllers/EvaluationPeriodController.php`, `MPW-V2 §5.2.2`, `§7`, `§8`, `RF-GRU-001`, `RF-GRU-018`, `RF-CAL-001..005`, `RW-28..36`

---

## 1. Contexto y Problema

Las vistas de detalle de grupo (`groups/show.blade.php`) y gestión de periodos de evaluación (`evaluation-periods/index.blade.php`) presentaban las siguientes inconsistencias arquitectónicas y de diseño:
1. **Ocultamiento de Folio y Ausencia de Métricas:** En `groups.show`, el folio alfanumérico no estaba expuesto en la superficie principal, y la vista carecía de tarjetas KPI contextuales sobre estudiantes, exámenes, entregas y periodos.
2. **Duplicación de Modal QR y CDN Externo Inseguro:** Se inyectaba por CDN un script externo `qrcode.min.js` y más de 100 líneas de vanilla JS y marcado manual, ignorando el componente atómico oficial `<x-group.qr-modal>`.
3. **Acciones Destructivas Desubicadas:** El botón *"Cerrar grupo"* flotaba huérfano al final de la página con 148 líneas de modal HTML manual duplicado, en vez de estar integrado en la barra de acciones superior con confirmación SweetAlert2 (`ADR-023`).
4. **Lógica Algorítmica en Blade:** Se ejecutaban 42 líneas de regex y parseo matemático de fórmulas en la plantilla Blade en lugar de encapsularse en accessors del modelo `EvaluationPeriod`.
5. **Iconos Vectoriales Rotos:** En `evaluation-periods/index.blade.php` se utilizaban alias inexistentes (`plus`, `calendar`, `exclamationCircle`, `documentText`) que caían en el `@default` del componente `<x-icon>`, dibujando círculos mudos.
6. **Omisión de Guarda de Grupo Cerrado:** Se permitía abrir el formulario de periodos aun cuando el aula escolar estuviese en estado cerrado (`$group->is_closed`).

---

## 2. Decisión de Arquitectura

1. **Reestructuración Espacial en 5 Niveles Jerárquicos:**
   - **Nivel 1:** Hero Institucional con migas de pan limpias, chip del folio alfanumérico visible con botón de copiado rápido (`AcodeAlert.toast`), badge semántico de estado (`<x-badge>`), y botones canónicos de acción directa (*"Ver código QR"*, *"Periodos"* y *"Cerrar grupo"* / *"Reabrir grupo"*).
   - **Nivel 2:** Barra de 4 KPIs Contextuales del grupo (Alumnos inscritos, Exámenes asignados, Entregas registradas y Periodos escolares).
   - **Nivel 3:** Pestañas rediseñadas con contadores dinámicos y transiciones fluidas a 60 FPS (`ADR-047`) sin saltos de altura (*Layout Shifts*), sincronizadas con `window.location.hash`.
   - **Nivel 4:** Los 4 apartados (Estudiantes, Exámenes, Calificaciones y Periodos) migrados al 100% al UI Kit A-Code (`<x-card>`, `<x-table>`, `<x-empty-state>`, `<x-button>`), incorporando los **avatares vectoriales de iniciales** (`ADR-049`) en el censo de alumnos y el formateo de calificaciones.
   - **Nivel 5:** Reutilización exclusiva del componente institucional `<x-group.qr-modal>`, erradicando scripts de CDN externos.

2. **Encapsulamiento de Parseo de Ponderaciones en el Modelo:**  
   Se definen los accessors `parsed_weights` e `is_valid_formula` en el modelo Eloquent `EvaluationPeriod`, erradicando por completo el bloque `@php` algorítmico de la vista Blade y permitiendo la visualización clara mediante chips de porcentajes por rubro (*Exámenes*, *Tareas*, *Proyectos*, *Otros*).

3. **Homologación de SweetAlert2 (`alerts.js`) y Catálogo de 18 Verbos:**  
   - Se incluye `sweetalert2` y `alerts.js` en el layout maestro `resources/views/layouts/app.blade.php`.
   - Se formaliza el verbo canónico simétrico **«Reabrir grupo»** (o **«Reactivar grupo»**) para la reapertura semestral controlada vía `AcodeAlert.confirm`.
   - Se corrigen los textos no oficiales: *"Crear examen"*, *"Crear periodo"*, *"Abrir calificaciones"*, *"Ver código QR"*.

4. **Corrección de Iconos e Inmutabilidad de Grupo Cerrado:**  
   - Se homologan los iconos a los identificadores estrictos de la Tabla 31: `add`, `clock`, `warning` y `document`.
   - Se incorpora guarda en `EvaluationPeriodController::store()` que rechaza con HTTP 403 Forbidden la creación de parciales si el grupo está cerrado.

---

## 3. Consecuencias

### Positivas
- **Cero Dependencias Externas Inseguras:** Erradicación del CDN de `qrcode.min.js`.
- **Cero Código Muerto y Duplicado:** Eliminación de más de 200 líneas de modales manuales y scripts vanilla.
- **Rendimiento a 60 FPS:** Transiciones compuestas fluidas en pestañas Alpine.js sin parpadeos.
- **Consistencia Visual Absoluta:** Integración total con el UI Kit A-Code, avatares vectoriales LFPDPPP y confirmaciones seguras con SweetAlert2.

### Negativas / Mitigaciones
- Ninguna. Todos los cambios preservan al 100% los contratos existentes y las aserciones de la suite de pruebas automatizadas.
