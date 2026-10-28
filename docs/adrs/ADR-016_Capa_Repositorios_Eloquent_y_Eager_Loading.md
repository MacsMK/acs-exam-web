# ADR-016 — Capa de Repositorios Eloquent y Activación de `Model::preventLazyLoading()`

> **Estado:** Aceptado  
> **Fecha:** 27 de Octubre de 2026  
> **Decisores:** `db-eloquent-architect`, `backend-laravel-specialist`  
> **Normativa de Referencia:** Manual `MPW-V2` (§2.4, §5.4, §5.5, §6.7)

---

## 1. Contexto y Problemática
En la implementación inicial del backend:
1. Las consultas a base de datos estaban dispersas entre controladores web y controladores de API v1, duplicando la lógica de filtrado y carga de relaciones.
2. Existían consultas dentro de ciclos (`foreach`/`map`), generando problemas de **N+1** (por ejemplo, en `GradeController::teacherGradeRows` y en vistas Blade).
3. No se utilizaba la protección `Model::preventLazyLoading()` en el proveedor de servicios, lo que permitía que accesos inadvertidos a propiedades de relaciones dispararan consultas SQL implícitas en tiempo de renderizado de vistas.

---

## 2. Decisión de Arquitectura
1. **Creación de la Capa de Repositorios (`app/Repositories/`):**
   - Se definieron interfaces formales en `app/Repositories/Contracts/`:
     - `ExamRepositoryInterface`
     - `GroupRepositoryInterface`
     - `QuestionRepositoryInterface`
     - `AttemptRepositoryInterface`
   - Se implementaron las clases Eloquent en `app/Repositories/Eloquent/`:
     - `EloquentExamRepository`
     - `EloquentGroupRepository`
     - `EloquentQuestionRepository`
     - `EloquentAttemptRepository`
2. **Encapsulamiento de Consultas con Eager Loading Obligatorio:**
   - Todos los métodos de los repositorios usan explícitamente `with()`, `withCount()` y `paginate()` a nivel SQL, prohibiendo la paginación de colecciones en memoria (`Collection::slice()`).
3. **Registro en Contenedor IoC y Activación de `preventLazyLoading`:**
   - En `App\Providers\AppServiceProvider::register()`, se vincularon las interfaces con sus implementaciones Eloquent.
   - En `App\Providers\AppServiceProvider::boot()`, se activó:
     ```php
     Model::preventLazyLoading(! $this->app->isProduction());
     ```
     garantizando que cualquier consulta no precargada adecuadamente falle de inmediato en los entornos de desarrollo y pruebas automatizadas (`LazyLoadingViolationException`), erradicando de raíz el N+1.

---

## 3. Consecuencias
- **Positivas:** Reducción drástica del número de consultas a PostgreSQL; cero N+1 tanto en Web como en API v1; contratos desacoplados que facilitan pruebas unitarias y mocking.
- **Compromisos (Trade-offs):** Todo nuevo endpoint o vista que acceda a relaciones anidadas (`$examQuestion->question->questionable`) debe incluir explícitamente `with()` o `loadMissing()`, lo cual es forzado en tiempo de desarrollo por el framework.
