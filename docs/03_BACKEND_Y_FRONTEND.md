# 03 — Backend y frontend (panel web)

> Complementa a `01_ARQUITECTURA_GENERAL.md`. Aquí sí se listan rutas,
> controladores y vistas concretas, para que ubicar "dónde está X" sea
> rápido.

## 1. Rutas — panel web (`routes/web.php`)

Middleware global relevante (definido en `bootstrap/app.php`):
guests → `/login`, usuarios autenticados sin ruta específica → `/dashboard`.

| Grupo | Middleware | Rutas (resumen) |
| --- | --- | --- |
| Invitados | — | `auth/register-teacher`, `auth/register-student`, `login`, `verify-2fa` (GET+POST cada una) |
| Autenticado (cualquier rol) | `auth` | `dashboard` (redirige según rol), `logout` |
| Docente | `auth` + `role:teacher` | `grupos` (index/create/store/show), `reactivos` (index/create/store), `examenes` (index/create/store, `armar`/`assemble`) |
| Alumno | `auth` + `role:student` | `grupos/unirse` (form + submit), `mis-examenes` |
| Intentos (ambos lados pueden iniciarlos) | `auth` | `examenes/{exam}/iniciar`, `intentos/{attempt}` (show/responder/terminar/resultado) |

El flujo de "presentar examen" (`intentos/*`) no está bajo `role:student`
porque el controlador (`AttemptController`, lado web) ya valida por sí
mismo que el intento pertenezca al usuario autenticado — es la misma
convención que sigue el lado API.

## 2. Rutas — API v1 (`routes/api.php`)

Todo bajo `Route::prefix('v1')->name('api.v1.')`. Públicas: registro
(docente/alumno), login, verificación 2FA. Todo lo demás requiere
`auth:sanctum`:

```
GET  /v1/auth/me
POST /v1/auth/logout
POST /v1/groups/join
GET  /v1/groups/mine
GET  /v1/exams/assigned-to-me
POST /v1/exams/{exam}/attempts/start
GET  /v1/attempts/{attempt}/current-question
POST /v1/attempts/{attempt}/answers
POST /v1/attempts/{attempt}/submit
```

Nota para quien integre la app Flutter: el nombre de los endpoints de
intento difiere un poco del lado web (`current-question`/`answers`/
`submit` vs. `responder`/`terminar`/`resultado`) porque el lado API
expone un paso explícito "dame la siguiente pregunta" pensado para una
UI que avanza pregunta por pregunta sin recargar toda la vista, mientras
que el lado web resuelve eso dentro de la misma vista con Alpine. La
regla de negocio detrás (quién puede responder qué, cómo se califica) es
la misma — vive en `AttemptService`, no en el controlador.

## 3. Controladores

**Web** (`app/Http/Controllers/`):

- `Auth/LoginController`, `Auth/RegisterController` — delegan a
  `AuthService`.
- `DashboardController` — arma los datos del dashboard según
  `auth()->user()->role`.
- `GroupController`, `QuestionController`, `ExamController` —
  CRUD del lado docente. `QuestionController::store()` es el que arma la
  variante polimórfica correcta (`MultipleChoiceItem`/`MatchingItem`/
  `OpenItem`/`CodeItem`) según `question_type` recibido, incluyendo el
  método privado `buildMatchingItem()` que arma `left_column_payload`/
  `right_column_payload`/`pairs_payload` a partir de los arrays paralelos
  `concepts`/`definitions` del formulario.
- `StudentExamController` — lado alumno: unirse a grupo por folio
  (`join()`, valida `access_folio` + que el grupo no esté `is_closed`,
  usa `syncWithoutDetaching` para no duplicar inscripción) y listar
  exámenes asignados (`index()`, exámenes publicados de los grupos del
  alumno).
- `AttemptController` (web) — empezar intento, responder, terminar, ver
  resultado. Delgado: todo pasa por `AttemptService`.

**API** (`app/Http/Controllers/Api/V1/`): `AuthController`,
`GroupController`, `ExamController`, `AttemptController` — mismo patrón,
mismos servicios, responses en JSON (`Resource`/arrays simples según el
endpoint) en vez de vistas.

## 4. Capa de servicios (la lógica real)

Ver el detalle completo en `01_ARQUITECTURA_GENERAL.md` sección 4. En
resumen, para no repetirlo: `AuthService`, `ExamAssemblyService`,
`AttemptService`, `GradeCalculatorService` y `MockAiEvaluator` (detrás de
`AiEvaluatorContract`) viven en `app/Services/` y los usan tanto los
controladores web como los de la API.

## 5. Middleware propio

**`App\Http\Middleware\EnsureUserHasRole`** (alias `role`, registrado en
`bootstrap/app.php` vía `$middleware->alias(['role' => ...])`). Uso:
`Route::middleware('role:teacher')` / `Route::middleware('role:student')`.
Compara contra `auth()->user()->role` (el enum de `acs_users`) y aborta
403 si no coincide.

## 6. Vistas Blade

```
resources/views/
  layouts/
    app.blade.php      Layout con sesión (navbar según rol, flash de
                        sesión, lista de errores de validación)
    guest.blade.php     Layout sin sesión (login/registro)
  auth/
    login.blade.php
    register-teacher.blade.php
    register-student.blade.php
    verify-2fa.blade.php     Paso 2 del login (código de 6 dígitos)
  dashboard/
    teacher.blade.php
    student.blade.php
  groups/
    index.blade.php    Lista de grupos del docente
    create.blade.php
    show.blade.php      Detalle de grupo + su access_folio
    join.blade.php       Form del alumno para unirse por folio
  questions/
    index.blade.php     Banco de reactivos del docente
    create.blade.php     Form dinámico por question_type (Alpine.js:
                         cambia los campos según el tipo elegido, sin
                         recargar la página)
  exams/
    index.blade.php
    create.blade.php
    assemble.blade.php   "Armar examen": agrega secciones, dispara el
                         sorteo de ExamAssemblyService, permite
                         re-sortear un reactivo individual
    student-index.blade.php   "Mis exámenes" (alumno)
    take-exam.blade.php        Presentar examen: cronómetro por reactivo
                               con auto-envío al expirar (Alpine.js),
                               sin poder regresar a la pregunta anterior
    result-summary.blade.php
```

No existe `welcome.blade.php` (se eliminó — era el stub default de
Laravel, sin usar, con rutas rotas).

## 7. Sistema de diseño `acode-*`

No hay build de Vite/Node: Tailwind se carga desde el CDN
(`cdn.tailwindcss.com`) y los tokens institucionales se registran en
tiempo de ejecución con `tailwind.config = {...}` **antes** de cargar el
script del CDN (ver `layouts/app.blade.php`). Esta es la fuente de verdad
exacta — cópiala tal cual si vas a usar los mismos colores en otra vista
o en el futuro front de Flutter:

```js
tailwind.config = {
    theme: {
        extend: {
            fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
            colors: {
                acode: {
                    primary: '#142B4A',
                    tech:    '#2563A6',
                    accent:  '#16A6A1',
                    warning: '#F4B942',
                    surface: '#E9F0F5',
                    danger:  '#DC2626',
                },
            },
        },
    },
};
```

Uso: clases Tailwind normales con el prefijo `acode-` (`bg-acode-primary`,
`text-acode-danger`, `border-acode-accent/30`, etc.). Tipografía Inter
cargada desde Google Fonts. Alpine.js (`alpinejs@3.14.1` por CDN) para
interactividad sin JS "a mano": el form dinámico de reactivos y el
cronómetro de examen son los dos usos reales hoy.

Por qué sin build: para que la demo no dependa de `npm install`/`npm run
build` funcionando en la máquina de quien la corra — un riesgo que no
valía la pena tomar bajo la presión de tiempo de la entrega. Si el
proyecto migra a un build real de Vite más adelante, estos mismos tokens
deben moverse a `tailwind.config.js` tal cual están aquí, no reinventarse.

## 8. Dónde seguir leyendo

- `02_BASE_DE_DATOS.md` — el esquema completo que consumen estos
  controladores/servicios.
- `04_DECISIONES_ADR.md` — por qué cada pieza quedó como quedó.
