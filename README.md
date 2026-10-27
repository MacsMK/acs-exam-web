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

## Entorno oficial: Docker + Laravel Sail

Conforme al Manual de Programación Web con Laravel (MPW-V2 §1.3.4) y al
Manual de Base de Datos (MBD-V4 §9.2), el entorno autoritativo del proyecto
es el que levanta **Laravel Sail**, con 3 contenedores (`compose.yaml` en la
raíz): `laravel.test` (la app, PHP 8.3), `pgsql` (PostgreSQL 15) y `reverb`
(WebSockets). Sin Redis: cola y caché van sobre drivers nativos.

```bash
composer install
cp .env.example .env
```

En tu `.env`, para correr con Sail cambia `DB_HOST=127.0.0.1` por
`DB_HOST=pgsql` (es el nombre del servicio en la red de Docker). Luego:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

Abre `http://localhost` — redirige a `/login`. `./vendor/bin/sail artisan …`
reemplaza a `php artisan …` para todo (son el mismo contenedor `laravel.test`).

**Requiere Docker Desktop corriendo** (en Windows, con WSL2 habilitado).

## Alternativa sin Docker (PHP/Composer instalados directo en tu máquina)

Si no tienes Docker a la mano todavía, el proyecto corre igual de bien con
PHP y PostgreSQL instalados directo en tu máquina — es el camino que se usó
para validar todo lo de abajo antes de tener Sail armado, y sigue siendo
válido para probar rápido:

- PHP 8.3+ con extensiones habituales de Laravel (`pdo_pgsql`, `mbstring`,
  `bcmath`, etc.) y Composer 2 — por ejemplo con [Laravel Herd](https://herd.laravel.com) en Windows/Mac.
  PostgreSQL 15 instalado localmente.
- No se requiere Node/npm: la interfaz web usa Tailwind CSS y Alpine.js por
  CDN (sin build de Vite), para no depender de un paso extra de compilación
  durante la demo.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Deja `DB_HOST=127.0.0.1` tal cual (el valor por defecto en `.env.example`
está pensado para este camino). Crea la base de datos vacía
(`createdb acs_exam_web` o equivalente) y luego:

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

## Documentación técnica completa

Para arquitectura a detalle, el esquema de base de datos completo, rutas/
controladores/vistas, y el porqué de cada decisión que se desvía de los
manuales oficiales, ver la carpeta [`docs/`](docs/README.md).

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

## Desviaciones conocidas respecto al stack congelado (MPW-V2 Tabla 2)

Documentado explícitamente para que no parezca un descuido — recomendable
pasarlo por un Formato DE-06 (Registro de cambios) con su justificación:

- **Laravel 13.x en vez de la versión 11.x congelada en el manual.**
  Verificado contra Packagist (no es una suposición): a día de hoy, **toda
  la línea 11.x publicada (de 11.31.0 a 11.57.0) tiene advisories de
  seguridad activos sin parche disponible en esa rama** (Composer la
  bloquea por default — para instalarla de todos modos habría que
  deshabilitar explícitamente ese bloqueo de seguridad). Usar 13.x, la
  versión estable más reciente y sin esos advisories, es la decisión más
  defendible para un proyecto de Auditoría de Sistemas: entre "cumplir la
  versión congelada" y "no tener vulnerabilidades conocidas sin parchar",
  se prefirió lo segundo.
- **Autenticación: `AuthService`/`LoginController` a mano, no Laravel
  Fortify.** El resultado funcional es el mismo (login + 2FA de 6 dígitos
  por correo, igual que especifica MPM-V2 §6.6.1), pero no está construido
  sobre el paquete Fortify que manda el manual. Se decidió no migrarlo
  bajo presión de la fecha de entrega: es la pieza con más riesgo de
  romper algo que ya funciona, para un beneficio principalmente de
  conformidad con el manual, no funcional. Pendiente de decidir con el
  equipo si se migra después.
- **Laravel Reverb está instalado y su contenedor corre** (`compose.yaml`,
  config/reverb.php, config/broadcasting.php, routes/channels.php), pero
  ninguna funcionalidad del demo emite eventos en tiempo real todavía
  (monitoreo en vivo de alumnos conectados, etc.) — eso seguiría pendiente
  de construirse sobre esta base.
- **Cola/sesión/caché:** se dejaron en `sync`/`file`/`file` en vez de
  `database` (el manual pide todo sobre PostgreSQL, sin Redis, pero sin
  usar Redis tampoco con los drivers nativos de archivo). Para la demo no
  hay diferencia funcional; migrarlo implica 2-3 migraciones adicionales
  para las tablas de sesión/cola/caché con prefijo `acs_`.

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
