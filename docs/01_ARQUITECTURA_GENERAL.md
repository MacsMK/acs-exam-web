# 01 — Arquitectura general

> A-Code Solutions · Plataforma Evaluadora / Aplicadora de Exámenes
> Repo: `acs-exam-web` (backend Laravel + base de datos + panel web)
> Alcance de este documento: cómo está armado el sistema hoy, para que
> cualquiera del equipo pueda orientarse sin tener que leer todo el código
> de una sentada. Si algo de aquí no cuadra con lo que ves en el repo, el
> repo manda — actualiza este documento.

## 1. Qué es este repo

Este repo contiene **dos cosas a la vez**, sobre la misma base de código:

1. El **backend/API** que consume la app móvil (Flutter, repo
   `acs-exam-mobile`) — autenticación con Sanctum (Bearer token).
2. Un **panel web** (sesión de navegador) donde el docente arma grupos,
   banco de reactivos y exámenes, y donde además se puede correr el flujo
   completo del lado alumno desde el navegador, sin necesitar la app
   móvil. Esto último se armó así a propósito: sirve para probar/demostrar
   todo el flujo de negocio aunque la app móvil todavía no cubra una
   pantalla, y para no bloquear la entrega del backend esperando a que el
   front de Flutter esté listo.

No son dos proyectos separados: comparten modelos, migraciones y, sobre
todo, la lógica de negocio (ver sección 4).

## 2. Stack real (verificado en `composer.json`, no de memoria)

| Pieza | Versión / detalle |
| --- | --- |
| PHP | ^8.3 |
| Laravel (framework) | ^13.17 |
| Base de datos | PostgreSQL (15 en Docker/Sail, o la que tengas local) |
| Autenticación API | Laravel Sanctum ^4.0 (tokens personales) |
| Autenticación web | Sesión propia (`AuthService` + `LoginController`/`RegisterController`, **no** Fortify — ver `04_DECISIONES_ADR.md`) |
| WebSockets | Laravel Reverb ^1.0 (instalado y el contenedor corre; todavía sin ninguna funcionalidad que emita eventos — ver ADR) |
| Entorno de desarrollo | Laravel Sail (Docker) — `compose.yaml` en la raíz, 3 servicios: `laravel.test`, `pgsql`, `reverb` |
| Frontend del panel web | Blade + Tailwind CSS y Alpine.js **por CDN** (sin Node/Vite ni paso de build) |
| Testing | PHPUnit ^12 vía `php artisan test` |
| CI | GitHub Actions (`.github/workflows/ci.yml`) — instala con Composer, migra con `--seed` sobre un contenedor `postgres:16`, corre la suite completa en cada push |

**Por qué no es exactamente la versión "congelada" en el Manual de
Programación Web con Laravel (MPW-V2):** el manual especifica Laravel
11.x. Al intentar fijar esa versión se encontró que **toda la línea 11.x
publicada tiene advisories de seguridad activos sin parche** (Composer la
bloquea por default). El detalle completo, con la referencia a la
advisory concreta, está en `04_DECISIONES_ADR.md` y en el README
("Desviaciones conocidas").

## 3. Los dos consumidores, en detalle

### 3.1 Panel web (sesión)

- Rutas: `routes/web.php`.
- Controladores: `app/Http/Controllers/*` (sin el subnamespace `Api`).
- Vistas: `resources/views/**/*.blade.php`.
- Middleware de autorización por rol: `App\Http\Middleware\EnsureUserHasRole`
  (alias `role`, registrado en `bootstrap/app.php`) — protege rutas con
  `Route::middleware('role:teacher')` / `role:student'`.
- Redirecciones post-login/guest: `redirectGuestsTo('/login')` y
  `redirectUsersTo('/dashboard')` (también en `bootstrap/app.php`).

### 3.2 API v1 (para la app móvil)

- Rutas: `routes/api.php`, todas bajo el prefijo `v1` (`Route::prefix('v1')`,
  nombre de ruta `api.v1.*`).
- Controladores: `app/Http/Controllers/Api/V1/*`
  (`AuthController`, `GroupController`, `ExamController`, `AttemptController`).
- Autenticación: `auth:sanctum` en todas las rutas salvo registro/login/2FA.
  El cliente (Flutter) manda `Authorization: Bearer <token>`.
- Formato de respuesta: JSON siempre — `bootstrap/app.php` fuerza
  `shouldRenderJsonWhen` para cualquier request bajo `api/*` o que pida
  JSON explícitamente, así que un error ahí nunca regresa una página HTML
  de error por accidente.

## 4. Cómo comparten lógica de negocio (lo importante)

Los controladores — tanto los del panel web como los de `Api/V1` — son
**delgados a propósito**: validan el request (con `Form Requests` en
`app/Http/Requests/`) y llaman a una clase de `app/Services/`. La regla de
negocio vive en un solo lugar, no duplicada entre web y API:

| Servicio | Responsabilidad | Lo usan |
| --- | --- | --- |
| `AuthService` | Registro (docente/alumno), login + verificación de 2FA por correo (código de 6 dígitos) | `Auth\LoginController`/`RegisterController` (web) y `Api\V1\AuthController` |
| `ExamAssemblyService` | Arma/sortea reactivos de un examen por tipo y cantidad declarada en cada sección (`ExamSection`), evitando repetir reactivo | `ExamController` (web, vista "Armar examen") |
| `AttemptService` | Inicia un intento, entrega la siguiente pregunta, recibe y guarda cada respuesta, cierra el intento y dispara la calificación | `AttemptController` (web) **y** `Api\V1\AttemptController` — este es el ejemplo más claro de "una sola lógica, dos puertas de entrada" |
| `GradeCalculatorService` | Califica: reactivos objetivos (opción múltiple, relacionar columnas) se califican por comparación directa; reactivos abiertos/código se mandan al evaluador de IA | `AttemptService` internamente |
| `MockAiEvaluator` (implementa `App\Contracts\AiEvaluatorContract`) | Evalúa reactivos abiertos/de código **sin** depender de un servicio externo (compara por traslape de palabras contra la respuesta de referencia) | `GradeCalculatorService`, inyectado vía el contrato — ver más abajo |

**El patrón del evaluador de IA vale la pena entenderlo aparte:** nada en
`AttemptService`/`GradeCalculatorService` conoce `MockAiEvaluator`
directamente, solo conocen la interfaz `AiEvaluatorContract`. El binding
concreto se resuelve en `AppServiceProvider::register()` según la variable
de entorno `AI_EVALUATOR_DRIVER` (hoy solo existe el driver `mock`). El
día que haya un evaluador real (por ejemplo contra Gemini), se agrega como
un nuevo driver ahí y **no se toca nada de `AttemptService` ni de los
controladores** — esto es intencional, para que conectar un modelo real
más adelante sea un cambio aislado.

## 5. Estructura de carpetas

```
app/
  Contracts/
    AiEvaluatorContract.php      Interfaz del evaluador de reactivos abiertos/código
  Http/
    Controllers/                 Panel web (sesión)
      Auth/                      Login, registro (docente/alumno)
      Api/V1/                    API para la app móvil (Sanctum)
    Middleware/
      EnsureUserHasRole.php      Alias "role" (role:teacher / role:student)
    Requests/                    Form Requests (validación) — compartidos donde aplica
  Models/                        22 modelos Eloquent, prefijo de tabla acs_ (ver 02_BASE_DE_DATOS.md)
  Providers/
    AppServiceProvider.php       Binding de AiEvaluatorContract + Sanctum::usePersonalAccessTokenModel
  Services/                      Lógica de negocio compartida entre web y API (ver sección 4)
database/
  migrations/                   24 migraciones, folio BD01-2026-001 a 024
  seeders/                      Datos de ejemplo para demo (ver 02_BASE_DE_DATOS.md)
resources/views/                Blade del panel web (Tailwind/Alpine por CDN, paleta institucional acode-*)
routes/
  web.php                       Panel docente + flujo alumno de prueba
  api.php                       API v1 para Flutter
  channels.php                  Canales de Reverb (infraestructura, sin uso funcional aún)
compose.yaml                    Laravel Sail: laravel.test + pgsql + reverb
.github/workflows/ci.yml        CI: composer install + migrate --seed + test, en cada push
docs/                           Esta carpeta
```

## 6. Flujo de datos general (de punta a punta)

```
Docente                                    Alumno
  │                                          │
  ├─ 1. Crea reactivos (banco)               │
  │    (opción múltiple / relacionar /       │
  │     abierto / código)                    │
  │                                          │
  ├─ 2. Crea grupo → obtiene folio            │
  │                                          ├─ 3. Se une al grupo con el folio
  │                                          │     (registro de alumno si no existe cuenta)
  ├─ 4. Crea examen → arma secciones          │
  │    (ExamAssemblyService sortea            │
  │     reactivos del banco por tipo)         │
  ├─ 5. Publica el examen al grupo            │
  │                                          ├─ 6. Ve el examen en "Mis exámenes"
  │                                          ├─ 7. Inicia intento (AttemptService)
  │                                          │     → responde reactivo por reactivo
  │                                          │     (cronómetro, sin regresar)
  │                                          ├─ 8. Termina el intento
  │                                          │     → GradeCalculatorService califica:
  │                                          │       objetivos: directo
  │                                          │       abiertos/código: AiEvaluatorContract
  ├─ 9. Puede ver resultados del grupo        ├─ 9. Ve su resultado
```

Este mismo flujo (pasos 7–8 en particular) es el que la app móvil debe
reproducir contra la API v1 — por eso `AttemptService` es compartido: la
regla de "cómo se califica" y "qué pregunta sigue" no debe reinterpretarse
del lado de Flutter, solo consumirse.

## 7. Dónde seguir leyendo

- `02_BASE_DE_DATOS.md` — las 24 tablas, relaciones, el diseño polimórfico
  del banco de preguntas.
- `03_BACKEND_Y_FRONTEND.md` — rutas, middleware, vistas Blade y el sistema
  de diseño `acode-*` en detalle.
- `04_DECISIONES_ADR.md` — por qué se tomó cada decisión que se desvía del
  manual (Laravel 13 vs 11, Fortify diferido, Sail, Reverb sin uso
  funcional, etc.), con su justificación.
- `README.md` (raíz del repo) — cómo levantar el proyecto (con o sin
  Docker), credenciales de demo, flujo sugerido para una demostración.
