# ADR-020 — Sistema de Diseño Blade, Layout `exam-runner` y Sensores Anti-Trampa

> **Estado:** Aceptado  
> **Fecha:** 27 de Octubre de 2026  
> **Decisores:** `frontend-ui-specialist`, `architecture-quality-auditor`  
> **Normativa de Referencia:** Manual `MPW-V2` (§3.8.2, §5.1.4, §5.2.2, §7, §8), `RF-EXA-039`, `RF-EXA-043`

---

## 1. Contexto y Problemática
El frontend web en `resources/views/**` presentaba inconsistencias estructurales y funcionales graves frente a `MPW-V2`:
1. **Falta de componentes reutilizables:** Las 20 vistas duplicaban marcado HTML para tarjetas, insignias y botones sin usar `<x-...>`.
2. **Consultas N+1 en vistas Blade:** En `questions/index.blade.php` y `exams/student-index.blade.php` se ejecutaban `$question->paraphrases()->count()` y `$s->examQuestions()->count()` dentro de bucles `@foreach`.
3. **Bug crítico en cronómetro de examen ([take-exam.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/exams/take-exam.blade.php)):** El temporizador Alpine.js ejecutaba `this.$refs.answerForm.requestSubmit()` al llegar a 0. Sin embargo, como los inputs tenían el atributo `required`, la validación HTML5 del navegador abortaba el auto-envío si el alumno dejaba el reactivo en blanco, atrapando al alumno en la pregunta expirada. Además, no se limpiaba el `setInterval()`.
4. **Distracción y anti-trampa deficiente:** El examen extendía `layouts.app`, mostrando la barra de navegación completa con enlaces externos que permitían salir del examen. El `<textarea>` de código permitía pegar soluciones de fuentes externas (`paste`).

---

## 2. Decisión de Arquitectura
1. **Catálogo de Componentes Blade Reutilizables (`resources/views/components/`):**
   - `<x-card>`: Tarjeta institucional (`bg-white rounded-xl border border-slate-200 shadow-sm p-6`).
   - `<x-badge>`: Insignia de estado semántica (`accent`, `warning`, `danger`, `tech`, `primary`, `neutral`).
   - `<x-button>`: Botón normalizado con los tokens `acode-*` sin valores arbitrarios.
   - `<x-empty-state>`: Componente visual uniforme para colecciones vacías dentro de directivas `@empty`.
2. **Estandarización de `@forelse ... @empty` y Erradicación de N+1:**
   - Se migraron todas las vistas de listas a `@forelse ... @empty`.
   - Se reemplazaron las llamadas SQL por propiedades precargadas (`$question->paraphrases_count` y `$s->exam_questions_count`).
3. **Layout Distraction-Free ([exam-runner.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/layouts/exam-runner.blade.php)):**
   - Lienzo limpio `bg-slate-50`, barra superior fija compacta `h-14 bg-acode-navy` sin enlaces de navegación externa, título del examen y cronómetro fijado a la derecha.
4. **Reparación del Cronómetro Alpine.js y Auto-Envío Seguro:**
   - Se almacena el ID del temporizador y se invoca `clearInterval(this.timerId)` al expirar.
   - Al llegar a 0 segundos, el script remueve los atributos `required` de los inputs y ejecuta `this.$refs.answerForm.submit()`, garantizando el auto-envío de la respuesta vacía sin interrupciones del navegador.
5. **Anti-Trampa en Frontend:**
   - En el reactivo de programación se agregó `@paste.prevent` y `onpaste="return false;"` (`RF-EXA-039`) para impedir el copiado de código.
   - Se añadieron sensores de pérdida de foco (`visibilitychange`) y bloqueo de navegación de historial (`history.pushState`).
6. **Catálogo Normativo de 18 Verbos Oficiales (`MPW-V2 Tabla 20`):**
   - Se eliminaron textos arbitrarios (`Salir`, `Armar`, `Unirme`, `Presentar examen`) y se homologaron a: **Cerrar sesión**, **Crear grupo**, **Crear examen**, **Iniciar examen**, **Enviar respuesta**, **Finalizar examen**, **Ver detalle**, **Editar examen**.

---

## 3. Consecuencias
- **Positivas:** Experiencia de examen fluida y a prueba de fallos de red/cronómetro; eliminación de consultas SQL en el cliente de plantillas; interfaz web alineada 100% con la identidad gráfica institucional de A-Code Solutions.
