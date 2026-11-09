# ADR-046 — Homologación Integral de Contratos API v1 en Flutter y Mapeo de Excepciones HTTP 409

**Estado:** Aceptado  
**Fecha:** 2026-11-09  
**Autores:** Equipo de API y Desarrollo Móvil Flutter (A-Code Solutions)  
**Requerimientos Relacionados:** RM-01..13, RF-AUT-006, RF-GRU-012, RF-REP-006, MPM-V4 §6.7, Tabla 13  

---

## Contexto

La auditoría de contratos entre `acs-exam-web` y `acs-exam-mobile` identificó dos brechas técnicas críticas:
1. **Omisión de Endpoints en el Catálogo de Dart:** `ApiEndpoints` en Flutter carecía de 10 rutas presentes en el backend (reenvío de 2FA, consulta pública previa de aviso de privacidad, salida voluntaria de grupos escolares y consulta de desglose de reactivos).
2. **Degradación Crítica del Código HTTP 409 (`Conflict`):** El backend Laravel responde `409 Conflict` en situaciones de negocio de examen (por ejemplo: la pregunta activa cambió, el intento concluyó o un alumno intenta salir de un grupo con un examen activo). Al no existir `ConflictException` en `acs_exception.dart`, `AcsExceptionMapper` degradaba el error a `UnknownException`, ocultando el mensaje específico de Laravel y mostrando en Flutter el texto genérico *"Ocurrió un problema inesperado"*.

## Decisión

1. **Ampliación de `ApiEndpoints.dart`:** Registrar formalmente las constantes:
   - `AUTH_REGISTER_STUDENT`, `AUTH_TWO_FACTOR_RESEND`, `AUTH_ME`.
   - `PRIVACY_NOTICE_CURRENT`.
   - `groupLeave(groupId)`.
   - `gradeShow(attemptId)`, `gradeReview(attemptId)`.
2. **Incorporación de `ConflictException` en la Jerarquía Sellada:**
   - Crear `ConflictException extends AcsException` con código `409` en `lib/core/errors/acs_exception.dart`.
   - Mapear el caso `409` en `AcsExceptionMapper` extrayendo el mensaje devuelto por el servidor (`_serverMessage(error)`).
3. **Componente de Estado Vacío en Móvil (`RM-09`):** Crear `AcsEmptyStateWidget` para presentar una experiencia amigable cuando el alumno no pertenece a ningún grupo escolar, reemplazando la pantalla provisional de *"En construcción"*.
4. **Variantes Extensibles de Botón en Flutter:** Ampliar `AcsButtonVariant` para incluir `accent` y `warning`, garantizando paridad con `<x-button>` de Blade.

## Consecuencias

- **Positivas:**
  - Los errores de conflicto de reglas de negocio en la aplicación móvil muestran el motivo exacto al estudiante en vez de fallas opacas.
  - El cliente Flutter cuenta con el catálogo íntegro de rutas de la API v1 sincronizado 1:1.
- **Negativas:**
  - Requiere recompilar los cubits y pantallas que consuman las nuevas variantes para aprovechar los mensajes mejorados.
