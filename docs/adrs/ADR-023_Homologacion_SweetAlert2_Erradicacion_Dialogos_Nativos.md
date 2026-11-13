# ADR-023 — Homologación de SweetAlert2 (`alerts.js`), Erradicación de Diálogos Nativos y Catálogo de 18 Verbos

> **Estado:** Aceptado  
> **Fecha:** 02 de Noviembre de 2026  
> **Decisores:** `frontend-ui-specialist`, `architecture-quality-auditor`, `security-vulnerability-auditor`  
> **Normativa de Referencia:** Manual `MPW-V2` (§7.8.2, §8.7, Tablas 20/21)

---

## 1. Contexto y Problemática
En la implementación inicial del sistema se detectaron múltiples malas prácticas de interfaz que degradaban la experiencia de usuario y contravenían los lineamientos corporativos:
1. **Uso de diálogos síncronos y bloqueantes del navegador:** En vistas fundamentales se utilizaba JavaScript nativo para confirmaciones y captura:
   - `window.prompt(...)` en `questions/index.blade.php` para la redacción de paráfrasis anti-copia.
   - `window.confirm(...)` en `groups/show.blade.php` para cerrar grupos escolares, en `exams/assemble.blade.php` para publicar exámenes y en `exams/take-exam.blade.php` para finalizar la evaluación contrarreloj.
2. **Violación normativa de `MPW-V2 §8.7`:** El manual estipula expresamente la prohibición de alertas nativas del navegador y la homologación mediante la librería **SweetAlert2** con la paleta de colores institucional.
3. **Disparidad en microcopy de acciones:** Botones y enlaces presentaban etiquetas dispersas, imperativos no estandarizados o frases ambiguas que no se apegaban a la lista de los 18 verbos oficiales de la Tabla 20 (Tabla 21 en versiones consolidadas).

---

## 2. Decisión de Arquitectura

### 2.1. Helper Centralizado de Notificaciones ([public/js/alerts.js](file:///home/laambws/Escritorio/acs-exam-web/public/js/alerts.js))
Se creó un controlador JavaScript desacoplado y reutilizable que encapsula SweetAlert2 v11 (`sweetalert2@11` por CDN), sobreescribiendo sus estilos por defecto con la paleta oficial de A-Code Solutions:
- **Alerta de Éxito (`AcodeAlert.success`):** Icono verde azulado (`#16A6A1`) y botón de confirmación en Azul Marino (`#142B4A`).
- **Alerta de Error (`AcodeAlert.error`):** Icono rojo (`#DC2626`) con botón primario de reintento.
- **Alerta de Advertencia (`AcodeAlert.warning`):** Icono ámbar (`#F4B942`) para cronómetros y avisos de foco.
- **Diálogo de Confirmación Destructiva (`AcodeAlert.confirm`):**
  - Botón primario en Rojo Alerta (`#DC2626`) para acciones destructivas (*"Eliminar reactivo"*, *"Cerrar grupo"*, *"Finalizar examen"*).
  - Botón secundario neutro (*"Cancelar"*).
  - Esquinas redondeadas institucionales `rounded-2xl` (16 px) con elevación `shadow-xl`.
- **Notificaciones Toast no intrusivas (`AcodeAlert.toast`):**
  - Despliegue en esquina superior derecha con auto-cierre programado en 3000 ms y barra de progreso.
  - Diseñado específicamente para acciones inmediatas como *"Copiar folio"* o confirmaciones de guardado en borrador.
- **Captura Modal Estilizada (`AcodeAlert.prompt`):**
  - Reemplazo directo de `window.prompt` con caja de texto estilizada en `rounded-lg`, validación de texto vacío y confirmación asíncrona.
- **Resiliencia:** Incluye degradación elegante a APIs estándar en caso de fallo de carga de CDN.

### 2.2. Erradicación Integral en Vistas Blade
Se eliminó el 100% de `confirm(...)` y `prompt(...)` en:
- `questions/index.blade.php`: La acción *"Crear paráfrasis"* dispara `AcodeAlert.prompt` y asigna el valor al campo oculto antes de remitir el formulario.
- `groups/show.blade.php`: El botón *"Cerrar grupo"* ejecuta confirmación con SweetAlert2 en rojo antes de enviar el cierre semestral.
- `exams/assemble.blade.php`: La publicación de examen valida con SweetAlert2.
- `exams/take-exam.blade.php`: El botón *"Finalizar examen"* y el auto-envío por tiempo agotado utilizan diálogos SweetAlert2.
- `layouts/app.blade.php`: El botón *"Cerrar sesión"* en el menú de perfil valida mediante confirmación SweetAlert2.

### 2.3. Adopción Estricta del Catálogo de 18 Verbos Oficiales (`MPW-V2 Tabla 20`)
Todos los botones interactivos (`<x-button>`) y enlaces de navegación se ajustaron para emplear exclusivamente la nómina autorizada:
- **Abrir:** *"Abrir examen"*, *"Abrir reactivo"*.
- **Eliminar:** *"Eliminar reactivo"*, *"Eliminar grupo"* (con SweetAlert2 rojo obligatorio).
- **Guardar:** *"Guardar cambios"*, *"Guardar reactivo"*, *"Guardar grupo"*.
- **Crear / Agregar:** *"Crear examen"*, *"Crear reactivo"*, *"Crear grupo"*, *"Crear opción"*, *"Crear par"*.
- **Actualizar:** *"Actualizar reactivo"*.
- **Enviar:** *"Enviar respuesta"*, *"Enviar solicitud"*.
- **Editar:** *"Editar reactivo"*, *"Editar examen"*.
- **Cancelar:** *"Cancelar operación"*, *"Cancelar"*.
- **Aceptar:** *"Aceptar términos"*, *"Aceptar y continuar"*.
- **Cerrar:** *"Cerrar sesión"*, *"Cerrar grupo"*, *"Cerrar ventana"*.
- **Descargar:** *"Descargar reporte"*.
- **Reintentar:** *"Reintentar conexión"*.
- **Volver:** *"Volver al listado"*, *"Volver al inicio"*.
- **Continuar:** *"Continuar"*, *"Continuar al paso 2"*.
- **Buscar:** *"Buscar reactivo"*, *"Buscar examen"*.
- **Limpiar:** *"Limpiar filtros"*.
- **Copiar:** *"Copiar folio"* (asociado a Toast verde).
- **Ver más / Detalles:** *"Ver detalles"*.

---

## 3. Consecuencias
- **Positivas:**
  - Experiencia de usuario profesional, accesible y no bloqueante.
  - Prevención de ejecuciones accidentales en acciones destructivas mediante diálogos de advertencia claros.
  - Lenguaje y llamadas a la acción (CTA) predecibles y coherentes a lo largo de todo el sistema web.
- **Negativas/Compromisos:**
  - Dependencia de la librería externa SweetAlert2 (mitigada por la inclusión local o CDN ultraliviano con fallback).

---

## 4. Trazabilidad y Requerimientos Asociados
- **Requerimientos:** `RF-AUT-002`, `RF-GRU-001`, `RF-BAN-003`, `RF-EXA-002`, `RF-EXA-024`.
- **Manuales:** `MPW-V2` (§7.8, §8.7, Tabla 20), `MC-V2` (§5.1).
- **Pruebas de Certificación:** `tests/Feature/FrontendViewRenderingTest.php`.

---

## 5. Actualización de Alineación (Anillo 2 — Banco de Reactivos y Materias)
- Las operaciones destructivas y de confirmación masiva introducidas en el Banco de Reactivos (eliminación de reactivos, eliminación de paráfrasis, fusión de materias similares y confirmación de lotes tras previsualización de importación CSV/XLSX) mantienen la prohibición absoluta de `window.confirm()`, `window.alert()` y `window.prompt()`.
- Los errores de dominio devueltos por el backend mediante `DomainException` (por ejemplo, bloqueo de borrado lógico cuando un reactivo tiene vínculos activos o publicados, o límites de paráfrasis) se exponen vía `$errors` / mensajes flash en sesión para ser presentados al docente mediante alertas/toasts institucionales SweetAlert2 en la capa UI (`Anillo 2 Paso 3`).
