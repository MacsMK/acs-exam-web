<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        PrivacySeeder.php
 * Ubicación:      database/seeders/PrivacySeeder.php
 * Requerimiento:  RW-07, RF-AUT-003, RF-AUT-011, LFPDPPP Arts. 15, 16, 36
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.1.0
 * Fecha Creación: 09/11/2026
 * Descripción:    Aviso de Privacidad Integral formal y vinculante conforme a la
 *                 Ley Federal de Protección de Datos Personales en Posesión de
 *                 los Particulares (LFPDPPP - México). Conserva la versión 2.0
 *                 histórica y publica la versión 2.1 con cláusulas de
 *                 acreditación docente y purga automática de comprobantes.
 * ============================================================================
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\PrivacyNotice;

/**
 * Semilla oficial del Aviso de Privacidad Integral (versiones 2.0 y 2.1).
 *
 * @package Database\Seeders
 */
class PrivacySeeder extends Seeder
{
    /**
     * Inserta o actualiza las versiones oficiales del Aviso de Privacidad.
     *
     * @return void
     */
    public function run(): void
    {
        $legalNoticeV20 = <<<TEXT
AVISO DE PRIVACIDAD INTEGRAL — A-CODE SOLUTIONS

1. IDENTIDAD Y DOMICILIO DEL RESPONSABLE
A-Code Solutions (en adelante, "la Empresa"), con domicilio convencional para oír y recibir notificaciones en territorio mexicano y portal web oficial, es responsable del uso, tratamiento y protección de sus datos personales, en estricto apego a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP), su Reglamento y los Lineamientos del Aviso de Privacidad emitidos por el INAI.

2. DATOS PERSONALES RECABADOS
Para prestar los servicios de la Plataforma Evaluadora y Aplicadora de Exámenes en línea, recabamos las siguientes categorías de datos personales:
a) Datos de Identificación y Contacto: Nombre completo, apellidos, correo electrónico personal o institucional y número de teléfono móvil (para notificaciones y autenticación de dos factores).
b) Datos Académicos y de Evaluación: Asignaturas matriculadas, grupos escolares, reactivos capturados, respuestas emitidas en exámenes contrarreloj, puntuaciones asignadas, promedios parciales y retroalimentación docente.
c) Datos Técnicos y de Navegación: Dirección IP, registros de fecha y hora de sesión, modelo y sistema operativo del dispositivo, y métricas de supervisión de entorno de examen (pérdida de foco de ventana o cambio de pestaña, para garantizar la integridad académica).
Principio de No Recabación de Datos Sensibles: La plataforma no solicita ni almacena datos personales sensibles de origen racial, ideológico, de salud ni biométricos faciales.

3. FINALIDADES DEL TRATAMIENTO
Finalidades Primarias (necesarias para el servicio):
- Creación, administración y autenticación segura de cuentas de docentes, estudiantes y administradores.
- Envío de códigos temporales de seguridad para autenticación de dos pasos (2FA).
- Aplicación, control de tiempo regresivo y calificación de evaluaciones académicas.
- Prevención de conductas fraudulentas o suplantación durante la resolución de pruebas.
- Consulta y exportación de sábanas oficiales de calificaciones para docentes.

Finalidades Secundarias (no necesarias para el servicio principal):
- Envío de notificaciones informativas sobre actualizaciones técnicas y nuevos paquetes de servicio.
- Análisis estadístico disociado para la optimización del rendimiento y usabilidad del sistema.

4. TRANSFERENCIAS Y EVALUACIÓN ASISTIDA CON INTELIGENCIA ARTIFICIAL (ART. 36 LFPDPPP)
Le informamos que para la calificación asistida de preguntas abiertas y de código (RW-10, RW-11), la plataforma se apoya en modelos de lenguaje provistos por Google Cloud (Gemini API). 
Garantía de Minimización y Anonimización: Al motor de inteligencia artificial únicamente se envían el reactivo educativo, la solución modelo de referencia y el texto de la respuesta ingresada por el estudiante. En ningún momento se transfieren a la infraestructura de IA nombres, correos electrónicos, identificadores de usuario ni datos que permitan la reidentificación de la persona. La evaluación de IA tiene carácter meramente provisional y está sujeta al recoteje y resolución definitiva del docente titular.

5. MECANISMOS PARA EL EJERCICIO DE DERECHOS ARCO
Usted tiene derecho a conocer qué datos personales tenemos de usted, para qué los utilizamos y las condiciones de su uso (Acceso). Asimismo, es su derecho solicitar la corrección de su información personal si está desactualizada, inexacta o incompleta (Rectificación); que la eliminemos de nuestros registros cuando considere que no está siendo utilizada conforme a los principios legales (Cancelación); así como oponerse al uso de sus datos personales para fines específicos (Oposición).

Para el ejercicio de cualquiera de los derechos ARCO, usted deberá enviar una solicitud por escrito al buzón de privacidad institucional:
Correo Electrónico: privacidad@acode-solutions.com
Su solicitud deberá incluir: 1) Nombre completo del titular y correo de registro; 2) Copia digital de documento oficial que acredite su identidad; 3) Descripción clara de los datos sobre los que desea ejercer el derecho y el tipo de derecho ARCO solicitado.
Plazo de Respuesta: En cumplimiento del Artículo 32 de la LFPDPPP, se le dará respuesta formal en un plazo máximo de 20 (veinte) días hábiles contados desde la recepción de su solicitud.

6. SEGURIDAD Y CONSERVACIÓN DE LA INFORMACIÓN
Sus datos personales son tratados bajo estrictas medidas de seguridad técnicas, administrativas y físicas contra daño, pérdida, alteración, destrucción o uso no autorizado. Las contraseñas se almacenan mediante algoritmos criptográficos irreversibles (Bcrypt) y las comunicaciones cliente-servidor se encuentran cifradas mediante protocolos TLS/HTTPS.

7. CAMBIOS AL PRESENTE AVISO DE PRIVACIDAD
El presente aviso de privacidad puede sufrir modificaciones, cambios o actualizaciones derivadas de reformas legales o necesidades de nuestros servicios. Cualquier cambio será publicado dentro de la plataforma en la sección /aviso-privacidad y se solicitará la reaceptación de la nueva versión a los usuarios en su próximo inicio de sesión.

Fecha de última actualización: 09 de Noviembre de 2026.
Versión del Documento: 2.0 Oficial Homologada.
TEXT;

        $legalNoticeV21 = <<<TEXT
AVISO DE PRIVACIDAD INTEGRAL — A-CODE SOLUTIONS

1. IDENTIDAD Y DOMICILIO DEL RESPONSABLE
A-Code Solutions (en adelante, "la Empresa"), con domicilio convencional para oír y recibir notificaciones en territorio mexicano y portal web oficial, es responsable del uso, tratamiento y protección de sus datos personales, en estricto apego a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP), su Reglamento y los Lineamientos del Aviso de Privacidad emitidos por el INAI.

2. DATOS PERSONALES RECABADOS
Para prestar los servicios de la Plataforma Evaluadora y Aplicadora de Exámenes en línea, recabamos las siguientes categorías de datos personales:
a) Datos de Identificación y Contacto: Nombre completo, apellidos, correo electrónico personal o institucional y número de teléfono móvil (para notificaciones y autenticación de dos factores).
b) Datos Académicos y de Evaluación: Asignaturas matriculadas, grupos escolares, reactivos capturados, respuestas emitidas en exámenes contrarreloj, puntuaciones asignadas, promedios parciales y retroalimentación docente.
c) Datos Técnicos y de Navegación: Dirección IP, registros de fecha y hora de sesión, modelo y sistema operativo del dispositivo, y métricas de supervisión de entorno de examen (pérdida de foco de ventana o cambio de pestaña, para garantizar la integridad académica).
d) Datos de Acreditación Docente: Nombre de la institución educativa de adscripción, nivel educativo impartido, número de cédula profesional SEP (dato opcional) y comprobante temporal de adscripción docente en formato PDF, JPG o PNG (credencial institucional de docente, constancia laboral o nombramiento, u horario oficial sellado), recabados exclusivamente para validar la identidad profesional de cuentas con rol docente.
Principio de No Recabación de Datos Sensibles: La plataforma no solicita ni almacena datos personales sensibles de origen racial, ideológico, de salud ni biométricos faciales.

3. FINALIDADES DEL TRATAMIENTO
Finalidades Primarias (necesarias para el servicio):
- Creación, administración y autenticación segura de cuentas de docentes, estudiantes y administradores.
- Verificación y acreditación de identidad profesional de docentes previo a la habilitación de herramientas de evaluación y gestión de grupos.
- Envío de códigos temporales de seguridad para autenticación de dos pasos (2FA).
- Aplicación, control de tiempo regresivo y calificación de evaluaciones académicas.
- Prevención de conductas fraudulentas o suplantación durante la resolución de pruebas.
- Consulta y exportación de sábanas oficiales de calificaciones para docentes.

Finalidades Secundarias (no necesarias para el servicio principal):
- Envío de notificaciones informativas sobre actualizaciones técnicas y nuevos paquetes de servicio.
- Análisis estadístico disociado para la optimización del rendimiento y usabilidad del sistema.

4. TRANSFERENCIAS Y EVALUACIÓN ASISTIDA CON INTELIGENCIA ARTIFICIAL (ART. 36 LFPDPPP)
Le informamos que para la calificación asistida de preguntas abiertas y de código (RW-10, RW-11), la plataforma se apoya en modelos de lenguaje provistos por Google Cloud (Gemini API).
Garantía de Minimización y Anonimización: Al motor de inteligencia artificial únicamente se envían el reactivo educativo, la solución modelo de referencia y el texto de la respuesta ingresada por el estudiante. En ningún momento se transfieren a la infraestructura de IA nombres, correos electrónicos, identificadores de usuario ni datos que permitan la reidentificación de la persona. La evaluación de IA tiene carácter meramente provisional y está sujeta al recoteje y resolución definitiva del docente titular.

5. MECANISMOS PARA EL EJERCICIO DE DERECHOS ARCO
Usted tiene derecho a conocer qué datos personales tenemos de usted, para qué los utilizamos y las condiciones de su uso (Acceso). Asimismo, es su derecho solicitar la corrección de su información personal si está desactualizada, inexacta o incompleta (Rectificación); que la eliminemos de nuestros registros cuando considere que no está siendo utilizada conforme a los principios legales (Cancelación); así como oponerse al uso de sus datos personales para fines específicos (Oposición).

Para el ejercicio de cualquiera de los derechos ARCO, usted deberá enviar una solicitud por escrito al buzón de privacidad institucional:
Correo Electrónico: privacidad@acode-solutions.com
Su solicitud deberá incluir: 1) Nombre completo del titular y correo de registro; 2) Copia digital de documento oficial que acredite su identidad; 3) Descripción clara de los datos sobre los que desea ejercer el derecho y el tipo de derecho ARCO solicitado.
Plazo de Respuesta: En cumplimiento del Artículo 32 de la LFPDPPP, se le dará respuesta formal en un plazo máximo de 20 (veinte) días hábiles contados desde la recepción de su solicitud.

6. SEGURIDAD Y CONSERVACIÓN DE LA INFORMACIÓN
Sus datos personales son tratados bajo estrictas medidas de seguridad técnicas, administrativas y físicas contra daño, pérdida, alteración, destrucción o uso no autorizado. Las contraseñas se almacenan mediante algoritmos criptográficos irreversibles (Bcrypt) y las comunicaciones cliente-servidor se encuentran cifradas mediante protocolos TLS/HTTPS.
Resguardo Privado y Eliminación Automática de Comprobantes Docentes (Principio de Minimización LFPDPPP): Todo comprobante de adscripción docente en formato PDF, JPG o PNG cargado durante el registro o reenvío se resguarda en almacenamiento privado del servidor inaccesible públicamente y se elimina automáticamente de manera definitiva e irreversible en cuanto la solicitud es aprobada o rechazada por el equipo administrador, conforme al principio de minimización de la LFPDPPP, conservando únicamente los metadatos administrativos de acreditación y la bitácora de auditoría correspondiente.

7. CAMBIOS AL PRESENTE AVISO DE PRIVACIDAD
El presente aviso de privacidad puede sufrir modificaciones, cambios o actualizaciones derivadas de reformas legales o necesidades de nuestros servicios. Cualquier cambio será publicado dentro de la plataforma en la sección /aviso-privacidad y se solicitará la reaceptación de la nueva versión a los usuarios en su próximo inicio de sesión.

Fecha de última actualización: 17 de Noviembre de 2026.
Versión del Documento: 2.1 Oficial Homologada.
TEXT;

        PrivacyNotice::updateOrCreate(
            ['version' => '2.0'],
            [
                'content' => $legalNoticeV20,
                'published_at' => now()->subDay(),
            ]
        );

        PrivacyNotice::updateOrCreate(
            ['version' => '2.1'],
            [
                'content' => $legalNoticeV21,
                'published_at' => now(),
            ]
        );
    }
}
