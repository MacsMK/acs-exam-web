# ACS Exam Web — A-Code Solutions

> "Analizamos, diseñamos, transformamos."

Backend (Laravel) + base de datos de la Plataforma Evaluadora / Aplicadora de
Exámenes, para la Materia de Auditoría de Sistemas. Expone:

- Un **panel web** (sesión) donde el docente arma grupos, banco de reactivos
  y exámenes, y donde además se puede probar el flujo completo del lado
  alumno desde el navegador (sin necesitar la app móvil).
- Una **API v1** (`routes/api.php`, autenticación con Sanctum/Bearer token)
  que es la que consume la app móvil (Flutter) del proyecto.

La lógica de negocio (calificación, armado/sorteo de examen, 2FA, etc.) vive
en `app/Services/` y la comparten el panel web y la API — ver
`AttemptService` y `AuthService` — para no duplicar reglas entre los dos
lados.

## Requisitos

- PHP 8.3+ con extensiones habituales de Laravel (`pdo_pgsql`, `mbstring`,
  `bcmath`, etc.)
- Composer 2
- PostgreSQL 14+ (el proyecto usa `pgsql` según el Manual de Base de Datos)
- No se requiere Node/npm: la interfaz web usa Tailwind CSS y Alpine.js por
  CDN (sin build de Vite), para no depender de un paso extra de compilación
  durante la demo.

## Puesta en marcha

```bash
composer install

cp .env.example .env
php artisan key:generate
```

Edita `.env` con los datos de tu PostgreSQL local (por defecto apunta a
`acs_exam_web` en `127.0.0.1:5432`, usuario/clave `postgres`/`postgres`).
Crea la base de datos vacía (`createdb acs_exam_web` o equivalente) y luego:

```bash
php artisan migrate --seed
php artisan serve
```

Abre `http://127.0.0.1:8000` — redirige a `/login`.

## Datos de prueba (seeders)

`php artisan migrate --seed` deja la base con un semestre de ejemplo ya
armado (grupo, reactivos de los 4 tipos, un examen publicado). Todas las
contraseñas de demo son **`Demo1234`**:

| Rol | Correo | Notas |
| --- | --- | --- |
| Docente | `docente.demo@acode-solutions.test` | Laura Martínez — dueña del grupo y examen de ejemplo |
| Alumno | `alumno0.demo@acode-solutions.test` … `alumno3.demo@acode-solutions.test` | Ya inscritos al grupo de ejemplo |
| Super admin | `admin@acode-solutions.test` | Cuenta de administración |

**Nota sobre el código de verificación (2FA):** el login es en dos pasos
(correo+contraseña, luego un código de 6 dígitos). En este entorno de demo
el código se muestra directamente en la pantalla de verificación (para
poder probar sin bandeja de correo real) y además se escribe en
`storage/logs/laravel.log`.

## Flujo sugerido para la demo

1. Entra como docente (`docente.demo@...`) → Dashboard → Grupos / Reactivos
   / Exámenes ya tienen datos de ejemplo.
2. Desde "Exámenes" → "Armar" puedes agregar otro apartado (sorteo
   automático de reactivos por tipo) o volver a sortear un reactivo
   ("Nuevo sorteo").
3. Cierra sesión y entra como alumno (`alumno0.demo@...`) → "Mis exámenes"
   → "Presentar examen" → contesta los reactivos (cronómetro por reactivo,
   sin poder regresar) → al terminar ves tu resultado.
4. Para probar el alta desde cero: "Unirme a un grupo" con el folio que
   aparece en la vista del grupo del docente.

## Evaluador de IA (reactivos abiertos y de código)

`AI_EVALUATOR_DRIVER=mock` (valor por defecto en `.env.example`) usa
`App\Services\MockAiEvaluator`, que compara por traslape de palabras contra
la respuesta de referencia — sin llamar a ningún servicio externo. Está
aislado detrás de `App\Contracts\AiEvaluatorContract`
(`app/Providers/AppServiceProvider::register()`), así que conectar un
evaluador real (p. ej. Gemini) más adelante es agregar un nuevo driver y
cambiar la variable de entorno, sin tocar `AttemptService`.

## Estructura relevante

```
app/
  Models/          22 modelos Eloquent (prefijo de tabla acs_, MBD-V4)
  Services/        Lógica de negocio compartida (web + API)
  Http/Controllers/        Panel web (sesión)
  Http/Controllers/Api/V1/ API para la app móvil (Sanctum)
  Http/Middleware/EnsureUserHasRole.php
database/
  migrations/      24 migraciones (folios BD01-2026-001 a 024)
  seeders/         Datos de ejemplo
resources/views/   Blade (Tailwind/Alpine por CDN, paleta institucional
                   acode-* del Manual de Programación Laravel MP-V2 §7.4)
routes/
  web.php          Panel docente + flujo alumno de prueba
  api.php          API v1 para Flutter
```

## Qué falta / siguientes pasos

- Este repo se armó por separado, bajo presión de tiempo, para tener un
  backend + BD de punta a punta listos para una fecha de entrega. Falta
  reconciliarlo con el repo/avance del resto del equipo (especialmente la
  base de datos — ver `Avance de construccion de la BD` en el proyecto —
  y cualquier endpoint adicional que la app móvil termine necesitando).
- El UI Kit institucional completo (componentes Blade `<x-ui.*>`,
  SweetAlert2, Chart.js, anti-fraude por cámara web) descrito en el Manual
  de Programación Laravel es más amplio que lo implementado aquí; esta
  interfaz web cubre el flujo crítico de punta a punta con la paleta y
  tipografía oficiales, pero no (todavía) cada componente del manual.
- Validar en un entorno real con `composer install` + `php artisan test`
  (ver `.github/workflows/ci.yml`, que corre esto automáticamente en cada
  push).
