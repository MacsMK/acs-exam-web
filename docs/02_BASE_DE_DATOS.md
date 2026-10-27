# 02 — Base de datos

> Fuente de verdad: `database/migrations/` (24 migraciones, folio
> `BD01-2026-001` a `BD01-2026-024`). Este documento es un resumen legible
> de lo que ya está migrado y corriendo — si hay diferencia, las
> migraciones mandan.

Todas las tablas usan el prefijo **`acs_`** (convención del Manual de Base
de Datos, MBD-V4) y todas tienen `timestamps()` salvo donde se indica lo
contrario. PK autoincremental (`id()`) en todas.

## 1. Usuarios y acceso (001–003)

**`acs_users`** — una sola tabla para los 3 roles (`role` enum:
`super_admin` / `teacher` / `student`), no tablas separadas por rol.

- `first_name`, `last_name`, `email` (único), `phone` (10 dígitos,
  nullable), `password` (hash), `is_active`.
- `email_verified_at`, `phone_verified_at` — verificación de contacto en
  el registro.
- `two_factor_code` (6 dígitos), `two_factor_expires_at`,
  `two_factor_verified_at` — **no** es TOTP de Fortify: es un código de un
  solo uso enviado por correo, acorde a MPM-V2 §6.6.1 (ver ADR sobre por
  qué no se usó Fortify).

**`acs_privacy_notices`** — versiones del aviso de privacidad (`version`,
`content`, `published_at`).

**`acs_privacy_acceptances`** — qué usuario aceptó qué versión y cuándo
(`user_id`, `privacy_notice_id`, `accepted_at`, `ip_address`).

## 2. Suscripciones de docente (004–005)

**`acs_subscription_packages`** — catálogo de planes (`tier` único:
`basic`/`advanced`/`pro`), con límites por plan: `max_groups`,
`max_questions`, `max_concurrent_students`, `max_active_exams`,
`ai_requests_limit` (todos nullable = sin límite), y `monthly_price`.

**`acs_teacher_subscriptions`** — qué docente tiene qué plan activo
(`teacher_id` → `acs_users`, `subscription_package_id`), con
`payment_status` (`paid`/`pending`/`overdue`/`suspended`), `starts_at`,
`expires_at`, `suspended_at`.

## 3. Grupos (006–007)

**`acs_groups`** — `teacher_id` (dueño), `name`, `subject`,
**`access_folio`** (único, 12 chars — el código que el alumno usa para
unirse), `is_closed`/`closed_at` (un grupo cerrado ya no acepta altas).

**`acs_group_student`** — tabla pivote (`group_id`, `student_id`,
`joined_at`), única por `(group_id, student_id)` — un alumno no puede
unirse dos veces al mismo grupo.

## 4. Banco de preguntas — el diseño polimórfico (008–013)

Esta es la parte menos obvia del esquema, vale la pena leerla completa.

**`acs_questions`** es la tabla raíz y **compartida** por los 4 tipos de
reactivo. No tiene columnas específicas de tipo — esas viven en una tabla
aparte por tipo, y `acs_questions` apunta a la fila correspondiente por
polimorfismo:

- `question_type` (enum: `multiple_choice` / `matching` / `open` / `code`)
- `questionable_type` + `questionable_id` → apuntan a una fila de una de
  las 4 tablas de abajo, según `question_type`. En Eloquent esto es la
  relación `morphTo()` del modelo `Question` (ver `questionable()`).
- `teacher_id`, `subject_tag` (para filtrar banco por tema),
  `title`, `stem` (enunciado), `default_points`, `default_time_seconds`.
- `parent_question_id` (auto-referencia a `acs_questions`) — **esto es
  versionado/edición del reactivo** (una edición posterior del mismo
  reactivo), *no* el mecanismo anti-copia. No confundir con lo siguiente.

Las 4 tablas de variante (cada una solo tiene las columnas que le
corresponden a su tipo, sin columnas compartidas duplicadas):

| Tabla | Columnas propias |
| --- | --- |
| `acs_multiple_choice_items` | `options_payload` (jsonb), `correct_option_index` |
| `acs_matching_items` | `left_column_payload`, `right_column_payload`, `pairs_payload` (jsonb — columna izquierda/derecha y el mapeo correcto entre ambas) |
| `acs_open_items` | `reference_answer`, `evaluation_rubric` (nullable) — es lo que compara `MockAiEvaluator` |
| `acs_code_items` | `programming_language`, `is_find_error` (si es "encuentra el error" en vez de "escribe el código"), `starter_code`, `reference_solution`, `instructions` |

**`acs_question_paraphrases`** — esta es la tabla del mecanismo
**anti-copia**: variantes de redacción del mismo `stem` canónico
(`question_id` → `acs_questions`, `stem` alterno). Es una relación
`hasMany` independiente de `parent_question_id`. La idea es que dos
alumnos contestando "la misma" pregunta (mismo `questionable_id`, misma
respuesta correcta) la vean redactada distinto, para dificultar copiarse
mirando la pantalla del compañero.

## 5. Exámenes (014–017)

**`acs_exams`** — `teacher_id`, `title`, `description`, `grading_scale`
(`0-10` / `0-100`), `randomize_question_order`, `result_visibility_mode`,
`require_webcam` (anti-fraude), `max_allowed_incidents` (cuántas veces
puede "incidentar" — ej. perder foco de la app — antes de cancelarse),
`starts_at`/`ends_at`, `share_token` (único), `is_published`.

**`acs_exam_sections`** — un examen se arma por secciones
(`exam_id`, `title`, `question_type`, `requested_quantity`,
`sort_order`) — cada sección pide "N reactivos de tipo X", y
`ExamAssemblyService` sortea del banco para llenarla (ver
`03_BACKEND_Y_FRONTEND.md`).

**`acs_exam_group`** — pivote: a qué grupos está publicado el examen
(único por `exam_id`+`group_id`).

**`acs_exam_question`** — los reactivos concretos que quedaron
sorteados/asignados a cada sección (`exam_id`, `exam_section_id`,
`question_id`, `time_limit_seconds` propio del examen —puede
sobreescribir el `default_time_seconds` del reactivo—, `points_weight`,
`sort_order`).

## 6. Intentos y respuestas (018–019)

**`acs_student_attempts`** — un alumno solo puede tener un intento por
examen (único por `exam_id`+`student_id`). Guarda
`question_sequence_payload` (jsonb — el orden ya sorteado de reactivos
para ESE intento, fijo una vez empezado) y `current_question_index`
(hasta dónde va). `status`: `in_progress` / `submitted` / `expired` /
`cancelled_by_incident`. Tres marcadores de calificación:
`objective_score` (lo que se califica solo, al instante),
`ai_estimated_score` (lo que ya evaluó la IA), `final_score` (nullable
hasta que `pending_ai_count` llega a 0). `incidents_count` para el
anti-fraude de `max_allowed_incidents`.

**`acs_student_answers`** — una fila por reactivo contestado
(`student_attempt_id`, `exam_question_id`, único entre ambos).
`answer_payload` (jsonb, la respuesta cruda del alumno). Para objetivos:
`is_correct`, `points_awarded` (calculados al responder). Para
abierto/código: `ai_similarity_score`, `ai_meets_goal`, `ai_feedback`,
`ai_evaluated_at` (los llena `MockAiEvaluator`/`GradeCalculatorService`),
más `teacher_recoteje_score`/`teacher_recoteje_notes` (nullable — espacio
para que el docente recotejé manualmente el resultado de la IA).

## 7. Calificación por periodo (020–021)

**`acs_evaluation_periods`** — un periodo de evaluación por grupo
(`group_id`, `name`, `formula` — string libre con la fórmula de cómo
pesan los componentes, `starts_at`/`ends_at`).

**`acs_student_period_grades`** — la calificación final de un alumno en
un periodo (único por `evaluation_period_id`+`student_id`):
`exams_component`, `homework_component`, `project_component`,
`other_component`, `final_grade` — todos nullable hasta que se capturan/
calculan.

## 8. Soporte, auditoría y Sanctum (022–024)

**`acs_audit_logs`** — bitácora genérica (`user_id`, `action`,
`auditable_type`+`auditable_id` polimórfico, `old_values`/`new_values`
jsonb, `ip_address`, `user_agent`). Solo `created_at` (con
`useCurrent()`), sin `updated_at` — un log no se edita.

**`acs_user_devices`** — tokens FCM para push de la app móvil (`user_id`,
`fcm_token`, `platform`: `android`/`ios`, `last_active_at`).

**`acs_personal_access_tokens`** — la tabla de tokens de Sanctum, pero
**con el prefijo institucional `acs_`** en vez del nombre default del
paquete (`personal_access_tokens`). Esto se logra sin tocar el paquete de
Sanctum: el modelo `App\Models\PersonalAccessToken` apunta a esta tabla, y
`AppServiceProvider::boot()` llama
`Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class)` para
que Sanctum lo use en vez de su modelo/tabla por defecto. Usa `morphs('tokenable')`
estándar del paquete (compatible con cualquier modelo autenticable, hoy
solo `User`).

## 9. Resumen de convenciones

- Prefijo `acs_` en toda tabla.
- `jsonb` (no `json`) para los payloads polimórficos/flexibles —
  Postgres indexa y consulta mejor sobre `jsonb`.
- `decimal(6,2)` para todo lo que es puntaje/calificación (nunca
  `float`), para no arrastrar errores de redondeo en la nota final.
- Únicos compuestos para reglas de negocio que, si se rompieran, serían
  bugs graves (`exam_id`+`student_id` en intentos, `group_id`+`student_id`
  en inscripción, etc.) — la base de datos es la última línea de defensa,
  no solo la validación en PHP.
