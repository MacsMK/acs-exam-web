# ADR-043 — Proctoring Pasivo y Sensores Anti-Trampa frente a Biometría de Cámara (LFPDPPP)

**Estado:** Aceptado  
**Fecha:** 2026-11-09  
**Autores:** Equipo de Arquitectura y Seguridad (A-Code Solutions)  
**Requerimientos Relacionados:** RW-24, RW-25, RNF-SEG-001, RNF-PRI-001, MC-V2, MPW-V2 §7.10  

---

## Contexto

Durante la reunión de levantamiento de requerimientos (`requerimientos_evaluador_examenes.md`, Sección 2.5 y Punto 5 de puntos por confirmar), el cliente planteó como alternativa anti-suplantación *"cámara abierta, detección de desvío de mirada, aviso y corte del examen"*.

No obstante, al contrastar esta propuesta contra el marco legal mexicano (Ley Federal de Protección de Datos Personales en Posesión de los Particulares — LFPDPPP) y los requerimientos no funcionales de la plataforma:
1. **Riesgo Legal y Biometría Sensible (LFPDPPP Arts. 8, 15 y 16):** La captura y análisis continuo de video facial de estudiantes menores o mayores de edad implica tratamiento de datos biométricos sensibles que exigen consentimiento expreso por escrito y medidas de seguridad desproporcionadas.
2. **Factibilidad Técnica y Conectividad Escolar:** El streaming continuo de video en tiempo real desde 40 o más dispositivos simultáneos en laboratorios escolares satura el ancho de banda y genera bloqueos espurios.
3. **Falsos Positivos y Ergonomía:** El seguimiento ocular ("eye-tracking") en navegadores produce una alta tasa de falsos positivos cuando el estudiante mira el teclado, parpadea o consulta reactivos de programación en pantallas pequeñas.

El usuario confirmó expresamente en la revisión del plan: *"la parte de la camara me gustaria dejarla al final como una opcion posible, aun no la quiero desarrollar"*.

## Decisión

1. **Pospuesta de la Biometría de Cámara Web:** Se excluye de la fase actual el desarrollo de reconocimiento facial y seguimiento ocular continuo por cámara. La cámara web se mantendrá como parámetro booleano configurable (`require_webcam = false`) para fases posteriores.
2. **Adopción de Proctoring Pasivo No Invasivo:** El sistema de supervisión anti-trampa se fundamenta en cuatro capas técnicas de integridad:
   - **Sensor de Foco de Ventana (`visibilitychange` y `window.blur`):** Ante cualquier abandono de la pestaña o cambio de aplicación, se emite un incidente tipificado (`tab_switch`, `app_left`) con registro de marca de tiempo (`ADR-020`).
   - **Bloqueo Estricto de Portapapeles:** Prevención de pegado externo en áreas de respuesta mediante directivas `@paste.prevent`.
   - **Temporizador Server-Side con Ventana de Tolerancia:** El reloj del examen reside inmutablemente en el servidor (`ADR-029`), impidiendo la manipulación del reloj del cliente.
   - **Monitoreo en Tiempo Real con Laravel Reverb (`ADR-042`):** El docente supervisa en vivo desde su panel qué alumnos presentan la prueba y recibe notificaciones instantáneas ante incidentes acumulados.

## Consecuencias

- **Positivas:**
  - Cumplimiento pleno de la LFPDPPP y blindaje en auditorías de protección de datos al no procesar biometría innecesaria.
  - Consumo mínimo de ancho de banda y máxima fluidez en redes móviles y escolares.
  - Cero falsos positivos que interrumpan exámenes de alumnos por simples movimientos de cabeza.
- **Negativas:**
  - El sistema no detecta si una tercera persona se encuentra físicamente al lado del estudiante fuera del encuadre del dispositivo; la supervisión recae en el entorno escolar y en la restricción contrarreloj por pregunta.
