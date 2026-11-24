# Catálogos de consulta del Manual de Programación Web

Estos catálogos amplían el inventario con valores, mensajes y ejemplos que pueden comprobarse individualmente. Cada fila indica la página física de la tabla original. Se han unido cortes tipográficos de palabras y filas entre páginas. Los valores de contraste y licencias se presentan tal como los declara el manual. El catálogo de iconos remite al PDF para sus dibujos.

Fuente: [MPW-V3](</home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Programación Web (MPW-V3).pdf>).

## Tabla 7 · Validación por Form Request

MPW-V3 §6.3.2, pp. 61–63.

| Clase Form Request (app/Http/Requests) | Módulo / Requerimiento | Reglas y Mensajes Normativos Clave (Tabla 18) | Pág. |
| --- | --- | --- | --- |
| RegisterTeacherRequest / RegisterStudentRequest | RF-AUT-001 y RF-AUT-002 / ADR-057 | first_name, last_name, email único en acs_users, phone 10 dígitos, password min:8, privacy_notice_id y acreditación híbrida docente. | 61 |
| LoginRequest / VerifyTwoFactorRequest / ResendTwoFactorRequest | RF-AUT-003 a RF-AUT-007 / ADR-010/035 | code: required&#124;digits:6; user_id opcional en API v1 (localización segura con throttle:20,1 y reenvío 60 s). | 61 |
| QuestionStoreRequest / QuestionUpdateRequest / QuestionBatchStoreRequest | RF-BAN-001..010 / ADR-026/055 | Valida los 4 tipos polimórficos usando NormalizesQuestionInputs y exclude_unless; hasta 30 reactivos por lote y 3 paráfrasis iniciales. | 61–62 |
| QuestionImportRequest / SubjectMergeRequest | RF-BAN-015 / ADR-053/055 | Importación CSV/TXT/XLSX con protección contra inyección de fórmulas (CWE-1236) y fusión transaccional de materias. | 62 |
| ExamStoreRequest / ExamUpdateRequest / ExamConfigurationUpdateRequest | RF-EXA-001..016 / ADR-056 | title, grading_scale (0-10&#124;0-100), result_visibility_mode (4 modos), max_allowed_incidents y ventana starts_at/ends_at. | 62 |
| ExamSectionStoreRequest / ExamManualQuestionsStoreRequest | RF-EXA-001 a RF-EXA-008 / ADR-056 | Armado dual: secciones por tipo/materia/cantidad o selección manual directa con control de linajes Copy-On-Write. | 62 |
| AttemptAnswerWebRequest / AttemptAnswerApiRequest / AttemptIncidentStoreRequest | RF-EXA-035 a RF-EXA-041 / ADR-012/042 | Valida exam_question_id, payload por tipo de reactivo e incident_type en lista blanca (tab_switch, focus_lost, etc.). | 62 |
| TeacherReviewStoreRequest / RequestAiEvaluationRequest | RF-EVA-009 / RF-IAR-001..014 / ADR-059 | reviews.*.score dentro del peso máximo; is_reevaluation y dismiss_warning booleanos con mensajes en español sin punto final. | 62–63 |
| EvaluationPeriodStoreRequest / GradebookSaveRequest | RF-CAL-001..004 / ADR-038 | Fórmula ponderada del periodo y captura manual de tareas/proyectos en rango 0..100. | 63 |
| ProcessSubscriptionCheckoutRequest / RedeemInvitationCodeRequest | RF-SUS-001..015 / ADR-057/058/060 | Checkout simulado (tarjeta Luhn o SPEI con spei_sender_bank.in y onboarding_overlay.boolean) y canje atómico de invitación. | 63 |

## Tabla 8 · Cuotas y precios de referencia

MPW-V3 §6.9.1, pp. 70–71.

| Nivel de Paquete SaaS | Grupos | Reactivos | Alumnos / Exámenes | Créditos IA y Precio | Pág. |
| --- | --- | --- | --- | --- | --- |
| Prueba Gratuita (Freemium) | Hasta 1 (histórico) | Hasta 50 (históricos) | 40 alumnos / 1 examen histórico | 25 evaluaciones IA (histórico) · $0 MXN | 70 |
| Paquete Básico (Docente) | Hasta 3 grupos | Hasta 5,000 reactivos | 40 concurrentes / 5 exámenes activos | 200 créditos IA / ciclo · $299 MXN/mes | 70 |
| Paquete Avanzado (Colegiado) | Hasta 10 grupos | Hasta 10,000 reactivos | 120 concurrentes / 25 exámenes activos | 600 créditos IA / ciclo · $599 MXN/mes | 71 |
| Paquete Institucional / Pro | Ilimitados (null) | Ilimitados (null) | 500 concurrentes / Exámenes ilimitados | Créditos IA ilimitados · $999 MXN/mes | 71 |

## Tabla 9 · Fórmulas y escalas

MPW-V3 §6.12, pp. 76–77.

La tabla reproduce código en dos páginas. Los valores siguientes corresponden a ese fragmento.

| Operación del ejemplo del manual | Expresión o comportamiento | Pág. |
|---|---|---:|
| Calcular un periodo | Se separan términos por `+` y componentes y pesos por `*`. Peso implícito `1`. Componente ausente `0.0`. `bcmul` y `bcadd` a 6 decimales y redondeo final a 2. | 76 |
| Normalizar puntos | Con peso total positivo, `(rawPointsEarned / totalExamWeight) * 100.0`. Para peso total no positivo devuelve base cero. | 77 |
| Convertir escala | Escala `0-10` divide la base entre 10 y redondea a 2 decimales. Para la otra escala el ejemplo redondea la base a 2 decimales. | 77 |

## Tabla 10 · Textos de acción, confirmaciones y ejemplos oficiales

MPW-V3 §7.8.2, pp. 84–88.

| Verbo de Acción | Propósito Operativo en la Plataforma | Contexto de Uso Recomendado | Confirmación Previa | Ejemplo Textual Oficial en UI | Pág. |
| --- | --- | --- | --- | --- | --- |
| Abrir | Iniciar visualización de un examen, reactivo o modal informativo. | Botones en listados, tarjetas de examen. | No requiere | "Abrir examen", "Abrir reactivo" | 84–85 |
| Eliminar | Borrado definitivo de un registro o entidad del sistema. | Acciones de fila en tablas, botones destructivos. | Obligatoria (Modal SweetAlert2 Rojo) | "Eliminar reactivo", "Eliminar grupo" | 85 |
| Guardar | Persistir datos capturados en borrador o banco de preguntas. | Botón primario en formularios de captura. | No requiere | "Guardar cambios", "Guardar reactivo" | 85 |
| Crear / Agregar | Dar de alta un nuevo registro o módulo en la base de datos. | Botón principal en encabezados de catálogo. | No requiere | "Crear examen", "Agregar grupo escolar" | 85 |
| Actualizar | Sobrescribir datos modificados sobre un registro existente. | Formularios de edición de perfil o examen. | No requiere | "Actualizar usuario", "Actualizar reactivo" | 85 |
| Enviar | Remitir formalmente un examen resuelto o solicitud final. | Pantalla final de examen contrarreloj. | Obligatoria (Modal de confirmación) | "Enviar respuestas", "Enviar solicitud" | 85 |
| Editar | Habilitar el modo de modificación de datos de un registro. | Icono o botón secundario en filas de tablas. | No requiere | "Editar reactivo", "Editar grupo" | 86 |
| Cancelar | Detener o abortar un flujo interactivo sin guardar cambios. | Botón secundario en modales y formularios. | No requiere | "Cancelar operación", "Cancelar cambios" | 86 |
| Aceptar | Confirmar el entendimiento de un aviso o políticas legales. | Modal de Términos de Servicio y alertas de éxito. | No requiere | "Aceptar términos", "Aceptar y continuar" | 86 |
| Cerrar | Ocultar una ventana emergente, modal o panel lateral. | Botón secundario al pie de modales y botón 'X'. | No requiere | "Cerrar ventana", "Cerrar panel" | 86 |
| Descargar | Iniciar la exportación de archivos ofimáticos (PDF o Excel). | Módulo de calificaciones y reportes analíticos. | No requiere | "Descargar reporte Excel", "Descargar PDF" | 86 |
| Reintentar | Reanudar un intento fallido por latencia o caída de red. | Alertas SweetAlert2 ante desconexión de socket. | No requiere | "Reintentar conexión", "Reintentar envío" | 87 |
| Volver | Regresar a la pantalla o listado anterior sin perder datos. | Barra de navegación de detalle de módulo. | No requiere | "Volver al listado", "Volver al inicio" | 87 |
| Continuar | Avanzar a la siguiente etapa de un asistente o formulario. | Flujos guiados (wizards) y registro docente. | No requiere | "Continuar al paso 2", "Continuar examen" | 87 |
| Buscar | Ejecutar consulta de filtrado predictivo en tiempo real. | Barras de búsqueda en catálogos y bancos. | No requiere | "Buscar reactivo", "Buscar alumno" | 87 |
| Limpiar | Restablecer filtros de búsqueda a su estado inicial. | Botón secundario dentro de la zona de filtros. | No requiere | "Limpiar filtros", "Restablecer búsqueda" | 87 |
| Copiar | Transferir al portapapeles un folio, código o enlace URL. | Modal de código QR de grupo y folios de examen. | No requiere (Despliega Toast verde) | "Copiar folio", "Copiar enlace" | 87–88 |
| Ver más / Detalles | Desplegar información técnica extendida o colapsada. | Tarjetas de analítica y paneles con acordeón. | No requiere | "Ver más", "Ver detalles completos" | 88 |

## Tabla 11 · Mensajes aprobados y prohibidos

MPW-V3 §7.8.2, pp. 89–90.

| Situación en la Plataforma | Tono de Voz Requerido | Mensaje Oficial Aprobado | Mensaje Prohibido | Pág. |
| --- | --- | --- | --- | --- |
| Advertencia pre-examen | Firme y preventiva | Este examen es contrarreloj. Una vez iniciado, el cronómetro no se detendrá. | Ten cuidado porque si te sales repruebas. | 89 |
| Agotamiento de tiempo | Informativo y neutro | El tiempo asignado a este reactivo ha concluido. Tu avance fue guardado. | ¡Se te acabó el tiempo! No alcanzaste a responder. | 89 |
| Error de validación | Orientativo | Por favor selecciona al menos una opción válida para continuar. | Error en el formulario. Datos incorrectos. | 89 |
| Evaluación de IA | Transparente | Tu respuesta de código fue analizada por el evaluador asistido. Pendiente de recotejo docente. | La máquina te calificó con 8 de 10. | 89 |
| Cierre de grupo | Institucional | El ciclo académico para este grupo ha sido finalizado por el profesor. | Grupo borrado. Ya no puedes entrar. | 90 |

## Tabla 12 · Tokens de color y contrastes declarados

MPW-V3 §8.1, pp. 93–94.

| Nombre Institucional | Código HEX | Token Tailwind | Rol en Interfaz | Contraste WCAG | Pág. |
| --- | --- | --- | --- | --- | --- |
| Azul Marino Profundo / Dark | #142B4A / #0E1F36 | acode-primary / -dark | Sidebar, encabezados, botones primarios y títulos H1–H3. | 13.2:1 (AAA) | 93 |
| Azul Tecnológico / Dark | #2563A6 / #1D4F85 | acode-tech / -dark | Botones secundarios, enlaces, foco activo e indicadores. | 5.8:1 (AA) | 93 |
| Verde Azulado / Teal Dark | #16A6A1 / #11807C | acode-accent / -dark | Insignias de éxito, aprobado, créditos y confirmaciones. | 4.6:1 (AA) | 93–94 |
| Ámbar Cálido / Dark | #F4B942 / #D97706 | acode-warning / -dark | Advertencias SweetAlert2, estados pendientes y alertas. | 8.9:1 s/oscuro | 94 |
| Gris Superficie / Tinta | #E9F0F5 / #0F172A | acode-surface / acode-ink | Fondo general del body y tipografía de máximo contraste. | 15.1:1 (AAA) | 94 |
| Rojo Alerta / Dark | #DC2626 / #B91C1C | acode-danger / -dark | Acciones destructivas, cronómetro &lt;=10 s e incidentes. | 5.1:1 (AA) | 94 |
| Blanco Puro | #FFFFFF | white | Superficie de tarjetas, topbar institucional, inputs y modales. | N/A (Fondo base) | 94 |

## Tabla 13 · Fondos y superficies

MPW-V3 §8.1.1, pp. 95–96.

| Superficie / Componente | Código HEX | Clase Tailwind Real | Rol Funcional y Contraste | Pág. |
| --- | --- | --- | --- | --- |
| Fondo Global de Aplicación | #E9F0F5 | bg-acode-surface | Superficie neutra exterior; reduce fatiga óptica. | 95 |
| Tarjetas y Paneles (Cards) | #FFFFFF | bg-white | Contenedor elevado (&lt;x-card&gt;) para formularios, KPIs y listados. | 95 |
| Barra Superior (Navbar) | #FFFFFF | bg-white border-b border-slate-200 | Cabecera fija superior con título de módulo y menú de perfil (ADR-024). | 95 |
| Barra Lateral (Sidebar) | #142B4A | bg-acode-primary | Menú lateral retráctil (256 px / 80 px zero-flicker) para 3 roles. | 95 |
| Modales y Diálogos | #FFFFFF + Backdrop | bg-white / bg-slate-900/60 | Superficie focal con backdrop-blur-sm y transiciones x-transition. | 95–96 |
| Filas de Tabla (Cebra) | #FFFFFF / #F8FAFC | bg-white / even:bg-slate-50/60 | Alternancia sutil para lectura de listados extensos. | 96 |
| Fila de Tabla en Hover | #EFF6FF | hover:bg-blue-50/70 | Retroalimentación interactiva al desplazar el puntero. | 96 |
| Entorno de Examen | #F8FAFC | bg-slate-50 | Superficie limpia Distraction-Free en layouts/exam-runner.blade.php. | 96 |

## Tabla 14 · Escala tipográfica

MPW-V3 §8.2, pp. 98–99.

| Nivel Tipográfico | Familia Tipográfica | Tamaño (px / pt / rem) | Peso (Weight) | Altura de Línea | Color Oficial en UI | Pág. |
| --- | --- | --- | --- | --- | --- | --- |
| Display / Título Hero | Inter, Sans-serif | 28 px (21 pt) / 1.75 rem | Negrita (700) | 36 px (1.30) | #142B4A (text-acode-primary) | 98 |
| Encabezado H1 (Módulo) | Inter, Sans-serif | 24 px (18 pt) / 1.50 rem | Negrita (700) | 32 px (1.33) | #142B4A (text-acode-primary) | 98 |
| Encabezado H2 (Sección) | Inter, Sans-serif | 20 px (15 pt) / 1.25 rem | Seminegrita (600) | 28 px (1.40) | #142B4A (text-acode-primary) | 98 |
| Encabezado H3 (Tarjeta) | Inter, Sans-serif | 16 px (12 pt) / 1.00 rem | Seminegrita (600) | 24 px (1.50) | #142B4A (text-acode-primary) | 98 |
| Cuerpo Regular (Body) | Inter, Sans-serif | 14 px (10.5 pt) / 0.875 rem | Regular (400) | 20 px (1.43) | #334155 (text-slate-700) | 98 |
| Cuerpo Pequeño (Small) | Inter, Sans-serif | 12 px (9 pt) / 0.750 rem | Regular (400) | 16 px (1.33) | #64748B (text-slate-500) | 99 |
| Etiquetas de Formulario | Inter, Sans-serif | 13 px (10 pt) / 0.812 rem | Medio (500) | 18 px (1.38) | #1E293B (text-slate-800) | 99 |
| Botones Interactivos | Inter, Sans-serif | 14 px (10.5 pt) / 0.875 rem | Seminegrita (600) | 20 px (1.43) | #FFFFFF / #142B4A | 99 |
| Código en Pantalla | Fira Code, Mono | 13 px (10 pt) / 0.812 rem | Regular (400) | 20 px (1.54) | #0F172A sobre #F1F5F9 | 99 |

## Tabla 15 · Variantes y dimensiones de botones

MPW-V3 §8.3.3, pp. 101–102.

| Variante / Rol | Clase Fondo / Borde | Medida Pequeña (sm) | Medida Estándar (md) | Medida Grande (lg) | Pág. |
| --- | --- | --- | --- | --- | --- |
| Primario (Acción Principal) | bg-acode-primary (#142B4A) | h-8 px-3 text-xs | h-10 px-4 text-sm | h-12 px-6 text-base | 101 |
| Secundario (Exploración) | bg-acode-tech (#2563A6) | h-8 px-3 text-xs | h-10 px-4 text-sm | h-12 px-6 text-base | 101 |
| Éxito (Guardar / Aprobar) | bg-acode-accent (#16A6A1) | h-8 px-3 text-xs | h-10 px-4 text-sm | h-12 px-6 text-base | 101 |
| Advertencia (Atención) | bg-acode-warning (#F4B942) | h-8 px-3 text-xs text-slate-900 | h-10 px-4 text-sm text-slate-900 | h-12 px-6 text-base text-slate-900 | 102 |
| Peligro (Destructivo) | bg-acode-danger (#DC2626) | h-8 px-3 text-xs | h-10 px-4 text-sm | h-12 px-6 text-base | 102 |
| Neutro / Cancelar | border border-slate-300 text-slate-700 | h-8 px-3 text-xs | h-10 px-4 text-sm | h-12 px-6 text-base | 102 |

## Tabla 16 · Dimensiones de controles de captura

MPW-V3 §8.3.4, p. 103.

| Variante / Escala | Altura Oficial (px / Tailwind) | Padding Interior | Tamaño de Texto | Esquinas | Uso Obligatorio en la Plataforma | Pág. |
| --- | --- | --- | --- | --- | --- | --- |
| Pequeño (sm) | 32 px (h-8) | px-3 py-1 | 13 px (text-xs) | rounded-lg (8 px) | Filtros rápidos de tablas y selectores de paginación. | 103 |
| Estándar (md) | 40 px (h-10) | px-3.5 py-2 | 14 px (text-sm) | rounded-lg (8 px) | Formularios generales de reactivos, perfil y login. | 103 |
| Grande (lg) | 48 px (h-12) | px-4 py-3 | 16 px (text-base) | rounded-lg (8 px) | Buscador global superior y captura de folios de examen. | 103 |

## Tabla 17 · Esquinas y elevación

MPW-V3 §8.3.5, pp. 104–105.

| Token Tailwind | Radio de Esquinas | Nivel de Elevación | Componentes Oficiales de Aplicación | Pág. |
| --- | --- | --- | --- | --- |
| rounded-md | 6 px | none | Casillas de verificación (checkboxes), switches e indicadores compactos. | 104 |
| rounded-lg | 8 px | shadow-sm | Estándar oficial para botones, cajas de texto (inputs), selects y menús dropdown. | 104 |
| rounded-xl | 12 px | shadow-sm a shadow-md | Tarjetas de datos (cards), contenedores principales y paneles de control. | 104 |
| rounded-2xl | 16 px | shadow-xl | Ventanas modales, diálogos emergentes SweetAlert2 y tarjetas flotantes de examen. | 104–105 |
| rounded-full | 9999 px | shadow-sm | Avatares de usuario circulares, insignias (badges) y botones redondos flotantes. | 105 |

## Tabla 18 · Plantillas de validación y ejemplos

MPW-V3 §8.11, pp. 115–116.

| Categoría de Validación | Plantilla Oficial Obligatoria | Regla de Estilo y Sintaxis | Ejemplos Oficiales de Aplicación | Pág. |
| --- | --- | --- | --- | --- |
| 1. Campos Vacíos | "El campo [nombre del campo] es obligatorio" | Inicia con 'El campo', nombre en minúsculas, finaliza en 'es obligatorio', sin punto final. | "El campo correo electrónico es obligatorio", "El campo contraseña es obligatorio" | 115 |
| 2. Formato Inválido | "El [nombre del campo] no tiene un formato válido" | Inicia con 'El', especifica la regla de estructura incumplida sin tecnicismos. | "El correo electrónico no tiene un formato válido", "El teléfono debe contener 10 dígitos" | 115 |
| 3. Longitud de Caracteres | "El [nombre del campo] debe tener al menos [n] caracteres" / "no debe exceder los [n] caracteres" | Define con precisión la cota mínima o máxima permitida. | "La contraseña debe tener al menos 8 caracteres", "El título no debe exceder los 120 caracteres" | 115 |
| 4. Selección Inválida | "La opción seleccionada en [nombre del campo] no es válida" | Aplica a selectores desplegables, radios y checkboxes manipulados. | "La opción seleccionada en tipo de reactivo no es válida", "La opción en grupo no es válida" | 115 |
| 5. Coincidencia entre Campos | "Los campos [campo1] y [campo2] deben coincidir" | Aplica a confirmaciones de seguridad (contraseñas, correos). | "Los campos contraseña y confirmar contraseña deben coincidir" | 116 |
| 6. Límites Numéricos | "El [nombre del campo] debe ser mayor que [valor]" / "menor o igual que [valor]" | Aplica a ponderaciones de reactivos, tiempos en minutos y cupos. | "La ponderación debe ser mayor que 0", "El tiempo límite debe ser menor o igual que 180" | 116 |
| 7. Mensajes Globales de Error | "[Mensaje claro relacionado con el proceso general]" | Redacción asertiva y directa que no exponga detalles sensibles de base de datos. | "Las credenciales ingresadas no coinciden con nuestros registros", "No se pudo completar la operación intente nuevamente" | 116 |

## Tabla 19 · Alias, clases y uso de los iconos

MPW-V3 §8.12, pp. 117–119.

| Categoría | Alias | Clase Icono | Descripción / Uso en Plataforma | Pág. |
| --- | --- | --- | --- | --- |
| Usuarios y Roles | userTie | academic-cap | Docente / Maestro (gestión de grupos y exámenes) | 117 |
| Usuarios y Roles | user | user | Estudiante / Alumno (cuenta individual y presentación) | 117 |
| Usuarios y Roles | userGroup | users | Grupos escolares y matriculación de alumnos | 117 |
| Usuarios y Roles | shield | shield-check | Súper Administrador (gestión de paquetes y suspensión) | 117 |
| Acciones | add | plus-circle | Crear nuevo examen, reactivo o grupo escolar | 117 |
| Acciones | edit | pencil-square | Modificar preguntas, reactivos o ponderaciones | 118 |
| Acciones | delete | trash | Eliminar reactivo o cerrar grupo escolar | 118 |
| Acciones | view | eye | Consultar respuestas, folio o detalles de examen | 118 |
| Acciones | save | floppy-disk | Guardar reactivo en el banco o notas manuales | 118 |
| Acciones | search | magnifying-glass | Búsqueda predictiva con debounce de 300 ms | 118 |
| Acciones | filter | funnel | Filtrado múltiple de reactivos (materia, grupo, fecha) | 118 |
| Acciones | downloadReport | arrow-down-tray | Exportación de calificaciones a Excel (RF-REP-014) | 118 |
| Exámenes &amp; Tiempo | clock | clock | Cronómetro regresivo contrarreloj (RF-EXA-042) | 118 |
| Exámenes &amp; Tiempo | qrCode | qr-code | Folio y código QR para unirse a grupo (RF-GRU-005 y RF-GRU-006) | 118 |
| Exámenes &amp; Tiempo | camera | video-camera | Supervisión por cámara web anti-suplantación (alcance pospuesto, ADR-043) | 119 |
| Seguridad &amp; Acceso | key | key | Verificación de dos pasos 2FA (RF-AUT-005) | 119 |
| Seguridad &amp; Acceso | lock | lock | Examen cerrado por horario o tiempo finalizado | 119 |
| Estados del Sistema | success | check-circle | Examen aprobado o guardado exitoso en el sistema | 119 |
| Estados del Sistema | warning | triangle-exclamation | Alerta de cronómetro por expirar o pérdida de foco | 119 |
| Estados del Sistema | error | x-circle | Examen reprobado, fallo de conexión o incidencia | 119 |
| Reportes &amp; Métricas | reportBar | chart-bar | Gráficas de rendimiento docente con Chart.js (RF-REP-010 a RF-REP-012) | 119 |

## Tabla 20 · Procedencia y licencias declaradas

MPW-V3 §8.13, pp. 120–122.

| Recurso declarado | Autor o titular según el manual | Licencia declarada | Uso declarado | Pág. |
|---|---|---|---|---:|
| Heroicons v2 | Tailwind Labs Inc. | MIT | Componentes SVG en línea `<x-icon>` y `<x-nav-icon>`. | 120–121 |
| Inter / Fira Code | Rasmus Andersson / Nikita Prokopov | SIL Open Font License 1.1 | Interfaz y editores de código. | 121 |
| Ilustraciones y diagramas A-Code | A-Code Solutions | CC BY 4.0 / CC0 | 25 ilustraciones técnicas y de interfaz del manual. | 121 |
| Chart.js 4.4 | Chart.js Community | MIT | Gráficos de desempeño y recoteje. | 121 |
| SweetAlert2 v11 | SweetAlert2 Contributors | MIT | Alertas y confirmaciones. | 121–122 |

El texto de §8.13, p. 120, incluye también Tailwind CSS y Alpine.js entre las librerías MIT.
