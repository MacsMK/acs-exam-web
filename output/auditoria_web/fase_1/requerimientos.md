# Inventario de la lista maestra

Fuente única de este anexo: [Lista maestra, hoja «Lista maestra», A2:I244](</home/laambws/Descargas/manuales/acode-solutions/09_Nuevos_Requerimientos/Lista_maestra_requerimientos.xlsx>). El número de fila permite consultar el original. Se transcriben los 238 códigos previstos, nombres, descripciones y referencias. Los códigos agrupan las filas por módulo RF o categoría RNF, sin asignarles una prioridad. Los nombres de los módulos funcionales proceden de MPW-V3 §1.2, p. 18. Las categorías RNF se identifican con las siglas de la fuente. El nombre oficial desarrollado de cada una se registra en el DE-02 del proyecto (MD-V3 §4.4.1, p. 26).

La cabecera del libro dice «238 propuestas activas. Códigos previstos según DE-02; no implican validación.» No hay columnas de prioridad, código de aprobación DE-01 ni estado de validación por fila. En las tres columnas web se conservan las marcas originales de revisión por rol. Según A5, ✓ significa implementado al 100 %, ✗ significa no implementado o incompleto, N/A significa no aplica y el vacío significa sin revisar. El guion largo representa una celda vacía. En esta fase ninguna marca constituye un dictamen de la auditoría.

Las notas de la columna I se reproducen al final de cada grupo cuando existen. Pueden describir pruebas, decisiones y componentes móviles. Se conservan como antecedentes de la fuente. La columna G pertenece a la aplicación móvil y queda fuera de la evaluación solicitada.

| Módulo / categoría | Filas del inventario |
|---|---:|
| AUT · Cuentas y acceso | 14 |
| GRU · Grupos e incorporación | 21 |
| SUS · Paquetes y suscripciones | 15 |
| BAN · Banco de preguntas | 15 |
| EXA · Preparación y aplicación de exámenes | 57 |
| EVA · Evaluación de respuestas | 13 |
| IAR · Asistencia de inteligencia artificial | 14 |
| CAL · Periodos y calificaciones | 28 |
| REP · Resultados y reportes | 17 |
| ADM · Administración general | 18 |
| TEC · Categoría RNF | 4 |
| SEG · Categoría RNF | 11 |
| CON · Categoría RNF | 4 |
| PRI · Categoría RNF | 2 |
| USA · Categoría RNF | 2 |
| ACC · Categoría RNF | 2 |
| REC · Categoría RNF | 1 |

## Marcas originales por rol web

| Columna | ✓ | ✗ | N/A | Sin revisar |
|---|---:|---:|---:|---:|
| E · Docente | 74 | 4 | 43 | 117 |
| F · Alumno | 45 | 2 | 48 | 143 |
| H · Superadmin | 8 | 0 | 113 | 117 |

## AUT · Cuentas y acceso

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 7 | P-01 | RF-AUT-001 | Registro de docente | El sistema deberá permitir el registro de docentes mediante nombre, correo electrónico, número telefónico y contraseña. | ✓ | N/A | N/A |
| 8 | P-02 | RF-AUT-002 | Registro de estudiante | El sistema deberá permitir el registro gratuito de estudiantes mediante nombre, correo electrónico y contraseña. | N/A | ✓ | N/A |
| 9 | P-03 | RF-AUT-003 | Autenticación mediante credenciales | El sistema deberá comprobar el correo electrónico y la contraseña proporcionados por el usuario. Para docentes, estudiantes y superadministradores, esta comprobación deberá complementarse con el segundo factor antes de permitir el acceso. | ✓ | ✓ | ✓ |
| 10 | P-04 | RF-AUT-004 | Envío de código de autenticación | El sistema deberá enviar un código de autenticación al correo electrónico del docente, estudiante o superadministrador en cada inicio de sesión, después de comprobar sus credenciales. | ✓ | ✓ | ✓ |
| 11 | P-05 | RF-AUT-005 | Verificación del segundo factor | El sistema deberá completar la autenticación del docente, estudiante o superadministrador únicamente cuando proporcione un código válido. El código será de un solo uso y tendrá una vigencia de 10 minutos desde su emisión. | ✓ | ✓ | ✓ |
| 12 | P-06 | RF-AUT-006 | Reenvío de código de autenticación | El sistema deberá permitir solicitar un nuevo código después de transcurridos 60 segundos desde el último envío. La emisión del nuevo código invalidará el anterior. | ✓ | ✓ | ✓ |
| 13 | P-07 | RF-AUT-007 | Límite de intentos por código | El sistema deberá permitir un máximo de cinco intentos de verificación por código. Después del quinto intento fallido, deberá invalidarlo y requerir la solicitud de uno nuevo. | ✓ | ✓ | ✓ |
| 14 | P-08 | RF-AUT-008 | Carga de fotografía del estudiante | El sistema deberá permitir al estudiante proporcionar opcionalmente una fotografía durante su registro, aceptando archivos JPG o PNG de hasta 5 MB. | N/A | ✓ | N/A |
| 15 | P-09 | RF-AUT-009 | Imagen de perfil predeterminada | El sistema deberá mostrar una imagen de perfil predeterminada cuando el estudiante no proporcione una fotografía. | N/A | ✓ | N/A |
| 16 | P-10 | RF-AUT-010 | Creación restringida de superadministradores | La creación de cuentas de superadministrador deberá realizarse mediante un procedimiento técnico restringido al personal autorizado. El sistema no ofrecerá registro público para este rol. | N/A | N/A | ✓ |
| 17 | — | RF-AUT-011 | Consulta y aceptación del aviso de privacidad | El sistema deberá permitir al usuario consultar el aviso de privacidad y exigir su aceptación explícita antes de completar el registro de su cuenta, tanto en la aplicación web como en la aplicación móvil. Deberá conservar un registro de la versión aceptada y la fecha y hora de aceptación. | ✓ | ✓ | N/A |
| 18 | — | RF-AUT-012 | Cierre de sesión | El sistema deberá permitir al usuario cerrar su sesión, previa confirmación, revocando su acceso en el servidor y eliminando los datos de sesión almacenados en el dispositivo. | ✓ | ✓ | ✓ |
| 19 | — | RF-AUT-013 | Perfil y ajustes | El sistema deberá permitir al usuario consultar los datos de su cuenta y, desde la misma sección, consultar el aviso de privacidad vigente y cerrar su sesión. | ✓ | ✓ | ✓ |
| 20 | — | RF-AUT-014 | Actualización de la fotografía de perfil | El sistema deberá permitir al estudiante reemplazar su fotografía de perfil desde la sección de perfil, aceptando archivos JPG o PNG de hasta 5 MB. | N/A | ✓ | N/A |

**Observaciones originales**

| Celda | Código previsto | Observación (I) |
|---|---|---|
| I9 | RF-AUT-003 | Flujo de credenciales y segundo factor común a todos los roles; probado con docente y con superadministrador el 19/11/2026. |
| I10 | RF-AUT-004 | El código se envía al correo de todos los roles; probado con docente y con superadministrador el 19/11/2026. |
| I11 | RF-AUT-005 | Código de un solo uso con vigencia de 10 minutos para todos los roles; probado con docente y con superadministrador el 19/11/2026. |
| I12 | RF-AUT-006 | La aplicación móvil permite reenviar a los 60 segundos e invalida el código anterior. Web: reenvío agregado el 19/11/2026 (ACT-WEB-047) para todos los roles; probado en navegador el 19/11/2026. |
| I13 | RF-AUT-007 | Web y API: límite de 5 intentos por código agregado el 19/11/2026 (ACT-WEB-037); probado en navegador el 19/11/2026. App: la petición de verificación envía solo el código, sin correo, por lo que la API no puede contar los intentos; falta que la app envíe el correo. |
| I14 | RF-AUT-008 | Web: fotografía opcional JPG/PNG hasta 5 MB en el registro, guardada en almacenamiento privado (ACT-WEB-038, 19/11/2026); probada en navegador el 19/11/2026. La API ya acepta el campo photo; falta que la app lo envíe (ACT-MOV-009). |
| I15 | RF-AUT-009 | Web: sin fotografía se muestra la imagen predeterminada con la inicial (componente x-avatar, ACT-WEB-038, 19/11/2026; probado en navegador el 19/11/2026). App pendiente (ACT-MOV-009). |
| I16 | RF-AUT-010 | Sin registro público para superadministradores. Alta mediante el comando restringido php artisan acs:crear-superadmin (19/11/2026, ACT-WEB-049), con validación, confirmación y registro en la bitácora de auditoría; probado el 19/11/2026. El comando está documentado en MPW-V3 (comandos de consola). |
| I17 | RF-AUT-011 | El registro exige aceptar el aviso y guarda la versión aceptada con fecha y hora (prueba web 18/11/2026). |
| I18 | RF-AUT-012 | Agregado el 10/11/2026. La aplicación móvil pide confirmación y revoca el token. Web: confirmación agregada el 19/11/2026 (ACT-WEB-048); probada en navegador el 19/11/2026. |
| I19 | RF-AUT-013 | Agregado el 10/11/2026. Web: el docente tiene su perfil; el alumno y el superadministrador tienen "Mi perfil" desde el 19/11/2026 (ACT-WEB-050) con datos de la cuenta, aviso de privacidad y cierre de sesión; probado en navegador el 19/11/2026. App: muestra aviso y cierre de sesión, pero no los datos de la cuenta. |
| I20 | RF-AUT-014 | Agregado el 19/11/2026 (decisión de Eli tras la prueba de RF-AUT-008). Web: "Actualizar fotografía" en Mi perfil (ACT-WEB-054); probado en navegador el 19/11/2026. App pendiente. |

## GRU · Grupos e incorporación

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 21 | P-11 | RF-GRU-001 | Creación de grupos | El sistema deberá permitir al docente crear grupos con nombre obligatorio y descripción opcional, respetando el límite aplicable a su nivel o a la prueba gratuita descrita en P-34 a P-36. | ✓ | N/A | N/A |
| 22 | P-12 | RF-GRU-002 | Consulta de grupos del docente | El sistema deberá permitir al docente consultar los grupos que administra e identificar si están activos o cerrados. | ✓ | N/A | N/A |
| 23 | P-13 | RF-GRU-003 | Modificación de grupos | El sistema deberá permitir al docente modificar el nombre y la descripción de los grupos que administra mientras se encuentren activos. | ✓ | N/A | N/A |
| 24 | P-14 | RF-GRU-004 | Consulta de integrantes | El sistema deberá permitir al docente consultar los estudiantes incorporados a cada uno de sus grupos. | ✓ | N/A | N/A |
| 25 | P-15 | RF-GRU-005 | Código de incorporación del grupo | El sistema deberá generar un código de incorporación para cada grupo y permitir su consulta al docente que lo administra. El docente no podrá renovarlo. | ✓ | N/A | N/A |
| 26 | P-16 | RF-GRU-006 | QR de incorporación del grupo | El sistema deberá proporcionar un código QR de incorporación al mismo grupo identificado por su código de incorporación. El docente podrá consultarlo, pero no renovarlo. | ✓ | N/A | N/A |
| 27 | P-17 | RF-GRU-007 | Incorporación mediante código | El sistema deberá permitir al estudiante incorporarse inmediatamente a un grupo activo al introducir un código de incorporación válido. La aplicación móvil deberá ofrecer esta captura manual cuando no se autorice la cámara o no pueda utilizarse. | N/A | ✓ | N/A |
| 28 | P-18 | RF-GRU-008 | Incorporación mediante QR | La aplicación móvil deberá permitir al estudiante escanear un código QR válido mediante la cámara para incorporarse inmediatamente al grupo activo correspondiente. | N/A | N/A | N/A |
| 29 | P-19 | RF-GRU-009 | Prevención de incorporaciones duplicadas | El sistema deberá impedir que un estudiante se incorpore nuevamente a un grupo al que ya pertenece y mostrarle un mensaje que indique su pertenencia. | N/A | ✓ | N/A |
| 30 | P-21 | RF-GRU-010 | Consulta de grupos del estudiante | El sistema deberá permitir al estudiante consultar los grupos a los que pertenece, incluidos los administrados por distintos docentes. | N/A | ✓ | N/A |
| 31 | P-22 | RF-GRU-011 | Retiro de estudiantes por el docente | El sistema deberá permitir al docente retirar a un estudiante de un grupo que administra, previa confirmación explícita. Antes de confirmar, deberá advertir que el estudiante perderá su pertenencia al grupo y el acceso a sus recursos y resultados. Si cancela, no se realizará el retiro. Los registros académicos del estudiante en ese grupo se conservarán en el sistema. La operación estará sujeta a la restricción de intentos en curso definida en el apartado 7. | ✓ | N/A | N/A |
| 32 | P-23 | RF-GRU-012 | Salida voluntaria del estudiante | El sistema deberá permitir al estudiante abandonar un grupo, previa confirmación explícita. Antes de confirmar, deberá advertir que perderá su pertenencia y el acceso a los recursos y resultados de ese grupo. Si cancela, no se realizará la salida. Los registros académicos del estudiante en ese grupo se conservarán en el sistema. La operación estará sujeta a la restricción de intentos en curso definida en el apartado 7. | N/A | ✓ | N/A |
| 33 | P-24 | RF-GRU-013 | Cierre de grupos | El sistema deberá permitir al docente cerrar los grupos que administra. El cierre impedirá nuevas incorporaciones y nuevos intentos de examen, conservando la información del grupo para consulta conforme a los permisos establecidos. | ✓ | N/A | N/A |
| 34 | P-25 | RF-GRU-014 | Control de grupos activos | El sistema deberá contabilizar cada grupo dentro del límite del paquete desde su creación hasta su cierre. Los grupos cerrados no se contabilizarán como activos. | ✓ | N/A | N/A |
| 35 | P-26 | RF-GRU-015 | Eliminación automática de grupos | El sistema deberá eliminar automáticamente los grupos cerrados cuando hayan transcurrido 12 meses desde su cierre sin haberse reabierto. Con 30 días de anticipación deberá avisar al docente por correo y en el detalle del grupo, para que pueda exportar las calificaciones o reabrir el grupo; la reapertura cancelará la eliminación. Al eliminar el grupo se eliminarán las incorporaciones de estudiantes, los periodos de evaluación, las calificaciones y los intentos y respuestas de los exámenes aplicados en ese grupo, junto con sus historiales individuales. Se conservarán los exámenes y el banco de preguntas del docente, los registros de pago, los movimientos de créditos de IA y el consumo de la prueba gratuita, y la operación quedará registrada en la bitácora de auditoría. | ✓ | N/A | N/A |
| 116 | P-107 | RF-GRU-016 | Restricción de retiro durante un intento | El sistema deberá impedir que el docente retire del grupo a un estudiante mientras este tenga un intento en curso. | ✓ | N/A | N/A |
| 117 | P-108 | RF-GRU-017 | Restricción de salida durante un intento | El sistema deberá impedir que el estudiante abandone un grupo mientras tenga un intento en curso asociado a él. | N/A | ✓ | N/A |
| 118 | P-109 | RF-GRU-018 | Restricción de cierre del grupo | El sistema deberá impedir el cierre de un grupo mientras existan intentos en curso. El docente deberá esperar a su conclusión o detener las aplicaciones correspondientes. | ✓ | N/A | N/A |
| 198 | P-186 | RF-GRU-019 | Ocultamiento de grupos cerrados al estudiante | Al cerrarse un grupo, el sistema deberá dejar de mostrarlo al estudiante e impedir su acceso a los resultados asociados, tanto desde la web como desde la aplicación móvil. | N/A | ✓ | N/A |
| 199 | — | RF-GRU-020 | Reapertura de grupos | El sistema deberá permitir al docente reabrir un grupo que haya cerrado, para que vuelva a admitir incorporaciones y la aplicación de exámenes. | ✓ | N/A | N/A |
| 200 | — | RF-GRU-021 | Materia del grupo | El sistema deberá permitir al docente indicar, de manera opcional, la materia a la que corresponde cada grupo. La materia y el nombre del docente deberán mostrarse al estudiante al consultar sus grupos y los exámenes asociados a ellos. | ✓ | ✓ | N/A |

**Observaciones originales**

| Celda | Código previsto | Observación (I) |
|---|---|---|
| I21 | RF-GRU-001 | Nombre obligatorio y descripción opcional de hasta 500 caracteres (BD01-2026-032); respeta el límite del plan y de la prueba gratuita. ACT-WEB-020 (commit 75f98c9); comprobado con GroupEditTest y en navegador el 22/11/2026. |
| I22 | RF-GRU-002 | Listado con estado activo/cerrado. |
| I23 | RF-GRU-003 | «Editar grupo» modifica nombre, materia y descripción solo mientras el grupo está activo: con el grupo cerrado no se muestra el botón y el sistema rechaza el cambio. ACT-WEB-020 (commit 75f98c9); comprobado con GroupEditTest y en navegador el 22/11/2026. |
| I24 | RF-GRU-004 | Detalle del grupo con sus alumnos. |
| I25 | RF-GRU-005 | Folio generado al crear; no hay opción para renovarlo. |
| I26 | RF-GRU-006 | QR del folio generado en el navegador con el servicio api.qrserver.com (componente x-group.qr-modal), como lo establece MPW-V3, apartado 6.11. |
| I27 | RF-GRU-007 | Web: formulario Unirse a grupo. App: captura manual del folio. |
| I28 | RF-GRU-008 | App: lectura de QR con la cámara (mobile_scanner). |
| I29 | RF-GRU-009 | Mensaje "Ya te encuentras inscrito en este grupo escolar." |
| I30 | RF-GRU-010 | Incluye grupos de distintos docentes; oculta los cerrados. |
| I31 | RF-GRU-011 | Descripción modificada el 19/11/2026 (CAM-008: se conservan los registros académicos). Retiro con confirmación agregado el 19/11/2026 (ACT-WEB-021); cubierto con prueba automática, sin prueba manual. |
| I32 | RF-GRU-012 | Descripción modificada el 19/11/2026 (CAM-008: se conservan los registros académicos). Web y app piden confirmación, avisan la pérdida de pertenencia y acceso, impiden salir con intento en curso y conservan los registros. |
| I33 | RF-GRU-013 | Impide nuevas incorporaciones y deja de mostrar sus exámenes a los alumnos. |
| I34 | RF-GRU-014 | Crear y reabrir respetan el límite de grupos activos del plan; los cerrados no cuentan. Reapertura corregida el 19/11/2026 (ACT-WEB-051); probado el 19/11/2026. |
| I35 | RF-GRU-015 | Condiciones definidas el 22/11/2026 (CAM-009). Comando diario groups:purge-expired: aviso por correo 30 días antes, fecha de eliminación visible en el detalle del grupo cerrado, borrado lógico del grupo (BD01-2026-033) y eliminación de su información académica conservando lo indicado. ACT-WEB-022 (commit 4cf20e2); comprobado con GroupRetentionTest el 22/11/2026 (el plazo de 12 meses no se puede esperar en el navegador). |
| I116 | RF-GRU-016 | El retiro se rechaza si el estudiante tiene un intento en curso en el grupo (ACT-WEB-021, 19/11/2026); cubierto con prueba automática, sin prueba manual. |
| I117 | RF-GRU-017 | Mensaje: no puedes abandonar el grupo mientras tengas un examen en curso. |
| I118 | RF-GRU-018 | El cierre se rechaza si hay intentos activos. |
| I198 | RF-GRU-019 | Oculta el grupo y bloquea sus calificaciones en web y API. |
| I199 | RF-GRU-020 | Agregado el 10/11/2026. Reapertura disponible y sujeta al límite de grupos activos (ACT-WEB-051, 19/11/2026); probado el 19/11/2026. |
| I200 | RF-GRU-021 | Agregado el 17/11/2026. Materia opcional al crear; el alumno web ve materia y docente. La app aún no los muestra (ACT-MOV-027/028). (revisado en código el 19/11/2026; pendiente prueba de escritorio) |

## SUS · Paquetes y suscripciones

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 36 | P-27 | RF-SUS-001 | Consulta de paquetes docentes | El sistema deberá permitir al docente consultar los niveles disponibles, incluyendo precio mensual, capacidades y límites. | ✓ | N/A | N/A |
| 37 | P-28 | RF-SUS-002 | Contratación de paquete docente | El sistema deberá permitir al docente contratar uno de los niveles disponibles mediante una suscripción de pago mensual domiciliado. | ✓ | N/A | N/A |
| 38 | P-29 | RF-SUS-003 | Registro de medio de pago | El sistema deberá permitir al docente registrar un medio de pago admitido por el proveedor seleccionado y autorizar su utilización para los cobros domiciliados. | ✓ | N/A | N/A |
| 39 | P-30 | RF-SUS-004 | Activación inicial del paquete | El sistema deberá habilitar las capacidades del paquete contratado después de recibir la confirmación del primer pago. | ✓ | N/A | N/A |
| 40 | P-31 | RF-SUS-005 | Renovación mensual | El sistema deberá gestionar el cobro automático mensual del paquete mientras la suscripción continúe activa, conforme a la fecha de renovación establecida. | ✓ | N/A | N/A |
| 41 | P-32 | RF-SUS-006 | Consulta de suscripción | El sistema deberá permitir al docente consultar su nivel, estado de suscripción, precio mensual y próxima fecha de cobro. | ✓ | N/A | N/A |
| 42 | P-33 | RF-SUS-007 | Aplicación de capacidades y límites | El sistema deberá habilitar las capacidades y aplicar los límites correspondientes al nivel del docente. | ✓ | N/A | N/A |
| 43 | P-34 | RF-SUS-008 | Creación del grupo de prueba | El sistema deberá permitir al docente sin paquete contratado crear un único grupo como parte de la prueba gratuita. | ✓ | N/A | N/A |
| 44 | P-35 | RF-SUS-009 | Aplicación del examen de prueba | El sistema deberá permitir al docente sin paquete contratado aplicar un único examen con las capacidades del nivel 1 dentro de su grupo de prueba. | — | — | — |
| 45 | P-36 | RF-SUS-010 | Control de consumo de la prueba | El sistema deberá conservar el registro del consumo del grupo y examen gratuitos por cuenta. El cierre o eliminación de estos elementos no restablecerá los beneficios utilizados. | ✓ | N/A | N/A |
| 46 | P-37 | RF-SUS-011 | Actualización a un nivel superior | El sistema deberá permitir al docente con suscripción activa solicitar el cambio a un nivel superior y habilitar inmediatamente sus capacidades, conservando sus grupos, preguntas, exámenes y calificaciones. | ✓ | N/A | N/A |
| 47 | P-38 | RF-SUS-012 | Aplicación del nuevo precio | El sistema deberá aplicar el precio mensual del nivel superior desde la siguiente factura, sin realizar un cobro inmediato ni prorratear el periodo restante. | ✓ | N/A | N/A |
| 48 | P-39 | RF-SUS-013 | Consulta de operaciones de pago | El sistema deberá permitir al docente consultar sus contrataciones, renovaciones y actualizaciones, identificando fecha, concepto, importe y estado de pago cuando corresponda. | ✓ | N/A | N/A |
| 49 | P-40 | RF-SUS-014 | Cancelación de la suscripción | El sistema deberá permitir al docente cancelar la renovación de su suscripción. La cancelación impedirá cobros futuros y conservará las capacidades contratadas hasta finalizar el periodo pagado. | ✓ | N/A | N/A |
| 50 | P-41 | RF-SUS-015 | Consulta de pagos pendientes o rechazados | El sistema deberá permitir al docente identificar los pagos pendientes o rechazados. Una contratación o actualización cuyo pago todavía no corresponda no deberá presentarse como un pago fallido. | ✓ | N/A | N/A |

**Observaciones originales**

| Celda | Código previsto | Observación (I) |
|---|---|---|
| I36 | RF-SUS-001 | Catálogo de paquetes con precio mensual, capacidades y límites en la pantalla de suscripción. |
| I37 | RF-SUS-002 | Contratación con tarjeta y casilla de autorización de cobro mensual (domiciliado simulado, ACU-001); SPEI otorga un mes sin renovación. ACT-WEB-023. Probado en navegador el 22/11/2026 (tarjeta y SPEI). |
| I38 | RF-SUS-003 | Se registra la tarjeta autorizada para los cobros domiciliados; se conservan solo marca, últimos 4 dígitos y titular (BD01-2026-034). ACT-WEB-023. Probado en navegador el 22/11/2026. |
| I39 | RF-SUS-004 | El paquete se activa solo con el pago aprobado; un pago rechazado no lo activa. |
| I40 | RF-SUS-005 | Comando diario subscriptions:renew: cobra con la tarjeta domiciliada y suma un mes; un rechazo se reintenta 3 días con el plan vigente y al tercero termina. ACT-WEB-023. Probado el 22/11/2026 simulando el vencimiento (renovación aprobada, rechazo con plan vigente y fin del plan al tercer rechazo). |
| I41 | RF-SUS-006 | Muestra nivel, estado, vigencia, medio de pago, precio mensual y próxima fecha de cobro (ACT-WEB-023); probado en navegador el 22/11/2026. |
| I42 | RF-SUS-007 | Límites de grupos, reactivos, exámenes y alumnos aplicados por SubscriptionLimitService. |
| I43 | RF-SUS-008 | La prueba gratuita permite un único grupo. |
| I44 | RF-SUS-009 | Se revisará junto con RF-EXA (capacidades del nivel 1). |
| I45 | RF-SUS-010 | El conteo de la prueba es histórico; cerrar o eliminar no restablece el beneficio. |
| I46 | RF-SUS-011 | «Actualizar a este nivel» solo para niveles superiores y con la renovación activa; capacidades inmediatas y se conservan los datos; no se permite bajar de nivel ni volver a contratar con plan activo. ACT-WEB-024; probado en navegador el 22/11/2026. |
| I47 | RF-SUS-012 | La actualización no cobra ni prorratea; el nuevo precio se aplica desde la siguiente renovación (ACT-WEB-024); probado en navegador el 22/11/2026. |
| I48 | RF-SUS-013 | Historial con folio, fecha, concepto (contratación, renovación o actualización de nivel), paquete, método, monto y estado (BD01-2026-035, ACT-WEB-025); probado en navegador el 22/11/2026. |
| I49 | RF-SUS-014 | «Cancelar renovación» con confirmación: no hay más cobros y el plan sigue vigente hasta el fin del periodo pagado (ACT-WEB-023, implementado por Eli); probado en navegador el 22/11/2026. |
| I50 | RF-SUS-015 | Los pagos rechazados se identifican en el historial; los pagos simulados no quedan pendientes. (revisado en código el 19/11/2026; pendiente prueba de escritorio) |

## BAN · Banco de preguntas

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 51 | P-42 | RF-BAN-001 | Registro de preguntas de opción múltiple | El sistema deberá permitir al docente registrar preguntas con enunciado, opciones de respuesta y una única opción identificada como correcta. | ✓ | N/A | N/A |
| 52 | P-43 | RF-BAN-002 | Registro de preguntas de relación | El sistema deberá permitir al docente registrar preguntas con enunciado, dos listas de elementos y sus correspondencias correctas. Las correspondencias serán uno a uno, sin reutilizar elementos de respuesta. | ✓ | N/A | N/A |
| 53 | P-44 | RF-BAN-003 | Registro de preguntas abiertas | El sistema deberá permitir al docente registrar preguntas abiertas con enunciado y respuesta de referencia obligatorios. | ✓ | N/A | N/A |
| 54 | P-45 | RF-BAN-004 | Registro de preguntas de programación | El sistema deberá permitir al docente registrar preguntas de programación con enunciado, lenguaje de programación y respuesta de referencia obligatorios, así como criterios de evaluación opcionales. | ✓ | N/A | N/A |
| 55 | P-46 | RF-BAN-005 | Clasificación de preguntas | El sistema deberá permitir al docente asignar un tema a cada pregunta y conservar su identificación por tipo. | ✓ | N/A | N/A |
| 56 | P-47 | RF-BAN-006 | Consulta del banco privado | El sistema deberá permitir al docente consultar exclusivamente las preguntas de su propio banco. No se permitirá compartir preguntas entre docentes en el alcance inicial. | ✓ | N/A | N/A |
| 57 | P-48 | RF-BAN-007 | Consulta del detalle de preguntas | El sistema deberá permitir al docente consultar el contenido, las respuestas de referencia, los criterios y los recursos asociados a sus preguntas, según corresponda a su tipo. | ✓ | N/A | N/A |
| 58 | P-49 | RF-BAN-008 | Búsqueda de preguntas | El sistema deberá permitir al docente buscar preguntas de su banco mediante texto. | ✓ | N/A | N/A |
| 59 | P-50 | RF-BAN-009 | Filtrado de preguntas | El sistema deberá permitir al docente filtrar las preguntas de su banco por tema y tipo. | ✓ | N/A | N/A |
| 65 | P-56 | RF-BAN-010 | Modificación de preguntas del banco | El sistema deberá permitir al docente modificar las preguntas de su banco sin alterar las copias previamente incorporadas a exámenes. | ✓ | N/A | N/A |
| 66 | P-57 | RF-BAN-011 | Eliminación de preguntas del banco | El sistema deberá permitir al docente eliminar preguntas de su banco sin eliminar las copias ni los recursos necesarios para consultar los exámenes donde fueron incorporadas. | ✓ | N/A | N/A |
| 67 | P-58 | RF-BAN-012 | Incorporación de imágenes | El sistema deberá permitir al docente incorporar imágenes al contenido de las preguntas, conforme a los formatos, tamaños y cantidades que se definan. | ✓ | N/A | N/A |
| 68 | P-59 | RF-BAN-013 | Incorporación de archivos PDF | El sistema deberá permitir al docente adjuntar archivos exclusivamente en formato PDF a las preguntas, conforme al tamaño máximo y cantidad que se definan. | ✓ | N/A | N/A |
| 69 | P-60 | RF-BAN-014 | Conservación del formato de código | El sistema deberá conservar y mostrar los saltos de línea, espacios y sangrías de los fragmentos de código incluidos en las preguntas y sus respuestas de referencia. | ✓ | N/A | N/A |
| 70 | — | RF-BAN-015 | Catálogo de materias del banco de preguntas | El sistema deberá permitir al docente clasificar sus preguntas por materia mediante un catálogo propio, reconociendo como una misma materia los nombres que solo difieran en mayúsculas, acentos o espacios, sugiriendo materias similares al registrarlas y permitiendo unificar materias duplicadas. | ✓ | N/A | N/A |

**Observaciones originales**

| Celda | Código previsto | Observación (I) |
|---|---|---|
| I51 | RF-BAN-001 | Opción múltiple de 2 a 6 opciones con una sola correcta. (revisado en código el 19/11/2026; pendiente prueba de escritorio) |
| I52 | RF-BAN-002 | Relación de columnas de 2 a 8 pares, uno a uno y sin repetir elementos. (revisado en código el 19/11/2026; pendiente prueba de escritorio) |
| I53 | RF-BAN-003 | Pregunta abierta con enunciado y respuesta modelo obligatorios; rúbrica opcional. (revisado en código el 19/11/2026; pendiente prueba de escritorio) |
| I54 | RF-BAN-004 | Descripción modificada el 22/11/2026 (CAM-010). El docente elige el lenguaje; los criterios de evaluación son opcionales (BD01-2026-036), se envían a la evaluación con IA y no se muestran al alumno. ACT-WEB-058 (commit 4c5e81b); comprobado con QuestionBankManagementTest y en navegador el 22/11/2026. |
| I55 | RF-BAN-005 | El tema se maneja como la materia de la pregunta (catálogo de RF-BAN-015), decisión de Eli del 19/11/2026; el tipo se conserva en cada pregunta. |
| I56 | RF-BAN-006 | Cada docente ve solo su banco; no se comparten preguntas. (revisado en código el 19/11/2026; pendiente prueba de escritorio) |
| I57 | RF-BAN-007 | El detalle muestra enunciado, respuestas de referencia y criterios según el tipo. Detalle de preguntas de programación corregido y probado en navegador el 22/11/2026 (ACT-WEB-052, commit bc6455a); los demás tipos, revisados en código el 19/11/2026, pendientes de prueba de escritorio. |
| I58 | RF-BAN-008 | Búsqueda por texto en el banco. (revisado en código el 19/11/2026; pendiente prueba de escritorio) |
| I59 | RF-BAN-009 | Filtros por materia (tema) y tipo. (revisado en código el 19/11/2026; pendiente prueba de escritorio) |
| I65 | RF-BAN-010 | Al modificar, las copias ya incorporadas a exámenes no cambian. (revisado en código el 19/11/2026; pendiente prueba de escritorio) |
| I66 | RF-BAN-011 | Se elimina con borrado lógico aunque la pregunta esté en exámenes publicados o con intentos; esos exámenes la conservan con su contenido. ACT-WEB-053 (commit a952968); comprobado con QuestionBankManagementTest y en navegador el 22/11/2026. |
| I67 | RF-BAN-012 | Límites definidos el 22/11/2026: una imagen JPG o PNG de hasta 5 MB por pregunta. Se guarda en disco privado (image_path, BD01-2026-037) y solo la ven el docente dueño y los alumnos con intento en un examen que la incluye; se muestra en el detalle, al presentar el examen y en las revisiones. ACT-WEB-026 (commit 6d2ad83); comprobado con QuestionBankManagementTest y en navegador el 22/11/2026. La API entrega la dirección protegida; mostrarlo en la app queda en ACT-MOV-043. |
| I68 | RF-BAN-013 | Límites definidos el 22/11/2026: un PDF de hasta 5 MB por pregunta. Se guarda en disco privado (pdf_path, BD01-2026-037) con el mismo control de acceso que la imagen. ACT-WEB-026 (commit 6d2ad83); comprobado con QuestionBankManagementTest y en navegador el 22/11/2026. La API entrega la dirección protegida; mostrarlo en la app queda en ACT-MOV-043. |
| I69 | RF-BAN-014 | Saltos de línea, espacios y sangrías se conservan en el detalle, la vista previa, la aplicación y la revisión del intento (ACT-WEB-052); probado en navegador el 22/11/2026. |
| I70 | RF-BAN-015 | Catálogo propio de materias que unifica mayúsculas, acentos y espacios, con sugerencias y unificación de duplicados. (revisado en código el 19/11/2026; pendiente prueba de escritorio) |

## EXA · Preparación y aplicación de exámenes

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 60 | P-51 | RF-EXA-001 | Generación de propuesta de examen | El sistema deberá seleccionar preguntas del banco del docente conforme a los temas, tipos y cantidades solicitados para los apartados del examen, sin repetir una pregunta dentro del mismo examen. | ✓ | — | N/A |
| 61 | P-52 | RF-EXA-002 | Revisión de la propuesta | El sistema deberá permitir al docente consultar las preguntas propuestas antes de aceptar su incorporación al examen. | ✓ | — | N/A |
| 62 | P-53 | RF-EXA-003 | Generación de una nueva propuesta | El sistema deberá permitir al docente solicitar otra selección antes de aceptar el contenido del examen. La nueva propuesta podrá coincidir parcialmente con la anterior, pero no contendrá preguntas repetidas dentro del mismo examen. | ✓ | — | N/A |
| 63 | P-54 | RF-EXA-004 | Aceptación de preguntas propuestas | El sistema deberá permitir al docente aceptar la selección propuesta y conservar las preguntas y sus recursos asociados, tal como se aceptaron, como parte del examen. | ✓ | — | N/A |
| 64 | P-55 | RF-EXA-005 | Modificación de preguntas durante la preparación | El sistema deberá permitir al docente modificar las copias de las preguntas incorporadas a un examen antes de su publicación, incluyendo las respuestas de referencia y los criterios aplicables, sin modificar automáticamente las originales del banco. | ✓ | — | N/A |
| 71 | P-61 | RF-EXA-006 | Creación de exámenes | El sistema deberá permitir al docente crear exámenes con título obligatorio e instrucciones generales opcionales. | ✓ | — | N/A |
| 72 | P-62 | RF-EXA-007 | Consulta de exámenes del docente | El sistema deberá permitir al docente consultar los exámenes que administra, su configuración y el estado de sus aplicaciones a grupos. | ✓ | — | N/A |
| 73 | P-63 | RF-EXA-008 | Configuración de apartados | El sistema deberá permitir al docente definir apartados con nombre, tipo de pregunta y cantidad de preguntas. Un examen podrá combinar apartados de distintos tipos, conforme a las capacidades del paquete. | ✓ | — | N/A |
| 74 | P-64 | RF-EXA-009 | Asignación de puntos por pregunta | El sistema deberá permitir al docente asignar puntos a cada pregunta del examen. El valor de cada apartado será la suma de los puntos de sus preguntas, sin una ponderación adicional. | ✓ | — | N/A |
| 75 | P-65 | RF-EXA-010 | Validación de puntuación total | El sistema deberá comprobar que la suma de los puntos asignados a todas las preguntas del examen sea exactamente 100 antes de permitir su programación. | ✓ | — | N/A |
| 76 | P-66 | RF-EXA-011 | Selección de escala | El sistema deberá permitir al docente seleccionar por examen una escala de 0–10 con decimales o de 0–100 sin decimales. La calificación se obtendrá a partir de los 100 puntos configurados. | ✓ | — | N/A |
| 77 | P-67 | RF-EXA-012 | Selección de modalidad de navegación | El sistema deberá permitir al docente seleccionar modalidad secuencial o libre, conforme a las capacidades de su paquete. | ✗ | — | N/A |
| 78 | P-68 | RF-EXA-013 | Configuración de tiempo por pregunta | Para la modalidad secuencial, el sistema deberá permitir al docente establecer el tiempo individual disponible para cada pregunta. | ✓ | — | N/A |
| 79 | P-69 | RF-EXA-014 | Configuración de tiempo total | Para la modalidad libre, el sistema deberá permitir al docente establecer el tiempo total disponible para responder el examen. | ✗ | — | N/A |
| 80 | P-70 | RF-EXA-015 | Configuración del orden de preguntas | El sistema deberá permitir al docente seleccionar orden fijo o aleatorio, conforme a su paquete. El orden aleatorio se generará por estudiante y respetará la separación de apartados. | ✓ | — | N/A |
| 81 | P-71 | RF-EXA-016 | Asignación del examen a grupos | El sistema deberá permitir al docente asignar el mismo contenido de examen a distintos grupos que administra, con una programación común para todos ellos o independiente para cada aplicación, a elección del docente. | ✗ | — | N/A |
| 82 | P-72 | RF-EXA-017 | Configuración de apertura y cierre de acceso | El sistema deberá permitir al docente establecer una fecha y hora exactas de apertura y una fecha y hora límite para iniciar el examen, comunes a todos los grupos asignados o específicas para cada aplicación. | ✗ | — | N/A |
| 83 | P-73 | RF-EXA-018 | Identificación de zona horaria | El sistema deberá mostrar a docentes y estudiantes la zona horaria utilizada para interpretar las fechas y horas del examen. | ✓ | ✓ | N/A |
| 84 | P-74 | RF-EXA-019 | Validación previa a la programación | El sistema deberá impedir la programación cuando falten preguntas, respuestas de referencia o criterios obligatorios, tiempos, grupo destinatario o fechas coherentes, o cuando la puntuación total sea distinta de 100. También deberá comprobar la compatibilidad de la configuración con el paquete docente. | ✓ | — | N/A |
| 85 | P-75 | RF-EXA-020 | Modificación previa a la publicación | El sistema deberá permitir al docente modificar el contenido y la configuración del examen mientras no se haya publicado. Si está programado, deberá comprobar nuevamente su validez antes de guardar las modificaciones. | ✓ | — | N/A |
| 86 | P-76 | RF-EXA-021 | Publicación automática | El sistema deberá publicar cada aplicación del examen al llegar la fecha y hora de apertura programadas, habilitando el acceso conforme a los permisos y condiciones establecidos. | ✓ | — | N/A |
| 87 | P-77 | RF-EXA-022 | Bloqueo de modificaciones | El sistema deberá impedir modificaciones al contenido y la configuración de un examen publicado. | ✓ | — | N/A |
| 88 | P-78 | RF-EXA-023 | Cancelación de programación | El sistema deberá permitir al docente cancelar una programación antes de su publicación, conservando el examen para editarlo o programarlo nuevamente. | ✓ | — | N/A |
| 89 | P-79 | RF-EXA-024 | Detención de una aplicación publicada | El sistema deberá permitir al docente detener una aplicación de examen publicada. La detención impedirá nuevos inicios y finalizará los intentos en curso, conservando las respuestas y resultados existentes e identificando la causa de finalización. | ✓ | — | N/A |
| 90 | P-80 | RF-EXA-025 | Aviso de disponibilidad por correo | El sistema deberá enviar a los estudiantes del grupo un aviso por correo electrónico con el enlace de acceso cuando se publique la aplicación del examen. El enlace no sustituirá las comprobaciones de identidad, pertenencia y permisos. | ✓ | — | N/A |
| 91 | P-81 | RF-EXA-026 | Consulta de exámenes del estudiante | El sistema deberá permitir al estudiante consultar los exámenes de sus grupos, identificando la fecha de apertura, el límite para iniciar y el estado de su participación. | N/A | ✓ | N/A |
| 92 | P-82 | RF-EXA-027 | Comprobación de acceso | El sistema deberá verificar la sesión del estudiante, su pertenencia al grupo, la disponibilidad de la aplicación y la existencia de un intento permitido antes de autorizar el inicio. | N/A | ✓ | N/A |
| 93 | P-83 | RF-EXA-028 | Consulta de instrucciones | El sistema deberá mostrar las instrucciones y condiciones del examen antes de que el estudiante inicie su intento. | N/A | ✓ | N/A |
| 94 | P-84 | RF-EXA-029 | Inicio del intento | El sistema deberá registrar el inicio del intento y comenzar el control del tiempo cuando el estudiante confirme la opción de iniciar el examen. | N/A | ✓ | N/A |
| 95 | P-85 | RF-EXA-030 | Presentación secuencial | El sistema deberá presentar una pregunta a la vez en la modalidad secuencial e impedir el regreso a preguntas anteriores. | N/A | ✓ | N/A |
| 96 | P-86 | RF-EXA-031 | Avance manual en modalidad secuencial | El sistema deberá permitir al estudiante avanzar antes de agotar el tiempo de la pregunta. Si está sin responder, deberá solicitar confirmación. El tiempo sobrante no se acumulará para las preguntas siguientes. | N/A | ✓ | N/A |
| 97 | P-87 | RF-EXA-032 | Vencimiento del tiempo por pregunta | El sistema deberá conservar la respuesta registrada y avanzar automáticamente al agotarse el tiempo de una pregunta. Si corresponde a la última pregunta, deberá finalizar el examen. | N/A | ✓ | N/A |
| 98 | P-88 | RF-EXA-033 | Navegación libre | El sistema deberá permitir al estudiante recorrer las preguntas y modificar sus respuestas en la modalidad libre mientras disponga de tiempo y no haya entregado el examen. | N/A | ✗ | N/A |
| 99 | P-89 | RF-EXA-034 | Conservación del orden de preguntas | El sistema deberá mantener el orden de preguntas asignado al estudiante durante todo su intento, incluso cuando se reconecte. | N/A | ✓ | N/A |
| 100 | P-90 | RF-EXA-035 | Captura de respuestas de opción múltiple | El sistema deberá permitir al estudiante seleccionar una única opción como respuesta a cada pregunta de opción múltiple. | N/A | ✓ | N/A |
| 101 | P-91 | RF-EXA-036 | Captura de respuestas de relación | El sistema deberá permitir al estudiante establecer correspondencias uno a uno, sin reutilizar elementos de respuesta. | N/A | ✓ | N/A |
| 102 | P-92 | RF-EXA-037 | Captura de respuestas abiertas | El sistema deberá permitir al estudiante escribir sus respuestas a las preguntas abiertas. | N/A | ✓ | N/A |
| 103 | P-93 | RF-EXA-038 | Captura de respuestas de programación | El sistema deberá permitir al estudiante escribir código en el lenguaje configurado en la pregunta, en un campo que conserve sus saltos de línea, espacios y sangrías, sin compilarlo ni ejecutarlo. | N/A | ✓ | N/A |
| 104 | P-94 | RF-EXA-039 | Restricción de pegado en programación | La interfaz deberá impedir la operación de pegar contenido en el campo de respuesta de programación, tanto mediante atajos de teclado como mediante las opciones de pegado del dispositivo o navegador. | N/A | ✓ | N/A |
| 105 | P-95 | RF-EXA-040 | Guardado automático de respuestas | El sistema deberá guardar automáticamente cada respuesta o modificación realizada durante el intento, conforme a las condiciones de navegación y tiempo del examen. | N/A | ✓ | N/A |
| 106 | P-96 | RF-EXA-041 | Visualización del estado de guardado | El sistema deberá mostrar si una respuesta está guardada, pendiente de guardar o presenta un error. Únicamente deberá identificarla como guardada después de recibir la confirmación del servidor de que fue almacenada. | N/A | ✓ | N/A |
| 107 | P-97 | RF-EXA-042 | Visualización del tiempo restante | El sistema deberá mostrar el tiempo restante de la pregunta en modalidad secuencial o del examen en modalidad libre. | N/A | ✓ | N/A |
| 108 | P-98 | RF-EXA-043 | Vencimiento del tiempo total | El sistema deberá finalizar automáticamente el examen al agotarse su tiempo total, conservando las respuestas registradas. | N/A | ✗ | N/A |
| 109 | P-99 | RF-EXA-044 | Entrega con preguntas sin responder | El sistema deberá permitir la entrega voluntaria de un examen con preguntas sin responder, mostrando una advertencia antes de solicitar la confirmación. | N/A | ✓ | N/A |
| 110 | P-100 | RF-EXA-045 | Entrega voluntaria del examen | El sistema deberá solicitar confirmación antes de registrar la entrega voluntaria. Si el estudiante cancela, continuará en el intento mientras disponga de tiempo. | N/A | ✓ | N/A |
| 111 | P-101 | RF-EXA-046 | Confirmación y bloqueo tras la finalización | El sistema deberá confirmar la recepción del examen al concluir el intento e impedir modificaciones posteriores de sus respuestas. | N/A | ✓ | N/A |
| 112 | P-102 | RF-EXA-047 | Continuidad del tiempo ante interrupciones | El sistema deberá mantener el tiempo en curso ante desconexión, cierre o recarga de la aplicación, sin reiniciarlo ni pausarlo. | N/A | ✓ | N/A |
| 113 | P-103 | RF-EXA-048 | Recuperación del intento | El sistema deberá permitir al estudiante regresar al mismo intento mientras no haya concluido, recuperando las respuestas guardadas y el estado de avance correspondiente al tiempo transcurrido. | N/A | ✓ | N/A |
| 114 | P-104 | RF-EXA-049 | Restricción de acceso simultáneo | El sistema deberá permitir una sola sesión activa por intento y rechazar su apertura simultánea desde otra sesión o dispositivo. | N/A | ✓ | N/A |
| 115 | P-105 | RF-EXA-050 | Cierre de acceso a nuevos intentos | El sistema deberá impedir nuevos inicios al llegar la fecha y hora límite de acceso. Los intentos iniciados previamente podrán continuar hasta agotar su tiempo o finalizar por otra causa establecida. | N/A | ✓ | N/A |
| 119 | P-110 | RF-EXA-051 | Control de intentos permitidos | El sistema deberá permitir un intento por estudiante y aplicación de examen, salvo que el docente autorice un intento adicional. | N/A | ✓ | N/A |
| 120 | P-111 | RF-EXA-052 | Autorización de reaplicación | El sistema deberá permitir al docente asignar un intento adicional individual para reaplicar el examen, conservando el intento anterior y sus resultados. | N/A | ✓ | N/A |
| 121 | P-112 | RF-EXA-053 | Presentación desde navegador | El sistema deberá permitir presentar exámenes desde un navegador web en computadora y desde un navegador móvil, conforme a la compatibilidad que se defina. | N/A | — | N/A |
| 122 | — | RF-EXA-054 | Notificación de programación o publicación de exámenes | El sistema deberá enviar una notificación push a los estudiantes pertenecientes al grupo destinatario inmediatamente después de que el docente programe o publique un examen para dicho grupo. | N/A | N/A | N/A |
| 123 | — | RF-EXA-055 | Consulta del avance del examen por el docente | El sistema deberá permitir al docente consultar, durante la aplicación de un examen, cuántos estudiantes del grupo lo han respondido. | ✓ | — | N/A |
| 124 | — | RF-EXA-056 | Presentación del examen en la aplicación móvil | El sistema deberá permitir al estudiante presentar desde la aplicación móvil los exámenes asignados a sus grupos, aplicando las mismas reglas de acceso, navegación, tiempo, guardado y entrega que en la aplicación web. | N/A | N/A | N/A |
| 125 | — | RF-EXA-057 | Notificaciones de apertura, inicio y término del examen | El sistema deberá enviar una notificación push a los estudiantes del grupo cuando el examen quede disponible, y al docente cuando el examen comience y cuando termine. | N/A | N/A | N/A |

**Observaciones originales**

| Celda | Código previsto | Observación (I) |
|---|---|---|
| I60 | RF-EXA-001 | Sorteo por materia, tipo y cantidad sin repetir preguntas del examen. Probado por Eli en navegador y con ExamTeacherWorkflowTest (22/11/2026). |
| I61 | RF-EXA-002 | «Previsualización de propuesta» antes de aceptar. Probado por Eli en navegador (22/11/2026). |
| I62 | RF-EXA-003 | «Reintentar sorteo» genera otra propuesta sin repetir preguntas del examen. Probado por Eli y con ExamTeacherWorkflowTest (22/11/2026). |
| I63 | RF-EXA-004 | Descripción modificada el 22/11/2026 (CAM-011). El examen conserva las preguntas tal como se aceptaron: editar una pregunta incorporada crea una nueva versión (RF-BAN-010) y eliminarla es lógico (RF-BAN-011). Comprobado con QuestionBankManagementTest (versionado y eliminación con examen). |
| I64 | RF-EXA-005 | Botón "Editar" en el armado: modifica solo la copia del examen (nueva versión, RF-BAN-010) y deja intacta la original del banco; incluye respuestas de referencia y criterios. ACT-WEB-062/ACT-WEB-031 (commit 25e2c9a); comprobado con ExamTeacherWorkflowTest. |
| I71 | RF-EXA-006 | Título obligatorio y descripción opcional. Comprobado con ExamTeacherWorkflowTest y FormRequestRulesTest (22/11/2026). |
| I72 | RF-EXA-007 | Listado de exámenes con grupos y estado, aislado por docente. Comprobado con ExamTeacherWorkflowTest (22/11/2026). |
| I73 | RF-EXA-008 | Apartados con nombre, tipo y cantidad; un examen combina tipos. Comprobado con ExamTeacherWorkflowTest (22/11/2026). |
| I74 | RF-EXA-009 | Puntos por pregunta y por apartado. Comprobado con ExamTeacherWorkflowTest y StudentResultsTest (22/11/2026). |
| I75 | RF-EXA-010 | No se publica si la suma no es exactamente 100. Comprobado con ExamTeacherWorkflowTest (lista de verificación) (22/11/2026). |
| I76 | RF-EXA-011 | Escala 0–10 o 0–100 por examen, calculada sobre 100 puntos. Comprobado con ExamTeacherWorkflowTest (22/11/2026). |
| I77 | RF-EXA-012 | Solo existe la modalidad secuencial; no hay campo para elegir modalidad. |
| I78 | RF-EXA-013 | Tiempo por pregunta. Comprobado con ExamQuestionTimingTest (22/11/2026). |
| I79 | RF-EXA-014 | No hay tiempo total (ACT-WEB-029). El alumno ve como tiempo máximo la suma de los tiempos por pregunta (ACT-WEB-059). |
| I80 | RF-EXA-015 | Orden fijo o aleatorio por examen; el aleatorio se genera por estudiante dentro de cada apartado, respetando el orden de los apartados. Los paquetes no definen el orden. ACT-WEB-060 (commit c93eebc); comprobado con ExamTeacherWorkflowTest (20 sorteos). |
| I81 | RF-EXA-016 | Se asigna a varios grupos solo con programación común; no hay programación por aplicación. |
| I82 | RF-EXA-017 | Apertura y cierre comunes a todos los grupos; el cierre funciona como fin del examen, no como límite para iniciar (ver RF-EXA-050). |
| I83 | RF-EXA-018 | Zona horaria America/Mexico_City (APP_TIMEZONE); la leyenda "Hora del centro de México (UTC-06:00)" se muestra en creación y configuración del examen, en el listado del alumno y en las instrucciones. ACT-WEB-062/ACT-WEB-031 (commit 25e2c9a). App móvil sin revisar. |
| I84 | RF-EXA-019 | Además de apartados, 100 puntos, grupo, fechas y paquete, el checklist exige que cada pregunta tenga tiempo y referencia según su tipo. ACT-WEB-062/ACT-WEB-031 (commit 25e2c9a); comprobado con ExamTeacherWorkflowTest. |
| I85 | RF-EXA-020 | Mientras no se publique se puede modificar contenido y configuración; si está programado, cada cambio se revalida y se revierte si deja de cumplir el checklist. ACT-WEB-062/ACT-WEB-031 (commit 25e2c9a); comprobado con ExamTeacherWorkflowTest. |
| I86 | RF-EXA-021 | «Publicar» equivale a programar: el acceso se habilita al llegar la apertura y exams:push-due avisa una sola vez. Comprobado con ExamPushNotificationsTest (22/11/2026). |
| I87 | RF-EXA-022 | Estructura bloqueada al iniciar la aplicación o con intentos. Comprobado con ExamTeacherWorkflowTest (22/11/2026). |
| I88 | RF-EXA-023 | «Despublicar» mientras no haya intentos. Comprobado con ExamTeacherWorkflowTest (22/11/2026). |
| I89 | RF-EXA-024 | «Cerrar» adelanta el cierre e impide nuevos inicios; los intentos en curso se finalizan conservando sus respuestas y la causa queda en la bitácora («Detención del examen por el docente»), visible para el docente y el alumno. ACT-WEB-059 (commits be1bc8f y e48f1c0); comprobado con ExamQuestionTimingTest. |
| I90 | RF-EXA-025 | Al quedar disponible el examen (publicación con ventana abierta o llegada de la apertura), cada estudiante de los grupos recibe un correo con el enlace a las instrucciones, una sola vez; el enlace exige iniciar sesión y pertenecer al grupo. ACT-WEB-030 (commit bb8b823); comprobado con ExamPushNotificationsTest. |
| I91 | RF-EXA-026 | Lista con apertura, estado y tiempo máximo real (suma de tiempos por pregunta) en lugar de «60 min». ACT-WEB-059 (commit be1bc8f); comprobado con FrontendViewRenderingTest. |
| I92 | RF-EXA-027 | Comprueba publicación, ventana, pertenencia al grupo e intento único. Comprobado con FrontendViewRenderingTest y StudentWebModuleComplianceTest (22/11/2026). |
| I93 | RF-EXA-028 | Instrucciones y condiciones antes de iniciar, con el tiempo máximo real y el orden de preguntas. ACT-WEB-059 (commit be1bc8f); comprobado con StudentWebModuleComplianceTest. |
| I94 | RF-EXA-029 | Registra el inicio y arranca el tiempo. Comprobado con ExamQuestionTimingTest (22/11/2026). |
| I95 | RF-EXA-030 | Una pregunta a la vez sin regreso. Comprobado con ExamQuestionTimingTest (22/11/2026). |
| I96 | RF-EXA-031 | Se puede avanzar antes del tiempo; si la pregunta está sin responder se pide confirmación («¿Continuar sin responder?») y vale cero puntos. ACT-WEB-060 (commit c93eebc); comprobado con ExamQuestionTimingTest y por Claude en navegador con alumno de prueba el 22/11/2026. |
| I97 | RF-EXA-032 | Al agotarse el tiempo se envía y continúa. Comprobado con ExamQuestionTimingTest (22/11/2026). |
| I98 | RF-EXA-033 | No existe la modalidad libre (ver RF-EXA-012). |
| I99 | RF-EXA-034 | Orden guardado en el intento. Comprobado con ExamTeacherWorkflowTest (22/11/2026). |
| I100 | RF-EXA-035 | Selección única. Comprobado con el flujo completo de examen (22/11/2026). |
| I101 | RF-EXA-036 | Correspondencias uno a uno: el servidor rechaza elementos repetidos en web y API. Comprobado con prueba de relación de columnas (22/11/2026). |
| I102 | RF-EXA-037 | Campo de texto. Comprobado con el flujo completo de examen (22/11/2026). |
| I103 | RF-EXA-038 | Campo de código en el lenguaje de la pregunta, conservando espacios y sangrías. Comprobado con el flujo completo de examen (22/11/2026). |
| I104 | RF-EXA-039 | Pegado bloqueado en el campo de código (evento paste: atajo y menú). Revisado en código (22/11/2026). |
| I105 | RF-EXA-040 | El borrador de la pregunta activa se guarda automáticamente en el servidor tras cada cambio, conforme a la navegación secuencial y el tiempo de la pregunta. ACT-WEB-061 (commit e48f1c0); comprobado con ExamQuestionTimingTest y por Claude en navegador con alumno de prueba el 22/11/2026. |
| I106 | RF-EXA-041 | Indicador «Guardando… / ✓ Respuesta guardada / No se pudo guardar»; solo muestra guardada tras la confirmación del servidor y reintenta ante error. ACT-WEB-061 (commit e48f1c0); probado por Claude en navegador el 22/11/2026. |
| I107 | RF-EXA-042 | Cronómetro por pregunta. Comprobado con ExamQuestionTimingTest (22/11/2026). |
| I108 | RF-EXA-043 | No hay tiempo total (ver RF-EXA-014). |
| I109 | RF-EXA-044 | «Finalizar examen» advierte que las preguntas sin responder no obtendrán puntos. Revisado en código (22/11/2026). |
| I110 | RF-EXA-045 | Confirmación antes de entregar; al cancelar sigue el intento. Revisado en código (22/11/2026). |
| I111 | RF-EXA-046 | Confirma la recepción y bloquea cambios. Comprobado con BackendArchitectureAndSecurityTest (22/11/2026). |
| I112 | RF-EXA-047 | El tiempo se calcula en el servidor. Comprobado con ExamQuestionTimingTest (22/11/2026). |
| I113 | RF-EXA-048 | Al volver se reanuda el intento sin reiniciar el tiempo y se recupera la respuesta guardada de la pregunta activa. ExamQuestionTimingTest; ACT-WEB-061 (commit e48f1c0); probado por Claude en navegador el 22/11/2026. |
| I114 | RF-EXA-049 | Una sola sesión activa por intento (bloqueo en caché con presencia cada 15 s; se libera tras 45 s sin actividad); otra sesión o dispositivo recibe rechazo, en web y API. ACT-WEB-062/ACT-WEB-031 (commit 25e2c9a); comprobado con ExamQuestionTimingTest. App móvil sin revisar. |
| I115 | RF-EXA-050 | Decisión de Eli del 22/11/2026: la hora límite solo impide nuevos inicios y los intentos iniciados continúan hasta agotar el tiempo de sus preguntas; las soluciones no se muestran mientras haya intentos en curso. ACT-WEB-059 (commit be1bc8f); comprobado con ExamQuestionTimingTest y StudentResultsTest. |
| I119 | RF-EXA-051 | Un intento por estudiante y examen; solo una reaplicación autorizada por el docente abre otro (RF-EXA-052). Un intento entregado no se reabre (BackendArchitectureAndSecurityTest) y sin autorización no se crea un segundo intento (ExamReapplicationTest, ACT-WEB-032, commit e0a89af). |
| I120 | RF-EXA-052 | El docente autoriza un intento adicional desde la revisión del examen (intento vigente, terminado y con el examen abierto); queda en la bitácora. El intento anterior se conserva con sus resultados y cuenta el más reciente. Decisiones de Eli del 22/11/2026: cuenta el último intento, solo mientras el examen sigue abierto y se permite más de una. ACT-WEB-032 (commit e0a89af, BD01-2026-038); comprobado con ExamReapplicationTest. App móvil sin revisar. |
| I121 | RF-EXA-053 | Web en computadora comprobada en las pruebas de Eli; falta definir la compatibilidad y probar en navegador móvil. |
| I122 | RF-EXA-054 | Servidor: push al publicar, sin duplicados y sin romper la publicación si falla. Comprobado con ExamPushNotificationsTest (22/11/2026). Recepción en la app se revisa en móvil. |
| I123 | RF-EXA-055 | Monitoreo en vivo y endpoint de avance, solo para el docente dueño. Comprobado con las pruebas de monitoreo (22/11/2026). |
| I124 | RF-EXA-056 | Se revisa en la etapa móvil. |
| I125 | RF-EXA-057 | Servidor: aviso de apertura a alumnos y de inicio y término al docente, una sola vez. Comprobado con ExamPushNotificationsTest (22/11/2026). Recepción en la app se revisa en móvil. |

## EVA · Evaluación de respuestas

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 126 | P-113 | RF-EVA-001 | Calificación de opción múltiple | El sistema deberá otorgar los puntos completos de la pregunta cuando la opción seleccionada sea correcta y cero puntos cuando sea incorrecta. Las preguntas sin respuesta se tratarán conforme a P-126. | — | — | — |
| 127 | P-114 | RF-EVA-002 | Calificación de relación de columnas | El sistema deberá otorgar una puntuación proporcional a la cantidad de correspondencias correctas respecto del total de correspondencias de la pregunta. Las incorrectas o no contestadas no sumarán puntos. | — | — | — |
| 128 | P-115 | RF-EVA-003 | Consulta de respuestas para revisión | El sistema deberá permitir al docente consultar las respuestas de los estudiantes a los exámenes que administra, junto con el enunciado, la referencia, los criterios aplicables y el valor de cada pregunta. | — | — | — |
| 129 | P-116 | RF-EVA-004 | Calificación manual de respuestas abiertas | El sistema deberá permitir al docente registrar manualmente la puntuación de cada respuesta abierta, sin requerir créditos de inteligencia artificial. | — | — | — |
| 130 | P-117 | RF-EVA-005 | Calificación manual de programación | El sistema deberá permitir al docente registrar manualmente la puntuación de cada respuesta de programación, sin requerir créditos de inteligencia artificial ni compilar o ejecutar el código. | — | — | — |
| 137 | P-124 | RF-EVA-006 | Validación de puntuaciones | El sistema deberá impedir que se establezcan calificaciones menores que cero o mayores que el valor asignado a la pregunta. | — | — | — |
| 138 | P-125 | RF-EVA-007 | Retroalimentación docente | El sistema deberá permitir al docente agregar comentarios a la calificación de una respuesta. Su disponibilidad para el estudiante se establecerá en las condiciones de consulta de resultados. | — | — | — |
| 139 | P-126 | RF-EVA-008 | Tratamiento de respuestas sin contestar | El sistema deberá asignar cero puntos a las preguntas sin respuesta, sin enviarlas a evaluación asistida ni consumir créditos por ellas. | — | — | — |
| 148 | P-136 | RF-EVA-009 | Restricción de estimaciones al estudiante | El sistema deberá impedir que el estudiante consulte las puntuaciones y justificaciones provisionales generadas por IA. Una puntuación solo podrá formar parte de sus resultados como calificación después de la revisión docente correspondiente. | — | — | — |
| 149 | P-137 | RF-EVA-010 | Identificación de revisión pendiente | El sistema deberá distinguir las respuestas pendientes de revisión de las calificadas y determinar si la evaluación del intento está pendiente o concluida. | — | — | — |
| 150 | P-138 | RF-EVA-011 | Cálculo de calificación del examen | El sistema deberá sumar las puntuaciones establecidas para las respuestas y convertir el resultado a la escala configurada. | — | — | — |
| 151 | P-139 | RF-EVA-012 | Corrección de calificaciones | El sistema deberá permitir al docente corregir calificaciones previamente establecidas, respetando los límites de puntuación. | — | — | — |
| 152 | P-140 | RF-EVA-013 | Historial de cambios de calificación | El sistema deberá conservar, por cada corrección de calificación, el valor anterior, el nuevo valor, la identidad de quien realizó el cambio, la fecha y el motivo. | — | — | — |

## IAR · Asistencia de inteligencia artificial

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 131 | P-118 | RF-IAR-001 | Solicitud de evaluación asistida | El sistema deberá permitir al docente seleccionar respuestas abiertas o de programación y solicitar su evaluación mediante IA. La entrega del examen por el estudiante no generará solicitudes ni consumo automático de créditos. | — | — | — |
| 132 | P-119 | RF-IAR-002 | Evaluación asistida de respuestas abiertas | El sistema deberá solicitar a la IA una evaluación basada en el enunciado y la respuesta de referencia, valorando el cumplimiento del objetivo sin exigir coincidencia textual. | — | — | — |
| 133 | P-120 | RF-IAR-003 | Evaluación asistida de programación | El sistema deberá solicitar a la IA una evaluación basada en el enunciado, la respuesta de referencia y los criterios establecidos, valorando el cumplimiento del objetivo sin exigir coincidencia textual ni compilar o ejecutar el código. | — | — | — |
| 134 | P-121 | RF-IAR-004 | Consulta de sugerencia y justificación | El sistema deberá mostrar exclusivamente al docente autorizado la puntuación sugerida y su justificación, vinculadas con la respuesta evaluada e identificadas como una sugerencia pendiente de revisión. | — | — | — |
| 135 | P-122 | RF-IAR-005 | Confirmación de puntuación sugerida | El sistema deberá permitir al docente confirmar una puntuación sugerida por IA para establecerla como calificación de la respuesta. | — | — | — |
| 136 | P-123 | RF-IAR-006 | Modificación de puntuación sugerida | El sistema deberá permitir al docente modificar una puntuación sugerida por IA antes de establecerla como calificación de la respuesta. | — | — | — |
| 140 | P-127 | RF-IAR-007 | Consulta de créditos | El sistema deberá permitir al docente consultar su saldo disponible de créditos de inteligencia artificial. | — | — | — |
| 141 | P-128 | RF-IAR-008 | Adquisición de créditos | El sistema deberá permitir al docente adquirir créditos de inteligencia artificial de manera separada del paquete docente, conforme al esquema comercial y mecanismo de pago que se definan. | — | — | — |
| 142 | P-129 | RF-IAR-009 | Renovación mensual de créditos | El sistema deberá renovar mensualmente los créditos conforme a la cantidad, fecha y condiciones que se definan. El tratamiento de los créditos no utilizados deberá establecerse antes de implementar esta función. | — | — | — |
| 143 | P-130 | RF-IAR-010 | Comprobación de créditos disponibles | El sistema deberá comprobar que el docente disponga de los créditos necesarios antes de aceptar una solicitud de evaluación asistida. | — | — | — |
| 144 | P-131 | RF-IAR-011 | Consumo por evaluación utilizable | El sistema deberá descontar los créditos correspondientes únicamente cuando se obtenga una evaluación utilizable, conforme a los criterios que se definan. Las solicitudes fallidas no consumirán créditos. | — | — | — |
| 145 | P-133 | RF-IAR-012 | Nueva evaluación de una respuesta | El sistema deberá permitir al docente solicitar otra sugerencia para una respuesta previamente evaluada. Esta solicitud se considerará una nueva evaluación y generará el consumo correspondiente cuando produzca una evaluación utilizable. | — | — | — |
| 146 | P-134 | RF-IAR-013 | Advertencia de nuevo consumo | Antes de solicitar otra evaluación de una respuesta, el sistema deberá mostrar un recuadro que advierta sobre el nuevo consumo y permita confirmar o cancelar la solicitud. | — | — | — |
| 147 | P-135 | RF-IAR-014 | Preferencia de advertencia | El sistema deberá ofrecer en el recuadro la opción «No mostrar de nuevo». Al seleccionarla, deberá omitir esa advertencia en las siguientes solicitudes del docente, sin modificar las reglas de consumo. | — | — | — |

## CAL · Periodos y calificaciones

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 153 | P-141 | RF-CAL-001 | Definición de periodos | El sistema deberá permitir al docente definir los periodos de evaluación de cada grupo que administra, indicando su nombre y orden. La cantidad de periodos será configurable. | — | — | — |
| 154 | P-142 | RF-CAL-002 | Consulta de periodos | El sistema deberá permitir al docente consultar los periodos de evaluación de un grupo y su configuración. | — | — | — |
| 155 | P-143 | RF-CAL-003 | Definición de rubros | El sistema deberá permitir al docente definir los rubros que integrarán cada periodo. Los rubros podrán ser distintos entre periodos e incluir exámenes, conceptos de captura manual y resultados de periodos anteriores. | — | — | — |
| 156 | P-144 | RF-CAL-004 | Asignación de ponderaciones a rubros | El sistema deberá permitir al docente asignar un porcentaje de ponderación a cada rubro del periodo. | — | — | — |
| 157 | P-145 | RF-CAL-005 | Validación de ponderaciones del periodo | El sistema deberá comprobar que la suma de las ponderaciones de los rubros de cada periodo sea exactamente 100 % antes de utilizar su configuración para calcular calificaciones. | — | — | — |
| 158 | P-146 | RF-CAL-006 | Vinculación de exámenes | El sistema deberá permitir al docente relacionar cada aplicación de examen con el periodo y rubro del grupo al que contribuirá. | — | — | — |
| 159 | P-147 | RF-CAL-007 | Ponderación de exámenes dentro del rubro | El sistema deberá permitir al docente asignar un porcentaje a cada examen que integre un rubro de exámenes. | — | — | — |
| 160 | P-148 | RF-CAL-008 | Validación de ponderaciones de exámenes | El sistema deberá comprobar que los porcentajes de los exámenes que integran un rubro sumen exactamente 100 % antes de utilizar esa configuración para calcular su resultado. | — | — | — |
| 161 | P-149 | RF-CAL-009 | Captura de calificaciones por rubro manual | El sistema deberá permitir al docente registrar una calificación global por estudiante para cada rubro manual, sin desglosar tareas, proyectos u otras actividades individuales. | — | — | — |
| 162 | P-150 | RF-CAL-010 | Configuración de escala del grupo | El sistema deberá permitir al docente seleccionar una escala común para las calificaciones del grupo: 0–10 con decimales o 0–100 sin decimales. | — | — | — |
| 163 | P-151 | RF-CAL-011 | Conversión de resultados de exámenes | El sistema deberá convertir proporcionalmente los resultados de exámenes a la escala del grupo cuando utilicen una escala distinta, conforme a las reglas de precisión y redondeo que se definan. | — | — | — |
| 164 | P-152 | RF-CAL-012 | Validación de calificaciones manuales | El sistema deberá impedir la captura de calificaciones manuales menores que cero o mayores que el máximo de la escala del grupo. | — | — | — |
| 165 | P-153 | RF-CAL-013 | Comentarios de calificaciones manuales | El sistema deberá permitir al docente agregar comentarios opcionales a la calificación de un estudiante en un rubro manual. | — | — | — |
| 166 | P-154 | RF-CAL-014 | Cálculo de rubros de exámenes | El sistema deberá calcular el resultado de cada rubro de exámenes utilizando las calificaciones de sus exámenes y los porcentajes asignados. | — | — | — |
| 167 | P-155 | RF-CAL-015 | Integración de periodos anteriores | El sistema deberá permitir al docente utilizar los resultados de uno o varios periodos anteriores como componentes de un periodo posterior, indicando su contribución a la calificación. | — | — | — |
| 168 | P-156 | RF-CAL-016 | Validación de dependencias entre periodos | El sistema deberá impedir que un periodo dependa de sí mismo o que se formen dependencias circulares entre periodos. | — | — | — |
| 169 | P-157 | RF-CAL-017 | Cálculo de calificación por periodo | El sistema deberá calcular automáticamente la calificación de cada estudiante en un periodo utilizando los resultados de sus rubros y las ponderaciones establecidas. | — | — | — |
| 170 | P-158 | RF-CAL-018 | Configuración de calificación final | El sistema deberá permitir al docente seleccionar los periodos que integrarán la calificación final del grupo y asignarles sus porcentajes de ponderación. | — | — | — |
| 171 | P-159 | RF-CAL-019 | Validación de ponderaciones finales | El sistema deberá comprobar que las ponderaciones de los periodos seleccionados para integrar la calificación final sumen exactamente 100 %. | — | — | — |
| 172 | P-160 | RF-CAL-020 | Cálculo de calificación final | El sistema deberá calcular automáticamente la calificación final de cada estudiante a partir de los periodos y ponderaciones configurados. | — | — | — |
| 173 | P-161 | RF-CAL-021 | Identificación de calificaciones pendientes | El sistema deberá distinguir la ausencia de calificación de una calificación de cero y no presentar como definitivo un resultado que dependa de calificaciones pendientes. | — | — | — |
| 174 | P-162 | RF-CAL-022 | Corrección de calificaciones manuales | El sistema deberá permitir al docente corregir las calificaciones capturadas en rubros manuales, respetando la escala del grupo y registrando el motivo de la corrección. | — | — | — |
| 175 | P-163 | RF-CAL-023 | Historial de correcciones manuales | El sistema deberá conservar el valor anterior, el nuevo valor, la identidad de quien realizó el cambio, la fecha y el motivo de cada corrección de calificación manual. | — | — | — |
| 176 | P-164 | RF-CAL-024 | Confirmación de cambios en la evaluación | Antes de aplicar cambios a ponderaciones o elementos de evaluación con calificaciones existentes, el sistema deberá advertir sus efectos y solicitar confirmación al docente. Si cancela, no deberá aplicar los cambios. | — | — | — |
| 177 | P-165 | RF-CAL-025 | Conservación de configuraciones anteriores | El sistema deberá conservar la configuración anterior cuando se modifiquen ponderaciones o elementos de evaluación que ya tengan calificaciones asociadas, identificando quién realizó el cambio y cuándo. | — | — | — |
| 178 | P-166 | RF-CAL-026 | Recálculo de resultados | El sistema deberá actualizar los resultados afectados cuando cambie una calificación o una configuración de evaluación, manteniendo la relación con el cambio que originó el recálculo. | — | — | — |
| 179 | P-167 | RF-CAL-027 | Consulta del desglose docente | El sistema deberá permitir al docente consultar las calificaciones por estudiante, periodo, rubro y examen, distinguiendo los resultados pendientes de los definitivos. | — | — | — |
| 191 | P-179 | RF-CAL-028 | Configuración de calificación aprobatoria | El sistema deberá permitir al docente establecer desde la web la calificación mínima aprobatoria de cada grupo, dentro de su escala. Una calificación definitiva igual o superior al mínimo se considerará aprobatoria. | — | — | — |

## REP · Resultados y reportes

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 180 | P-168 | RF-REP-001 | Publicación automática de resultados | El sistema deberá publicar automáticamente la calificación de un intento cuando haya finalizado y todas sus respuestas tengan una calificación definitiva. En exámenes de preguntas cerradas, la publicación procederá al concluir la calificación automática. | — | — | — |
| 181 | P-169 | RF-REP-002 | Estado de evaluación pendiente | Mientras la evaluación del intento se encuentre pendiente conforme a P-137, el sistema deberá mostrar al estudiante únicamente «Pendiente de evaluación», sin publicar calificaciones parciales ni estimaciones de IA. | — | — | — |
| 182 | P-170 | RF-REP-003 | Consulta de calificación del examen | El sistema deberá permitir al estudiante consultar la calificación publicada de sus exámenes y la escala utilizada. | — | — | — |
| 183 | P-171 | RF-REP-004 | Consulta de puntuaciones del examen | Una vez publicada la calificación, el sistema deberá permitir al estudiante consultar los puntos obtenidos por pregunta y por apartado. | — | — | — |
| 184 | P-172 | RF-REP-005 | Consulta de respuestas y soluciones | El sistema deberá mostrar al estudiante sus respuestas y las soluciones de referencia cuando la calificación esté publicada y haya finalizado el plazo de acceso al examen. Antes de cumplir ambas condiciones, deberá impedir su consulta. | — | — | — |
| 185 | P-173 | RF-REP-006 | Consulta de retroalimentación docente | El sistema deberá mostrar al estudiante los comentarios del docente cuando se publique la calificación correspondiente, sin mostrar las justificaciones provisionales generadas por IA. | — | — | — |
| 186 | P-174 | RF-REP-007 | Consulta de calificaciones por periodo | El sistema deberá permitir al estudiante consultar sus rubros, porcentajes de ponderación y calificaciones registradas por periodo. Los valores pendientes deberán identificarse sin sustituirlos por cero. | — | — | — |
| 187 | P-175 | RF-REP-008 | Consulta de calificación final | El sistema deberá permitir al estudiante consultar su calificación final e identificarla como definitiva únicamente cuando estén completos los resultados de los que depende. | — | — | — |
| 188 | P-176 | RF-REP-009 | Consulta docente de resultados del examen | El sistema deberá permitir al docente consultar los resultados y estados de evaluación de los estudiantes en cada aplicación de examen que administra. | — | — | — |
| 189 | P-177 | RF-REP-010 | Estadísticas de participación | El sistema deberá mostrar al docente, por aplicación de examen, la cantidad de estudiantes que iniciaron, finalizaron y tienen evaluación pendiente. | — | — | — |
| 190 | P-178 | RF-REP-011 | Estadísticas de calificaciones | El sistema deberá mostrar al docente el promedio de las calificaciones definitivas y la cantidad de estudiantes aprobados y no aprobados por aplicación de examen. Los resultados pendientes no se contabilizarán como calificaciones definitivas. | — | — | — |
| 192 | P-180 | RF-REP-012 | Estadísticas del estudiante | El sistema deberá permitir al estudiante consultar sus propios resultados y evolución por periodos, sin mostrar nombres ni calificaciones individuales de sus compañeros. | — | — | — |
| 193 | P-181 | RF-REP-013 | Consulta desde web y móvil | El sistema deberá permitir a docentes y estudiantes consultar los resultados y estadísticas que les correspondan desde la web y la aplicación móvil, respetando sus permisos. | — | — | — |
| 194 | P-182 | RF-REP-014 | Exportación de calificaciones | El sistema deberá permitir al docente exportar desde la web las calificaciones de un grupo, seleccionando un periodo o el conjunto de periodos. El archivo Excel deberá incluir estudiantes, exámenes, rubros, ponderaciones, calificaciones por periodo y calificación final, según la selección realizada. | — | — | — |
| 195 | P-183 | RF-REP-015 | Identificación de datos en la exportación | El sistema deberá representar las calificaciones pendientes mediante el texto «Pendiente», distinguiéndolas de cero, e incluir la fecha de generación del archivo y la escala utilizada. | — | — | — |
| 196 | P-184 | RF-REP-016 | Actualización de consultas y estadísticas | El sistema deberá actualizar las consultas y estadísticas cuando se modifiquen las calificaciones o se recalculen los resultados relacionados. Los archivos previamente exportados conservarán su contenido; para obtener los resultados actualizados deberá generarse una nueva exportación. | — | — | — |
| 197 | P-185 | RF-REP-017 | Aviso de corrección de calificación | El sistema deberá mostrar al estudiante un aviso cuando cambie una calificación publicada. El aviso mostrará únicamente la nueva calificación, sin identificar el elemento afectado. | — | — | — |

## ADM · Administración general

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 201 | P-188 | RF-ADM-001 | Consulta administrativa de docentes | El sistema deberá permitir al superadministrador consultar los docentes registrados, su nivel, estado de suscripción, fechas de renovación y estado de pago. | — | — | — |
| 202 | P-189 | RF-ADM-002 | Consulta administrativa de estudiantes | El sistema deberá permitir al superadministrador consultar los datos básicos y el estado de las cuentas de estudiantes, sin mostrar preguntas, respuestas ni calificaciones. | — | — | — |
| 203 | P-190 | RF-ADM-003 | Suspensión de cuentas | El sistema deberá permitir al superadministrador suspender cuentas, registrando el motivo, su identidad y la fecha de la operación. Los efectos específicos para docentes y estudiantes deberán definirse antes de implementar esta función. | — | — | — |
| 204 | P-191 | RF-ADM-004 | Reactivación de cuentas | El sistema deberá permitir al superadministrador reactivar cuentas cuando se resuelva la causa de suspensión. La reactivación no deberá registrar un pago ni habilitar por sí misma una suscripción pagada. | — | — | — |
| 205 | P-192 | RF-ADM-005 | Consulta administrativa de pagos | El sistema deberá permitir al superadministrador consultar las operaciones de pago, sus estados y las confirmaciones proporcionadas por el servicio de cobro. | — | — | — |
| 206 | P-193 | RF-ADM-006 | Comprobación de evidencia de pago | El sistema deberá impedir que un cobro se registre manualmente como pagado sin evidencia que lo respalde. Este requerimiento no autoriza por sí mismo la conciliación manual de pagos. | — | — | — |
| 207 | P-194 | RF-ADM-007 | Supervisión de cobros fallidos | El sistema deberá permitir al superadministrador consultar cobros pendientes o rechazados e identificar las incidencias relacionadas. | — | — | — |
| 208 | P-195 | RF-ADM-008 | Administración de precios | El sistema deberá permitir al superadministrador modificar los precios de los tres niveles, conforme a las reglas de vigencia y aplicación a suscripciones existentes que se definan. | — | — | — |
| 209 | P-196 | RF-ADM-009 | Administración de capacidades y límites | El sistema deberá permitir al superadministrador configurar las capacidades y límites de los tres niveles, conforme a las reglas que se definan para suscripciones existentes. | — | — | — |
| 210 | P-197 | RF-ADM-010 | Consulta administrativa de créditos de IA | El sistema deberá permitir al superadministrador consultar los saldos, renovaciones y movimientos de consumo de créditos, sin acceder al contenido académico enviado a evaluación ni a sus resultados. | — | — | — |
| 211 | P-198 | RF-ADM-011 | Consulta de consumo de prueba gratuita | El sistema deberá permitir al superadministrador consultar el consumo del grupo y examen de prueba por cuenta. La eliminación de grupos, exámenes o suscripciones no deberá restablecer automáticamente el beneficio. | — | — | — |
| 212 | P-199 | RF-ADM-012 | Consulta de estados de grupos y exámenes | El sistema deberá permitir al superadministrador consultar el estado operativo de grupos y aplicaciones de examen para atender incidencias, sin mostrar preguntas, respuestas ni calificaciones. | — | — | — |
| 213 | P-201 | RF-ADM-013 | Protección de credenciales en administración | El sistema deberá impedir que las funciones administrativas muestren contraseñas o códigos de autenticación de los usuarios. | — | — | — |
| 214 | P-202 | RF-ADM-014 | Consulta limitada de medios de pago | El sistema deberá mostrar al superadministrador únicamente los datos limitados del medio de pago proporcionados por el servicio seleccionado, sin exponer credenciales de pago completas. | — | — | — |
| 215 | P-203 | RF-ADM-015 | Publicación de términos y condiciones | El sistema deberá permitir al superadministrador publicar versiones de los términos y condiciones, conservando las anteriores e identificando la fecha de entrada en vigor de cada versión. | — | — | — |
| 216 | P-204 | RF-ADM-016 | Publicación del aviso de privacidad | El sistema deberá permitir al superadministrador publicar versiones del aviso de privacidad, conservando las anteriores e identificando la fecha de entrada en vigor de cada versión. | — | — | — |
| 217 | P-205 | RF-ADM-017 | Registro de operaciones administrativas | El sistema deberá conservar la identidad del superadministrador, fecha, operación realizada y elemento afectado para las acciones administrativas relevantes. Cuando corresponda, deberá registrar también el motivo, sin incorporar contenido académico restringido. | — | — | — |
| 218 | P-206 | RF-ADM-018 | Confirmación de operaciones delicadas | El sistema deberá solicitar confirmación antes de ejecutar suspensiones, eliminaciones o ajustes autorizados que afecten acceso, pagos, créditos o información. Si se cancela la confirmación, no deberá ejecutar la operación. | — | — | — |

## TEC · Categoría RNF

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 219 | P-207 | RNF-TEC-001 | Tecnología web | La aplicación web deberá desarrollarse utilizando Laravel, conforme al Manual de Programación Laravel vigente. | — | — | — |
| 220 | P-208 | RNF-TEC-002 | Tecnología móvil | La aplicación móvil deberá desarrollarse utilizando Flutter, conforme al Manual de Programación Móvil vigente. | — | — | — |
| 221 | P-209 | RNF-TEC-003 | Gestor de base de datos | El sistema deberá utilizar PostgreSQL como gestor de base de datos. | — | — | — |
| 243 | P-231 | RNF-TEC-004 | Restricción de ejecución de respuestas | En el alcance inicial, el sistema no deberá compilar ni ejecutar el código proporcionado por los estudiantes, incluido el proceso de evaluación asistida mediante IA. | — | — | — |

**Observaciones originales**

| Celda | Código previsto | Observación (I) |
|---|---|---|
| I221 | RNF-TEC-003 | Modificado el 10/11/2026 (CAM-003). |

## SEG · Categoría RNF

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 222 | P-210 | RNF-SEG-001 | Comunicación cifrada | Las comunicaciones entre las aplicaciones y el servidor deberán realizarse mediante HTTPS. | — | — | — |
| 223 | P-211 | RNF-SEG-002 | Protección de contraseñas almacenadas | Las contraseñas deberán almacenarse mediante un mecanismo de hash diseñado para contraseñas. No deberán conservarse en texto legible ni mediante un mecanismo que permita recuperar su valor original. | — | — | — |
| 224 | P-212 | RNF-SEG-003 | Autorización en el servidor | El servidor deberá comprobar los permisos del usuario para cada operación y acceso a información, incluidas las solicitudes directas que no utilicen la interfaz prevista. | — | — | — |
| 225 | P-213 | RNF-SEG-004 | Separación de información entre docentes | El sistema deberá impedir que un docente consulte o modifique bancos de preguntas, grupos, exámenes o calificaciones pertenecientes a otro docente. | — | — | — |
| 226 | P-214 | RNF-SEG-005 | Restricción de información del estudiante | El sistema deberá limitar las consultas y operaciones del estudiante a su propia información y a los grupos y recursos para los que tenga autorización vigente. | — | — | — |
| 227 | P-215 | RNF-SEG-006 | Restricción administrativa del contenido académico | El sistema deberá impedir al superadministrador consultar o modificar preguntas, respuestas y calificaciones mediante interfaces, servicios, reportes y exportaciones administrativos. | — | — | — |
| 231 | P-220 | RNF-SEG-007 | Validación de archivos | El servidor deberá validar el formato y tamaño de fotografías, imágenes y PDF conforme a los límites definidos, sin depender exclusivamente de las comprobaciones de la interfaz. | — | — | — |
| 232 | P-221 | RNF-SEG-008 | Protección de acceso a archivos | El servidor deberá comprobar la autorización antes de permitir el acceso a archivos almacenados. Conocer su dirección no deberá permitir acceder a un recurso restringido. | — | — | — |
| 233 | P-222 | RNF-SEG-009 | Protección de claves de servicios | Las claves secretas de pagos, correo e inteligencia artificial no deberán incluirse en código público ni en las aplicaciones distribuidas al usuario. | — | — | — |
| 240 | P-229 | RNF-SEG-010 | Protección de información en errores | Los mensajes dirigidos al usuario deberán identificar el problema y, cuando corresponda, la acción disponible, sin exponer contraseñas, claves, consultas internas ni información de otros usuarios. | — | — | — |
| 241 | — | RNF-SEG-011 | Controles de integridad en el dispositivo móvil | La aplicación móvil deberá impedir las capturas y la grabación de pantalla, su uso en pantalla dividida o ventana múltiple y la interacción mediante ventanas superpuestas, y registrar como incidente de integridad la salida de la aplicación durante un examen. | — | — | — |

**Observaciones originales**

| Celda | Código previsto | Observación (I) |
|---|---|---|
| I241 | RNF-SEG-011 | Agregado el 10/11/2026. |

## CON · Categoría RNF

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 228 | P-216 | RNF-CON-001 | Referencia temporal de exámenes | La disponibilidad y duración de los intentos deberán determinarse mediante el tiempo del servidor. Cambiar la hora del dispositivo no deberá ampliar el tiempo ni alterar las condiciones de acceso. | — | — | — |
| 229 | P-218 | RNF-CON-002 | Integridad de operaciones relacionadas | El sistema deberá impedir que una operación confirmada deje registros internos incompatibles o incompletos en pagos, consumo de créditos, incorporaciones a grupos y entregas de intentos. | — | — | — |
| 230 | P-219 | RNF-CON-003 | Prevención de duplicados por reintentos | El reintento técnico de una misma operación no deberá generar registros o efectos duplicados, como cargos, descuentos de créditos, inscripciones o entregas adicionales. Una nueva evaluación solicitada expresamente por el docente se distinguirá de un reintento técnico. | — | — | — |
| 244 | P-232 | RNF-CON-004 | Confirmación de operaciones externas | El sistema no deberá presentar como exitosa una operación de correo, pagos o IA sin recibir la confirmación correspondiente del proveedor. Cuando no pueda determinar su resultado, deberá conservar un estado que lo distinga de una operación confirmada o rechazada. En correo, la confirmación de aceptación del envío por el proveedor no deberá presentarse como confirmación de entrega al destinatario. | — | — | — |

## PRI · Categoría RNF

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 234 | P-223 | RNF-PRI-001 | Minimización de datos enviados a IA | Las solicitudes de evaluación asistida deberán incluir únicamente la información necesaria para evaluar la respuesta, excluyendo identificadores personales del estudiante que no sean necesarios. | — | — | — |
| 242 | P-230 | RNF-PRI-002 | Conservación y eliminación coherentes | La conservación y eliminación de información deberá respetar las reglas acordadas para cada tipo de dato, distinguiendo antecedentes académicos, registros de pago, movimientos de créditos y consumo de la prueba gratuita. La eliminación de antecedentes académicos deberá contemplar los historiales individuales relacionados, conforme al alcance de eliminación que se defina. | — | — | — |

## USA · Categoría RNF

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 235 | P-224 | RNF-USA-001 | Adaptación de interfaces | En los tamaños de pantalla y dispositivos incluidos en el alcance de compatibilidad, el usuario deberá poder visualizar y utilizar los controles necesarios para completar las operaciones previstas, sin que queden ocultos o inaccesibles por la distribución de la interfaz. | — | — | — |
| 237 | P-226 | RNF-USA-002 | Identificación de campos | Los campos de los formularios deberán contar con etiquetas que permitan reconocer la información solicitada. | — | — | — |

## ACC · Categoría RNF

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 236 | P-225 | RNF-ACC-001 | Acceso por teclado en web | Las operaciones web incluidas en el alcance de accesibilidad deberán poder completarse mediante teclado, con un indicador visible del elemento que tiene el foco. | — | — | — |
| 238 | P-227 | RNF-ACC-002 | Información independiente del color | Los estados, advertencias y errores deberán identificarse mediante texto u otro indicador adicional al color. | — | — | — |

## REC · Categoría RNF

| Fila | Referencia (A) | Código previsto (B) | Nombre (C) | Descripción (D) | Docente web (E) | Alumno web (F) | Superadmin web (H) |
|---:|---|---|---|---|:---:|:---:|:---:|
| 239 | P-228 | RNF-REC-001 | Respaldos y recuperación | La base de datos deberá contar con respaldos y procedimientos de recuperación conforme al Manual de Base de Datos. Su configuración operativa y las comprobaciones de restauración deberán definirse antes de su puesta en operación. | — | — | — |
