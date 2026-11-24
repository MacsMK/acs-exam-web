# Reglas de base de datos para la web · MBD-V3

**Fuente.** [Manual de Base de Datos (MBD-V3)](</home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Base de Datos (MBD-V3).pdf>). Versión V3 del 20/11/2026 según la tabla 1, p. 6. Se revisaron las 44 páginas físicas, las 17 tablas y las 7 ilustraciones de formatos. Las páginas citadas corresponden al visor PDF y coinciden con el pie de página del contenido. El índice contiene localizadores desactualizados, detallados al final.

**Lectura.** Los identificadores MBD son referencias del inventario. «Regla» designa una disposición del manual. «Condicional» identifica su supuesto de aplicación. «Proceso» pide evidencia documental, operativa o de una actuación. «Referencia» recoge una definición, ejemplo o alcance que ayuda a interpretar las demás filas. Los campos de un mismo formato se agrupan por bloque y conservan su identificación. En las filas con ejemplos, los nombres concretos ilustran la convención salvo que el manual los establezca expresamente para el proyecto.

## Alcance, gestor y entorno

| ID | Carácter | Disposición o dato que debe conservarse al evaluar | Origen |
|---|---|---|---|
| MBD-001 | Referencia de alcance | En el Evaluador de exámenes, la BD objeto del manual es `acs_exam_web` en `acs-exam-web`. La API v1 pertenece al sistema web. El almacenamiento de sesión del dispositivo móvil queda fuera de esta auditoría. | MBD-V3 §§1.1–1.2, p. 7 |
| MBD-002 | Proceso | Aplicar MC a la elaboración, actualización, presentación, identificación, nomenclatura, versiones y conservación. Vincular con MD para DE-01, DE-06, Kanban, firmas y evidencias, y con MPW para modelos, migraciones, seeders, variables de entorno y nombres de tablas. Resolver diferencias MBD–MPW mediante gestión de cambios de MD. | MBD-V3 §§1–1.3, pp. 7–9, tabla 2 |
| MBD-003 | Proceso | Al seleccionar el gestor, valorar como mínimo modelo de datos, tipos requeridos incluido JSON, transacciones e integridad (PK, FK, unicidad y verificación), controladores nativos, concurrencia y rendimiento previstos, herramientas para generar/verificar/restaurar respaldos, costo, licencia, documentación y comunidad de soporte. | MBD-V3 §2.1, pp. 9–10, tabla 3 |
| MBD-004 | Proceso | Documentar la decisión en los RNF del proyecto. Si cambia un gestor establecido o aprobado, aplicar gestión de cambios de MD. El manual asocia la sustitución previa de MySQL con RNF-TEC-003 y CAM-003. | MBD-V3 §§2.1–2.2, p. 11 |
| MBD-005 | Regla | PostgreSQL 15 o superior, seleccionado por su soporte JSONB con búsqueda indexada, transacciones, integridad referencial y restricciones. | MBD-V3 §2.2, p. 11 |
| MBD-006 | Regla de entorno | Entorno de desarrollo mediante Docker y Laravel Sail, definido en `compose.yaml`. Servicio `pgsql`, imagen oficial `postgres:15`, volumen persistente `sail-pgsql` y base independiente para pruebas automatizadas creada al inicializar el contenedor. | MBD-V3 §2.3, p. 11 |
| MBD-007 | Herramientas del proyecto | Controlar la estructura con migraciones en `database/migrations`, la carga con seeders en `database/seeders`, y ejecutar por Artisan (`migrate`, `migrate:rollback`, `migrate:status`, `db:seed`). Usar `psql` del contenedor para consulta/verificación y `pg_dump` para respaldos. | MBD-V3 §2.4, pp. 11–12, tabla 4 |

## Nomenclatura

| ID | Carácter | Disposición o ejemplo | Origen |
|---|---|---|---|
| MBD-008 | Regla | Todos los objetos de BD se nombran en inglés, en minúsculas y en `snake_case`. El manual acepta, entre otros, `first_name`, `access_folio`, `payment_status`. | MBD-V3 §§3–3.1, p. 13, tabla 5 |
| MBD-009 | Referencia | El glosario del manual y de los formatos recoge BD, DBA, SQL, DER, PK, FK, UQ y JSONB. PK identifica clave primaria, FK clave foránea y UQ unicidad. | MBD-V3 §3.2, p. 14, tabla 6 |
| MBD-010 | Regla | Tablas creadas por el equipo en plural con prefijo `acs_`. El manual ilustra la regla con `acs_users`, `acs_groups`, `acs_exams`, `acs_student_attempts`. | MBD-V3 §3.3, p. 15, tabla 7 |
| MBD-011 | Regla condicional | Para las tablas intermedias muchos-a-muchos se usa `acs_` seguido de los dos nombres en singular, en orden alfabético. El manual ilustra la regla con `acs_exam_group`, `acs_exam_question`, `acs_group_student`. | MBD-V3 §3.3, p. 16, tabla 8 |
| MBD-012 | Excepción expresa | Las tablas de uso interno que genera el framework conservan su nombre estándar, como `jobs` y `failed_jobs`. | MBD-V3 §3.3, p. 17 |
| MBD-013 | Regla | Nombres de columnas acordes con su propósito. PK `id`, entero de 64 bits autoincremental `BIGSERIAL` mediante `$table->id()`. | MBD-V3 §3.4, p. 17 |
| MBD-014 | Regla | FK con el nombre singular de la entidad y sufijo `_id`. Si la tabla agrupa roles, usar el rol de esa relación, como `teacher_id` y `student_id` para `acs_users`. | MBD-V3 §3.4, p. 17 |
| MBD-015 | Regla | Prefijos de booleanos `is_` o `has_`. Fechas y horas terminan en `_at`. Las columnas `created_at`, `updated_at` y, para eliminación lógica, `deleted_at` se generan con los métodos del framework. | MBD-V3 §3.4, p. 17 |
| MBD-016 | Regla condicional | JSONB que almacene la estructura de un elemento termina en `_payload`, por ejemplo `options_payload` y `answer_payload`. | MBD-V3 §3.4, p. 17 |
| MBD-017 | Regla | Índices, restricciones de unicidad y FK toman el nombre generado por el framework con tabla, columnas y tipo. Si excede el límite del gestor, se asigna explícito con esa estructura. La tabla 9 ilustra `acs_users_email_unique`, `acs_questions_teacher_id_subject_id_index` y `acs_student_answers_attempt_question_unique`. | MBD-V3 §3.5, pp. 17–18, tabla 9 |
| MBD-018 | Regla condicional | Si el proyecto requiere estos objetos, nombrar vista `vw_` + descripción, procedimiento `sp_` + verbo + objeto, función `fn_` + verbo + objeto y trigger `trg_` + tabla + momento + evento. La aplicación a este proyecto está acotada por MBD-036. | MBD-V3 §3.6, p. 19, tabla 10 y §4.2.5, p. 24 |

## Diseño lógico, diseño físico y carga

| ID | Carácter | Disposición o ejemplo | Origen |
|---|---|---|---|
| MBD-019 | Regla y proceso | El diseño lógico y físico atiende requerimientos validados y garantiza integridad y consistencia. Cada entidad representa un objeto o concepto del dominio, tiene PK y atributos sin valores compuestos ni repetidos. Crear entidades, atributos o relaciones requiere registro y aprobación. | MBD-V3 §§4–4.1.1, p. 20 |
| MBD-020 | Regla | Explicitar la cardinalidad 1:1, 1:N o N:M, la tabla padre y la hija que tiene la FK. Implementar N:M con tabla intermedia. | MBD-V3 §4.1.2, p. 20 |
| MBD-021 | Regla | Normalizar al menos hasta tercera forma normal. Evitar redundancia, dependencias parciales y transitivas. | MBD-V3 §4.1.3, p. 20 |
| MBD-022 | Regla condicional | Permitir JSONB únicamente para estructuras variables según el tipo, copias inmutables de lo presentado al estudiante durante un intento, o registros de auditoría y metadatos de servicios externos cuyo contenido no se consulta como columnas independientes. Describir cada uso en el diccionario de datos. | MBD-V3 §4.1.3, p. 20 |
| MBD-023 | Proceso | Elaborar y conservar el DER como artefacto de diseño según MD. Cada entidad es un rectángulo con tabla, columnas y marcas PK/FK. Las líneas llevan cardinalidad en ambos extremos. Actualizar por cada cambio estructural ejecutado y registrado en BD-01. | MBD-V3 §4.1.4, p. 21 |
| MBD-024 | Regla | Elegir tipos según naturaleza y uso del dato. Definir una longitud máxima justificada para las cadenas de texto y hacer coincidir el tipo de la FK con el de la PK de referencia. | MBD-V3 §4.2.1, p. 21 |
| MBD-025 | Estándar de tipos | `BIGSERIAL`/`BIGINT` y `id()`/`foreignId()` para claves. `VARCHAR(n)` y `string(nombre, n)` para texto acotado. `TEXT` y `text()`/`longText()` para texto extenso. `BOOLEAN` y `boolean()` para valores lógicos. | MBD-V3 §4.2.1, pp. 21–22, tabla 11 |
| MBD-026 | Estándar de tipos | `SMALLINT`/`INTEGER` y `smallInteger()`/`integer()` para cantidades, contadores y segundos. `NUMERIC(p,s)` y `decimal(nombre, p, s)` con precisión/escala (6,2) en puntos/calificaciones y (10,2) en importes. | MBD-V3 §4.2.1, p. 22, tabla 11 |
| MBD-027 | Estándar de tipos | `TIMESTAMP` mediante `timestamp()`, `timestamps()` o `softDeletes()` para fecha/hora. `JSONB` mediante `jsonb()` para los usos de §4.1.3. `VARCHAR` con `CHECK` mediante `enum()` para conjuntos cerrados. | MBD-V3 §4.2.1, p. 22, tabla 11 |
| MBD-028 | Regla | Validar en la aplicación los valores enteros que deban ser no negativos. El manual explica que los métodos `unsigned` del framework producen enteros con signo en PostgreSQL. | MBD-V3 §4.2.1, p. 23 |
| MBD-029 | Regla | Cada tabla tiene una sola clave primaria. Toda FK se define explícitamente mediante `foreignId()->constrained()` e indica acciones al actualizar y eliminar. | MBD-V3 §4.2.2, p. 23 |
| MBD-030 | Regla condicional | Al eliminar, usar `CASCADE` para dependientes sin sentido por separado, `RESTRICT` para evidencia que se debe preservar y `SET NULL` para relaciones opcionales cuyo hijo se conserva. El manual ilustra, respectivamente, integrantes de grupo, intentos/respuestas y materia de una pregunta o usuario de auditoría. | MBD-V3 §4.2.2, p. 23, tabla 12 |
| MBD-031 | Regla | Al actualizar, `CASCADE` salvo en relaciones con `SET NULL` al eliminar, que mantienen `NO ACTION` al actualizar. | MBD-V3 §4.2.2, p. 24 |
| MBD-032 | Regla | Aplicar unicidad a identificadores únicos, como correo, folio y combinaciones no repetibles. | MBD-V3 §4.2.3, p. 24 |
| MBD-033 | Regla | Crear índices solo para columnas frecuentes en filtros, uniones u ordenamientos. Evitar redundancias y evaluar el índice de las FK según consultas frecuentes. | MBD-V3 §4.2.3, p. 24 |
| MBD-034 | Regla condicional | Cuando deba ocultarse un registro y conservar sus relaciones, usar eliminación lógica mediante `deleted_at`. En el proyecto se aplica a preguntas del banco para conservar copias incorporadas a exámenes. | MBD-V3 §4.2.4, p. 24 |
| MBD-035 | Regla | La lógica de negocio reside en la capa de servicios de la aplicación. | MBD-V3 §4.2.5, p. 24 |
| MBD-036 | Alcance y condición | El manual declara que el Evaluador de exámenes prescinde de vistas, procedimientos almacenados, funciones y triggers de BD. Cuando un proyecto sí los requiera, justificar, crear por migración, aplicar la nomenclatura y documentar propósito, entradas, salidas, condiciones de ejecución y tablas. Registrar su alta, cambio o baja en BD-01. | MBD-V3 §4.2.5, p. 24 |
| MBD-037 | Regla | Cargar catálogos operativos, como paquetes y aviso de privacidad, y datos de demostración mediante seeders que puedan repetirse sin duplicar registros. Excluir datos de demostración en producción. | MBD-V3 §4.3, pp. 24–25 |

## Control de cambios y diccionario de datos

| ID | Carácter | Disposición o ejemplo | Origen |
|---|---|---|---|
| MBD-038 | Regla | Todo cambio estructural se implementa por migración Laravel registrada en el repositorio. El manual prohíbe modificar directamente la estructura en cualquier entorno. | MBD-V3 §5.1, p. 25 |
| MBD-039 | Regla | Incluir un `down()` reversible. Tras integrar una migración a la rama principal, conservarla y hacer modificaciones posteriores mediante otra migración. | MBD-V3 §5.1, p. 25 |
| MBD-040 | Regla | Incluir en la migración la cabecera de prólogo que establece MPW y referenciar el folio BD-01 y los requerimientos relacionados. | MBD-V3 §5.1, p. 25 |
| MBD-041 | Proceso | Antes de crear, modificar o eliminar tablas, columnas, claves, relaciones, índices, restricciones, vistas, procedimientos, funciones o triggers, registrar la solicitud BD-01 y obtener la aprobación del responsable técnico del proyecto. | MBD-V3 §5.2, p. 25 y §9.1.1, p. 35 |
| MBD-042 | Proceso condicional | Si el cambio altera significativamente un requisito, diseño u otro elemento establecido o aprobado, aplicar también DE-06 y referenciar el `CAM-###` en BD-01. | MBD-V3 §5.2, pp. 25–26 |
| MBD-043 | Regla | Folio BD-01 único `BD01-AAAA-###` con año y consecutivo de tres dígitos irrepetible. Añadir el folio tras la marca de tiempo en el nombre de la migración. El ejemplo usa `2026_10_26_140000_bd01_2026_006_create_acs_groups_table.php`. | MBD-V3 §5.3, p. 26, tabla 13 |
| MBD-044 | Proceso | Tras la aprobación, respaldar previamente si se conservan datos, ejecutar la migración en su entorno y verificar el resultado. Registrar fecha, entorno, commit y resultado en BD-01. Actualizar BD-02 de tablas afectadas y el DER cuando corresponda. Vincular la actividad Kanban con el folio BD-01. | MBD-V3 §5.4, p. 27 |
| MBD-045 | Proceso condicional | Revertir por `down()` con `php artisan migrate:rollback`. Si la reversión implica pérdida de información, restaurar el respaldo previo. Registrar la reversión en el BD-01 original. | MBD-V3 §5.5, p. 27 |
| MBD-046 | Proceso | Mantener un BD-02 vigente por cada tabla. Identificar nombre, descripción, requerimientos y archivo de migración de origen. Actualizar las fichas afectadas por cada BD-01 ejecutado, indicando fecha y autor. | MBD-V3 §§6–6.2, pp. 27–29 y §§9.2.1–9.2.3, pp. 38–40 |
| MBD-047 | Proceso | Documentar por columna nombre, tipo PostgreSQL y longitud o precisión, nulabilidad `Sí`/`No`, valor por defecto o guion, clave `PK`/`FK`/`UQ` o guion y descripción funcional. Para tablas propias del framework bastan la descripción general y sus columnas, con dispensa de descripción funcional por columna. | MBD-V3 §§6.1–6.2, pp. 27–29 y §9.2.3, p. 40, campos 5–10 |
| MBD-048 | Proceso | Documentar columna FK, tabla referenciada y acciones al actualizar y eliminar. Para cada índice o restricción, indicar nombre, tipo y columnas. | MBD-V3 §6.1, pp. 27–28 y §9.2.3, p. 40, campos 11–12 |
| MBD-049 | Ejemplo documental | La tabla 14 ejemplifica el diccionario de `acs_groups` con `id`, `teacher_id`, `name` (VARCHAR(120)), `access_folio` (VARCHAR(12), UQ), `is_closed` (false) y `closed_at` (nullable). Se presenta como ejemplo de registro de columnas. | MBD-V3 §6.1, p. 28, tabla 14 |

## Seguridad y permisos

| ID | Carácter | Disposición | Origen |
|---|---|---|---|
| MBD-050 | Regla | Credenciales de conexión en el `.env` de cada entorno, excluido del control de versiones. Solo valores de ejemplo en `.env.example`. | MBD-V3 §7.1, p. 29 |
| MBD-051 | Regla por entorno | El usuario del contenedor Sail se usa exclusivamente en desarrollo local. La aplicación en producción debe conectarse con usuario distinto del superusuario del gestor. | MBD-V3 §7.1, p. 29 |
| MBD-052 | Regla | Conceder permisos por roles con mínimo privilegio. Nombrar roles en inglés y `snake_case`. | MBD-V3 §7.2, p. 29 |
| MBD-053 | Regla productiva | `admin_role` corresponde al DBA con todos los privilegios, incluidos crear/modificar/eliminar objetos y ejecutar migraciones y respaldos. `app_role` para la conexión del sistema, con SELECT, INSERT, UPDATE y DELETE sobre tablas del proyecto y sin modificaciones de estructura. `audit_role` para auditores o personal de consulta, con SELECT sobre tablas autorizadas. | MBD-V3 §7.2, pp. 29–30, tabla 15 |
| MBD-054 | Proceso | Registrar en BD-03, con solicitud y aprobación previas a la operación, la creación, modificación o eliminación de usuarios/roles y la asignación o revocación de permisos del gestor. | MBD-V3 §7.2, p. 31 y §9.3.1, p. 40 |
| MBD-055 | Regla operativa | Las contraseñas de usuarios **de base de datos** deben tener al menos 12 caracteres con mayúsculas, minúsculas y números. Mantenerlas distintas por entorno y fuera del repositorio y de mensajería. Cambiarlas inmediatamente si quedan expuestas. | MBD-V3 §7.3, p. 31 |
| MBD-056 | Regla | Para usuarios **del sistema**, almacenar las contraseñas únicamente con el mecanismo de hash indicado por MPW. | MBD-V3 §7.4, p. 31 y MPW-V3 §1.3.1, p. 19 |
| MBD-057 | Regla | Limitar los datos personales a los necesarios para los requerimientos. Registrar las operaciones relevantes en `acs_audit_logs`. | MBD-V3 §7.4, p. 31 |

## Respaldo y recuperación

| ID | Carácter | Disposición | Origen |
|---|---|---|---|
| MBD-058 | Proceso | El responsable técnico o el DBA designado genera, verifica y restaura los respaldos. | MBD-V3 cap. 8, p. 31 |
| MBD-059 | Proceso | Generar un respaldo completo antes de cambios estructurales en entornos con información que deba conservarse, antes de cada entrega formal o evaluación, y programarlo en producción con la frecuencia establecida para ese entorno. | MBD-V3 §8.1, pp. 31–32 |
| MBD-060 | Regla y proceso | Guardar respaldos fuera del repositorio y restringir el acceso. Nombrar los archivos `<base_de_datos>_backup_AAAAMMDD_HHMM.sql.gz` y comprimir con Gzip. El ejemplo es `acs_exam_web_backup_20261124_0800.sql.gz`. | MBD-V3 §§8.1–8.2, p. 32, tabla 16 |
| MBD-061 | Proceso | En Sail el procedimiento documentado usa `pg_dump` en el servicio `pgsql` y comprime con gzip. Después, verificar que el archivo contenga datos y pueda descomprimirse sin error. | MBD-V3 §8.3, p. 33 |
| MBD-062 | Proceso condicional | El manual admite restaurar ante pérdida o daño de información, fallos en pruebas o despliegues, o reversión de un cambio no aprobado. | MBD-V3 §8.4, p. 33 |
| MBD-063 | Proceso condicional | Identificar la copia que corresponde al estado buscado, respaldar el estado actual, restaurar, verificar con `php artisan migrate:status` y consultar tablas afectadas. Registrar la operación `Restauración` en BD-01. El comando Sail documentado usa `gunzip -c` y `psql` en el contenedor. | MBD-V3 §8.4, p. 33 |

## Formatos, conservación y excepciones

| ID | Carácter | Disposición y campos | Origen |
|---|---|---|---|
| MBD-064 | Proceso | Identificar plantillas conforme a MC. Nombrar y conservar formatos llenos conforme al capítulo «Formatos de desarrollo» de MD. BD-01 controla cambios y restauraciones, BD-02 la estructura por tabla y BD-03 usuarios, roles y permisos. Obtener las firmas manuscritas especificadas para BD-01 y BD-03. | MBD-V3 cap. 9, pp. 33–34, tabla 17 |
| MBD-065 | Proceso | BD-01, campos 1–9. Folio, fecha de solicitud, solicitante, referencias relacionadas (RF/RNF, CAM-### y ACT-[BLOQUE]-###), todos los tipos de objeto involucrados, operación, nombres afectados, descripción y justificación. Se puede seleccionar Creación, Modificación, Eliminación o Restauración. El formato permite marcar Tabla, Columna, Llave primaria, Llave foránea, Índice, Restricción, Vista, Función, Trigger y Procedimiento almacenado. | MBD-V3 §§9.1.1–9.1.3, pp. 35–37, ilustración 1 |
| MBD-066 | Proceso | BD-01, campos 10–15. Archivo de migración o script, procedimiento de reversión, nombre del respaldo previo (`No aplica` si el entorno carece de datos que conservar), impacto en datos, decisión `Aprobado`/`Rechazado` y motivo del rechazo (puede quedar vacío si se aprueba). | MBD-V3 §§9.1.2–9.1.3, pp. 36–37, ilustración 2 |
| MBD-067 | Proceso | BD-01, campos 16–22. Fecha, entorno (`Desarrollo`, `Pruebas`, `Producción`), commit o versión, resultado `Exitoso`/`Revertido`, diccionario actualizado `Sí`/`No aplica`, observaciones y firmas manuscritas del solicitante y del responsable técnico que aprueba. El bloque de firmas pide nombre, rol, firma y fecha. | MBD-V3 §§9.1.2–9.1.3, pp. 36–38, ilustraciones 2–3 |
| MBD-068 | Proceso | BD-02, campos 1–14. Aplicar los criterios MBD-046 a MBD-048. La plantilla tiene bloques de datos de tabla, columnas, relaciones, índices/restricciones y actualización. En el último figuran fecha de última actualización y persona que elaboró o actualizó la ficha. | MBD-V3 §§9.2.1–9.2.3, pp. 38–40, ilustraciones 4–5 |
| MBD-069 | Proceso | BD-03, campos 1–9. Folio único `BD03-AAAA-###`, fecha, solicitante, entorno (`Desarrollo`, `Pruebas`, `Producción`), objeto `Usuario`/`Rol`, operación (Creación, Modificación, Eliminación, Asignación de permisos o Revocación de permisos), nombre según nomenclatura, rol asignado (puede quedar vacío en una operación sobre un rol) y justificación. | MBD-V3 §§9.3.1–9.3.3, pp. 40–43, ilustración 6 |
| MBD-070 | Proceso | BD-03, campos 10–18. Objeto (tabla, esquema u otro), permisos involucrados, acción `Otorgar`/`Revocar`, decisión `Aprobado`/`Rechazado`, motivo del rechazo (puede quedar vacío si se aprueba), fecha de ejecución, persona que ejecutó, observaciones y firmas manuscritas del solicitante y responsable técnico que aprueba. El bloque de firmas pide nombre, rol, firma y fecha. | MBD-V3 §§9.3.2–9.3.3, pp. 41–43, ilustraciones 6–7 |
| MBD-071 | Proceso | Si cambian gestor, herramientas o prácticas, actualizar el MBD bajo control documental de MC y preservar la validez e integridad de las evidencias generadas con versiones anteriores. | MBD-V3 §10.1, p. 43 |
| MBD-072 | Proceso condicional | Una excepción a una disposición que no aplique al proyecto se puede tramitar según «Excepciones al proceso» de MD. Conservar disposición afectada, motivo y tratamiento alternativo. | MBD-V3 §10.2, pp. 43–44 |
| MBD-073 | Proceso | Conservar los BD-01/02/03 completados, los respaldos y el DER conforme al almacenamiento definido en MC. Organizar los formatos en la carpeta de base de datos del proyecto, con una subcarpeta por formato. El respaldo se conserva además fuera del repositorio según §8.1. | MBD-V3 §10.3, p. 44 y §8.1, p. 32 |

## Decisiones y observaciones documentales

- **Requiere decisión si se prevé un simulacro preventivo.** RNF-REC-001 pide definir antes de la puesta en operación las comprobaciones de restauración. MBD §8.4 limita los supuestos para restaurar. Conviene precisar si una restauración de prueba en una base aislada y sana entra entre ellos, y cómo se documenta. Las fuentes sí permiten definir el procedimiento. [Lista maestra, hoja «Lista maestra», D239](</home/laambws/Descargas/manuales/acode-solutions/09_Nuevos_Requerimientos/Lista_maestra_requerimientos.xlsx>) y MBD-V3 §§8.3–8.4, p. 33.
- **Dato operativo por definir.** El manual delega al entorno productivo la frecuencia de los respaldos. Para verificarla se necesita el calendario aprobado para ese entorno. No especifica un periodo de retención, RPO ni RTO numéricos. MBD-V3 §§8.1–8.4, pp. 31–33 y RNF-REC-001, D239.
- **Localizadores del índice.** El índice sitúa §8.4 en p. 34, cap. 9 en p. 36 y §10.3 en p. 47. El cuerpo los contiene en pp. 33, 33 y 44, respectivamente. Las ilustraciones 1–7 figuran en pp. físicas 35–36, 39 y 41–42. La discrepancia es del documento y se conserva. MBD-V3, índices en pp. 3–6 y cuerpo pp. 33–44.
- **Delimitación útil para la revisión.** MPW §3.4.1, p. 38, usa «Toda tabla», pero MPW §3.2, p. 37, limita el prefijo a tablas creadas por el equipo y reconoce `jobs`/`failed_jobs` en §1.3.1, pp. 19–20. MBD §3.3, pp. 15–17, distingue tablas propias, pivotes y las generadas por el framework. El cotejo de nombres debe identificar primero el origen de la tabla.
- **Evidencia operativa.** La existencia del código puede sustentar migraciones o nomenclatura. Para dictaminar aprobaciones, roles efectivos, respaldos, restauraciones, DER vigente, Kanban y firmas se necesitan los registros correspondientes. El propio manual sitúa los respaldos fuera del repositorio. MBD-V3 §§4.1.4, 5.2–5.5, 7.2, 8.1–8.4, 9 y 10.3, pp. 21, 25–27, 29–34 y 44.

**Cobertura de lectura.** Portada, índices y versiones, pp. 1–6. Cap. 1, pp. 7–9. Cap. 2, pp. 9–12. Cap. 3, pp. 13–19. Cap. 4, pp. 20–25. Cap. 5, pp. 25–27. Cap. 6, pp. 27–29. Cap. 7, pp. 29–31. Cap. 8, pp. 31–33. Cap. 9, pp. 33–43. Cap. 10, pp. 43–44.
