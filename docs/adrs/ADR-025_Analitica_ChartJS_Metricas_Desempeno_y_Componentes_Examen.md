# ADR-025 — Visualización Analítica con Chart.js 4.4 (`RF-CAL-005`), Componentes Especializados y Escapado de Directivas Blade/Alpine (`::class`)

> **Estado:** Aceptado  
> **Fecha:** 02 de Noviembre de 2026  
> **Decisores:** `frontend-ui-specialist`, `backend-laravel-specialist`, `qa-testing-engineer`  
> **Normativa de Referencia:** Manual `MPW-V2` (§7.5.1, §7.9, §8.9.1, §8.9.2, Tablas 20 y 23), `RF-CAL-005`, `RF-GRU-001`, `RF-EXA-001`

---

## 1. Contexto y Problemática
1. **Ausencia de visualización analítica:** El panel del docente mostraba únicamente tres tarjetas con conteos numéricos elementales, incumpliendo el requerimiento funcional `RF-CAL-005` y la sección `MPW-V2 §7.9` que exigen gráficos interactivos de desempeño escolar (quién contestó, porcentaje de aprobados y promedios grupales).
2. **Consultas ineficientes o N+1:** Obtener métricas de calificaciones de múltiples exámenes y grupos en el controlador podía incurrir en consultas N+1 en Eloquent si no se realizaba agregación ansiosa.
3. **Falta de componentes de dominio:** El cronómetro de examen y el visualizador de folio/QR de grupo estaban acoplados de forma monolítica en vistas específicas, impidiendo su reutilización en otros módulos.
4. **Colisión sintáctica Blade vs. Alpine.js:** Durante la integración de componentes Blade (`<x-icon>`), la sintaxis de enlace dinámico de Alpine `:class="..."` era interceptada y evaluada por el compilador de Blade en tiempo de compilación como expresiones PHP, provocando excepciones del tipo `Undefined constant "sidebarOpen"` o `"remaining"`.

---

## 2. Decisión de Arquitectura

### 2.1. Agregación de Métricas en Backend sin N+1 ([DashboardController.php](file:///home/laambws/Escritorio/acs-exam-web/app/Http/Controllers/DashboardController.php))
Se refactorizó el controlador de dashboard bajo el principio de responsabilidad única (*Skinny Controller*):
- Se estructuraron dos métodos privados aislados: `teacherDashboard()` y `studentDashboard()`.
- **Métricas Docentes Calculadas:**
  - `passedCount`: Intentos de examen entregados (`status = 'submitted'`) con calificación $\ge 6.0$.
  - `failedCount`: Intentos de examen entregados con calificación $< 6.0$.
  - `averageScore`: Promedio global redondeado a 1 decimal mediante `selectRaw('COALESCE(ROUND(AVG(final_score), 1), 0.0) as avg_score')`.
  - `recentExams`: Últimos 5 exámenes del docente con precarga ansiosa `withCount(['examQuestions', 'studentAttempts'])`.
  - `recentGroups`: Últimos 4 grupos del docente con `withCount('students')`.
- **Métricas del Alumno:**
  - `enrolledGroups`: Grupos del alumno con su docente precargado (`with('teacher')`).
  - `pendingExams`: Exámenes publicados de los grupos del alumno que aún no han sido finalizados, evitando consultas N+1.
  - `recentAttempts`: Últimos 5 intentos con docente y examen precargados.

### 2.2. Componente de Analítica Gráfica ([components/analytics/group-chart.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/analytics/group-chart.blade.php))
- Se implementó un componente encapsulado en Alpine.js que monta la librería **Chart.js 4.4** (`chart.umd.min.js`) sobre un elemento `<canvas x-ref="canvas">`.
- Gráfica de barras estilizada con la paleta de A-Code Solutions: Verde Acento `#16A6A1` para aprobados y Rojo Peligro `#DC2626` para reprobados, esquinas de barra redondeadas (`borderRadius: 6`) y tooltips corporativos.
- **Degradación Elegante:** Si el docente aún no cuenta con intentos evaluados, el componente despliega un estado informativo limpio sin romper el layout ni lanzar excepciones de renderizado en JavaScript.

### 2.3. Cronómetro Contrarreloj Reactivo ([components/exam/timer.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/exam/timer.blade.php))
- Componente reactivo Alpine.js de cuenta regresiva decreciente cada 1000 ms (`MPW-V2 §7.5.1, Tabla 20`).
- Transición dinámica de estilos visuales: a $\le 10$ segundos conmuta a texto en Rojo Peligro (`text-acode-danger`) con animación pulsante (`animate-pulse`) y borde de advertencia.
- Al expirar el tiempo, limpia el intervalo con `clearInterval()`, remueve los atributos `[required]` del formulario para sortear bloqueos de validación del navegador y despliega una alerta SweetAlert2 antes de disparar el envío automático.

### 2.4. Visualizador Modal de Folio y Código QR ([components/group/qr-modal.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/group/qr-modal.blade.php))
- Componente modal para matrícula escolar (`RF-GRU-001, MPW-V2 §8.9.2`).
- Despliega el folio alfanumérico en `font-mono 2xl` e imagen QR de alta resolución.
- Incorpora botón **"Copiar folio"** que ejecuta `navigator.clipboard.writeText()` y notifica con un Toast verde en SweetAlert2.

### 2.5. Convención Normativa de Sintaxis: Escapado de Directivas Alpine en Blade (`::class`)
Para solucionar de manera definitiva la colisión entre el analizador de Blade y las directivas de Alpine.js en componentes Blade (`<x-...>`):
- **Regla Normativa:** Queda establecido que todo binding reactivo de Alpine.js aplicado sobre un componente Blade debe usar la sintaxis de escape **`::class="..."`** o **`x-bind:class="..."`**, por ejemplo:
  ```blade
  {{-- Sintaxis Correcta y Aprobada --}}
  <x-icon name="chevronDown" class="w-4 h-4 transform" ::class="sidebarOpen ? '-rotate-90' : 'rotate-90'" />
  <x-icon name="clock" class="w-4 h-4" ::class="remaining <= 10 ? 'text-acode-danger' : 'text-acode-primary'" />
  ```
- La sintaxis de dos puntos simple (`:`) se reserva de manera exclusiva para propiedades que deben ser evaluadas por PHP en el servidor (ej. `:href="route(...)"`, `:variant="$var"`).

---

## 3. Consecuencias
- **Positivas:**
  - Cumplimiento pleno de los requerimientos analíticos `RF-CAL-005` y evaluativos `RF-EXA-001`.
  - Cero consultas N+1 en la generación de métricas de panel.
  - Exámenes contrarreloj robustos y tolerantes a reactivos en blanco al expirar el tiempo.
  - Eliminación definitiva de advertencias y errores de compilación de plantillas Blade.
- **Negativas/Compromisos:**
  - Requiere la carga de Chart.js en las vistas de panel docente (mitigado por la inclusión asíncrona por CDN).

---

## 4. Trazabilidad y Requerimientos Asociados
- **Requerimientos:** `RF-CAL-005`, `RF-GRU-001`, `RF-EXA-001`, `RF-EXA-002`, `RF-EXA-024`.
- **Manuales:** `MPW-V2` (§7.5.1, §7.9, §8.9.1, §8.9.2, Tablas 20 y 23), `MC-V2` (§6.1).
- **Pruebas de Certificación:** `tests/Feature/FrontendViewRenderingTest.php`.
