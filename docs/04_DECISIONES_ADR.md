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

## Cómo agregar una decisión nueva

Copia el formato de arriba: Contexto (qué problema había y qué opciones
había encima de la mesa), Decisión (qué se eligió, en una frase clara),
Consecuencias (qué se gana, qué se sacrifica, qué queda pendiente). Una
decisión sin su "por qué" por escrito es la que más dolor de cabeza da
seis meses después — tanto para el equipo como frente a una auditoría.
