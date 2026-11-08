# ADR-036 — Exportación de Sábanas de Calificaciones a Excel (.xlsx) y Representación Normativa (`RF-REP-014`, `RF-REP-015`, `RF-CAL-021`, `RW-28`, `RW-38`)

> **Estado:** Aceptado  
> **Fecha:** 08 de Noviembre de 2026  
> **Decisores:** `normative-compliance-auditor`, `architecture-quality-auditor`, `backend-laravel-specialist`, `frontend-ui-specialist`, `qa-testing-engineer`  
> **Normativa de Referencia:** Manual `MPW-V2` (§2, §3.8.1, §6.11, §7.8 Tabla 20, §8.1.1), Manual `MC-V2`, `RF-REP-014`, `RF-REP-015`, `RF-CAL-021`, `RF-CAL-028`, `RW-28`, `RW-38`

---

## 1. Contexto y Problemática

1. **Obligatoriedad de Exportación Ofimática:** Los requerimientos `RF-REP-014` y `RW-38` exigen que el docente pueda descargar desde la interfaz web las calificaciones de sus grupos en formato Excel (.xlsx), ya sea por periodo individual o consolidado.
2. **Exigencia de Metadatos Normativos (`RF-REP-015`):** La norma ordena que el archivo generado no sea una simple tabla de datos en crudo, sino que incorpore de forma explícita e inequívoca:
   - Identificación del docente titular y su correo institucional.
   - Nombre de la asignatura, grupo y folio oficial de acceso.
   - Periodo evaluado y la fórmula matemática con sus ponderaciones porcentuales.
   - **Escala oficial de evaluación** utilizada (ej. `0–100 sin decimales` o `0–10 con decimales`).
   - **Fecha y hora exacta de emisión** referenciada a la hora local del servidor.
   - Calificación mínima aprobatoria (`60.00` o `6.00`).
3. **Prohibición de Coacción de Pendientes a Cero (`RF-CAL-021`, `RF-REP-015`):** Si un estudiante no ha completado un examen o el profesor aún no registra calificaciones manuales (tareas, proyectos, otros), el sistema debe representar el estado como texto literal **«Pendiente»** (`DataType::TYPE_STRING`), evitando que Excel o cálculos posteriores lo traten como una calificación de cero.
4. **Eficiencia y Prevención de Consultas $N+1$:** La generación de la sábana para grupos masivos no debe ejecutar consultas SQL por alumno dentro de los bucles de construcción de celdas.

---

## 2. Decisión de Arquitectura

### 2.1. Motor Ofimático Basado en `phpoffice/phpspreadsheet`
- Se incorporó la dependencia `phpoffice/phpspreadsheet` (`^5.11`) conforme a `MPW-V2 §6.11`.
- Se diseñó [`GradeSheetExportService`](file:///home/laambws/Escritorio/acs-exam-web/app/Services/GradeSheetExportService.php) siguiendo el orden canónico institucional de 16 bloques (`MPW-V2 Tabla 6`).
- El servicio entrega el archivo binario mediante `Symfony\Component\HttpFoundation\StreamedResponse` (`response()->streamDownload()`), evitando almacenar archivos temporales en disco y liberando memoria PHP tras la emisión (`Cache-Control: max-age=0`).

### 2.2. Diseño Visual con Paleta Institucional A-Code Solutions (`MC-V2`, `§8.1.1`)
- **Cabecera Principal (Fila 1):** Fondo Azul Marino Oficial `#142B4A` (`COLOR_PRIMARY`), tipografía blanca en negrita (14 pt), altura 30 pt.
- **Subcabecera Docente (Fila 2):** Fondo Azul Tecnológico `#2563A6` (`COLOR_TECH`), texto blanco negrita (10 pt).
- **Bloque de Metadatos (Filas 3 a 7):** Fondo Gris Superficie `#E9F0F5` (`COLOR_SURFACE`) con bordes institucionales `#CBD5E1`.
- **Encabezados de Columnas (Fila 9):** Fondo `#142B4A` con títulos tipados: `No.`, `ID Matrícula`, `Estudiante`, `Exámenes ([peso]%)`, `Tareas ([peso]%)`, `Proyectos ([peso]%)`, `Otros ([peso]%)`, `Calificación Final`, `Estatus Normativo`.
- **Filas Cebra:** Alternancia de color entre blanco `#FFFFFF` y gris tenue `#F8FAFC` (`COLOR_ZEBRA`).
- **Resaltado Semántico:** Colores condicionales en el estatus y nota final:
  - Verde Acento `#16A6A1` (`COLOR_ACCENT`) para notas $\ge 60.00$ (`Aprobado`).
  - Rojo Peligro `#DC2626` (`COLOR_DANGER`) para notas $< 60.00$ (`No Aprobado`).
  - Ámbar `#F4B942` (`COLOR_WARNING`) para evaluaciones incompletas (`Pendiente`).

### 2.3. Consulta Masiva Inmune a $N+1$
- Precarga del grupo, periodo y estudiantes ordenados alfabéticamente por `last_name`.
- Agregación SQL previa de los intentos reales de examen aplicados al grupo mediante:
  ```php
  $examAverages = StudentAttempt::query()
      ->whereIn('exam_id', $examIds)
      ->where('group_id', $group->id)
      ->whereNotNull('final_score')
      ->groupBy('student_id')
      ->selectRaw('student_id, AVG(final_score) as avg_score')
      ->pluck('avg_score', 'student_id');
  ```
- Mapeo $O(1)$ de calificaciones previas con `$period->studentGrades->keyBy('student_id')`.

### 2.4. Integración en UI y Catálogo de Verbos (`MPW-V2 Tabla 20`)
- En [`resources/views/evaluation-periods/gradebook.blade.php`](file:///home/laambws/Escritorio/acs-exam-web/resources/views/evaluation-periods/gradebook.blade.php), se agregó el botón con el verbo institucional #11: **«Descargar reporte Excel»**, enlazando a la ruta `groups.periods.gradebook.export`.

---

## 3. Consecuencias

### Positivas
- Cumplimiento pleno de los requerimientos `RF-REP-014`, `RF-REP-015`, `RF-CAL-021`, `RW-28` y `RW-38`.
- Exportación ofimática con fidelidad corporativa y protección IDOR (`403 Forbidden` ante intentos de acceso cruzado).
- Cobertura de pruebas automatizadas certificada en [`EvaluationPeriodGradebookTest`](file:///home/laambws/Escritorio/acs-exam-web/tests/Feature/EvaluationPeriodGradebookTest.php).
