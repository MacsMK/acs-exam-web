# Catálogo Maestro de Decisiones de Arquitectura (ADRs)

> **Proyecto:** `acs-exam-web` (A-Code Solutions)  
> **Formato Canónico:** Contexto → Decisión → Consecuencias → Trazabilidad y Módulos  
> **Normativa de Referencia:** Manual de Programación Laravel (`MPW-V2`), Manual de Calidad (`MC-V2`), Formato `DE-06_Registro_de_cambios.docx` y Catálogo de 226 Requerimientos (`Lista_maestra_requerimientos.xlsx`).

Este directorio almacena el registro histórico e incremental de decisiones arquitectónicas del sistema. Cada ADR documenta la justificación técnica detrás de una solución de diseño, sus compromisos y las alternativas descartadas para evitar discusiones redundantes y sustentar la trazabilidad de ingeniería de software.

---

## Índice General de Decisiones de Arquitectura

### Bloque I — Arquitectura Inicial y Fundamentos (Sprint 1)
| Código | Título | Estado | Módulos Impactados | Resumen |
| :---: | :--- | :---: | :--- | :--- |
| **[ADR-001](04_DECISIONES_ADR.md#adr-001--capa-de-servicios-compartida-entre-panel-web-y-api)** | Capa de servicios compartida entre panel web y API | Aceptado | `app/Services/` | Centralización de lógica de negocio entre Web y API v1. |
| **[ADR-002](04_DECISIONES_ADR.md#adr-002--evaluador-de-ia-detras-de-un-contrato-aievaluatorcontract)** | Evaluador de IA detrás de un contrato (`AiEvaluatorContract`) | Aceptado | `app/Contracts/`, `app/Services/` | Driver desacoplado (`MockAiEvaluator`) para portabilidad offline sin créditos. |
| **[ADR-003](04_DECISIONES_ADR.md#adr-003--parent_question_id-vs-paráfrasis-acs_question_paraphrases)** | `parent_question_id` vs. paráfrasis (`acs_question_paraphrases`) | Aceptado | `app/Models/Question.php`, `database/` | Separación entre versionado docente de reactivos y variantes anti-copia. |
| **[ADR-004](04_DECISIONES_ADR.md#adr-004--polimorfismo-questionable-para-reactivos)** | Polimorfismo `questionable` para reactivos (`morphTo`) | Aceptado | `app/Models/`, `database/migrations/` | 4 tablas especializadas de opciones en lugar de tabla monolítica plana. |
| **[ADR-005](04_DECISIONES_ADR.md#adr-005--adopción-de-laravel-13-y-php-83-por-cves-de-seguridad)** | Adopción de Laravel 13 y PHP 8.3 por CVEs de seguridad | Aceptado | `composer.json`, `Dockerfile` | Salto de versión respecto a MPW-V2 para eliminar vulnerabilidades de dependencias. |
| **[ADR-006](04_DECISIONES_ADR.md#adr-006--2fa-por-correo-y-diferimiento-de-laravel-fortify)** | 2FA por correo y diferimiento de Laravel Fortify | Aceptado | `app/Services/AuthService.php` | OTP de 6 dígitos por correo electrónico sin requerir TOTP app externa. |
| **[ADR-007](04_DECISIONES_ADR.md#adr-007--laravel-reverb-como-infraestructura-de-sockets-sin-uso-funcional-en-demo)** | Laravel Reverb como infraestructura de sockets (diferido) | Aceptado | `config/reverb.php`, `routes/channels.php` | Infraestructura instalada para tiempo real, diferida a favor de polling/REST. |
| **[ADR-008](04_DECISIONES_ADR.md#adr-008--colas-en-modo-sync-y-sesión-file-para-portabilidad-de-demo)** | Colas en modo `sync` y sesión `file` para portabilidad | Aceptado | `.env`, `config/queue.php` | Eliminación de dependencias de Redis o workers en segundo plano para demo. |
| **[ADR-009](04_DECISIONES_ADR.md#adr-009--contrato-api-v1-uniforme-con-appsupportapiresponse)** | Contrato API v1 uniforme con `App\Support\ApiResponse` | Aceptado | `app/Support/ApiResponse.php` | Sobre estándar JSON `{status, code, message, data, errors}` y renderer global. |
| **[ADR-010](04_DECISIONES_ADR.md#adr-010--flujo-2fa-en-api-v1-y-mitigación-de-colisión-de-otp)** | Flujo 2FA en API v1 y mitigación de colisión de OTP | Aceptado | `Api/V1/AuthController.php` | Resolución de sesión pendiente y mitigación de emparejamiento de código. |
| **[ADR-011](04_DECISIONES_ADR.md#adr-011--sorteo-de-preguntas-por-estudiante-y-persistencia-en-acs_student_attempts)** | Sorteo de preguntas por estudiante y persistencia en intento | Aceptado | `ExamAssemblyService.php` | Congelamiento de secuencia y paráfrasis para garantizar determinismo evaluativo. |
| **[ADR-012](04_DECISIONES_ADR.md#adr-012--métricas-docentes-en-tiempo-real-sin-sobrecarga-de-base-de-datos)** | Métricas docentes sin sobrecarga de base de datos | Aceptado | `ExamController.php` | Agregaciones estadísticas optimizadas sobre intentos y apartados. |
| **[ADR-013](04_DECISIONES_ADR.md#adr-013--aislamiento-multi-docente-a-nivel-aplicación-sin-multi-tenant-de-bd)** | Aislamiento multi-docente a nivel aplicación (Logical Tenancy) | Aceptado | `app/Models/Scopes/` | Filtrado por `teacher_id` sin particionamiento físico de base de datos. |
| **[ADR-014](04_DECISIONES_ADR.md#adr-014--generación-de-folio-único-de-grupo-con-cota-máxima-de-reintentos)** | Generación de folio único de grupo con cota máxima | Aceptado | `app/Models/Group.php` | Algoritmo determinista de 8 caracteres con límite de 100 iteraciones (MPW-V2 §5.2.1). |

---

### Bloque II — Auditoría Normativa, Arquitectura y Refactorización Integral (Sprint 2 / Auditoría V2)
| Código | Título | Estado | Módulos Impactados | Resumen |
| :---: | :--- | :---: | :--- | :--- |
| **[ADR-015](ADR-015_Topologia_Multiagente_MSA_V1.md)** | Topología de 8 Subagentes Especializados en 3 Anillos (`MSA-V1`) | **Aceptado** | `.agents/`, `docs/`, `AGENTS.md` | Separación estricta entre auditores (solo lectura), ingenieros (escritura por dominio) y QA. |
| **[ADR-016](ADR-016_Capa_Repositorios_Eloquent_y_Eager_Loading.md)** | Implementación de Capa de Repositorios y Erradicación de N+1 | **Aceptado** | `app/Repositories/`, `AppServiceProvider.php` | Contratos e implementaciones Eloquent; activación de `Model::preventLazyLoading()`. |
| **[ADR-017](ADR-017_Erradicacion_Validaciones_Inline_FormRequests_Estandar.md)** | Erradicación de `$request->validate()` Inline y Adopción de Form Requests | **Aceptado** | `app/Http/Requests/`, `Controllers/` | 16 FormRequests con mensajes canónicos en español (MPW-V2 Tabla 17) y corrección de IDOR. |
| **[ADR-018](ADR-018_Calificacion_Proporcional_Matching_y_Omision_IA.md)** | Calificación Proporcional en Matching (`RF-EVA-002`) y Omisión de IA (`RF-EVA-008`) | **Aceptado** | `app/Services/AttemptService.php` | Puntaje fraccional en pares y omisión de consumo de IA en reactivos abiertos en blanco. |
| **[ADR-019](ADR-019_Validacion_Server_Side_Tiempo_Ventana_y_Estado_Intentos.md)** | Validación Server-Side de Tiempo, Ventana de Examen y Estado de Intentos | **Aceptado** | `AttemptService.php`, `Controllers/Api/V1/` | Bloqueo estricto con HTTP 409 y 403; blindaje contra respuestas en intentos cancelados. |
| **[ADR-020](ADR-020_Sistema_Diseno_Blade_Layout_Exam_Runner_y_Anti_Trampa.md)** | Sistema de Diseño Blade, Layout `exam-runner` y Sensores Anti-Trampa | **Aceptado** | `resources/views/**`, `resources/css/` | Layout Distraction-Free, componentes `<x-...>`, `@forelse`, `@paste.prevent` y 18 verbos oficiales. |
| **[ADR-021](ADR-021_Paridad_Contratos_API_v1_Preservacion_Decimales_y_Mobile.md)** | Paridad de Contratos API v1: Preservación de Decimales JSON y Cliente Flutter | **Aceptado** | `ApiResponse.php`, `Api/V1/`, `Requests/` | `JSON_PRESERVE_ZERO_FRACTION`, soporte dual `folio`/`qr_payload` y respuestas vacías `nullable`. |
