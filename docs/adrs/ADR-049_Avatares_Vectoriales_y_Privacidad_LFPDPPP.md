# ADR-049 — Avatares Vectoriales Basados en Iniciales y Protección LFPDPPP

**Estado:** Aceptado  
**Fecha:** 2026-11-09  
**Autores:** Equipo de Seguridad y Calidad (A-Code Solutions)  
**Requerimientos Relacionados:** P-08, P-09, RF-AUT-008, RF-AUT-009, RNF-PRI-001, MC-V2 §5.1  

---

## Contexto

El requerimiento `RF-AUT-008` contempla la carga opcional de fotografía del estudiante y `RF-AUT-009` exige mostrar una imagen predeterminada cuando el usuario no proporcione fotografía.

El almacenamiento y procesamiento de fotografías biométricas para miles de estudiantes en un entorno multi-tenant escolar introduce riesgos y obligaciones estrictas bajo la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP), así como costos de infraestructura en almacenamiento y transferencia de datos.

## Decisión

1. **Adopción de Avatares Vectoriales SVG/CSS de Iniciales:** Se establece como imagen predeterminada institucional un círculo de 36x36 px con fondo Azul Marino Profundo (`bg-acode-primary`, `#142B4A`) y la inicial en mayúscula del nombre del usuario en blanco puro (`text-white font-bold`).
2. **Cumplimiento de Accesibilidad WCAG 2.1 AA:** El contraste entre `#142B4A` y blanco supera el ratio de 12:1 (muy superior al estándar mínimo de 4.5:1).
3. **Cero Dependencia de Terceros:** No se utilizan servicios externos como Gravatar para evitar rastreo y filtración de correos electrónicos.

## Consecuencias

- **Positivas:**
  - Cero carga y riesgo de exposición de datos biométricos de estudiantes.
  - Cero consumo de ancho de banda o almacenamiento S3 para avatares.
  - Consistencia estética absoluta con el UI Kit A-Code.
- **Negativas:**
  - La personalización mediante foto propia queda postergada para etapas futuras con almacenamiento cifrado.
