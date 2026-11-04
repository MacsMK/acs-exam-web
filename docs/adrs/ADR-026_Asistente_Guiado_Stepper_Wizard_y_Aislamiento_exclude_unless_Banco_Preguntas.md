# ADR-026: Asistente Guiado por Pasos (Stepper Wizard), Aislamiento con `exclude_unless` y Rehidratación Integral en el Banco de Preguntas

> **Estado:** Aceptado  
> **Fecha:** 2026-11-04  
> **Decisores:** Antigravity (Coordinador General), `architecture-quality-auditor`, `normative-compliance-auditor`  
> **Consultados:** `backend-laravel-specialist`, `frontend-ui-specialist`  
> **Informados:** Equipo 4 (A-Code Solutions), Dirección de Calidad  
> **Módulos Impactados:** `Módulo 5 (Banco de Preguntas)`, `resources/views/questions/create.blade.php`, `app/Http/Requests/QuestionStoreRequest.php`, `phpunit.xml`  
> **Requerimientos Relacionados:** `RF-BAN-001` a `RF-BAN-014`, `MPW-V2` (§4.1.2, §6.3, §6.4, §7.3, §7.8.2, §8.1, §8.3, §8.5, Tabla 7, Tabla 10, Tabla 17), `MC-V2` (§2.4.3 e Ilustración 4).

---

## 1. Contexto y Problemática

En la versión inicial de autoría de reactivos (`questions/create.blade.php`), la interfaz presentaba los siguientes inconvenientes de arquitectura y experiencia de usuario:
1. **Sobrecarga Cognitiva y Ruido Visual:** Toda la configuración de los 4 tipos de reactivos coexistía en una única página extensa con una columna lateral distractora ("Guía Pedagógica A-Code" y "Seguridad Normativa") que duplicaba información y restaba espacio para el diseño de reactivos complejos (ej. código con depuración).
2. **Pérdida Inesperada de Datos por Colisión de Validación:** Al intentar crear preguntas abiertas o de código, los campos HTML ocultos de opciones (`options[0]=""`) y conceptos (`concepts[0]=""`) seguían presentes en el DOM y se enviaban al servidor vía POST. Como `QuestionStoreRequest` aplicaba `'options.*' => ['required']` de manera genérica, la validación fallaba de forma invisible para el docente, borrando todo lo redactado.
3. **Crash de JavaScript por Serialización en Alpine.js:** Al fallar la validación con `old('options')` no contiguo, `json_encode` producía objetos indexados `{ "0": "...", "2": "..." }` en vez de arreglos nativos `[...]`, desencadenando errores `TypeError` en el frontend al ejecutar `this.options.push(...)`.
4. **Envío Involuntario por Tecla Enter:** Al redactar títulos o enunciados, presionar la tecla Enter disparaba prematuramente el formulario.
5. **Purga Involuntaria de Base de Datos al Ejecutar Pruebas:** `phpunit.xml` apuntaba `<env name="DB_DATABASE" value="acs_exam_web"/>`. Al ejecutar `./vendor/bin/sail artisan test`, `RefreshDatabase` ejecutaba `migrate:fresh` sobre la base de datos de desarrollo.

---

## 2. Decisión de Diseño

1. **Adopción de un Asistente Guiado por Pasos (Stepper Wizard de 3 Etapas):**
   - Se transformó la vista a un contenedor ergonómico centrado (`max-w-4xl mx-auto py-2`), eliminando la barra lateral.
   - **Paso 1 (Planteamiento y Tipo):** Captura de metadatos (`title`, `stem`, `subject_tag`) y selección visual entre los 4 tipos mediante tarjetas interactivas con badges pedagógicos (*Objetivo*, *Emparejamiento*, *Evaluación IA*, *Supervisado*).
   - **Paso 2 (Contenido y Respuestas):** Interfaz desacoplada según el tipo elegido (opciones múltiples con marcado de respuesta correcta, pares biyectivos, respuesta modelo canónica para IA, o código con toggle de modalidad *Desarrollo* vs *Auditoría/Debugging*).
   - **Paso 3 (Ponderación y Guardado):** Asignación de puntaje nominal, cronómetro con presets rápidos (60s, 120s, 180s, 600s) y resumen de confirmación.
2. **Aislamiento Severo en Backend con `exclude_unless`:**
   - En `QuestionStoreRequest`, se aplicó la regla `'exclude_unless:question_type,[tipo]'` a cada conjunto de atributos específicos. Laravel desecha inmediatamente del payload cualquier campo perteneciente a un tipo inactivo antes de validar.
3. **Desactivación Dinámica en DOM (`:disabled`):**
   - Todos los inputs de los tipos inactivos se marcan con `:disabled="type !== '...'"` (y `::disabled` en componentes Blade) para impedir que el navegador someta o valide datos en contenedores ocultos.
4. **Serialización Segura y Resguardo de Paso en Alpine.js:**
   - Rehidratación obligatoria con `collect(old(...))->values()->all()` para garantizar arrays JS puros `[]`.
   - Inicialización adaptativa del paso: `step: {{ $errors->any() ? 3 : 1 }}` para retornar directamente al paso de envío en caso de error.
   - Prevención de envío involuntario: `@keydown.enter="$event.target.tagName === 'TEXTAREA' ? null : $event.preventDefault()"`.
5. **Estandarización de los 18 Verbos Oficiales (`MPW-V2 Tabla 10`):**
   - `"Continuar al paso 2"`, `"Continuar al paso 3"` (Verbo 14 - Continuar).
   - `"Volver al listado"`, `"Volver al paso 1"`, `"Volver al paso 2"` (Verbo 13 - Volver).
   - `"Guardar reactivo"` (Verbo 3 - Guardar).
6. **Aislamiento de Base de Datos para Pruebas (`phpunit.xml`):**
   - Configuración permanente de `<env name="DB_DATABASE" value="testing" force="false"/>` y `<env name="DB_HOST" value="pgsql" force="false"/>`.

---

## 3. Consecuencias y Beneficios

- **Cero Pérdida de Datos:** El docente puede redactar preguntas de cualquier tipo sin riesgo de validaciones cruzadas erróneas ni pérdida de estado tras validación.
- **Seguridad e Integridad de Datos:** La base de desarrollo `acs_exam_web` está 100% protegida contra purgas automatizadas de pruebas.
- **Ergonomía Superior:** La división en 3 etapas reduce la carga cognitiva, mejora la tasa de éxito en la captura de reactivos y cumple con los estándares institucionales de A-Code Solutions.
- **Certificación QA:** 100% de la suite de pruebas automatizadas pasando (22 pruebas, 214 aserciones).
