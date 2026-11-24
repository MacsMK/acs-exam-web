# Informe de cumplimiento web · Fase 2

Commit `e0a89af1d774769e1d00deb5423329f632199944`. Cada ficha reúne la fuente, evidencia estática, diferencia observada y acción sugerida. Las fichas WEB recogen diferencias de implementación, de documentación del proyecto o de trazabilidad. Las fichas DOC conservan los conflictos entre fuentes y su tratamiento. Las condiciones operativas pendientes no se cuentan como fallas demostradas.

[Consultar la matriz de 238 requerimientos](matriz_trazabilidad.md) · [Consultar las 298 reglas de manual, los 14 catálogos y el registro DOC](manuales_transversales.md) · [Consultar el corte y las huellas](00_estado_inicial.md).

| Severidad | WEB | DOC | Total |
|---|---|---|---|
| Crítica | 19 | 0 | 19 |
| Alta | 49 | 12 | 61 |
| Media | 54 | 20 | 74 |
| Baja | 12 | 9 | 21 |
| **Total** | **134** | **41** | **175** |

Los DOC-001 a DOC-035 proceden de la Fase 1. DOC-033 mantiene la discrepancia de índice ya localizada. DOC-035 solo requiere decisión si se redistribuyen las ilustraciones. Los demás 39 están abiertos. Las observaciones del Excel y el informe histórico de otro commit se trataron como antecedentes.

## Severidad crítica

### WEB-AUT-001

Categoría 1 · Crítica · [Informe de origen](AUT.md).

**Fuente** · RF-AUT-003/004, D9:D10. MPW §1.3.7 pp. 21–22

**Evidencia** · resources/views/auth/verify-2fa.blade.php:17-25, config/mail.php:17, .env.example:65 y app/Services/AuthService.php:294-301

**Esperado** · Comprobar posesión del buzón después de las credenciales.

**Encontrado** · Con los transportes log o array el código aparece en el mismo navegador y no llega al buzón. La selección depende del transporte y el ejemplo distribuido usa log. El transporte efectivo en producción requiere evidencia.

**Acción sugerida** · Restringir el comportamiento de demostración a entornos aislados con cuentas ficticias y exigir entrega externa para accesos reales.


### WEB-AUT-002

Categoría 8 · Crítica · [Informe de origen](AUT.md).

**Fuente** · RF-AUT-005/007, D11 y D13

**Evidencia** · app/Http/Controllers/Auth/LoginController.php:100-112 y app/Services/AuthService.php:383-411

**Esperado** · Consumir el código exactamente una vez y respetar el máximo de cinco intentos.

**Encontrado** · La validación lee el estado y después lo guarda por separado. La escritura carece de condición que imponga consumo único frente a verificaciones simultáneas. La comprobación del máximo también precede al incremento (app/Services/AuthService.php:385–396,428–436).

**Acción sugerida** · Consumir mediante una operación atómica que valide vigencia y verifique una única transición.


### WEB-CAL-001

Categoría 8 · Crítica · [Informe de origen](CAL.md).

**Fuente** · RF-CAL-011/014 y RF-REP-015; MPW-V3 §6.12, tabla 9, pp. 76–77

**Evidencia** · app/Http/Controllers/EvaluationPeriodController.php:122–130, app/Services/GradeSheetExportService.php:185–203, app/Services/GradeCalculatorService.php:48–54

**Esperado** · Convertir resultados a la misma escala antes de combinarlos.

**Encontrado** · Dos exámenes equivalentes de 8/10 y 80/100 producen una sugerencia de 44. La captura o exportación puede usarla como nota de exámenes del periodo.

**Acción sugerida** · Normalizar cada examen a la escala grupal antes de combinar, capturar o exportar.


### WEB-CAL-002

Categoría 8 · Crítica · [Informe de origen](CAL.md).

**Fuente** · RF-CAL-017/021, RF-REP-007/008/015 y DOC-003

**Evidencia** · app/Http/Controllers/EvaluationPeriodController.php:158–185, app/Services/GradeCalculatorService.php:35–41 y resources/views/evaluation-periods/gradebook.blade.php:218–225

**Esperado** · Distinguir cero capturado y rubro pendiente en los cálculos dependientes.

**Encontrado** · Con dos rubros de 50 %, uno en 80 y el otro nulo, el guardado escribe 40 como nota final del periodo. La vista y el Excel pueden darla como definitiva.

**Acción sugerida** · Establecer la precondición del cálculo y propagar el estado pendiente de cada rubro con peso positivo.


### WEB-CAL-003

Categoría 8 · Crítica · [Informe de origen](CAL.md).

**Fuente** · RNF-SEG-003, D224; RF-CAL-009, D161

**Evidencia** · app/Http/Controllers/EvaluationPeriodController.php:114–119,147–157,175–186, app/Http/Requests/GradebookSaveRequest.php:35–43 y database/migrations/2026_10_26_180100_bd01_2026_021_create_acs_student_period_grades_table.php:32–43

**Esperado** · La captura del grupo afecta exclusivamente a estudiantes inscritos en él.

**Encontrado** · La lectura carga el padrón, pero la escritura verifica el grupo del periodo y que el usuario exista; carece de comprobación de pertenencia para cada nota. El esquema guarda la asociación con cualquier usuario existente.

**Acción sugerida** · Validar la membresía y el rol en el servidor antes de confirmar la captura del grupo.


### WEB-EVA-001

Categoría 8 · Crítica · [Informe de origen](EVA.md).

**Fuente** · RF-EVA-008 (D139); RF-EVA-001, D126

**Evidencia** · app/Services/AttemptService.php:778–788, resources/views/exams/review-attempt.blade.php:437–451, app/Http/Controllers/TeacherReviewController.php:352–384,418–425; app/Http/Requests/AttemptAnswerWebRequest.php:49, app/Http/Controllers/AttemptController.php:224–230 y app/Services/AttemptService.php:666–673

**Esperado** · La respuesta vacía conserva cero puntos en la calificación.

**Encontrado** · La escritura manual admite puntos positivos para abiertas o código incluso cuando la propia entrega los marcó como blancos. El recálculo incluye esos puntos. En opción múltiple, si la selección está presente pero su valor es nulo, el controlador la transforma en índice cero y puede asignar los puntos de la primera opción.

**Acción sugerida** · Conservar el cero del blanco tanto en el formulario como en la validación de escritura. Conservar la distinción entre blanco e índice cero desde la validación hasta la puntuación.


### WEB-EXA-006

Categoría 8 · Crítica · [Informe de origen](EXA_1_armado_y_programacion.md).

**Fuente** · RF-EXA-022, D87/I87; RF-BAN-010 y RF-EXA-004

**Evidencia** · app/Http/Controllers/ExamController.php:141–145; app/Services/ExamAssemblyService.php:102–149; app/Repositories/Eloquent/EloquentExamRepository.php:312–324; resources/views/exams/edit.blade.php:146–193; app/Services/QuestionBankService.php:526–555,577 y app/Services/AttemptService.php:188–196,872–887

**Esperado** · Bloquear los cambios en configuración y contenido durante la aplicación con intentos.

**Encontrado** · La edición de datos base protege escala y orden, pero todavía guarda cambios en instrucciones, grupos, límite de incidentes y cierre de un examen abierto. Estas modificaciones pueden cambiar acceso y condiciones académicas a mitad de la aplicación. Agregar paráfrasis a la misma versión después de publicar también amplía el conjunto que pueden recibir los alumnos que comiencen más tarde.

**Acción sugerida** · Aplicar el control de inmutabilidad en todas las entradas de edición con el estado actual bajo bloqueo.


### WEB-EXA-007

Categoría 8 · Crítica · [Informe de origen](EXA_1_armado_y_programacion.md).

**Fuente** · RF-EXA-024 y RF-EXA-040, D89/D105

**Evidencia** · app/Services/ExamAssemblyService.php:368–385; app/Services/AttemptService.php:352–369 y app/Services/AttemptService.php:382–403

**Esperado** · Conservar respuestas registradas cuando el docente detiene la aplicación.

**Encontrado** · El intento pasa a expirado sin incorporar el borrador de la pregunta activa. La respuesta que el sistema ya confirmó como guardada en caché queda fuera de las respuestas consultables y caduca.

**Acción sugerida** · Consolidar la última respuesta confirmada y su fecha antes de cerrar el intento.


### WEB-EXA-010

Categoría 8 · Crítica · [Informe de origen](EXA_2_aplicacion.md).

**Fuente** · RF-EXA-040/044/045, D105/D109/D110

**Evidencia** · resources/views/exams/take-exam.blade.php:54–60, resources/views/exams/take-exam.blade.php:197–225; app/Http/Controllers/AttemptController.php:150–164; app/Services/AttemptService.php:393–403, app/Services/AttemptService.php:514–550

**Esperado** · Usar la última respuesta que el servidor confirmó al entregar voluntariamente.

**Encontrado** · El botón final envía un formulario separado sin el reactivo activo. Al consolidar se suman solo student answers ya registradas mediante avance; el borrador actual, incluso confirmado al alumno, no pasa al resultado.

**Acción sugerida** · Consolidar el borrador activo con su marca de tiempo al aceptar la entrega.


### WEB-EXA-011

Categoría 8 · Crítica · [Informe de origen](EXA_2_aplicacion.md).

**Fuente** · RF-EXA-030/046, Excel D95 y D111

**Evidencia** · app/Http/Controllers/AttemptController.php:85–106; app/Services/AttemptService.php:326–330, app/Services/AttemptService.php:352–366, app/Services/AttemptService.php:503–514 y app/Services/AttemptService.php:544–551

**Esperado** · Que cada avance se registre una vez y el conjunto de respuestas permanezca inmutable tras el cierre.

**Encontrado** · El control de posición y estado ocurre antes de bloquear el intento; el bloque que escribe actualiza la respuesta e incrementa incondicionalmente el índice. La entrega final calcula sobre una lectura distinta. El solapamiento de operaciones admite saltos o respuestas que divergen de la nota consolidada.

**Acción sugerida** · Revalidar estado y posición dentro de una transacción que serialice respuesta y cierre; hacer el avance idempotente.


### WEB-EXA-016

Categoría 8 · Crítica · [Informe de origen](EXA_2_aplicacion.md).

**Fuente** · RF-EXA-049, Excel D114/I114

**Evidencia** · app/Http/Controllers/AttemptController.php:54–66, app/Http/Controllers/AttemptController.php:150–159 y app/Http/Controllers/AttemptController.php:237–253; app/Services/AttemptService.php:417–438

**Esperado** · Reservar la única sesión presentadora y comprobarla en operaciones del intento.

**Encontrado** · La apertura lee disponibilidad sin reservarla. La presencia lee y escribe en pasos separados; la entrega comprueba propietario y estado pero omite sesión titular. La exclusividad que protege la entrega queda incompleta.

**Acción sugerida** · Adquirir la titularidad atómicamente al abrir y exigirla en operaciones que cambian el intento.


### WEB-GRU-001

Categoría 8 · Crítica · [Informe de origen](GRU.md).

**Fuente** · RF-GRU-013, D33

**Evidencia** · app/Http/Controllers/StudentExamController.php:62-67, app/Http/Controllers/AttemptController.php:41-49, app/Services/AttemptService.php:167-194 y app/Models/Exam.php:104-106

**Esperado** · Impedir inicios nuevos en grupos cerrados.

**Encontrado** · Las instrucciones exigen grupo activo. Al crear el intento, el servicio selecciona la primera pertenencia de los grupos del examen sin verificar que el grupo siga activo.

**Acción sugerida** · Comprobar matrícula activa y estado del grupo al crear el intento y fijar el grupo correcto.


### WEB-GRU-002

Categoría 1 · Crítica · [Informe de origen](GRU.md).

**Fuente** · RF-GRU-011/012/019, D31:D32 y D198

**Evidencia** · app/Http/Controllers/AttemptController.php:167-190,261-264, app/Http/Controllers/StudentGradeController.php:43-52 y resources/views/student/grades/index.blade.php:163-207

**Esperado** · Retirar el permiso de consultar resultados al salir, ser retirado o cerrarse el grupo.

**Encontrado** · El detalle del intento comprueba solo a su propietario. El resumen de notas filtra grupo abierto pero omite matrícula, por lo que también conserva notas tras la baja.

**Acción sugerida** · Aplicar matrícula vigente y grupo abierto a cada consulta de resultados y a las listas.


### WEB-GRU-003

Categoría 8 · Crítica · [Informe de origen](GRU.md).

**Fuente** · RF-GRU-015/020, D35 y D199

**Evidencia** · app/Services/GroupRetentionService.php:125-141,151-171 y app/Http/Controllers/GroupController.php:156-166

**Esperado** · La reapertura cancela la purga y preserva los datos académicos.

**Encontrado** · La selección de candidatos ocurre antes de la transacción. La purga no vuelve a verificar ni bloquea el estado de cierre antes de borrar. Una reapertura entre ambas operaciones queda ignorada.

**Acción sugerida** · Serializar reapertura y purga sobre el mismo grupo y revalidar el cierre exacto antes del borrado.


### WEB-GRU-004

Categoría 1 · Crítica · [Informe de origen](GRU.md).

**Fuente** · RF-GRU-015, D35

**Evidencia** · app/Services/GroupRetentionService.php:141-152, app/Services/AttemptService.php:103-114 y app/Services/ExamAssemblyService.php:377-385

**Esperado** · Eliminar historiales individuales de intentos y respuestas del grupo y conservar el registro de purga.

**Encontrado** · Solo se eliminan registros de auditoría vinculados a StudentAnswer. Los del intento, incluida la autorización de reaplicación con identificador del estudiante, pueden persistir después de su eliminación.

**Acción sugerida** · Incluir los historiales individuales de los intentos en el borrado y mantener el movimiento agregado de purga.


### WEB-GRU-009

Categoría 8 · Crítica · [Informe de origen](GRU.md).

**Fuente** · RF-GRU-016/017, D116:D117; RF-GRU-007, D27

**Evidencia** · app/Services/GroupEnrollmentService.php:160-175,238-253 y app/Services/AttemptService.php:167-194; app/Services/GroupEnrollmentService.php:86–103,289–306

**Esperado** · Conservar la matrícula en todo intento en curso.

**Encontrado** · La comprobación de intento y la desvinculación son pasos separados. El inicio también consulta matrícula antes de su transacción. Pueden coexistir un intento iniciado y una baja aceptada. La incorporación comprueba que el grupo está abierto antes de la transacción de alta; el cierre puede producirse entre ambas operaciones.

**Acción sugerida** · Coordinar alta de intento y baja de matrícula bajo el mismo bloqueo y comprobar el estado dentro de la transacción.


### WEB-MBD-002

Categoría 4 · Crítica · [Informe de origen](manuales_transversales.md).

**Fuente** · MBD-030 · MBD-V3 §4.2.2, tabla 12, p. 23

**Evidencia** · database/migrations/2026_10_26_170000_bd01_2026_018_create_acs_student_attempts_table.php:34–37 y database/migrations/2026_10_26_170100_bd01_2026_019_create_acs_student_answers_table.php:36–43

**Esperado** · Proteger por RESTRICT los intentos y respuestas con valor de evidencia frente a borrado indirecto.

**Encontrado** · El esquema permite que un borrado físico del alumno arrastre sus intentos y que el borrado físico del intento arrastre respuestas. Se constató la acción declarada en FK, sin observar un borrado ejecutado.

**Acción sugerida** · Alinear las acciones de borrado con la conservación de evidencia y con el procedimiento autorizado de retención.


### WEB-RNF-001

Categoría 1 · Crítica · [Informe de origen](RNF.md).

**Fuente** · RNF-SEG-005/008, RF-GRU-011/012/019, D226, D232, D31:D32, D198

**Evidencia** · app/Http/Controllers/QuestionFileController.php:37-49 y app/Services/QuestionFileService.php:103-119

**Esperado** · Restringir archivos a titulares o estudiantes con autorización vigente.

**Encontrado** · La consulta permite al alumno consultar material de una pregunta por tener un intento histórico del examen. No exige que conserve su matrícula o que el grupo siga abierto. El dato se comprueba en servidor, pero el permiso permanece después de la baja.

**Acción sugerida** · Comprobar grupo, matrícula y etapa autorizada junto con la propiedad del intento antes de servir el archivo.


### WEB-RNF-003

Categoría 1 · Crítica · [Informe de origen](RNF.md).

**Fuente** · RNF-PRI-001, D234 y MPW §6.10.2 pp. 72–73

**Evidencia** · app/Services/AiEvaluationService.php:302-342 y app/Services/Ai/GeminiAiEvaluator.php:101-102,134-142,302-330

**Esperado** · Excluir identificadores personales innecesarios de la solicitud de evaluación.

**Encontrado** · La lista permitida excluye identificadores estructurados de la cuenta. El texto libre de respuesta, referencia y enunciado pasa íntegro al proveedor, incluso si contiene un identificador personal ajeno a la evaluación. Es una propiedad de la construcción de la solicitud; no se acreditó un envío real de datos personales.

**Acción sugerida** · Aplicar minimización también a texto libre y conservar separada la respuesta original de la copia enviada al proveedor.


## Severidad alta

### DOC-001

Categoría 6 · Alta · [Informe de origen](manuales_transversales.md).

**Fuente** · RF-EXA-017 y RF-EXA-050, D82/I82 y D115/I115. MPW-V3 §5.6.1, p. 59 y §7.10, p. 92.

**Evidencia** · La admisión y el reloj de una pregunta tienen predicados distintos en app/Services/AttemptService.php:142,159–165,252–269,326–366. La continuación de un intento previo y la igualdad exacta se detallan en EXA_2, WEB-EXA-017.

**Esperado** · El límite para iniciar debe distinguirse del tiempo del intento ya iniciado.

**Encontrado** · D115 e I115 permiten continuar tras el cierre de admisiones. I82 dice que el cierre termina el examen. El MPW combina `ends_at / duration_minutes` en una validación global con tolerancia de 60 s y fija 3 s por reactivo.

**Acción sugerida** · Identificar el evento que cierra admisiones y el que vence el intento. Acordar la prueba de un inicio anterior al límite y de una entrega posterior, con ambas tolerancias.


### DOC-002

Categoría 6 · Alta · [Informe de origen](manuales_transversales.md).

**Fuente** · RF-EXA-011 y RF-CAL-010/011, D76, D162:D163. MPW-V3 §6.12, tabla 9, pp. 76–77.

**Evidencia** · Para 87.25 puntos sobre 100, app/Services/GradeCalculatorService.php:48–54 devuelve 87.25 en escala 0–100 y 8.73 en 0–10 si el peso es 100. app/Services/AttemptService.php:536–549 guarda la nota, resources/views/exams/result-summary.blade.php:117 conserva dos decimales y app/Services/GradeSheetExportService.php:185–206 agrega y exporta. WEB-CAL-001 y WEB-REP-010 separan conversión y presentación.

**Esperado** · La escala de examen 0–100 debe ser sin decimales. La conversión entre escalas debe tener una regla de precisión.

**Encontrado** · La función del ejemplo devuelve `round($base100, 2)` también para 0–100. Un resultado como 87.25 sobre 100 conserva fracción.

**Acción sugerida** · Precisar en qué punto se redondea la nota definitiva y cuál es el valor visible, almacenado y exportado en ambas escalas.


### DOC-003

Categoría 6 · Alta · [Informe de origen](manuales_transversales.md).

**Fuente** · RF-CAL-021, RF-REP-002/007/008/015, D173, D181, D186:D187, D195. MPW-V3 §6.12, tabla 9, p. 76.

**Evidencia** · app/Services/GradeCalculatorService.php:35–41 sustituye un componente ausente por cero. app/Http/Controllers/EvaluationPeriodController.php:158–185 guarda una nota aunque falten componentes. Ver WEB-CAL-002 y WEB-REP-004.

**Esperado** · Una calificación pendiente debe distinguirse de cero. Los resultados dependientes deben conservar la condición de pendientes.

**Encontrado** · El ejemplo de la fórmula sustituye un componente ausente por `0.0`. El fragmento podría presuponer que un control anterior ya verificó la integridad del periodo.

**Acción sugerida** · Establecer la precondición del cálculo y comprobar por separado un cero capturado y un rubro sin calificar en pantalla y exportación.


### DOC-004

Categoría 6 · Alta · [Informe de origen](manuales_transversales.md).

**Fuente** · RF-SUS-002 a 005/015, RF-ADM-005/006 y RNF-CON-004, D37:D40, D50, D205:D206, D244, I37:I40 e I50. MPW-V3 §6.3.2, tabla 7, p. 63.

**Evidencia** · app/Providers/AppServiceProvider.php:90–95 resuelve la demo. app/Services/TeacherSubscriptionService.php:348–389 condiciona el alta desde el checkout al resultado; app/Services/TeacherVerificationService.php:1254–1292 también asigna un estado pagado desde administración. La bitácora del ajuste y una operación del proveedor son entidades distintas.

**Esperado** · La activación depende de confirmación de pago. Renovaciones, rechazos e indeterminados deben poder distinguirse.

**Encontrado** · El manual prescribe checkout simulado con tarjeta/SPEI. I37 cita domiciliación simulada y ACU-001; I50 indica que los pagos simulados no quedan pendientes. El requerimiento habla de confirmación del proveedor.

**Acción sugerida** · Precisar alcance de demo y de operación real, comprobante aceptable por entorno y tratamiento del estado indeterminado. Contrastar el acuerdo citado.


### DOC-005

Categoría 6 · Alta · [Informe de origen](manuales_transversales.md).

**Fuente** · RF-AUT-003 a 005, D9:D11. MPW-V3 §§1.1 y 8.10.5, pp. 15, 113–114.

**Evidencia** · app/Http/Controllers/Auth/LoginController.php:48–62,98–114 implementa segundo paso; resources/views/auth/verify-2fa.blade.php:17–25 contempla acceso de demostración. Falta política por rol/entorno.

**Esperado** · Se exige código de un solo uso por correo en cada inicio de sesión de los tres roles.

**Encontrado** · La descripción de la pantalla condiciona el paso de seis dígitos a «cuentas con autenticación de dos pasos activada». No precisa si existe una cuenta exenta.

**Acción sugerida** · Confirmar si siempre está activo para los tres roles y conservar como caso de aceptación el acceso después de comprobar ambos factores.


### DOC-006

Categoría 6 · Alta · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §§7.10 y 8.9.3, pp. 92, 111–112; ilustración 20 del §8.6, p. 108.

**Evidencia** · resources/views/student/exams/instructions.blade.php:58–99 muestra el aviso previo, app/Services/ExamAssemblyService.php:145–147 trata el indicador y resources/views/layouts/exam-runner.blade.php:124–145 recoge señales pasivas. Los detalles de configuración, texto y ausencia de captura en el recorrido web están en EXA_2.

**Esperado** · Los avisos previos deben describir las condiciones de supervisión vigentes.

**Encontrado** · El texto pospone la cámara, conserva `require_webcam=false` y describe supervisión pasiva. La ilustración de inicio advierte «La cámara web debe permanecer encendida y activa».

**Acción sugerida** · Determinar el aviso autorizado y la validez de esa ilustración para auditar el examen.


### DOC-007

Categoría 6 · Alta · [Informe de origen](manuales_transversales.md).

**Fuente** · RF-CAL-009/010/012, D161:D162 y D164. MPW-V3 §6.3.2, tabla 7, p. 63.

**Evidencia** · app/Http/Requests/GradebookSaveRequest.php:35–44 fija un rango de captura y resources/views/evaluation-periods/gradebook.blade.php:177–213 presenta componentes globales. Falta decisión sobre escala de grupo y unidad de captura.

**Esperado** · Permitir una nota global por estudiante y rubro manual, dentro de la escala del grupo.

**Encontrado** · La tabla prescribe captura de tareas/proyectos «en rango 0..100». No precisa la distinción entre rubro y actividad individual ni el tratamiento de grupos en escala 0–10.

**Acción sugerida** · Precisar la unidad de captura, su validación y si el rango del manual describe un almacenamiento normalizado.


### DOC-008

Categoría 6 · Alta · [Informe de origen](manuales_transversales.md).

**Fuente** · RF-REP-001 a 006, D180:D185; RF-EVA-009, D148. MPW-V3 §6.3.2, tabla 7, p. 62 y §7.8.2, tabla 11, p. 89.

**Evidencia** · resources/views/exams/result-summary.blade.php:57–109,138–146 y app/Services/StudentResultService.php:76–97 distinguen modos. REP separa el cierre de admisión, el fin de evaluación y la visibilidad de nota o soluciones.

**Esperado** · Publicar automáticamente al quedar la nota definitiva. Mientras la evaluación está pendiente, el estudiante únicamente ve «Pendiente de evaluación». Las soluciones además esperan el fin del plazo de acceso.

**Encontrado** · La tabla 7 contempla cuatro modos de visibilidad sin mapearlos a estas condiciones. La tabla 11 aprueba «Tu respuesta de código fue analizada por el evaluador asistido. Pendiente de recotejo docente.» sin precisar el destinatario ni la pantalla.

**Acción sugerida** · Definir por modo, rol y momento qué puede mostrarse, incluido el texto de estado pendiente del estudiante.


### DOC-036

Categoría 6 · Alta · [Informe de origen](SUS.md).

**Fuente** · Excel D46, I46 y D47

**Evidencia** · Lista maestra, fila 46. app/Services/TeacherSubscriptionService.php:549–561

**Esperado** · Definir qué suscripción puede subir de nivel.

**Encontrado** · D46 admite suscripción activa. I46 requiere renovación activa y D47 remite a la siguiente factura. El periodo SPEI o cancelado puede seguir vigente sin próxima factura. Requiere decisión.

**Acción sugerida** · Precisar elegibilidad y precio para un periodo pagado sin domiciliación futura.


### DOC-037

Categoría 6 · Alta · [Informe de origen](IAR.md).

**Fuente** · RF-IAR-008/009/011/012, D141–142 y D144–145; MPW-V3 §§6.9.1 y 6.10.3–6.10.5, pp. 70–75

**Evidencia** · El Excel difiere reglas de venta, remanente y utilizabilidad. La tabla 8 y la ilustración 12 del MPW fijan cuotas y contingencia.

**Esperado** · Disponer de criterios aprobados para la venta separada, la renovación y el débito.

**Encontrado** · Requiere decisión. Las fuentes no precisan precio, abono, fecha, remanente ni el criterio completo de evaluación utilizable. DOC-020 trata las funciones de examen por paquete.

**Acción sugerida** · Documentar el producto de venta, la regla mensual de remanentes y los criterios para admitir una evaluación.


### DOC-038

Categoría 6 · Alta · [Informe de origen](ADM.md).

**Fuente** · RF-ADM-003/004, D203:D204; MPW §2.8 p. 32

**Evidencia** · Excel D203 remite los efectos por rol a definición posterior. Fase 1, decisiones_documentales.md, parámetros de administración.

**Esperado** · Definir efectos, conservación de sesiones y evidencia de resolución por rol antes de juzgar suspensión completa.

**Encontrado** · Las fuentes piden suspender/reactivar y no precisan todos los efectos ni el criterio para comprobar la resolución.

**Acción sugerida** · Requiere decisión. Acordar efecto y resolución para docente y estudiante.


### DOC-039

Categoría 6 · Alta · [Informe de origen](ADM.md).

**Fuente** · RF-ADM-008/009, D208:D209; MPW §6.9.1 pp. 70–71

**Evidencia** · La tabla 8 del MPW describe valores de referencia. El Excel deja la vigencia y la aplicación a suscripciones existentes por definir.

**Esperado** · Distinguir vigencia futura, nuevas ventas y tratamiento de cuentas ya contratadas.

**Encontrado** · No se localizó regla de retroactividad, transición ni momento aplicable a precio o capacidad al cambiar catálogo.

**Acción sugerida** · Requiere decisión. Establecer fechas y transición por tipo de cambio.


### WEB-ADM-001

Categoría 1 · Alta · [Informe de origen](ADM.md).

**Fuente** · RF-ADM-001, D201

**Evidencia** · resources/views/admin/teachers/index.blade.php:207-246 y app/Services/TeacherVerificationService.php:1101-1110

**Esperado** · Consultar nivel, estado de suscripción, pago y renovación.

**Encontrado** · El listado enseña estado de cuenta, paquete y pago, pero omite las fechas de renovación.

**Acción sugerida** · Mostrar las fechas de cada suscripción y distinguirlas del estado de cuenta.


### WEB-ADM-002

Categoría 3 · Alta · [Informe de origen](ADM.md).

**Fuente** · RF-ADM-002, D202

**Evidencia** · routes/web.php:239-265, app/Services/TeacherVerificationService.php:1101-1106

**Esperado** · Consultar cuentas estudiantiles y su estado con datos administrativos básicos.

**Encontrado** · La ruta de catálogo consulta exclusivamente docentes. Los demás destinos son acreditaciones e invitaciones.

**Acción sugerida** · Crear la consulta estudiantil con campos administrativos explícitos.


### WEB-ADM-003

Categoría 1 · Alta · [Informe de origen](ADM.md).

**Fuente** · RF-ADM-003/004/017, D203:D204 y D217

**Evidencia** · app/Http/Controllers/AdminTeacherController.php:157-171 y app/Services/TeacherVerificationService.php:1191-1209

**Esperado** · Suspender y reactivar las cuentas previstas, recoger el motivo y verificar la resolución; conservar actor, fecha y objetivo.

**Encontrado** · La operación alterna únicamente cuentas docentes, sin exigir motivo ni documentar causa resuelta. El motivo tampoco llega al historial. El cambio de estado de cuenta docente sí es independiente del cobro.

**Acción sugerida** · Registrar suspensión y resolución como operaciones explícitas con motivo y aplicar los efectos por rol que se aprueben.


### WEB-ADM-004

Categoría 1 · Alta · [Informe de origen](ADM.md).

**Fuente** · RF-ADM-005/007, D205 y D207

**Evidencia** · resources/views/admin/teachers/index.blade.php:221-246, app/Models/User.php:232-236 y routes/web.php:239-265

**Esperado** · Consultar operaciones, estados, confirmaciones, cobros pendientes/rechazados e incidencias.

**Encontrado** · Se conserva en pantalla solo una referencia por docente y el medio del último movimiento. El estado de la suscripción y el indicio de fallo por color no proporcionan historial, confirmación ni causa de incidencia.

**Acción sugerida** · Ofrecer consulta administrativa de transacciones, estados, incidencias y evidencia limitada del servicio.


### WEB-ADM-006

Categoría 3 · Alta · [Informe de origen](ADM.md).

**Fuente** · RF-ADM-008/009, D208:D209 y MPW §6.9.1 pp. 70–71

**Evidencia** · routes/web.php:239-265, database/seeders/SubscriptionPackageSeeder.php:45-70 y resources/views/admin/teachers/index.blade.php:321-341

**Esperado** · Modificar precios y capacidades de los tres niveles desde administración conforme a reglas de vigencia.

**Encontrado** · Los valores se cargan técnicamente. El superadministrador solo puede asignar uno de los paquetes sin modificar su precio ni sus capacidades.

**Acción sugerida** · Incorporar administración del catálogo con historial y reglas de vigencia acordadas.


### WEB-ADM-007

Categoría 3 · Alta · [Informe de origen](ADM.md).

**Fuente** · RF-ADM-010, D210

**Evidencia** · routes/web.php:239-265 y routes/web.php:233, app/Http/Controllers/TeacherAiCreditsController.php:50-59

**Esperado** · Consultar saldos, renovaciones y movimientos de consumo por cuenta sin acceder al contenido evaluado.

**Encontrado** · Existe consulta del titular docente, pero no una consulta administrativa de los tres elementos.

**Acción sugerida** · Exponer movimientos y saldos administrativos limitados a cuenta, fechas y cantidades.


### WEB-ADM-008

Categoría 3 · Alta · [Informe de origen](ADM.md).

**Fuente** · RF-ADM-011/012, D211:D212

**Evidencia** · app/Services/TeacherVerificationService.php:1101-1109, resources/views/admin/teachers/index.blade.php:162 y routes/web.php:239-265

**Esperado** · Consultar consumo histórico de prueba y estados operativos de grupos y aplicaciones por cuenta.

**Encontrado** · El contador general de exámenes no identifica los consumos de prueba, estado de un grupo ni estado individual de una aplicación. Los menús y controladores administrativos carecen de estas consultas.

**Acción sugerida** · Añadir vistas operativas e históricas por cuenta con campos no académicos.


### WEB-ADM-009

Categoría 3 · Alta · [Informe de origen](ADM.md).

**Fuente** · RF-ADM-015/016, D215:D216

**Evidencia** · routes/web.php:239-265, resources/views/auth/register-teacher.blade.php:478-491 y database/seeders/PrivacySeeder.php:141-154

**Esperado** · Publicar y conservar versiones con inicio de vigencia de ambos documentos.

**Encontrado** · El aviso admite distintas versiones en datos técnicos. Los términos adicionales están en plantilla. Falta el recorrido de publicación de ambos documentos desde administración.

**Acción sugerida** · Proporcionar publicación por versión, fecha y autor autorizado y conservar las anteriores.


### WEB-ADM-010

Categoría 1 · Alta · [Informe de origen](ADM.md).

**Fuente** · RF-ADM-018, D218

**Evidencia** · resources/views/admin/teachers/index.blade.php:321-343,742-803 y resources/views/admin/invitations/index.blade.php:401-418

**Esperado** · Confirmar ajustes sensibles o cancelarlos sin ejecutar.

**Encontrado** · Guardar un cambio de nivel o de estado de pago lo envía directamente. Otras operaciones administrativas confirman usando AcodeAlert, pero su rama sin el componente envía el formulario sin confirmar.

**Acción sugerida** · Requerir confirmación en los ajustes sensibles y posponer el envío cuando no esté disponible el diálogo.


### WEB-AUT-003

Categoría 8 · Alta · [Informe de origen](AUT.md).

**Fuente** · RF-AUT-006, D12

**Evidencia** · app/Services/AuthService.php:285-292,339-351,479-493

**Esperado** · El nuevo código debe invalidar siempre el anterior.

**Encontrado** · Al elegir un valor se comprueban otras cuentas, pero se excluye la cuenta propia. La generación admite repetir el valor que se pretendía invalidar.

**Acción sugerida** · Excluir el código anterior al generar su sustituto.


### WEB-AUT-005

Categoría 1 · Alta · [Informe de origen](AUT.md).

**Fuente** · RF-AUT-013, D19

**Evidencia** · routes/web.php:78-80,96,117,234 y app/Http/Controllers/DashboardController.php:67-82

**Esperado** · Cada usuario debe poder consultar sus datos, el aviso y el cierre en su sección de cuenta.

**Encontrado** · Un docente pendiente de acreditación o de elegir plan queda fuera del perfil docente y del perfil común.

**Acción sugerida** · Dar acceso a los datos propios desde el alta y mantener las restricciones del plan en las operaciones académicas.


### WEB-AUT-007

Categoría 3 · Alta · [Informe de origen](AUT.md).

**Fuente** · MPW §8.10.5 pp. 113–114

**Evidencia** · resources/views/auth/login.blade.php:12-75 y routes/web.php:43-60. Se revisaron también las rutas públicas y la plantilla guest.

**Esperado** · Ofrecer un enlace de recuperación de credenciales en el acceso.

**Encontrado** · El formulario lleva al segundo factor o a dos registros. Falta la opción de recuperación y su destino en las rutas web.

**Acción sugerida** · Incorporar al acceso el enlace y su destino conforme al manual.


### WEB-CAL-004

Categoría 1 · Alta · [Informe de origen](CAL.md).

**Fuente** · RF-CAL-003/004, D155–156

**Evidencia** · resources/views/evaluation-periods/index.blade.php:197–247, app/Http/Requests/EvaluationPeriodStoreRequest.php:42–45 y app/Http/Controllers/EvaluationPeriodController.php:87–99

**Esperado** · El docente define rubros y sus pesos por periodo.

**Encontrado** · Cuatro categorías predefinidas ocupan todas las entradas de la fórmula; solo cambian sus porcentajes.

**Acción sugerida** · Permitir rubros con nombre, tipo y peso propios y conservar su vínculo al periodo.


### WEB-CAL-005

Categoría 1 · Alta · [Informe de origen](CAL.md).

**Fuente** · RF-CAL-006/007/008/014/027, D158–160, D166 y D179

**Evidencia** · app/Http/Controllers/EvaluationPeriodController.php:122–130, resources/views/evaluation-periods/gradebook.blade.php:148–170 y database/migrations/2026_10_26_180000_bd01_2026_020_create_acs_evaluation_periods_table.php:32–42

**Esperado** · Cada aplicación contribuye a un periodo y rubro con peso propio; validar 100 % y poder consultar el desglose.

**Encontrado** · Se sugiere un promedio simple de todos los exámenes del grupo en cada periodo. No hay vínculo individual, pesos, validación de suma ni desglose asociado.

**Acción sugerida** · Registrar la relación por aplicación y comprobar la suma antes de calcular la nota del rubro.


### WEB-CAL-006

Categoría 3 · Alta · [Informe de origen](CAL.md).

**Fuente** · RF-CAL-010/012/028, D162, D164 y D191; DOC-002/007

**Evidencia** · app/Models/Group.php:86–94, app/Http/Requests/GroupStoreRequest.php:38–44, app/Http/Requests/GradebookSaveRequest.php:38–43, resources/views/evaluation-periods/gradebook.blade.php:219, resources/views/student/grades/show.blade.php:57 y app/Services/GradeSheetExportService.php:121,230

**Esperado** · Configurar escala y mínimo por grupo y usarlos al capturar, mostrar y clasificar resultados.

**Encontrado** · La configuración del grupo omite ambos campos. La captura usa 0..100; docente y exportación comparan con 60, alumno compara con 6.

**Acción sugerida** · Definir los dos parámetros del grupo y compartir su validación y presentación entre roles.


### WEB-CAL-007

Categoría 2 · Alta · [Informe de origen](CAL.md).

**Fuente** · RF-CAL-013, D165

**Evidencia** · resources/views/evaluation-periods/gradebook.blade.php:177–213, app/Http/Requests/GradebookSaveRequest.php:38–43 y database/migrations/2026_10_26_180100_bd01_2026_021_create_acs_student_period_grades_table.php:36–41

**Esperado** · Anotar opcionalmente una nota manual individual.

**Encontrado** · La captura y el esquema contienen únicamente el valor numérico de cada componente.

**Acción sugerida** · Conservar y mostrar un comentario opcional por estudiante y rubro.


### WEB-CAL-008

Categoría 2 · Alta · [Informe de origen](CAL.md).

**Fuente** · RF-CAL-015/016, D167–168

**Evidencia** · app/Http/Controllers/EvaluationPeriodController.php:87–99, database/migrations/2026_10_26_180000_bd01_2026_020_create_acs_evaluation_periods_table.php:32–42 y resources/views/evaluation-periods/index.blade.php:197–247

**Esperado** · Integrar uno o más periodos anteriores y rechazar dependencias propias o circulares.

**Encontrado** · El modelo de fórmula no expresa identificadores de periodos ni valida dependencias. El caso de uso de integración no es realizable.

**Acción sugerida** · Incorporar dependencias ponderadas y validar el orden y la ausencia de ciclos al guardarlas.


### WEB-CAL-009

Categoría 2 · Alta · [Informe de origen](CAL.md).

**Fuente** · RF-CAL-018/019/020 y RF-REP-008, D170–172 y D187

**Evidencia** · routes/web.php:124–128, app/Http/Controllers/EvaluationPeriodController.php:81–99,172–186 y database/migrations/2026_10_26_180100_bd01_2026_021_create_acs_student_period_grades_table.php:28–43

**Esperado** · Configurar periodos y pesos para la final del grupo, validar 100 % y obtener la nota de cada estudiante.

**Encontrado** · Final_grade pertenece a un solo periodo. No hay configuración ni agregación de los periodos seleccionados.

**Acción sugerida** · Crear la configuración de la final grupal e integrar resultados definitivos y ponderaciones.


### WEB-CAL-010

Categoría 1 · Alta · [Informe de origen](CAL.md).

**Fuente** · RF-CAL-022/023, D174–175

**Evidencia** · app/Http/Requests/GradebookSaveRequest.php:38–44, app/Http/Controllers/EvaluationPeriodController.php:175–186 y database/migrations/2026_10_26_180100_bd01_2026_021_create_acs_student_period_grades_table.php:36–43

**Esperado** · Corregir dentro de la escala y conservar motivo, autor, fecha y valores anterior y nuevo.

**Encontrado** · El guardado actualiza la misma fila con las nuevas notas y carece de bitácora y motivo de corrección.

**Acción sugerida** · Conservar el historial de cambios en la misma transacción que modifica el valor.


### WEB-CAL-011

Categoría 3 · Alta · [Informe de origen](CAL.md).

**Fuente** · RF-CAL-024/025, D176–177

**Evidencia** · routes/web.php:124–128, app/Http/Controllers/EvaluationPeriodController.php:81–99 y database/migrations/2026_10_26_180000_bd01_2026_020_create_acs_evaluation_periods_table.php:38–42

**Esperado** · Al cambiar configuración con notas, advertir efectos, admitir cancelación y conservar versión anterior, autor y fecha.

**Encontrado** · El catálogo web solo crea periodos y guarda calificaciones; no hay flujo de cambio confirmado, cancelación ni versiones de configuración.

**Acción sugerida** · Habilitar la edición con confirmación previa, cancelación sin efectos y registro histórico.


### WEB-CAL-012

Categoría 5 · Alta · [Informe de origen](CAL.md).

**Fuente** · RF-CAL-026 y RF-REP-016, D178 y D196

**Evidencia** · app/Http/Controllers/TeacherReviewController.php:418–427, app/Http/Controllers/EvaluationPeriodController.php:119–130,175–186, app/Services/GradeSheetExportService.php:199–206

**Esperado** · Una corrección del examen actualiza todas las notas dependientes y deja constancia del origen.

**Encontrado** · Corregir recalcula el intento. La sábana conserva el componente anterior hasta otro guardado; una nueva exportación prefiere el componente guardado.

**Acción sugerida** · Recalcular dependencias tras el cambio confirmado y vincular los resultados con su origen.


### WEB-EVA-004

Categoría 1 · Alta · [Informe de origen](EVA.md).

**Fuente** · RF-EVA-009, RF-REP-002, RF-IAR-004 y DOC-008

**Evidencia** · app/Services/AiEvaluationService.php:398–404,475–487, app/Services/StudentResultService.php:89–95, app/Http/Controllers/AttemptController.php:167–177 y resources/views/exams/result-summary.blade.php:67–87

**Esperado** · Reservar la estimación al docente y publicar la nota tras su revisión.

**Encontrado** · El modo estimated_score presenta al alumno un total que incorpora el puntaje sugerido. En intentos solo cualitativos revela directamente la estimación agregada.

**Acción sugerida** · Acordar la visibilidad por modo y aplicar la condición de revisión en el servicio y la vista.


### WEB-EXA-003

Categoría 2 · Alta · [Informe de origen](EXA_1_armado_y_programacion.md).

**Fuente** · RF-EXA-012/014/033/042/043, D77/D79/D98/D107/D108; DOC-020/021

**Evidencia** · app/Http/Requests/ExamConfigurationUpdateRequest.php:54–79; resources/views/exams/configuration.blade.php:261–284; app/Models/Exam.php:289–292; app/Services/AttemptService.php:225–242, app/Services/AttemptService.php:352–366

**Esperado** · Elegir entre secuencial y libre, definir el tiempo total y usarlo para navegación, revisión y vencimiento.

**Encontrado** · El único flujo tiene posición incremental y tiempo individual. El total informativo se deriva de los segundos de los reactivos. Faltan modalidad, navegación, reloj y finalización propios del modo libre.

**Acción sugerida** · Incorporar la modalidad y su temporización al modelo, validación, selección docente y presentación por estudiante.


### WEB-EXA-004

Categoría 3 · Alta · [Informe de origen](EXA_1_armado_y_programacion.md).

**Fuente** · RF-EXA-007/016/017/021/024/051/055, D72/D81/D82/D86/D89/D119/D123

**Evidencia** · resources/views/exams/configuration.blade.php:328–360; app/Services/ExamAssemblyService.php:79–82 y app/Services/ExamAssemblyService.php:364–375; app/Models/Exam.php:104–106; app/Services/AttemptService.php:174–195; app/Repositories/Eloquent/EloquentExamRepository.php:488–495

**Esperado** · Elegir programación común o independiente y operar cada aplicación por grupo.

**Encontrado** · Los grupos se vinculan al mismo examen con una sola ventana y estado. Apertura, detención, conteo e intento se resuelven por examen; no hay programación independiente por grupo.

**Acción sugerida** · Identificar la aplicación a grupo en programación, intentos, seguimiento y cierre.


### WEB-EXA-005

Categoría 8 · Alta · [Informe de origen](EXA_1_armado_y_programacion.md).

**Fuente** · RF-EXA-019/020, D84:D85

**Evidencia** · app/Http/Requests/ExamUpdateRequest.php:67–74; app/Http/Controllers/ExamController.php:141–145; app/Services/ExamAssemblyService.php:137–149; app/Repositories/Eloquent/EloquentExamRepository.php:321–324

**Esperado** · Antes de guardar un cambio sobre un examen programado, comprobar que permanece al menos un grupo destinatario abierto.

**Encontrado** · La edición de datos base admite grupos vacíos y sincroniza el conjunto sin llamar a la lista de comprobación; el examen sigue programado sin destinatarios.

**Acción sugerida** · Compartir la revalidación transaccional con la ruta de configuración.


### WEB-EXA-012

Categoría 1 · Alta · [Informe de origen](EXA_2_aplicacion.md).

**Fuente** · RF-EXA-032/047/048, D97/D112/D113; MPW-V3 §5.6.1, p. 59

**Evidencia** · app/Services/AttemptService.php:225–242, app/Services/AttemptService.php:252–260, app/Services/AttemptService.php:355–366, app/Services/AttemptService.php:586–588; app/Http/Controllers/AttemptController.php:64–77; routes/console.php:11–19

**Esperado** · Mantener el avance según el tiempo transcurrido, consolidar lo registrado y concluir al vencer la última pregunta.

**Encontrado** · El servidor calcula el tiempo transcurrido pero el índice solo avanza al recibir una respuesta. Tras una desconexión que abarca varios reactivos, vuelve a servir el mismo índice con cero segundos. La pregunta siguiente toma como comienzo el envío tardío; el corte global solo cambia el estado a expirado.

**Acción sugerida** · Reconciliar al reanudar los vencimientos acumulados y los borradores con fecha de guardado.


### WEB-EXA-013

Categoría 1 · Alta · [Informe de origen](EXA_2_aplicacion.md).

**Fuente** · RF-EXA-038, D103/I103; MPW-V3 §8.9.1, p. 110

**Evidencia** · bootstrap/app.php:17–25; vendor/laravel/framework/src/Illuminate/Foundation/Configuration/Middleware.php:461; vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TrimStrings.php:63–69; app/Http/Controllers/AttemptController.php:224–230; resources/views/exams/take-exam.blade.php:155–177

**Esperado** · Conservar literalmente el código del alumno, incluidas las sangrías iniciales.

**Encontrado** · El middleware global recorta answer_text antes de que el controlador lo extraiga. El editor tampoco presenta el nombre de lenguaje que sí contiene el objeto de la pregunta.

**Acción sugerida** · Exceptuar los campos de código del recorte, mantener validación de vacíos y rotular el lenguaje del enunciado.


### WEB-EXA-014

Categoría 8 · Alta · [Informe de origen](EXA_2_aplicacion.md).

**Fuente** · RF-EXA-040/041, D105:D106

**Evidencia** · resources/views/exams/take-exam.blade.php:45–66; app/Http/Controllers/AttemptController.php:208–218; app/Services/AttemptService.php:393–394

**Esperado** · Confirmar únicamente la versión vigente de la respuesta y conservar el orden de las modificaciones.

**Encontrado** · Los envíos asíncronos no identifican la versión. Una confirmación anterior puede poner el indicador en «guardada» con una edición más reciente pendiente, y la caché admite sobreescrituras sin ordenar versiones.

**Acción sugerida** · Asociar acuse y actualización a la misma versión o serializar los guardados.


### WEB-EXA-015

Categoría 5 · Alta · [Informe de origen](EXA_2_aplicacion.md).

**Fuente** · RF-EXA-031 y RF-EVA-008, D96/I96 y criterio de respuesta en blanco

**Evidencia** · resources/views/exams/take-exam.blade.php:75–87 y resources/views/exams/take-exam.blade.php:169–177; app/Http/Controllers/AttemptController.php:224–230; app/Services/AttemptService.php:765–799

**Esperado** · Al confirmar avanzar sin respuesta, aplicar cero al blanco identificado por la interfaz.

**Encontrado** · En un ejercicio con código inicial, la interfaz considera vacío el contenido sin modificar y anuncia cero. El servidor recibe dicho código como texto no vacío y deja la respuesta cualitativa pendiente.

**Acción sugerida** · Unificar la detección de respuesta intacta entre interfaz, guardado y calificación.


### WEB-GRU-005

Categoría 8 · Alta · [Informe de origen](GRU.md).

**Fuente** · RF-GRU-013/018, D33 y D118

**Evidencia** · app/Services/GroupEnrollmentService.php:289-305

**Esperado** · Permitir cerrar el grupo al terminar sus propios intentos.

**Encontrado** · La consulta adicional busca cualquier intento de un examen asignado al grupo. Un intento de otro grupo que comparte el examen impide cerrar este. También se considera el estado persistido aunque hubiera transcurrido el plazo sin actualizarse.

**Acción sugerida** · Limitar el bloqueo al grupo del intento y resolver la vigencia con el mismo criterio que para el retiro.


### WEB-GRU-008

Categoría 1 · Alta · [Informe de origen](GRU.md).

**Fuente** · RF-GRU-015, D35

**Evidencia** · app/Console/Commands/PurgeExpiredGroupsCommand.php:34-37 y app/Services/GroupRetentionService.php:84-107,125-128,213-224

**Esperado** · Avisar por correo con treinta días antes de eliminar para dar oportunidad de exportar o reabrir.

**Encontrado** · Si los envíos fallan durante la ventana, el comando sigue con la purga al vencimiento sin comprobar aviso registrado. El detalle en la web sí informa.

**Acción sugerida** · Gestionar entrega fallida y condicionar la purga a la oportunidad efectiva de aviso establecida.


### WEB-IAR-001

Categoría 3 · Alta · [Informe de origen](IAR.md).

**Fuente** · RF-IAR-008, D141

**Evidencia** · routes/web.php:97–115,233; resources/views/teacher/ai-credits/index.blade.php:93–155; app/Services/SubscriptionLimitService.php:417–452

**Esperado** · El docente puede adquirir un saldo separado de su paquete.

**Encontrado** · El catálogo de compra tramita paquetes docentes. La pantalla de IA informa sobre saldo y consumo sin producto, pago ni abono independiente.

**Acción sugerida** · Definir el esquema comercial pendiente y habilitar compra, confirmación y abono independiente.


### WEB-IAR-002

Categoría 8 · Alta · [Informe de origen](IAR.md).

**Fuente** · RF-IAR-004/011/012, D134 y D144–145; MPW-V3 §§6.10.2–6.10.5, pp. 72–75

**Evidencia** · app/Services/Ai/GeminiAiEvaluator.php:194–211,230–245 y app/Services/AiEvaluationService.php:384–414

**Esperado** · Una sugerencia utilizable tiene puntuación y justificación. Un fallo no genera un cargo.

**Encontrado** · Aunque solicita feedback en el esquema, el adaptador acepta resultado sin esa clave, inserta una frase genérica y lo marca como real. El servicio puede debitar un crédito.

**Acción sugerida** · Validar los campos, tipos, intervalos y justificación antes de registrar el resultado como utilizable.


### WEB-MBD-001

Categoría 4 · Alta · [Informe de origen](manuales_transversales.md).

**Fuente** · MBD-037 · MBD-V3 §4.3, pp. 24–25

**Evidencia** · database/seeders/DatabaseSeeder.php:13–20, database/seeders/UserSeeder.php:43–59 y database/seeders/QuestionBankSeeder.php:23–39,75–80

**Esperado** · Cargar catálogos de forma repetible, conservar el banco de demostración sin duplicados y excluir la demostración de producción.

**Encontrado** · El seeder general carga catálogos y datos demo juntos para cualquier entorno. La siembra de preguntas crea nuevas filas al repetirse. El seeder de usuarios crea también una cuenta de superadministración de demostración con una credencial conocida, según database/seeders/UserSeeder.php:56–66,145–146. Se desconoce si esa siembra se ejecutó en producción.

**Acción sugerida** · Separar los datos de demostración por entorno y usar un identificador estable para cada fila que se deba sembrar repetidamente.


### WEB-MPW-109

Categoría 3 · Alta · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-109 · MPW-V3, §8.10.7, p. 114

**Evidencia** · resources/views/layouts/app.blade.php:648-662

**Esperado** · Desde el pie deben poder abrirse términos, aviso, centro de ayuda y soporte.

**Encontrado** · Tres de los cuatro enlaces apuntan a #. El aviso sí dirige al documento.

**Acción sugerida** · Vincular cada opción con un destino efectivo accesible al rol.


### WEB-REP-001

Categoría 1 · Alta · [Informe de origen](REP.md).

**Fuente** · RF-REP-001 y DOC-008

**Evidencia** · app/Services/AttemptService.php:538–550, resources/views/exams/result-summary.blade.php:57–66, app/Http/Controllers/StudentGradeController.php:47–49 y app/Services/ExamAssemblyService.php:395–409

**Esperado** · La nota definitiva se publica al quedar resueltas todas las respuestas.

**Encontrado** · En modo oculto se calcula la nota y el alumno queda sin verla hasta la liberación docente. Las definitivas se publican en los otros modos. La diferencia frente a D180 es verificable; su aceptación queda reservada a DOC-008.

**Acción sugerida** · Resolver la prioridad entre publicación automática y reserva por modo y unificar el criterio.


### WEB-REP-002

Categoría 1 · Alta · [Informe de origen](REP.md).

**Fuente** · RF-REP-002/006 y DOC-008

**Evidencia** · resources/views/exams/result-summary.blade.php:91–109,138–146,190–205

**Esperado** · Hasta completar evaluación, mostrar únicamente «Pendiente de evaluación».

**Encontrado** · Aun fuera del modo de estimación se presentan puntajes objetivos, estados individuales y notas docentes si las hay. WEB-EVA-004 documenta por separado la exposición de la estimación IA.

**Acción sugerida** · Aplicar la restricción a todo el resumen y a sus comentarios mientras falte una nota definitiva.


### WEB-REP-003

Categoría 3 · Alta · [Informe de origen](REP.md).

**Fuente** · RF-REP-004, D183

**Evidencia** · app/Http/Controllers/AttemptController.php:167–177, resources/views/exams/result-summary.blade.php:138–146,190–198 y app/Services/StudentResultService.php:188–210

**Esperado** · El alumno puede consultar puntos por pregunta y apartado al publicarse la nota.

**Encontrado** · La ficha web presenta puntos por respuesta registrada sin subtotales de apartado. El servicio de detalle ya construye ese desglose para otra entrada.

**Acción sugerida** · Conectar el desglose por sección al resultado publicado de navegador.


### WEB-REP-005

Categoría 5 · Alta · [Informe de origen](REP.md).

**Fuente** · RF-REP-009/010, D188–189

**Evidencia** · resources/views/exams/review-index.blade.php:11–22,70–87,143–169, app/Http/Controllers/TeacherReviewController.php:104–105 y app/Http/Controllers/ExamMonitorController.php:48–60

**Esperado** · Obtener cantidades de alumnos que iniciaron, finalizaron y están pendientes para toda la aplicación.

**Encontrado** · La ficha de revisión mezcla el total paginado con pendientes contados solo en la página; las abiertas en blanco pueden contarse pendientes. El monitor cuenta vigentes por examen con los grupos combinados.

**Acción sugerida** · Agregar en servidor por aplicación e intento vigente y compartir el estado de evaluación con el recálculo.


### WEB-REP-006

Categoría 1 · Alta · [Informe de origen](REP.md).

**Fuente** · RF-REP-011, D190

**Evidencia** · app/Http/Controllers/DashboardController.php:100–113, app/Services/TeacherGradingService.php:123–138 y app/Http/Controllers/ExamMonitorController.php:53–76

**Esperado** · Calcular promedio, aprobados y no aprobados por aplicación a partir de definitivas.

**Encontrado** · El tablero global del docente clasifica como definitivas las entregas pendientes usando su parte objetiva. El monitor por examen no ofrece el conjunto de tres estadísticas solicitado.

**Acción sugerida** · Agregar por aplicación solo las notas definitivas, usando escala y mínimo del grupo.


### WEB-REP-007

Categoría 8 · Alta · [Informe de origen](REP.md).

**Fuente** · RF-REP-012; RF-CAL-010/028

**Evidencia** · app/Http/Controllers/StudentGradeController.php:43–58 y resources/views/student/grades/index.blade.php:44–55

**Esperado** · Que las estadísticas personales representen los resultados en su escala y umbral correspondientes.

**Encontrado** · Promedia las puntuaciones sin convertir 0–10 y 0–100 y aplica un umbral fijo 6 al conjunto.

**Acción sugerida** · Normalizar antes de promediar y determinar aprobación con el umbral de cada grupo.


### WEB-REP-008

Categoría 3 · Alta · [Informe de origen](REP.md).

**Fuente** · RF-REP-014, D194

**Evidencia** · routes/web.php:124–128, app/Http/Controllers/EvaluationPeriodController.php:199–204 y app/Services/GradeSheetExportService.php:56–71,143–155

**Esperado** · Seleccionar un periodo o varios e incluir estudiantes, exámenes, pesos, notas por periodo y final según selección.

**Encontrado** · La única descarga exige un periodo y genera una hoja con cuatro agregados. Faltan la selección conjunta, notas individuales de exámenes y final de grupo.

**Acción sugerida** · Incorporar la selección de periodos y el detalle vinculado con el cálculo de grupo.


### WEB-REP-009

Categoría 8 · Alta · [Informe de origen](REP.md).

**Fuente** · RF-REP-014, D194

**Evidencia** · app/Http/Requests/EvaluationPeriodStoreRequest.php:39, app/Services/GradeSheetExportService.php:60 y vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Worksheet/Worksheet.php:72,466–471,916

**Esperado** · Exportar las calificaciones de cualquier periodo que el formulario permita registrar.

**Encontrado** · El nombre admite caracteres que el escritor de hojas rechaza. Solo se recorta la longitud y el error de título impide generar la descarga para esos periodos.

**Acción sugerida** · Asignar un título de hoja válido preservando el nombre original en el contenido.


### WEB-REP-011

Categoría 2 · Alta · [Informe de origen](REP.md).

**Fuente** · RF-REP-017, D197

**Evidencia** · app/Http/Controllers/TeacherReviewController.php:386–404,418–432; app/Services/ExamNotificationService.php:184; app/Http/Controllers/StudentGradeController.php:43–66

**Esperado** · Avisar al estudiante únicamente de la nueva calificación cuando se corrija una publicada.

**Encontrado** · La ruta registra el cambio en bitácora y redirige al docente. No se localizó emisión ni visualización del aviso de corrección en la web del estudiante.

**Acción sugerida** · Emitir un aviso tras confirmar la corrección y presentar al alumno solo la nueva nota.


### WEB-RNF-002

Categoría 8 · Alta · [Informe de origen](RNF.md).

**Fuente** · RNF-CON-003, D230 y RF-IAR-012

**Evidencia** · app/Jobs/EvaluateStudentAnswerJob.php:48,71-89 y app/Services/AiEvaluationService.php:175-183,398-415

**Esperado** · Distinguir reentrega técnica de reevaluación solicitada expresamente y descontar una sola vez por operación.

**Encontrado** · El trabajo transporta el identificador de respuesta y un indicador de reevaluación, sin clave por solicitud. Un segundo procesamiento satisfactorio inserta otro movimiento de consumo.

**Acción sugerida** · Persistir una identidad estable por solicitud y condicionar el movimiento de consumo a que siga sin contabilizarse.


### WEB-SUS-002

Categoría 8 · Alta · [Informe de origen](SUS.md).

**Fuente** · RF-SUS-007/008, RF-GRU-001; MPW-V3 §6.9.1, tabla 8, pp. 70–71

**Evidencia** · app/Services/GroupEnrollmentService.php:47–50,98–103; app/Http/Controllers/GroupController.php:161

**Esperado** · Aplicar el límite al confirmar cada alta.

**Encontrado** · La creación y la inscripción comprueban el cupo antes de la transacción de escritura. Solicitudes coincidentes pueden ocupar el mismo último lugar.

**Acción sugerida** · Serializar comprobación y escritura por titular o grupo.


### WEB-SUS-003

Categoría 1 · Alta · [Informe de origen](SUS.md).

**Fuente** · RF-SUS-007; MPW-V3 §6.9.1, tabla 8, pp. 70–71

**Evidencia** · app/Services/SubscriptionLimitService.php:417–424; app/Services/TeacherSubscriptionService.php:689–694

**Esperado** · Restituir la cuota de IA de los planes de pago en cada ciclo.

**Encontrado** · La consulta acumula desde starts_at y la renovación mueve solo expires_at. El saldo gastado del ciclo anterior reduce el siguiente.

**Acción sugerida** · Registrar el inicio del nuevo ciclo y usarlo al sumar créditos.


### WEB-SUS-004

Categoría 8 · Alta · [Informe de origen](SUS.md).

**Fuente** · RF-SUS-010; MPW-V3 §6.9.1, tabla 8, pp. 70–71

**Evidencia** · app/Services/SubscriptionLimitService.php:247–252; app/Services/ExamAssemblyService.php:157–178; app/Repositories/Eloquent/EloquentExamRepository.php:335–342; app/Models/Exam.php:39

**Esperado** · Conservar el consumo del examen gratuito por cuenta incluso al eliminarlo.

**Encontrado** · El alta consume la fila del borrador. Su eliminación física reduce el mismo conteo que autoriza otro.

**Acción sugerida** · Guardar consumo duradero por cuenta y precisar si se consume al crear o aplicar.


### WEB-SUS-007

Categoría 8 · Alta · [Informe de origen](SUS.md).

**Fuente** · RF-SUS-014; Excel D49

**Evidencia** · app/Services/TeacherSubscriptionService.php:614–617,643–669

**Esperado** · Excluir de cobros posteriores una renovación ya cancelada.

**Encontrado** · La selección de suscripciones y la llamada de cobro están separadas. Una cancelación confirmada entre ambas no se vuelve a consultar antes del cargo.

**Acción sugerida** · Coordinar cancelación y reserva idempotente del cargo por ciclo.


## Severidad media

### DOC-009

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · RF-EXA-051/052, D119:D120, E120, F120 e I120.

**Evidencia** · routes/web.php:218–220 y app/Services/AttemptService.php:94–115,174–200 localizan autorización docente y nuevo intento. Las marcas por rol de la fila 120 no se utilizaron como evidencia de exclusión.

**Esperado** · La asignación de una reaplicación individual la realiza el docente.

**Encontrado** · En la fila 120 figura N/A para docente web y ✓ para alumno web. La observación ubica la autorización en la revisión docente y permite varias autorizaciones sucesivas.

**Acción sugerida** · Separar la facultad docente de asignar de la del alumno de utilizar el intento y documentar el alcance de las reaplicaciones sucesivas.


### DOC-010

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · RF-EXA-054/057, D122, E122:H122, I122; D125, E125:H125, I125. MPW-V3 §§1.1, 2.5.1 y 2.6, pp. 15, 30–31.

**Evidencia** · app/Services/ExamNotificationService.php:60–105,155–187 contiene el envío de servidor. RF-EXA-054 y 057 quedan con alcance abierto; el seguimiento de recepción pertenece al cliente móvil.

**Esperado** · La atribución a plataformas debe distinguir el envío del servidor de la recepción del cliente.

**Encontrado** · Todos los roles web se marcan N/A, pero las notas documentan que el servidor envía y la app recibe. El manual sitúa las notificaciones en el backend del repositorio web.

**Acción sugerida** · Precisar si se computa el disparo del servidor en el denominador web. Mantener la recepción en cliente nativo fuera de esta auditoría.


### DOC-011

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · RNF-TEC-001, D219. MC-V3 §4.2, pp. 22–23; MPW-V3 portada y §1.1, pp. 1, 15.

**Evidencia** · composer.json:9–10 y bootstrap/app.php:8–26 acreditan la tecnología. Se usó el MPW-V3 entregado y se mantiene abierta la identificación formal de la referencia citada en D219.

**Esperado** · El manual normativo debe quedar identificable sin duda por nombre o código.

**Encontrado** · La celda dice «Manual de Programación Laravel vigente». La fuente entregada y catalogada es el «Manual de Programación Web», MPW-V3, que sí trata Laravel.

**Acción sugerida** · Confirmar la equivalencia documental de ambos títulos para cerrar RNF-TEC-001.


### DOC-012

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MBD-V3 §3.4, p. 17, MPW-V3 §7.10, p. 92.

**Evidencia** · app/Services/ExamAssemblyService.php:145–147 conserva require_webcam. El nombre contradice el prefijo general para booleanos. No se imputa una segunda infracción por el nombre prescrito expresamente.

**Esperado** · El MBD exige prefijo `is_` o `has_` para booleanos.

**Encontrado** · El MPW exige reservar por nombre la columna booleana `acs_exams.require_webcam` con valor inicial falso.

**Acción sugerida** · Establecer el tratamiento de ese nombre o identificar una excepción aprobada conforme a MBD-V3 §§1.3 y 10.2, pp. 9, 43–44.


### DOC-013

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MBD-V3 §§4.1.2 y 4.2.2, pp. 20, 23; MPW-V3 §§6.4.1 y 6.5, pp. 64–66, ilustración 10.

**Evidencia** · app/Models/Question.php:127–129 y las migraciones de variantes materializan morphTo. MBD-029 y MPW-057 distinguen la integridad ordinaria de la pareja polimórfica.

**Esperado** · Poder identificar padre, hijo, clave y mecanismo de integridad de cada relación.

**Encontrado** · El MBD exige identificar la FK y prescribe su construcción. El MPW describe una misma pareja polimórfica de tipo e identificador que puede remitir a cuatro tablas distintas.

**Acción sugerida** · Identificar si se clasifica como referencia lógica, qué garantiza su integridad y cómo queda representada en el diccionario y el DER.


### DOC-014

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §§2.8, 3.2 y 7.6.1, pp. 32, 37, 82.

**Evidencia** · routes/channels.php:27–51 usa canales personal y de examen. app/Events/ExamAttemptStarted.php:33 declara el evento de aplicación; la excepción al prefijo queda abierta. La sesión de AUT se evalúa de manera independiente.

**Esperado** · La norma general y los nombres de eventos y canales deberían permitir una comprobación consistente.

**Encontrado** · Se prescribe `acs_` para canales y eventos personalizados. Los ejemplos específicos de monitoreo prescriben `private-exam.{examId}` y eventos como `exam.attempt.started`.

**Acción sugerida** · Determinar qué nombres están exceptuados o en qué nivel se debe aplicar el prefijo.


### DOC-015

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MD-V3 §§3.9 y 8.3, pp. 20–21, 37. MPW-V3 §1.2, p. 16.

**Evidencia** · No hay exportación de Trello ni DE-05 aplicable en el corte. La lectura de Git y los ADR no identifica el estado en el que el equipo deja las revisiones que requieren ajustes.

**Esperado** · Para pasar a «Finalizado» han de haberse resuelto los ajustes.

**Encontrado** · El MD permite comentar «Requiere ajustes» al pasar a «Finalizado». La tabla de transición, el tratamiento de entregables y la definición del MPW exigen cumplimiento sin ajustes pendientes.

**Acción sugerida** · Identificar en qué transición se registra la revisión fallida y qué acredita el cierre.


### DOC-016

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MD-V3 §§5.1 y 11.8.3, pp. 29–30, 60. MPW-V3 §§1.2 y 4.1–4.1.2, pp. 17, 47–49.

**Evidencia** · app/Http/Requests/AttemptTakeRequest.php:5–14 y resources/views/components/nav-icon.blade.php:1 muestran variantes de identificación. WEB-MAN-003 aplica solo a ausencias expresas; las actividades sin RF directo se registran por separado.

**Esperado** · Los trabajos y cabeceras han de poder rastrearse incluso cuando sean auxiliares.

**Encontrado** · MD admite actividad técnica sin RF y da una leyenda. MPW pide código RF/RNF en las clases y tarjetas de programación. Las variantes de cabecera Blade ilustradas tampoco expresan todos los campos generales.

**Acción sugerida** · Acordar cómo identificar utilidades y plantillas sin requerimiento directo. Conservar la distinción entre funcionalidad del producto y actividad técnica.


### DOC-017

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §§7.5.1, 8.1 y 8.7, pp. 80–81, 93–94, 108–109, ilustraciones 14 y 21.

**Evidencia** · resources/views/components/exam/timer.blade.php:70–79 selecciona estado neutro/rojo cerca del vencimiento. No se atribuye falta de fase ámbar mientras el propio ejemplo use esa transición. WEB-CAT-003 conserva la comparación independiente de mensajes.

**Esperado** · Fijar la señal de aviso del cronómetro en valores reproducibles.

**Encontrado** · El texto habla de menos de 10 s, el fragmento y la tabla de color incluyen el segundo 10, la ilustración 14 señala los últimos 5 s y la 21 ilustra una advertencia ámbar inferior a 60 s.

**Acción sugerida** · Acordar umbral, inclusión del límite y diferencia entre alerta y color del cronómetro.


### DOC-018

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §§8.1, 8.3.1 y 8.3.3, tabla 12 pp. 93–94, p. 100 y tabla 15 pp. 101–102.

**Evidencia** · resources/css/app.css:16–20 y resources/views/components/button.blade.php:25–40 identifican turquesa y texto. El par blanco/turquesa del manual da aproximadamente 3:1. Falta aceptar la variante/contexto de contraste.

**Esperado** · El par de colores del botón de éxito debe poder cotejarse con el contraste declarado.

**Encontrado** · Se prescribe blanco sobre `#16A6A1`. El contraste de ese par, calculado a partir de los dos valores HEX, es 2.999:1. La fila del token claro/oscuro declara 4.6:1 sin desglosar ambas variantes.

**Acción sugerida** · Determinar qué par y estado respaldan la cifra de la tabla y cuál corresponde al botón de éxito. El cálculo utiliza (luminancia clara + 0.05) / (luminancia oscura + 0.05).


### DOC-019

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MC-V3 §§2.4.1–2.4.2, pp. 9–10, ilustraciones 1–3. MPW-V3 §8.1.2, p. 97.

**Evidencia** · resources/views/layouts/app.blade.php:423–433 y resources/views/layouts/guest.blade.php:118–133 usan los artes de marca y el lema. Falta fijar la variante autorizada por fondo.

**Esperado** · Reconocer qué arte y qué redacción están autorizados para cada fondo.

**Encontrado** · MC exige respetar los colores y muestra tres presentaciones a color. MPW incorpora una versión negativa en blanco sobre marino; reproduce también el lema sin el punto final del literal del MC.

**Acción sugerida** · Identificar el arte web aprobado para fondo oscuro y el tratamiento del punto en el lema gráfico.


### DOC-020

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · RF-SUS-009, RF-EXA-008/012/015/019, D44, D73, D77, D80/E80/I80, D84. MPW-V3 §6.9.1, pp. 70–71, tabla 8.

**Evidencia** · La tabla 8 y database/seeders/SubscriptionPackageSeeder.php:45–70 fijan cantidades. app/Http/Requests/ExamConfigurationUpdateRequest.php:54–79 define opciones del examen. La correspondencia de capacidades no especificadas por nivel sigue abierta.

**Esperado** · La modalidad, el orden, los tipos de apartados y las capacidades de la prueba se verifican según el paquete.

**Encontrado** · I80 indica «Los paquetes no definen el orden». La tabla 8 fija precios y cuotas de recursos, sin asignar todas las opciones de examen a cada nivel.

**Acción sugerida** · Precisar las funciones por plan, incluidas las de nivel 1 que hereda el examen de prueba.


### DOC-021

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · RF-EXA-012/014/033/042/043, E77/I77, E79/I79, F98/I98, D107/F107/I107, F108/I108.

**Evidencia** · app/Services/AttemptService.php:225–242,352–366 y resources/views/components/exam/timer.blade.php:12–35 implementan reloj por reactivo. La marca ✓ de F107 no cubre el tiempo del examen libre; WEB-EXA-003.

**Esperado** · La marca del temporizador debería cubrir el tiempo por pregunta en modo secuencial y el del examen en modalidad libre.

**Encontrado** · F107 indica ✓. I107 solo explica el cronómetro por pregunta. Cuatro filas relacionadas consignan ✗ y describen la ausencia de modalidad libre o del tiempo total.

**Acción sugerida** · Mantener ambas modalidades como casos de prueba distintos y documentar cómo se obtuvo la marca global.


### DOC-022

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · RF-EXA-020 a 023, D85:D88 e I85:I88.

**Evidencia** · app/Services/ExamAssemblyService.php:102–149,293–305 y routes/web.php:185–201 distinguen guardar, publicar y retirar. Falta acuerdo sobre el punto de bloqueo de cambios. Ver WEB-EXA-005/006.

**Esperado** · La publicación a la hora de apertura determina desde cuándo se bloquean cambios y hasta cuándo se puede cancelar la programación.

**Encontrado** · I86 equipara el botón «Publicar» con programar; I87 vincula el bloqueo al inicio o a tener intentos e I88 describe despublicar mientras no haya intentos. Queda sin resolver la combinación «publicado, abierto y sin intentos».

**Acción sugerida** · Confirmar las transiciones autorizadas y probar por separado ese intervalo.


### DOC-023

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · RF-EXA-038, RNF-TEC-004, D103 y D243. MPW-V3 §8.5, ilustración 19, p. 107, §8.9.1, ilustración 23, p. 110 y §6.10.5, pp. 74–75.

**Evidencia** · resources/views/exams/take-exam.blade.php:169–177 recoge texto; app/Services/AiEvaluationService.php:302–342 y app/Services/MockAiEvaluator.php:42–65 preparan o evalúan el texto. La ilustración y el alcance operativo tienen distinto significado.

**Esperado** · El editor debe conservar el código sin compilarlo ni ejecutarlo, también en la evaluación asistida.

**Encontrado** · La ilustración 19 rotula el tipo «Evaluación de Código (IA + Ejecución)». La ilustración 23 muestra el editor del estudiante. El alcance de IA documentado se limita a evaluación bajo demanda.

**Acción sugerida** · Determinar la etiqueta de interfaz autorizada y conservar la prohibición expresa como criterio para la operación.


### DOC-024

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · RNF-REC-001, D239. MBD-V3 §§8.1–8.4, pp. 31–33.

**Evidencia** · MBD-058–063 y RNF-REC-001 distinguen prescripción y ejecución. Faltan frecuencia definida, muestra de respaldo, restauración aislada y validación posterior en el entorno autorizado.

**Esperado** · Definir antes de operar la frecuencia y cómo se comprueba la restauración.

**Encontrado** · MBD asigna la frecuencia al entorno y enumera tres supuestos para restaurar. No precisa si un simulacro preventivo en una base aislada sana entra en alguno de ellos.

**Acción sugerida** · Identificar el calendario y el procedimiento autorizado para ensayar en un entorno de prueba.


### DOC-025

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MBD-V3 §3.4, p. 17. MPW-V3 §8.6.1, p. 108.

**Evidencia** · database/migrations/2026_10_26_121000_bd01_2026_003_create_acs_privacy_acceptances_table.php:63–67 vincula usuario y aviso. Queda abierta la excepción de nomenclatura de relaciones con varios roles.

**Esperado** · Las FK a tablas multirrol usan el rol de la relación.

**Encontrado** · MPW prescribe `user_id` para aceptaciones de privacidad compartidas por docentes y estudiantes. El caso es transversal a los dos roles.

**Acción sugerida** · Aclarar si la FK genérica es el nombre aprobado para una relación multirrol compartida.


### DOC-026

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MBD-V3 §5.3, p. 26. MPW-V3 §1.3, tabla 2 p. 22, y §2.1, tabla 3 p. 26.

**Evidencia** · Se contaron 38 archivos de migración. MBD-040/043 trata el nombre de archivo; MPW-032 y MBD-010 tratan el nombre del objeto. WEB-MBD-006 documenta diferencias independientes del ejemplo.

**Esperado** · Identificar por separado el archivo de migración y la tabla que crea.

**Encontrado** · MPW usa «Migraciones con prefijo acs_». El MBD sitúa marca temporal y folio del cambio en el nombre de archivo, y el `acs_` en el nombre de la tabla del ejemplo.

**Acción sugerida** · Confirmar el objeto al que se aplica cada convención antes de juzgar nombres de archivos.


### DOC-027

Categoría 6 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §2.1, tabla 3 p. 25, §7.8.2, tabla 11 p. 89, §8.5, ilustración 19 p. 107 y §8.11, tabla 18 pp. 114–116.

**Evidencia** · app/Http/Requests/GradebookSaveRequest.php:53–61 y las tablas 11/18 de este informe distinguen mensajes por situación y plantillas de campo. No se prescribe literalmente un ejemplo incompatible.

**Esperado** · Las siete plantillas obligatorias y sus ejemplos deberían permitir comparar literalmente cada mensaje por situación.

**Encontrado** · Para formato inválido la tabla también ejemplifica «El teléfono debe contener 10 dígitos». Un ejemplo de selección omite «seleccionada». La ilustración 19 incluye código y punto en un error de campo.

**Acción sugerida** · Distinguir excepciones autorizadas, alertas y validaciones por campo para aceptar literales.


### DOC-040

Categoría 6 · Media · [Informe de origen](RNF.md).

**Fuente** · RNF-USA-001, RNF-ACC-001 y RF-EXA-053, D235:D236 y D121; MPW §8.10.4 p. 113

**Evidencia** · Fase 1, decisiones_documentales.md, fila de alcance de navegador y accesibilidad.

**Esperado** · Precisar dispositivos, tamaños y operaciones incluidas para aceptar la adaptación y accesibilidad.

**Encontrado** · La web incluye navegador móvil y un corte de menú a 1024 px. Las fuentes no enumeran todos los tamaños, versiones de navegador ni operaciones de aceptación.

**Acción sugerida** · Requiere decisión. Documentar la matriz de dispositivos y los recorridos de teclado incluidos.


### WEB-ADM-005

Categoría 8 · Media · [Informe de origen](ADM.md).

**Fuente** · RF-ADM-006, D206 y DOC-004

**Evidencia** · resources/views/admin/teachers/index.blade.php:321-341, app/Http/Requests/AssignTeacherPackageRequest.php:48-54 y app/Services/TeacherVerificationService.php:1254-1287

**Esperado** · Distinguir ajuste manual de vigencia y cobro respaldado por un comprobante.

**Encontrado** · El ajuste deja la suscripción en estado pagado y habilita el plan sin vincular un comprobante. Crea la bitácora de asignación; no crea ni modifica un movimiento PaymentTransaction. El efecto acreditado es la discrepancia entre estado comercial y respaldo consultable. El dictamen de D206 queda reservado a DOC-004.

**Acción sugerida** · Separar asignación de paquete y confirmación de cobro. Sustentar esta última con evidencia verificable.


### WEB-AUT-004

Categoría 5 · Media · [Informe de origen](AUT.md).

**Fuente** · RF-AUT-012, D18 y MPW §8.10.4 p. 113

**Evidencia** · resources/views/dashboard/teacher.blade.php:303, resources/views/dashboard/partials/_plan-onboarding-overlay.blade.php:50-58 y resources/views/layouts/app.blade.php:552-566

**Esperado** · Confirmar siempre antes de cerrar.

**Encontrado** · Desde el asistente de plan el botón envía directamente el formulario de cierre. El menú general sí abre confirmación.

**Acción sugerida** · Unificar ambos botones con el mismo control de confirmación y cancelación.


### WEB-AUT-006

Categoría 4 · Media · [Informe de origen](AUT.md).

**Fuente** · MPW §8.10.6 p. 114

**Evidencia** · resources/views/auth/register-teacher.blade.php:249-258,275-281,466-474,499-507

**Esperado** · Habilitar la aceptación del aviso docente después de llegar al final.

**Encontrado** · El modal controla su botón por desplazamiento. La casilla exterior deja aceptar desde el primer paso sin abrirlo.

**Acción sugerida** · Compartir el estado de lectura entre casilla y modal.


### WEB-BAN-001

Categoría 5 · Media · [Informe de origen](BAN.md).

**Fuente** · RF-BAN-010, RF-EXA-004/022 y MPW-V3 §6.4.1, pp. 64–65

**Evidencia** · app/Services/QuestionBankService.php:371–379 y app/Services/QuestionBankService.php:436–455; app/Repositories/Eloquent/EloquentQuestionRepository.php:786–804; app/Models/ExamQuestion.php:84

**Esperado** · Mantener la clasificación de copias y apartados de un examen ya incorporado o publicado.

**Encontrado** · La edición ordinaria crea versión. La fusión de materias actualiza en bloque preguntas incluso borradas y apartados de todos los exámenes del docente, sin comprobar publicación ni versionar la clasificación.

**Acción sugerida** · Conservar materia histórica en la copia y limitar la reasignación de apartados a los editables.


### WEB-BAN-002

Categoría 1 · Media · [Informe de origen](BAN.md).

**Fuente** · RF-BAN-014, D69/I69

**Evidencia** · app/Http/Requests/Concerns/NormalizesQuestionInputs.php:94–103, app/Http/Requests/Concerns/NormalizesQuestionInputs.php:170–175 y resources/views/questions/show.blade.php:123

**Esperado** · Conservar sangrías y saltos en los fragmentos incluidos en preguntas y referencias.

**Encontrado** · La vista conserva los espacios interiores; el saneamiento retira la sangría inicial y saltos extremos cuando el fragmento ocupa el comienzo o final del enunciado o la referencia abierta.

**Acción sugerida** · Validar el vacío por separado y preservar esos caracteres al guardar campos que admiten código.


### WEB-BAN-003

Categoría 4 · Media · [Informe de origen](BAN.md).

**Fuente** · MPW-V3 §7.8.2, tabla 10, pp. 87–88

**Evidencia** · resources/views/questions/show.blade.php:91–105 y app/Repositories/Eloquent/EloquentQuestionRepository.php:526–551

**Esperado** · Usar «Copiar» para transferir al portapapeles y una etiqueta inequívoca para crear otro reactivo.

**Encontrado** · La acción «Copiar» envía el formulario de duplicación y crea una fila persistente con título «(Copia)».

**Acción sugerida** · Identificar el botón como duplicación e indicar que crea un reactivo.


### WEB-CAL-013

Categoría 1 · Media · [Informe de origen](CAL.md).

**Fuente** · RF-CAL-001, D153

**Evidencia** · resources/views/evaluation-periods/index.blade.php:141–174, app/Http/Controllers/EvaluationPeriodController.php:67–70,94–99 y database/migrations/2026_10_26_180000_bd01_2026_020_create_acs_evaluation_periods_table.php:38–42

**Esperado** · Permitir que el docente indique nombre y orden para la cantidad de periodos elegida.

**Encontrado** · Se admiten tantos registros como se necesiten; el orden deriva solo de la fecha de inicio sin un criterio definido por el docente para empates.

**Acción sugerida** · Incluir un orden de periodo verificable también cuando las fechas coinciden.


### WEB-CAL-014

Categoría 1 · Media · [Informe de origen](CAL.md).

**Fuente** · RF-CAL-005, D157

**Evidencia** · app/Http/Requests/EvaluationPeriodStoreRequest.php:53–65 y app/Http/Controllers/EvaluationPeriodController.php:87–96

**Esperado** · La suma utilizada en la fórmula debe ser exactamente 100 %.

**Encontrado** · El validador permite diferencias de hasta 0.01 y no verifica la suma de los cuatro coeficientes ya redondeados. Se admiten importes con más de dos decimales.

**Acción sugerida** · Definir la precisión de entrada y validar la suma en unidades enteras antes de usar los pesos guardados.


### WEB-CAT-003

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §7.8.2, tabla 11, pp. 89–90

**Evidencia** · app/Http/Controllers/GroupController.php:133 y resources/views/student/exams/instructions.blade.php:93; app/Http/Controllers/AttemptController.php:115 reproduce el literal de otra situación.

**Esperado** · Comunicar el cierre con el mensaje institucional aprobado, sin referencias de implementación en la interfaz.

**Encontrado** · El cierre semestral expone «RW-35» y un literal distinto del catálogo. La advertencia previa del examen también altera el texto aprobado, aunque conserva el sentido.

**Acción sugerida** · Centralizar los mensajes de situación del catálogo, con las excepciones que se autoricen al resolver DOC-027.


### WEB-CAT-004

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §7.8.2, tabla 10, pp. 86–87 y RF-EXA-003

**Evidencia** · resources/views/exams/assemble.blade.php:231–243 y resources/views/exams/assemble.blade.php:737–742; app/Http/Controllers/ExamController.php:386–399

**Esperado** · Que el rótulo de un nuevo sorteo o sustitución indique qué se hará; el catálogo reserva Reintentar para recuperarse de un fallo.

**Encontrado** · La misma acción «Reintentar» puede cambiar un reactivo del examen aun cuando la selección anterior terminó correctamente.

**Acción sugerida** · Rotular según el efecto: solicitar otro sorteo o sustituir el reactivo.


### WEB-EVA-002

Categoría 1 · Media · [Informe de origen](EVA.md).

**Fuente** · RF-EVA-003 (D128)

**Evidencia** · resources/views/exams/review-attempt.blade.php:215,244,288–350; referencias y rúbricas disponibles en app/Services/AiEvaluationService.php:319–337

**Esperado** · El docente contrasta la respuesta con la referencia y los criterios aplicables en la revisión.

**Encontrado** · La vista del intento muestra la opción correcta para selección múltiple. Para abierta y programación muestra el envío del alumno y omite la referencia y la rúbrica.

**Acción sugerida** · Presentar la referencia y los criterios de la versión del reactivo que contestó el alumno.


### WEB-EVA-003

Categoría 1 · Media · [Informe de origen](EVA.md).

**Fuente** · RF-EVA-007/012 (D138, D151)

**Evidencia** · resources/views/exams/review-attempt.blade.php:437–481 y app/Http/Requests/TeacherReviewStoreRequest.php:112–118

**Esperado** · Anotar una respuesta y corregir puntuaciones establecidas dentro de su valor.

**Encontrado** · Los controles y la escritura admiten únicamente preguntas abiertas y de programación. En selección y relación la puntuación queda en solo lectura y sin anotación.

**Acción sugerida** · Definir la corrección docente de las objetivas, con límites e historial, y habilitar sus comentarios.


### WEB-EVA-005

Categoría 5 · Media · [Informe de origen](EVA.md).

**Fuente** · RF-EVA-010 (D149)

**Evidencia** · resources/views/exams/review-attempt.blade.php:221–230, resources/views/exams/result-summary.blade.php:173–177, app/Services/AttemptService.php:527–532, app/Http/Controllers/TeacherReviewController.php:407–426

**Esperado** · La etiqueta de cada respuesta concuerda con su estado y con el pendiente del intento.

**Encontrado** · Una revisión manual sin IA sigue rotulada pendiente en la vista docente; el resumen del alumno rotula pendiente un blanco cualitativo que ya cuenta como resuelto.

**Acción sugerida** · Derivar las etiquetas de la calificación docente y de la marca de blanco con la misma regla que el contador.


### WEB-EVA-006

Categoría 1 · Media · [Informe de origen](EVA.md).

**Fuente** · RF-EVA-013 (D152)

**Evidencia** · app/Http/Requests/TeacherReviewStoreRequest.php:49 y app/Http/Controllers/TeacherReviewController.php:368–400

**Esperado** · Cada corrección identifica el motivo además de autor, fecha y cambio.

**Encontrado** · El motivo toma el valor de la retroalimentación opcional y puede guardarse nulo.

**Acción sugerida** · Pedir y validar un motivo para cambios de una nota ya establecida, conservándolo en la bitácora.


### WEB-EXA-001

Categoría 1 · Media · [Informe de origen](EXA_1_armado_y_programacion.md).

**Fuente** · RF-EXA-002, D61/I61

**Evidencia** · resources/views/exams/assemble.blade.php:202–223; resources/views/questions/show.blade.php:123

**Esperado** · Consultar las preguntas propuestas antes de tomar la decisión de aceptación.

**Encontrado** · La propuesta solo enumera título interno, puntos y segundos; no contiene el enunciado ni enlace al detalle. El detalle debe localizarse por separado en el banco.

**Acción sugerida** · Mostrar el enunciado o enlazar la versión propuesta desde la lista.


### WEB-EXA-002

Categoría 1 · Media · [Informe de origen](EXA_1_armado_y_programacion.md).

**Fuente** · RF-EXA-005 y RF-BAN-011, D64/D66

**Evidencia** · resources/views/exams/assemble.blade.php:729–736; app/Http/Controllers/ExamController.php:532–537; app/Models/ExamQuestion.php:84

**Esperado** · Editar una copia incorporada durante la preparación, incluso si la original ya salió del banco.

**Encontrado** · La copia puede leerse tras el borrado lógico, pero el formulario se oculta y el controlador de edición la rechaza.

**Acción sugerida** · Permitir crear una nueva versión desde la copia conservada.


### WEB-EXA-008

Categoría 8 · Media · [Informe de origen](EXA_1_armado_y_programacion.md).

**Fuente** · RF-EXA-025, D90/I90

**Evidencia** · app/Services/ExamNotificationService.php:93–105, app/Services/ExamNotificationService.php:137–148 y app/Services/ExamNotificationService.php:231–235

**Esperado** · Que cada alumno destinatario reciba el aviso cuando se abre su aplicación.

**Encontrado** · La marca de apertura es por examen y se escribe antes de enviar. Un fallo transitorio de entrega queda solo en el registro y el siguiente ciclo omite el examen entero.

**Acción sugerida** · Conservar estado de entrega por destinatario y reintentar los envíos fallidos.


### WEB-EXA-009

Categoría 1 · Media · [Informe de origen](EXA_2_aplicacion.md).

**Fuente** · RF-EXA-026, D91/I91

**Evidencia** · app/Services/GroupEnrollmentService.php:197–205; resources/views/exams/student-index.blade.php:112,141–160

**Esperado** · Ver apertura, límite de inicio y estado individual del estudiante.

**Encontrado** · Se incluyen publicados de grupos abiertos sin filtrar por ventana. La tarjeta muestra «Disponible» e «Iniciar examen» de forma fija y solo informa del cierre; no distingue futuro, terminado ni participación.

**Acción sugerida** · Proyectar apertura, estado de la aplicación y último intento en el listado.


### WEB-EXA-017

Categoría 5 · Media · [Informe de origen](EXA_2_aplicacion.md).

**Fuente** · RF-EXA-027/050, D92/D115; DOC-001

**Evidencia** · app/Services/AttemptService.php:159–165, app/Services/AttemptService.php:142, app/Models/Exam.php:299–301

**Esperado** · Dejar de admitir nuevos inicios en el instante de cierre.

**Encontrado** · La admisión rechaza únicamente cuando ahora es posterior al límite; para reaplicación y estado cerrado se usa mayor o igual. Los tres predicados discrepan en el instante exacto.

**Acción sugerida** · Compartir un predicado de admisión que trate el instante límite de forma uniforme.


### WEB-EXA-018

Categoría 5 · Media · [Informe de origen](EXA_1_armado_y_programacion.md).

**Fuente** · RF-EXA-001/003 y MPW-V3 §6.3.2, tabla 7, p. 62

**Evidencia** · app/Services/QuestionBankService.php:436–455, app/Repositories/Eloquent/EloquentQuestionRepository.php:239–261,469–479 y app/Services/ExamAssemblyService.php:637–646

**Esperado** · Poder aceptar una propuesta recién generada con el número de preguntas y linajes elegibles.

**Encontrado** · Se sortean identificadores de filas sin separar los linajes dentro de la propuesta. Al confirmar, una segunda versión del mismo linaje desaparece y la comprobación de cantidad rechaza la selección.

**Acción sugerida** · Aplicar la regla de linajes al contar y sortear candidatos, además de revalidar al confirmar.


### WEB-GRU-006

Categoría 1 · Media · [Informe de origen](GRU.md).

**Fuente** · RF-GRU-012, D32

**Evidencia** · resources/views/student/groups/index.blade.php:302-326

**Esperado** · Mencionar pérdida de pertenencia, recursos y resultados al confirmar la salida.

**Encontrado** · El diálogo describe salir del grupo y perder sus exámenes y recursos, pero omite expresamente la pérdida de acceso a los resultados.

**Acción sugerida** · Completar el texto de la confirmación de salida.


### WEB-GRU-007

Categoría 1 · Media · [Informe de origen](GRU.md).

**Fuente** · RF-GRU-021, D200

**Evidencia** · resources/views/student/groups/index.blade.php:217-225, resources/views/exams/student-index.blade.php:115-150 y resources/views/student/exams/instructions.blade.php:30-36

**Esperado** · Identificar materia y nombre del docente también al consultar exámenes asociados.

**Encontrado** · Los grupos sí muestran ambos. La tarjeta de Mis exámenes omite ambos campos y las instrucciones muestran docente sin la materia del grupo.

**Acción sugerida** · Incluir los dos datos en el contexto de los grupos vigentes del estudiante.


### WEB-IAR-003

Categoría 1 · Media · [Informe de origen](IAR.md).

**Fuente** · RF-IAR-013, D146

**Evidencia** · resources/views/exams/review-attempt.blade.php:544–549,550–579

**Esperado** · Si el docente mantiene la preferencia por defecto, la reevaluación requiere confirmar el nuevo consumo.

**Encontrado** · Si la biblioteca de diálogo no está disponible, la rama alternativa envía directamente la solicitud de reevaluación.

**Acción sugerida** · Proveer confirmación alternativa o impedir el envío hasta poder mostrarla.


### WEB-IAR-004

Categoría 1 · Media · [Informe de origen](IAR.md).

**Fuente** · RF-IAR-005/012, D135 y D145

**Evidencia** · app/Services/AiEvaluationService.php:384–402 y resources/views/exams/review-attempt.blade.php:403–414,444–452

**Esperado** · Poder confirmar la nueva puntuación sugerida incluso después de una revisión previa.

**Encontrado** · Si hay nota docente, la reevaluación cambia el porcentaje y justificación, pero el campo editable conserva la nota anterior. Guardar confirma la anterior; aplicar la nueva requiere calcularla e introducirla manualmente.

**Acción sugerida** · Exponer los puntos de la nueva sugerencia y permitir aplicarlos sin sobrescribir la nota hasta que el docente confirme.


### WEB-MAN-002

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §§2.2, 6.1 y 6.3, pp. 27, 60–61; MPW-015/051

**Evidencia** · app/Http/Controllers/StudentGradeController.php:43–52, app/Http/Controllers/EvaluationPeriodController.php:122–130 y routes/web.php:148–185

**Esperado** · Delegar consultas a servicios o repositorios y ubicar acciones auxiliares complejas en controladores invocables.

**Encontrado** · Las acciones HTTP construyen consultas Eloquent y agregaciones y el controlador de exámenes concentra las operaciones auxiliares de armado.

**Acción sugerida** · Trasladar consultas a la capa documentada y asignar las acciones auxiliares según §6.1.


### WEB-MAN-006

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §5.2.1, pp. 54–55

**Evidencia** · app/Services/QuestionBankService.php:1548–1555

**Esperado** · Acotar los reintentos para generar identificadores únicos.

**Encontrado** · Los dos ciclos de generación de identificadores del ejercicio de emparejamiento terminan al obtener un valor libre, sin máximo explícito.

**Acción sugerida** · Establecer el máximo y definir el resultado cuando se agota.


### WEB-MAN-007

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §5.4.2, pp. 56–57

**Evidencia** · app/Services/TeacherSubscriptionService.php:643–651

**Esperado** · Procesar un lote masivo de suscripciones de modo incremental.

**Encontrado** · La tarea recupera toda la colección vencida con sus relaciones antes de iniciar el primer cargo. El volumen real es evidencia pendiente.

**Acción sugerida** · Procesar el lote en segmentos estables o con recorrido incremental compatible con las relaciones.


### WEB-MAN-008

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §5.2.2, p. 55

**Evidencia** · resources/views/landing.blade.php:441–445, resources/views/admin/invitations/index.blade.php:310–321 y resources/views/teacher/subscription/index.blade.php:424–433

**Esperado** · Mantener el componente común de lista vacía en los listados de servidor.

**Encontrado** · Los primeros listados escriben el aviso vacío con HTML local. El historial de pagos usa el componente prescrito para la misma situación.

**Acción sugerida** · Usar el componente compartido en catálogos y tablas de servidor aplicables.


### WEB-MAN-009

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §6.3.2, tabla 7, pp. 61–63

**Evidencia** · app/Http/Requests/RegisterStudentRequest.php:33–40, app/Http/Controllers/Auth/RegisterController.php:97,121, app/Services/AuthService.php:114–121, app/Http/Requests/AttemptAnswerWebRequest.php:47–54 y app/Http/Controllers/AttemptController.php:224–230

**Esperado** · Declarar en los Form Requests el identificador del aviso y la forma de respuesta por tipo.

**Encontrado** · El identificador del aviso se verifica en el servicio. Para tipos distintos del emparejamiento, el controlador interpreta el campo genérico después de la validación.

**Acción sugerida** · Mantener la comprobación transaccional del aviso y declarar las condiciones de entrada/tipo en las solicitudes.


### WEB-MBD-004

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MBD-031 · MBD-V3 §4.2.2, p. 24

**Evidencia** · database/migrations/2026_11_17_000001_create_teacher_verification_tables.php:78–89, database/migrations/2026_11_18_000001_create_payment_transactions_and_onboarding_column.php:63–67 y database/migrations/2026_11_19_000001_create_jobs_and_ai_credit_support_tables.php:87–91

**Esperado** · Las FK que conservan al hijo mediante SET NULL al eliminar mantienen NO ACTION al actualizar.

**Encontrado** · Algunas FK opcionales de verificación docente, pagos y uso de créditos declaran CASCADE al actualizar la clave padre.

**Acción sugerida** · Revisar las FK opcionales con el responsable de datos y documentar o armonizar la acción de actualización.


### WEB-MBD-005

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MBD-035 · MBD-V3 §4.2.5, p. 24

**Evidencia** · app/Http/Controllers/EvaluationPeriodController.php:152–185 y app/Http/Controllers/TeacherReviewController.php:352–384,407–426

**Esperado** · Mantener las decisiones de negocio en servicios de aplicación.

**Encontrado** · La decisión sobre cuándo una nota es definitiva y el tratamiento de componentes nulos residen en el controlador de periodos. La admisibilidad del recotejo y el recuento de respuestas pendientes también residen en el controlador de revisión.

**Acción sugerida** · Concentrar las reglas de cálculo, recotejo y estado en servicios compartidos por las entradas web.


### WEB-MBD-007

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MBD-021 · MBD-V3 §4.1.3, p. 20

**Evidencia** · database/migrations/2026_10_26_150000_bd01_2026_008_create_acs_questions_table.php:38, database/migrations/2026_11_12_000001_create_acs_subjects_table_and_normalize_subject_links.php:43–55, docs/02_BASE_DE_DATOS.md:59–75 y app/Services/QuestionBankService.php:373–390

**Esperado** · Evitar atributos redundantes dependientes de otra entidad en el modelo de tercera forma normal.

**Encontrado** · La pregunta guarda el nombre de la materia en subject_tag y además subject_id. El servicio sincroniza ambos campos. El diseño declara una copia para compatibilidad y exige mantenerla al escribir.

**Acción sugerida** · Documentar la excepción de normalización si se aprueba o usar la relación como fuente del nombre.


### WEB-MBD-008

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MBD-027 · MBD-V3 §4.2.1, tabla 11, p. 22

**Evidencia** · database/migrations/2026_11_17_000001_create_teacher_verification_tables.php:67–68, database/migrations/2026_11_18_000001_create_payment_transactions_and_onboarding_column.php:69–70, app/Models/TeacherVerification.php:38–47 y app/Models/PaymentTransaction.php:38–59

**Esperado** · Usar enum con verificación en BD para dominios cerrados.

**Encontrado** · Las clases enumeran estados y métodos aceptados, mientras las columnas correspondientes son VARCHAR sin restricción de dominio; la persistencia no comprueba ese conjunto.

**Acción sugerida** · Registrar el dominio de cada columna y aplicar su restricción según el catálogo aprobado.


### WEB-MBD-009

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MBD-039/045 · MBD-V3 §§5.1 y 5.5, pp. 25 y 27

**Evidencia** · database/migrations/2026_11_19_000001_create_jobs_and_ai_credit_support_tables.php:102–110,120–142 y database/migrations/2026_11_23_000001_bd01_2026_038_add_reapplication_to_acs_student_attempts_table.php:40–54

**Esperado** · Que down() revierta el cambio o el procedimiento identifique la restauración necesaria para conservar información.

**Encontrado** · La reversión de trabajos/créditos elimina el soporte estructural pero deja las cuotas 200/600 instaladas en los paquetes preexistentes. La reversión de reaplicación además presupone que no existan dos intentos del mismo alumno/examen; si ya existen, la unicidad anterior no es restablecible con esos datos.

**Acción sugerida** · Identificar los cambios de datos reversibles y documentar en BD-01 las precondiciones o el respaldo requerido para restablecer el estado anterior.


### WEB-MPW-059

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-059 · MPW-V3, §6.4.3, p. 66

**Evidencia** · app/Services/ExamAssemblyService.php:304 y app/Services/AttemptService.php:202-209,488-495,553-561

**Esperado** · Programar la difusión con la confirmación definitiva de las transacciones del dominio.

**Encontrado** · Publicación registra DB::afterCommit. Inicio, incidente y entrega despachan directamente desde el servicio. Si lo invoca una transacción exterior, la difusión no queda vinculada a su confirmación.

**Acción sugerida** · Registrar la difusión mediante callbacks post-commit que contemplen transacciones anidadas.


### WEB-MPW-062

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-062 · MPW-V3, §§6.9–6.9.1, pp. 70–71, tabla 8

**Evidencia** · app/Exceptions/SubscriptionLimitExceededException.php:43,57-70

**Esperado** · El rechazo por cuota responde HTTP 403 según MPW.

**Encontrado** · La excepción define el estado 403 y lo usa en JSON; para el navegador devuelve una redirección con errores.

**Acción sugerida** · Ofrecer al navegador la explicación y el estado HTTP documentados.


### WEB-MPW-072

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-072 · MPW-V3, §3.5.2 p. 39 y §7.3 pp. 78–79

**Evidencia** · resources/views/groups/show.blade.php:902-907, resources/views/components/group/qr-modal.blade.php:49-83 y resources/views/groups/index.blade.php:476-511

**Esperado** · Reutilizar el componente del modal QR en las pantallas que lo ofrecen.

**Encontrado** · El detalle usa el componente y el catálogo repite a mano la composición y la construcción de URL del mismo QR.

**Acción sugerida** · Reutilizar el componente y parametrizar su folio para el grupo seleccionado.


### WEB-MPW-089

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-089 · MPW-V3, §8.1.2, p. 97 y MPW-V3 §8.10.2, p. 113

**Evidencia** · resources/views/layouts/exam-runner.blade.php:68-76, resources/views/layouts/app.blade.php:648-662 y resources/views/components/logo.blade.php:9-17

**Esperado** · Usar isotipo en la barra del examen y la marca prescrita en el pie del panel.

**Encontrado** · La barra monta la imagen horizontal y el pie contiene solo texto.

**Acción sugerida** · Aplicar las variantes y dimensiones prescritas en ambas ubicaciones, respetando la decisión de marca DOC-019.


### WEB-MPW-093

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-093 · MPW-V3, §§8.3.2–8.3.3, pp. 100–102, il. 17 y tabla 15

**Evidencia** · resources/views/components/button.blade.php:14-43

**Esperado** · Deshabilitar el control durante Loading para impedir repetir la acción.

**Encontrado** · La rama que emite button aplica disabled, mientras que la variante que emite enlace conserva el destino y puede navegar mientras presenta el spinner.

**Acción sugerida** · Aplicar comportamiento inactivo equivalente a los enlaces en carga.


### WEB-MPW-096

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-096 · MPW-V3, §8.4, p. 106, il. 18

**Evidencia** · resources/views/components/table/th.blade.php:8-14 y resources/views/admin/teachers/index.blade.php:136-145

**Esperado** · Centrar el texto blanco seminegrita de las cabeceras de tabla.

**Encontrado** · El valor inicial del componente alinea a la izquierda y la tabla administrativa hereda text-left sin centrar los encabezados.

**Acción sugerida** · Centrar los encabezados normativos en el componente y en las tablas que lo omiten.


### WEB-MPW-098

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-098 · MPW-V3, §8.6, pp. 107–108, il. 20

**Evidencia** · resources/views/components/modal.blade.php:39-57,71-79 y resources/views/admin/teachers/index.blade.php:398-406

**Esperado** · Hacer reconocible el tipo de acción mediante el borde de cabecera del modal.

**Encontrado** · La cabecera siempre usa borde gris, incluso en diálogos con acciones de aprobación o rechazo.

**Acción sugerida** · Enlazar la variante de cabecera a la acción o al estado del diálogo.


### WEB-MPW-100

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-100 · MPW-V3, §8.7, pp. 108–109, il. 21

**Evidencia** · public/js/alerts.js:29-32,82-84,113-119 y resources/views/groups/show.blade.php:81-96

**Esperado** · Mantener avisos y confirmaciones institucionales y una degradación segura si falla el CDN.

**Encontrado** · El componente de alertas y algunos consumidores vuelven a diálogos nativos en contingencia. Otras pantallas envían sin confirmar, ya registradas en WEB-ADM-010.

**Acción sugerida** · Proveer una alternativa local accesible que conserve la opción de cancelar y el vocabulario de la acción.


### WEB-MPW-101

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-101 · MPW-V3, §8.8, p. 109, il. 22

**Evidencia** · resources/views/components/search-filter.blade.php:45 y resources/views/teacher/gradebook/hub.blade.php:133-140

**Esperado** · Aplicar a los catálogos extensos el mismo retardo de 300 ms en su búsqueda predictiva.

**Encontrado** · El componente común espera 300 ms; el hub de calificaciones enlaza el texto y filtra de inmediato.

**Acción sugerida** · Aplicar el intervalo documentado también a la búsqueda de calificaciones.


### WEB-MPW-102

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-102 · MPW-V3, §8.9.1, p. 110, il. 23

**Evidencia** · resources/views/exams/take-exam.blade.php:8-33,99-177

**Esperado** · Mostrar número y puntaje del reactivo en su cabecera durante la aplicación.

**Encontrado** · Se muestran el número y el cronómetro; la tarjeta no incluye los puntos asignados al reactivo.

**Acción sugerida** · Mostrar la ponderación del reactivo junto a su número.


### WEB-MPW-110

Categoría 4 · Media · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-110 · MPW-V3, §8.11, tabla 18, pp. 114–115; §2.1 tabla 3 p. 25

**Evidencia** · app/Http/Requests/GradebookSaveRequest.php:53-61

**Esperado** · Aplicar los patrones de requerido y límite superior al registrar calificaciones.

**Encontrado** · El requerido usa «No se enviaron datos de calificaciones.» y los límites usan «no puede exceder 100.»; ambos difieren de las familias documentadas.

**Acción sugerida** · Usar los patrones documentados para esas familias, conservando el nombre comprensible del campo.


### WEB-REP-004

Categoría 1 · Media · [Informe de origen](REP.md).

**Fuente** · RF-REP-007, D186; DOC-003

**Evidencia** · resources/views/student/grades/show.blade.php:54–95 y resources/views/student/grades/index.blade.php:112–128

**Esperado** · Mostrar rubros y ponderaciones con identificación expresa de cada calificación pendiente.

**Encontrado** · Se muestra la fórmula y las notas no nulas; las entradas de componentes que todavía faltan se omiten de la tarjeta.

**Acción sugerida** · Mostrar todos los rubros ponderados de cada periodo con su porcentaje y estado.


### WEB-REP-010

Categoría 5 · Media · [Informe de origen](REP.md).

**Fuente** · RF-REP-015; DOC-002 y DOC-003

**Evidencia** · app/Services/GradeSheetExportService.php:119–123,185–206,225–239,263–272

**Esperado** · El libro indica fecha y escala efectivamente utilizada y distingue cada valor pendiente de cero.

**Encontrado** · Se incluye la fecha, pero se anuncia escala entera y se imprimen dos decimales. Un componente de examen nulo puede sustituirse por el promedio de todos los exámenes del grupo sin identificarlo como sugerido.

**Acción sugerida** · Usar la escala acordada y representar el rubro como pendiente hasta disponer de su calificación del periodo.


### WEB-RNF-004

Categoría 1 · Media · [Informe de origen](RNF.md).

**Fuente** · RNF-USA-002, D237 y MPW §8.5 pp. 106–107

**Evidencia** · resources/views/admin/teachers/index.blade.php:103-117,321-337 y resources/views/components/form/select.blade.php:23-31

**Esperado** · Identificar los campos mediante etiquetas persistentes y asociadas.

**Encontrado** · El buscador depende del placeholder. Los selectores del ajuste muestran las opciones o su valor seleccionado, sin identificar el campo de paquete ni el de estado de pago mediante etiqueta.

**Acción sugerida** · Asociar etiquetas de campo al buscador y a ambos selectores, con identificadores únicos por fila.


### WEB-RNF-005

Categoría 1 · Media · [Informe de origen](RNF.md).

**Fuente** · RNF-ACC-001, D236 y MPW §8.10.5 pp. 113–114

**Evidencia** · resources/views/auth/login.blade.php:40-48 y resources/views/layouts/app.blade.php:588-590,607-609

**Esperado** · Operar desde teclado con foco visible.

**Encontrado** · Mostrar u ocultar la contraseña queda fuera del orden de tabulación. Los botones de cierre de avisos suprimen el contorno sin definir un indicador de foco equivalente en el botón.

**Acción sugerida** · Incluir el alternador en la secuencia y aplicar un estilo focus-visible a los controles de cierre.


### WEB-RNF-006

Categoría 1 · Media · [Informe de origen](RNF.md).

**Fuente** · RNF-ACC-002, D238

**Evidencia** · resources/views/admin/teachers/index.blade.php:221-246

**Esperado** · Distinguir un cobro fallido con texto o indicador adicional al color.

**Encontrado** · El último movimiento cambia el color de su referencia cuando falla. El texto de pago anterior pertenece a la suscripción y no expresa el estado de esa operación concreta.

**Acción sugerida** · Añadir un estado textual del movimiento junto a la referencia.


### WEB-SUS-001

Categoría 5 · Media · [Informe de origen](SUS.md).

**Fuente** · RF-SUS-001; MPW-V3 §6.9.1, tabla 8, pp. 70–71

**Evidencia** · resources/views/teacher/subscription/index.blade.php:290–296; app/Services/SubscriptionLimitService.php:300–311

**Esperado** · Comunicar el límite contratado sobre exámenes activos.

**Encontrado** · El catálogo dice «exámenes guardados» aunque el límite cuenta publicados.

**Acción sugerida** · Nombrar la unidad como exámenes activos en el catálogo.


### WEB-SUS-005

Categoría 1 · Media · [Informe de origen](SUS.md).

**Fuente** · RF-SUS-013; Excel D48

**Evidencia** · app/Services/TeacherSubscriptionService.php:151–155; resources/views/teacher/subscription/index.blade.php:388–417; routes/web.php:96–115

**Esperado** · Permitir consultar las operaciones del titular con fecha, concepto, importe y estado.

**Encontrado** · Los campos existen. Desde la undécima operación, las anteriores salen de la única lista sin acceso para el docente.

**Acción sugerida** · Paginar el historial del titular.


### WEB-SUS-006

Categoría 5 · Media · [Informe de origen](SUS.md).

**Fuente** · RF-SUS-006; Excel D41

**Evidencia** · app/Services/SubscriptionLimitService.php:538–559; app/Services/TeacherSubscriptionService.php:109–118; resources/views/teacher/subscription/index.blade.php:78–125

**Esperado** · Identificar nivel y estado de la suscripción, incluidos vencimiento o suspensión.

**Encontrado** · Un periodo pagado vencido se resuelve como nivel nulo y aparece como prueba gratuita activa. El paquete suspendido también pierde la etiqueta de nivel y su precio.

**Acción sugerida** · Presentar por separado el contrato consultado, su estado y las capacidades efectivas.


### WEB-SUS-008

Categoría 1 · Media · [Informe de origen](SUS.md).

**Fuente** · RF-SUS-015; Excel D50; DOC-004

**Evidencia** · resources/views/teacher/subscription/index.blade.php:409–419; app/Services/TeacherSubscriptionService.php:437–452,575,676

**Esperado** · Distinguir pagos pendientes, rechazados y operaciones sin cobro.

**Encontrado** · La vista distingue el cambio sin cargo, pero el caso residual dice «Rechazado». No existe representación propia del pendiente en el flujo revisado.

**Acción sugerida** · Acordar el estado pendiente por entorno y darle tratamiento explícito.


## Severidad baja

### DOC-028

Categoría 6 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §8.2, pp. 97–99, tabla 14, y §8.3.4, tabla 16, p. 103.

**Evidencia** · resources/views/components/page-header.blade.php:36 y resources/views/components/form/input.blade.php:15–24 localizan clases por tamaño. Los valores en conflicto quedan abiertos; las diferencias no ambiguas figuran en WEB-CAT-001/002.

**Esperado** · Un tamaño comprobable por elemento visual.

**Encontrado** · El cuerpo figura con 16 y 14 px; los subtítulos de tarjeta con 18 px y H3 de tarjeta con 16 px. El input pequeño dice «13 px (text-xs)», mientras que §8.2 define esa clase en 12 px.

**Acción sugerida** · Precisar si hay contextos distintos y qué tamaño o clase se acepta en cada uno.


### DOC-029

Categoría 6 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §§8.3.4–8.3.5, tablas 16–17, pp. 103–105 y §8.5, p. 106.

**Evidencia** · resources/views/components/form/input.blade.php:44 y resources/views/components/form/select.blade.php:28 permiten identificar el radio. No se elige uno de los dos valores del manual como criterio excluyente.

**Esperado** · Un radio aprobado para las cajas de texto y selectores.

**Encontrado** · Las tablas prescriben `rounded-lg` (8 px) y §8.5 prescribe `rounded-md` (6 px).

**Acción sugerida** · Acordar variante por control.


### DOC-030

Categoría 6 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §§8.1–8.1.1, tablas 12–13, pp. 93–96 y §8.4, p. 106.

**Evidencia** · La fila de catálogo de superficie alterna y MPW-096 identifican la implementación de tablas. La ausencia de un fondo alterno único en el manual condiciona el juicio de ese color.

**Esperado** · Un fondo alterno identificable para las filas de las tablas de interfaz.

**Encontrado** · Tabla 13 prescribe `#F8FAFC` y `even:bg-slate-50/60`. §8.4 usa `even:bg-acode-surface`, cuyo color documentado es `#E9F0F5`.

**Acción sugerida** · Identificar la superficie y opacidad autorizadas.


### DOC-031

Categoría 6 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §7.2, ilustración 13 p. 78, §§8.1.1, 8.3 y 8.7, pp. 95–96, 100–102, 108–109, ilustraciones 17 y 21, y §8.10.1, p. 112.

**Evidencia** · resources/views/layouts/app.blade.php:423–433 y resources/views/components/button.blade.php:25–40 permiten ubicar superficies y variantes reales. La discrepancia entre figuras y tokens queda registrada y aislada de desvíos inequívocos.

**Esperado** · Imágenes de referencia, estados y tokens coherentes.

**Encontrado** · Se invierten los colores de barra superior y lateral en la figura 13. La figura 17 usa contorno para el secundario y la 21 presenta confirmación verde en alerta de éxito; el texto prescribe secundario azul tecnológico y confirmación azul marino.

**Acción sugerida** · Acordar qué referencia visual aplica al revisar cada estado.


### DOC-032

Categoría 6 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §§8.10.3 y 8.10.5, p. 113.

**Evidencia** · resources/views/layouts/guest.blade.php:126–133 y resources/views/auth/login.blade.php:12–75 son contenedores distintos. La decisión debe identificar cuál se mide para el control de ancho.

**Esperado** · Poder identificar los contenedores al medir el ancho del login.

**Encontrado** · El layout de autenticación indica `max-w-lg` y la tarjeta de inicio de sesión `max-w-md`. Podrían corresponder a dos contenedores distintos.

**Acción sugerida** · Precisar la jerarquía antes de aplicar una medida de aceptación.


### DOC-033

Categoría 6 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MC-V3 §3.13, pp. 18–19. MBD-V3 índices, pp. 3–6; cuerpo, §8.4 p. 33, cap. 9 p. 33 y §10.3 p. 44.

**Evidencia** · Se conserva «Discrepancia localizada». Se emplearon los folios del cuerpo del MBD y no los destinos erróneos del índice.

**Esperado** · El índice debe remitir a la página real.

**Encontrado** · El índice del MBD sitúa esos apartados en pp. 34, 36 y 47. El PDF tiene 44 páginas.

**Acción sugerida** · Usar los folios verificados en este inventario y registrar la corrección en el control documental correspondiente.


### DOC-034

Categoría 6 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §§1.3.2, 5.4.2, 7.5.2 y 7.8.1, pp. 20, 57, 82, 84; ilustraciones 8 y 15.

**Evidencia** · composer.json:10 fija Laravel ^13.17 y las vistas del reloj usan alertas de usuario. La lámina antigua se considera ilustrativa hasta definir la autoridad del rótulo.

**Esperado** · Poder distinguir material ilustrativo de mensajes o versiones de aceptación actuales.

**Encontrado** · La ilustración 8 conserva «Laravel 11» frente a Laravel 13 del texto. La ilustración 15 menciona PostgreSQL en la alerta al usuario; el manual pide evitar jerga técnica cruda.

**Acción sugerida** · Precisar vigencia y destinatario de los ejemplos al revisar la interfaz.


### DOC-035

Categoría 6 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §8.13, pp. 120–122, tabla 20.

**Evidencia** · El catálogo 20 y MPW-114 identifican las declaraciones de procedencia. El corte no acredita redistribución de las ilustraciones del manual. Se conserva el carácter condicional.

**Esperado** · Conocer la licencia atribuible a cada ilustración que se vaya a redistribuir.

**Encontrado** · Se indican CC BY 4.0 y CC0 para el conjunto sin especificar licencia por figura ni precisar si se ofrecen ambas para cada una.

**Acción sugerida** · Identificar la licencia en el inventario de los recursos que se entreguen.


### DOC-041

Categoría 6 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §3.1.4, pp. 34–36, tabla 4; MPW-D13

**Evidencia** · .editorconfig:3–9 define codificación y espaciado. El manual utiliza dos unidades para el editor.

**Esperado** · Poder contrastar de manera reproducible el tamaño usado en el IDE.

**Encontrado** · 14 pt y 14 px tienen magnitudes distintas. El propio texto admite ambas. No se localizó un acuerdo de medición ni configuración del IDE de los participantes.

**Acción sugerida** · Mantener las dos opciones admitidas y acordar unidad si se desea una única toma de medida.


### WEB-CAT-001

Categoría 4 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §8.2, tabla 14, pp. 98–99

**Evidencia** · resources/views/components/page-header.blade.php:36; resources/views/components/section-header.blade.php:11; resources/views/landing.blade.php:120; resources/views/components/form/textarea.blade.php:23

**Esperado** · Mantener la escala de título hero 28/36, H1 24/32, H2 20/28 y código 13/20 px.

**Encontrado** · El hero llega a text-6xl y usa extrabold; H1 asciende a text-3xl; H2 usa text-base/sm:text-lg con negrita; el campo monoespaciado usa text-sm y fondo blanco.

**Acción sugerida** · Ajustar los componentes afectados a los tamaños, pesos y fondo prescritos según la función del texto.


### WEB-CAT-002

Categoría 4 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §§8.3.4–8.3.5, tablas 16 y 17, pp. 103–105

**Evidencia** · resources/views/components/form/input.blade.php:15–24,44; resources/views/components/form/select.blade.php:12–15,28; resources/views/components/button.blade.php:28; resources/views/components/badge.blade.php:7

**Esperado** · Aplicar espaciado vertical py-1, py-2 y py-3 por tamaño y la elevación normal de los controles de las filas correspondientes.

**Encontrado** · Las variantes reutilizables conservan alto y padding horizontal pero omiten el vertical por tamaño; los campos e insignias no declaran shadow-sm y el botón la aplica solo al pasar el puntero.

**Acción sugerida** · Completar las clases de cada dimensión y su elevación normal sin anticipar el radio aún abierto en DOC-029.


### WEB-MAN-001

Categoría 4 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MC-V3 §§5.3–5.4, pp. 26–27, MC-036

**Evidencia** · docs/adrs/README.md:5 y docs/README.md:15–18; lista maestra A3 y nombres oficiales V3

**Esperado** · Distinguir la normativa vigente al consultar decisiones del proyecto.

**Encontrado** · El índice remite como normativa de referencia a V2 y 226 requisitos. Las huellas y el catálogo entregados corresponden a V3 y 238.

**Acción sugerida** · Identificar el corte de referencia y distinguir las decisiones históricas de la guía vigente.


### WEB-MAN-003

Categoría 4 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §§1.2, 3.8 y 4.1–4.2, pp. 17–18, 43–50; DOC-016

**Evidencia** · app/Http/Requests/AttemptTakeRequest.php:5–39, app/Services/TeacherSubscriptionService.php:25–42, resources/views/layouts/exam-runner.blade.php:1, resources/views/legal/privacy-notice.blade.php:1

**Esperado** · Documentar autoría, código trazable y finalidad en los archivos operativos y estructurar prólogos, importaciones y métodos conforme al MPW.

**Encontrado** · La solicitud de inicio omite el RF que sí existe y datos del prólogo. Hay métodos sin DocBlock, imports fuera del orden prescrito y plantillas sin prólogo o con la variante corta en un layout.

**Acción sugerida** · Completar la cabecera de cada flujo identificado y resolver con DOC-016 la excepción para archivos auxiliares.


### WEB-MAN-004

Categoría 4 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §§3.1, 3.3.3, 5.1 y 5.3, pp. 32–33, 38, 51–53, 55–56

**Evidencia** · app/Services/TeacherSubscriptionService.php:77–81,561, app/Services/QuestionBankService.php:1531, app/Services/GroupEnrollmentService.php:45–51, app/Http/Controllers/EvaluationPeriodController.php:165, app/Models/Question.php:94 y app/Repositories/Eloquent/EloquentExamRepository.php:104

**Esperado** · Conservar nomenclatura inglesa, tipado y formato del MPW con líneas de hasta 160 caracteres.

**Encontrado** · Se localizaron alias mixto, nombre genérico, apertura de método en la línea de la firma, argumentos sin tipo, condición larga y líneas que exceden el máximo.

**Acción sugerida** · Homologar las formas semánticamente equivalentes y documentar cualquier excepción que exija la firma heredada.


### WEB-MAN-005

Categoría 4 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-V3 §§3.2, 3.5–3.6, pp. 37, 39–40; DOC-014

**Evidencia** · app/Http/Controllers/Auth/LoginController.php:48,81 y resources/views/questions/_partials/form-fields.blade.php:1

**Esperado** · Usar prefijo institucional en claves de sesión propias y kebab-case en nombres Blade.

**Encontrado** · La clave de sesión del segundo paso prescinde del prefijo. El directorio de parciales usa un guion bajo inicial. El desacuerdo de canales se conserva en DOC-014.

**Acción sugerida** · Alinear nombres y referencias y diferenciar en el manual la excepción de eventos/canales.


### WEB-MAN-010

Categoría 7 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MD-V3 §5.1, pp. 29–30; MPW-V3 §6.3.2, pp. 61–63; catálogo RF-AUT, RF-BAN y RF-SUS

**Evidencia** · routes/web.php:138,235, resources/views/teacher/profile/index.blade.php:74–85 y resources/views/teacher/subscription/index.blade.php:345

**Esperado** · Poder rastrear cada opción al RF, manual o actividad de desarrollo autorizada.

**Encontrado** · Sin sustento localizado en las cinco fuentes para exigir específicamente duplicar un reactivo, cambiar voluntariamente la contraseña en perfil o contratar por WhatsApp. El último canal sí figura en el ADR-045. El MD admite actividades sin RF directo.

**Acción sugerida** · Vincular los controles a sus actividades o acuerdos y consignar si se aceptan como extensiones.


### WEB-MBD-003

Categoría 4 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MBD-008/014/015/017 · MBD-V3 §§3.1, 3.4–3.5, pp. 13,17–18

**Evidencia** · database/migrations/2026_10_26_170100_bd01_2026_019_create_acs_student_answers_table.php:48,51–52, database/migrations/2026_10_26_180100_bd01_2026_021_create_acs_student_period_grades_table.php:43, database/migrations/2026_11_17_000001_create_teacher_verification_tables.php:53,85, database/migrations/2026_11_19_000001_create_jobs_and_ai_credit_support_tables.php:67 y database/migrations/2026_11_22_000003_bd01_2026_034_add_renewal_columns_to_acs_teacher_subscriptions_table.php:36

**Esperado** · Usar inglés, FKs con sufijo _id, booleanos is_/has_ y nombres de índice con tabla, columnas y tipo.

**Encontrado** · Hay campos teacher_recoteje_*, FKs created_by/reviewed_by y booleanos skip_ai_reevaluation_warning o auto_renew. El índice de calificación por periodo omite las columnas en su nombre explícito. DOC-012 trata aparte require_webcam.

**Acción sugerida** · Registrar los nombres no conformes y tramitar equivalencias o cambios sin perder su trazabilidad.


### WEB-MBD-006

Categoría 4 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MBD-040/043 · MBD-V3 §§5.1 y 5.3, pp. 25–26

**Evidencia** · database/migrations/2026_11_04_000001_add_soft_deletes_to_acs_questions_table.php:9–16, database/migrations/2026_11_12_000001_create_acs_subjects_table_and_normalize_subject_links.php:9–17, database/migrations/2026_11_12_000002_add_paraphrase_snapshot_columns.php:9–18, database/migrations/2026_11_17_000001_create_teacher_verification_tables.php:9–17, database/migrations/2026_11_18_000001_create_payment_transactions_and_onboarding_column.php:9–20 y database/migrations/2026_11_19_000001_create_jobs_and_ai_credit_support_tables.php:9–19

**Esperado** · Incorporar folio BD-01 al nombre y al prólogo de cada migración e identificar los requisitos relacionados.

**Encontrado** · Seis archivos omiten el consecutivo en sus nombres; por ejemplo, el cambio de pagos cita ADR-058 sin identificar folio BD-01 ni RF/RNF. La referencia del archivo no acredita el formato firmado.

**Acción sugerida** · Conciliar folios y requisitos con los registros aprobados, preservando la historia de migraciones según §5.1.


### WEB-MPW-064

Categoría 4 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-064 · MPW-V3, §§6.10–6.10.1, p. 72 y §6.10.5 p. 75

**Evidencia** · app/Contracts/AiEvaluatorContract.php:32-41 y app/Services/Ai/GeminiAiEvaluator.php:240-246

**Esperado** · Declarar los cinco campos del contrato de respuesta como obligatorios.

**Encontrado** · Los drivers devuelven cinco campos, pero el DocBlock del contrato declara opcionales is_fallback y model.

**Acción sugerida** · Alinear la descripción tipada del contrato con los campos que exige el manual.


### WEB-MPW-073

Categoría 4 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-073 · MPW-V3, §§7.4–7.4.2, pp. 79–80

**Evidencia** · resources/views/exams/take-exam.blade.php:183-187

**Esperado** · Expresar el estado inicial del contenido mediante las clases y componentes permitidos.

**Encontrado** · Los mensajes de guardado llevan style directo además de x-show.

**Acción sugerida** · Aplicar el mecanismo de ocultación inicial institucional sin estilo HTML directo.


### WEB-MPW-113

Categoría 4 · Baja · [Informe de origen](manuales_transversales.md).

**Fuente** · MPW-113 · MPW-V3, §8.12, tabla 19, pp. 118–119

**Evidencia** · resources/views/components/icon.blade.php:86-124 y resources/views/components/nav-icon.blade.php:1-18

**Esperado** · Reservar el alias camera para el símbolo video-camera del catálogo.

**Encontrado** · La tabla de alias incluye reloj, QR, llave, candado, estados y reporte. Falta la entrada camera. La cámara está pospuesta y no se observó un fallo en una pantalla activa.

**Acción sugerida** · Registrar el alias reservado sin activar funciones de cámara.
