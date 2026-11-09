# ADR-045 — Arquitectura SaaS: Flujo WhatsApp y Panel de Superadministrador (RW-05)

**Estado:** Aceptado  
**Fecha:** 2026-11-09  
**Autores:** Equipo de Arquitectura e Infraestructura (A-Code Solutions)  
**Requerimientos Relacionados:** RW-01..05, RF-SUS-001..015, RF-ADM-001..018, MPW-V2 §6.9, Tabla 15  

---

## Contexto

El sistema está concebido como una plataforma en renta (SaaS) donde el docente contrata paquetes con cotas específicas (grupos, preguntas en banco, alumnos y exámenes concurrentes). El requerimiento `RW-05` estipula que el Súper Administrador (dueños de la plataforma) debe *"ver quién pagó y quién no, y suspender cuentas"*.

Para una primera versión comercial ágil y con mínimo riesgo operativo (evitando la complejidad y costos fijos de pasarelas como Stripe o MercadoPago con comisiones bancarias y conciliación de webhooks):
- El usuario consultó: *"¿crees que sea una buena opción que para esta primera versión se maneje por contacto en WhatsApp y que el administrador controle la activación del plan o hay alguna otra opción?"*
- Y confirmó la adopción de una interfaz administrativa dedicada en `/admin/docentes`.

## Decisión

1. **Panel de Autoservicio de Cuotas Docente (`GET /docente/suscripcion`):**
   - El docente visualiza en tiempo real el consumo de sus cotas frente al plan activo (grupos, banco de preguntas y exámenes).
   - Para contratar un plan de pago o ampliar sus límites, la interfaz ofrece un botón de **Atención Comercial por WhatsApp** con mensaje preformateado que incluye el nombre del profesor, correo registrado, ID de usuario y paquete solicitado.
2. **Panel de Superadministración (`GET /admin/docentes`):**
   - Rutas protegidas bajo el middleware `role:super_admin` (`AdminTeacherController`).
   - Permite al superadministrador visualizar el padrón de docentes registrados, su estado de cuenta (`Activo` o `Suspendido`) y el paquete actualmente asignado.
   - **Suspensión de cuentas (`RW-05`):** Botón para alternar `is_active` con auditoría en `acs_audit_logs`.
   - **Activación de planes (`RW-05`):** Formulario para registrar la acreditación de pago (transferencia/SPEI/depósito) y asignar o renovar el paquete en `acs_teacher_subscriptions` con vigencia mensual.

## Consecuencias

- **Positivas:**
  - Cumplimiento estricto y verificable de `RW-05` y `RW-06` mediante interfaz gráfica intuitiva para el superadministrador.
  - Flujo de cobro flexible y adaptado a las dinámicas de pago de docentes en México (transferencias SPEI o pagos en ventanilla sin fricción de tarjetas de crédito).
  - Cero dependencias externas complejas ni llaves secretas de pasarelas de pago para la entrega del proyecto.
- **Negativas:**
  - La acreditación de pago requiere un proceso humano de verificación por parte del superadministrador antes de activar el plan en el panel.
