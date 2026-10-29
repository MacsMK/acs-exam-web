# 04 — Registro de decisiones de arquitectura (ADR)

> Formato corto: Contexto → Decisión → Consecuencias. El objetivo es que
> nadie tenga que adivinar *por qué* algo quedó como quedó, ni reabrir una
> discusión ya resuelta sin ver primero el motivo original. Para la
> auditoría de la materia, esto también sirve como evidencia de que las
> desviaciones respecto a los manuales fueron decisiones conscientes y
> justificadas, no descuidos — ver también la sección "Desviaciones
> conocidas" del `README.md`.

---

## ADR-001 — Capa de servicios compartida entre panel web y API

**Contexto:** el sistema tiene dos consumidores de la misma lógica de
negocio: el panel web (docente, y alumno de prueba) y la API v1 que
consume la app móvil. El riesgo obvio de tener controladores separados
para cada uno es que la regla de negocio ("cómo se califica", "qué
pregunta sigue", "cómo se arma un examen") se implemente dos veces y, con
el tiempo, diverja — un bug arreglado de un lado y no del otro.

**Decisión:** controladores delgados; toda regla de negocio vive en
`app/Services/` (`AuthService`, `ExamAssemblyService`, `AttemptService`,
`GradeCalculatorService`). Tanto los controladores web como los de
`Api/V1` llaman a las mismas clases de servicio.

**Consecuencias:** un cambio de regla de negocio se hace en un solo
lugar. El costo es que los controladores de API tienen que adaptar la
salida de los servicios a JSON, y los web a una vista — pero eso es
responsabilidad de presentación, no de negocio, y está bien que esté
duplicado (no es la misma preocupación).

---

## ADR-002 — Evaluador de IA detrás de un contrato (`AiEvaluatorContract`)

**Contexto:** los reactivos abiertos y de código (RW-09/RW-10) necesitan
evaluarse contra una respuesta de referencia de forma menos rígida que
una comparación exacta. La integración real (ej. un modelo tipo Gemini)
no estaba lista a tiempo para la entrega, y depender de una API externa
durante una demo en vivo (sin internet garantizado, con límites de cuota)
era un riesgo innecesario.

**Decisión:** se define la interfaz `App\Contracts\AiEvaluatorContract` y
se implementa un driver `MockAiEvaluator` (compara por traslape de
palabras contra `reference_answer`/`evaluation_rubric`, sin llamar a
ningún servicio externo). El binding concreto se resuelve en
`AppServiceProvider::register()` según la variable de entorno
`AI_EVALUATOR_DRIVER` (hoy: `mock`).

**Consecuencias:** la demo funciona sin internet ni llaves de API. El
costo real: la calificación de abiertos/código en la demo **no** es una
evaluación semántica real, es una aproximación por palabras — hay que
decirlo así si alguien pregunta en la expo, no presentarlo como IA real.
Conectar un driver real más adelante es agregar una clase nueva que
implemente el contrato y cambiar la variable de entorno — no toca
`AttemptService` ni `GradeCalculatorService` ni los controladores.

---

## ADR-003 — `parent_question_id` vs. paráfrasis (`acs_question_paraphrases`)

**Contexto:** en el diseño del banco de preguntas aparecieron dos
necesidades distintas que a primera vista suenan parecidas y es fácil
confundir o colapsar en una sola columna: (a) versionar un reactivo
cuando el docente lo edita, y (b) tener variantes de redacción del mismo
reactivo para que dos alumnos no vean literalmente el mismo texto en
pantalla (anti-copia).

**Decisión:** son dos mecanismos separados, en dos lugares distintos:

- `parent_question_id` (columna auto-referencial en `acs_questions`) =
  **versionado/edición**. Una fila nueva con `parent_question_id`
  apuntando a la original es "esta es una edición posterior de aquella".
- `acs_question_paraphrases` (tabla aparte, `question_id` →
  `acs_questions`) = **variantes de redacción** del mismo reactivo
  canónico, para anti-copia. No crean una pregunta nueva ni afectan el
  banco — son alternativas de texto (`stem`) para el mismo
  `questionable_id`/respuesta correcta.

**Consecuencias:** quien lea el esquema por primera vez puede confundir
ambos mecanismos porque los dos "generan variantes de una pregunta" —
por eso este ADR y el comentario explícito en el modelo `Question`
(`parentQuestion()`/`paraphrases()`). Si en algún punto se necesita que
una *edición* también tenga *paráfrasis*, el diseño ya lo permite sin
cambios: ambas relaciones son independientes entre sí.

---

## ADR-004 — Laravel Sail/Docker como entorno oficial, con alternativa sin Docker

**Contexto:** el Manual de Programación Web con Laravel (MPW-V2 §1.3.4) y
el Manual de Base de Datos (MBD-V4 §9.2) especifican Docker/Laravel Sail
como el entorno de desarrollo del equipo. El backend se construyó primero
con PHP/Composer/PostgreSQL instalados directo en la máquina (para no
bloquear el avance mientras se resolvía el entorno Docker), y Sail se
agregó después, ya con el flujo de negocio funcionando y validado.

**Decisión:** se agregó `compose.yaml` con los 3 servicios que pide el
manual (`laravel.test`, `pgsql` fijado a `postgres:15` en vez del default
de Sail, `reverb`) y se documentan ambos caminos en el README (Sail como
entorno oficial, instalación directa como alternativa). No se eliminó la
alternativa sin Docker porque sigue siendo útil para quien no tenga
Docker Desktop/WSL2 a la mano de inmediato.

**Consecuencias:** cumple el entorno especificado en el manual.
Pendiente: validar el camino Sail en una máquina real del equipo (se
validó la instalación de PHP/Composer vía CI y de forma local sin Docker;
Sail en sí se validó por estructura del `compose.yaml`, no corriendo
`sail up` en una máquina con Docker Desktop encendido — es el siguiente
paso antes de dar esto por 100% probado).

---

## ADR-005 — Laravel 13.x en vez de la versión 11.x "congelada" en MPW-V2

**Contexto:** el manual especifica Laravel 11.x como versión del stack.
Al intentar fijar `laravel/framework` a `^11.31` para cumplir
literalmente, Composer rechazó la instalación: **toda la línea 11.x
publicada (de 11.31.0 a 11.57.0) tiene advisories de seguridad activos
sin parche disponible en esa rama** — Composer bloquea por default
cualquier paquete con una advisory de seguridad conocida y sin parchar.
Instalarla de todos modos habría requerido deshabilitar explícitamente
ese bloqueo de seguridad de Composer.

**Decisión:** se usa Laravel 13.x (la versión estable más reciente al
momento, sin esos advisories activos) en vez de la 11.x especificada.

**Consecuencias:** para un proyecto de la materia de Auditoría de
Sistemas, "cumplir la versión exacta del manual" y "no tener
vulnerabilidades conocidas sin parchar" entraron en conflicto directo, y
se priorizó lo segundo — es la postura más defendible frente a una
auditoría, aunque signifique una desviación documentada del manual. Si el
equipo decide que cumplir la versión exacta es un requisito no
negociable, la alternativa sería fijar 11.x deshabilitando el bloqueo de
advisories de Composer explícitamente, dejando constancia de qué CVEs
quedan sin parchar — no se tomó esa ruta porque el costo (vulnerabilidades
activas conocidas) pesa más que el beneficio (conformidad literal con el
número de versión).

---

## ADR-006 — Autenticación propia (`AuthService`) en vez de Laravel Fortify

**Contexto:** el Manual de Programación Móvil (MPM-V2 §6.6.1) especifica
el flujo de autenticación (login + verificación de 2 pasos con código de
6 dígitos por correo), y el manual de Laravel sugiere construirlo sobre
Fortify. El backend se construyó bajo presión fuerte de tiempo (entrega
al día siguiente de iniciado) y la autenticación con 2FA ya estaba
funcionando con una implementación propia (`AuthService` +
`LoginController`/`RegisterController`) antes de evaluar la migración a
Fortify.

**Decisión:** se mantiene la implementación propia. El resultado
funcional es el mismo que pide el manual (login dos pasos, código de 6
dígitos, vía correo) — lo que difiere es que no está construido sobre el
paquete Fortify.

**Consecuencias:** es la pieza del sistema con más superficie de
"romper algo que ya funciona" si se migra sin tiempo de probarla a fondo,
para un beneficio que es de conformidad con el manual, no funcional (el
usuario final no distingue si el 2FA corre sobre Fortify o no). Queda
pendiente de decidir en equipo si se migra después de la entrega, con
tiempo para probarlo con calma.

---

## ADR-007 — Reverb instalado como infraestructura, sin funcionalidad aún

**Contexto:** el manual especifica Laravel Reverb (WebSockets) como parte
del stack de 3 contenedores. No había, al momento de la entrega, ninguna
funcionalidad del sistema que dependiera de eventos en tiempo real
(ej. monitoreo en vivo de alumnos presentando examen, notificación
instantánea de incidente anti-fraude).

**Decisión:** se instaló y configuró Reverb completo (`config/reverb.php`,
`config/broadcasting.php`, `routes/channels.php`, contenedor `reverb` en
`compose.yaml`, variables en `.env.example`) — el servidor arranca y el
contenedor existe, cumpliendo el stack de 3 contenedores del manual — pero
**no se construyó ninguna función que emita o escuche eventos todavía**.

**Consecuencias:** cumple la infraestructura especificada sin inventar
una funcionalidad apurada de último momento solo para "usar" Reverb. El
candidato más natural para ser el primer evento real es el monitoreo en
vivo del docente sobre los alumnos que están presentando un examen
(`acs_student_attempts.status`, incidentes) — queda como trabajo futuro
explícito, no implícito.

---

## ADR-008 — Cola/sesión/caché en `sync`/`file`/`file`, no `database`

**Contexto:** el manual especifica todo sobre PostgreSQL (sin Redis). Las
tablas de sesión/cola/caché de Laravel (`sessions`, `jobs`, `cache`)
habrían requerido 2-3 migraciones adicionales con el prefijo `acs_`, sin
aportar nada observable a la demo (no hay ninguna tarea en cola que valga
la pena encolar todavía, ni tráfico concurrente real que se beneficie de
caché en BD en vez de archivo).

**Decisión:** se dejaron los drivers nativos (`QUEUE_CONNECTION=sync`,
`SESSION_DRIVER=file`, `CACHE_STORE=file`) en vez de migrar a `database`.

**Consecuencias:** no hay diferencia funcional visible para la demo.
Migrar a `database` más adelante es agregar las migraciones
correspondientes y cambiar 3 variables de entorno — no es un cambio de
diseño, solo quedó fuera del alcance de la entrega inicial por prioridad.

---

## ADR-009 — Sobre de respuesta uniforme (`App\Support\ApiResponse`)

**Contexto:** la app móvil (`acs-exam-mobile`, `lib/core/network/
api_response.dart`) ya está construida esperando que **toda** respuesta
de la API — éxito o error, incluyendo errores que Laravel generaría por
su cuenta (404 de ruta, 419 de token expirado, 500 no capturado) — venga
en la forma `{status, code, message, data, errors}` con
`isSuccess => status == 'success'`. El backend, antes de este ajuste,
devolvía arrays sueltos en éxito y las páginas/JSON default de Laravel en
error (forma distinta en cada caso).

**Decisión:** se agregó `App\Support\ApiResponse` con `success()`,
`error()` y `fromException()`, y un renderer global en `bootstrap/app.php`
(`$exceptions->render()`) que intercepta **cualquier** excepción bajo
`api/*` y la pasa por `ApiResponse::fromException()` antes de que Laravel
la convierta en su respuesta default. Todos los controladores de
`Api/V1/*` se reescribieron para devolver siempre a través de este helper.

**Consecuencias:** Flutter puede confiar en una sola forma de respuesta
sin tener que distinguir "¿esto es un error de validación mío o un 500
del servidor?" por la forma del JSON — ambos llegan con `status: "error"`
y el detalle en `message`/`errors`. El costo: cualquier excepción nueva
que no esté explícitamente mapeada en `fromException()` cae al `default`
(500 genérico) — si aparece un tipo de excepción que merece su propio
código HTTP, hay que agregarlo ahí explícitamente.

---

## ADR-010 — Verificación de 2FA sin identificador de usuario en el payload

**Contexto:** `lib/features/auth/data/repositories/auth_repository.dart`
(ya construido del lado Flutter) manda **solo** `{code}` a
`auth/two-factor` — nunca un `user_id`, `email` ni token intermedio del
paso de login. El backend, antes de este ajuste, exigía `user_id` como
campo obligatorio (`VerifyTwoFactorRequest`), algo que el cliente móvil
nunca iba a poder cumplir.

**Decisión:** `user_id` pasa de `required` a `sometimes` en
`VerifyTwoFactorRequest` (el panel web sigue mandándolo por un campo
oculto; la API simplemente no lo exige). `AuthController::twoFactor()`
busca al usuario **por el código mismo**: el primero cuyo
`two_factor_code` coincida, no esté expirado (`two_factor_expires_at >
now()`) y tenga rol `teacher` o `student`, tomando el más reciente si
hubiera colisión (`latest('two_factor_expires_at')`).

**Consecuencias:** es una decisión de **seguridad reducida y consciente**:
un código de 6 dígitos sin un identificador de usuario que lo acompañe
tiene más superficie de colisión que "código + user_id". Se acepta porque
(a) el código ya expira (`two_factor_expires_at`) y (b) cambiar el
contrato significaría pedirle al equipo móvil que reconstruya una pantalla
ya terminada y probada — más costoso que ajustar el backend. Si en la
auditoría esto se señala como riesgo, la mitigación futura más barata es
alargar el código (de 6 a 8 dígitos) o acortar su ventana de expiración,
no necesariamente rehacer el contrato.

---

## ADR-011 — Endpoints de aviso de privacidad, grupos extendidos y
calificaciones: nuevos del lado backend, contrato fijado por el móvil

**Contexto:** varios endpoints que la app móvil ya consume
(`privacy-notice` + `/accept`, `groups` como listado propio en vez de
"mine", `groups/verify`, `groups/{group}/students`, `grades` +
`/statistics`, `devices`) no existían del lado backend, o existían con
otro nombre/forma (`groups/mine` en vez de `groups`). Estos eran
justamente los puntos que el propio equipo móvil había dejado marcados
como "pendiente de confirmación con el equipo backend" (ver el documento
de seguimiento del equipo, insumo DE-03).

**Decisión:** se construyeron `PrivacyController`, `GradeController`,
`DeviceController` como nuevos, y se extendió `GroupController`
(`index()`, `verify()`, `students()`), todos con la forma exacta que ya
esperan los modelos Dart correspondientes
(`PrivacyNoticeModel`, `GroupModel`, `StudentModel`, `GradeModel`,
`GradePageModel`, `GradeStatisticsModel`) en vez de diseñar la forma de
respuesta desde cero del lado backend.

**Consecuencias:** se resuelve del lado servidor, sin pedirle nada nuevo
al equipo móvil, pero quedan dos simplificaciones documentadas que valen
la pena revisar con calma después de la entrega: (a) `GroupController
::index()` oculta grupos cerrados (`is_closed`) solo para alumnos, no
para docentes — resuelve RF-GRU-019 pero asume que un docente siempre
quiere ver sus grupos cerrados también; (b) en `GradeController::index()`
el campo `weight` se devuelve fijo en `100.0` porque **no existe hoy**
ningún mecanismo real de ponderación entre exámenes de un mismo alumno —
es un placeholder, no un cálculo. Si el proyecto necesita ponderación real
más adelante, es una columna/regla nueva, no un ajuste de forma.

---

## ADR-012 — Flujo de examen de la API rediseñado alrededor de
`exams/take` + `exams/{exam}/answers` (sin "current-question" aparte)

**Contexto:** el lado web resuelve "iniciar intento" → "responder" →
"terminar" → "ver resultado" como pasos separados dentro de la misma
vista (Alpine.js). El cliente Flutter (`exam_repository.dart`,
`exam_model.dart`, `answer_payload.dart`) ya estaba construido esperando
un flujo distinto: `POST exams/take` con `exam_id` en el cuerpo (no en la
URL) que entrega la primera pregunta, y cada `POST exams/{exam}/answers`
que recibe la respuesta de la pregunta *actual* y devuelve la *siguiente*
en la misma respuesta — sin un paso explícito de "dame la siguiente
pregunta" ni un endpoint separado de "terminar".

**Decisión:** se reescribió `Api\V1\AttemptController` alrededor de ese
flujo. Internamente sigue usando `AttemptService` sin modificarlo — solo
se agregaron, en el controlador, `normalizeAnswerPayload()` (traduce
`selected_option_ids`/`pairs`/`text`/`code` del payload de Flutter a la
forma interna que `AttemptService::gradeAnswer()` ya esperaba) y
`questionPayload()` (traduce la pregunta interna a la forma de
`QuestionModel` según su tipo, sin filtrar nunca la respuesta correcta).
El examen se autofinaliza (y califica) cuando se responde la última
pregunta — no hay un endpoint `submit`/`terminar` aparte del lado API.
También se agregó `exams/{exam}/incidents` (incidentes anti-fraude:
incrementa `incidents_count` y cancela el intento si excede
`max_allowed_incidents`), que no tenía equivalente del lado web.

**Consecuencias:** cero cambios de riesgo en `AttemptService` ni en
`GradeCalculatorService` (la lógica de calificación, ya probada del lado
web, no se tocó) — todo el ajuste quedó aislado en la capa de traducción
del controlador. El costo: el avance de examen del lado API
(`exams/{exam}/progress`, uso exclusivo del docente) puede contar dos
veces a un alumno que esté en más de un grupo asignado al mismo examen —
simplificación aceptada, documentada, no corregida en esta entrega.

---

## ADR-013 — `platform` fijo en `"android"` en el registro de dispositivo

**Contexto:** `POST /v1/devices` (registro de token de notificaciones
push) solo recibe `{"token": "..."}` del lado Flutter
(`device_repository.dart`) — nunca un campo `platform`. El hallazgo M-12
del propio equipo móvil ya documentaba que el alcance del proyecto es
Android únicamente (excluye iOS explícitamente).

**Decisión:** `DeviceController::store()` asume `platform: 'android'` al
guardar (`UserDevice::updateOrCreate`), en vez de exigir un campo que el
cliente nunca manda.

**Consecuencias:** si el proyecto alguna vez soporta iOS, este es el
primer lugar que hay que tocar — junto con pedirle al cliente que empiece
a mandar su plataforma real.

---

## ADR-014 — Suite de pruebas de contrato (`tests/Feature/Api/
MobileContractTest.php`) como validación antes de CI

**Contexto:** este ajuste del contrato de API tocó prácticamente todos los
controladores de `Api/V1`, sin poder correr PHP/Composer localmente en el
entorno donde se escribió el código — la única validación real disponible
es GitHub Actions, y cada vuelta de CI tiene un costo de tiempo que no
vale la pena gastar en errores que una prueba local habría atrapado.

**Decisión:** se agregó `tests/Feature/Api/MobileContractTest.php`
cubriendo de punta a punta: login → 2FA → logout; aviso de privacidad
(mostrar/aceptar); unirse a grupo por folio + listado + lista de alumnos
(docente); el flujo completo de examen ejercitando los 4 tipos de
reactivo en secuencia (opción múltiple, relacionar columnas, abierto,
código) con verificación de que la calificación resultante sea la
esperada; y registro de token de dispositivo. `phpunit.xml` se cambió de
SQLite en memoria (default del esqueleto de Laravel) a PostgreSQL real,
porque varias migraciones usan tipos propios de Postgres (`jsonb`) que
SQLite no tiene — correr la suite contra un motor que el proyecto nunca
usa de verdad no sirve como validación real.

**Consecuencias:** cualquiera del equipo que corra `php artisan test`
localmente necesita un Postgres disponible (ya sea Sail o uno instalado
directo) — ya no basta con el SQLite que trae Laravel por defecto. A
cambio, la suite sirve como documentación ejecutable del contrato: si
alguien cambia la forma de un endpoint sin querer, esta prueba debería
fallar antes de llegar a CI.

---

## Cómo agregar una decisión nueva

Copia el formato de arriba: Contexto (qué problema había y qué opciones
había encima de la mesa), Decisión (qué se eligió, en una frase clara),
Consecuencias (qué se gana, qué se sacrifica, qué queda pendiente). Una
decisión sin su "por qué" por escrito es la que más dolor de cabeza da
seis meses después — tanto para el equipo como frente a una auditoría.
