<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        FormRequestRulesTest.php
 * Ubicación:      tests/Feature/Validation/FormRequestRulesTest.php
 * Requerimiento:  N/A (verificación transversal de validaciones; ADR-017,
 *                 un FormRequest por operación)
 * Autor:          Calvo Sandoval Miguel Alexander (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Prueba parametrizada (equivalente PHP de pytest
 *                 parametrize) de las reglas de validación de los
 *                 FormRequest de registro, acceso, grupos, exámenes,
 *                 incidentes y dispositivos: valores válidos, límites
 *                 exactos (100 vs 101 caracteres, 9/10/11 dígitos, etc.)
 *                 y valores inválidos. Valida las reglas y mensajes reales
 *                 de cada request con el Validator de Laravel, sin pasar
 *                 por HTTP, de modo que un cambio de regla rompe la prueba.
 * ============================================================================
 */

namespace Tests\Feature\Validation;

use App\Http\Requests\AttemptIncidentStoreRequest;
use App\Http\Requests\DeviceRegisterRequest;
use App\Http\Requests\ExamStoreRequest;
use App\Http\Requests\GroupJoinRequest;
use App\Http\Requests\GroupStoreRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterStudentRequest;
use App\Http\Requests\RegisterTeacherRequest;
use App\Http\Requests\VerifyTwoFactorRequest;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FormRequestRulesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Marca de un campo que debe quitarse del payload (distinta de null).
     */
    private const UNSET = '__UNSET__';

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /**
     * Valida un payload con las reglas y mensajes reales del request.
     *
     * @param  array<string, mixed>  $payload
     */
    private function validate(FormRequest $request, array $payload): ValidatorInstance
    {
        return Validator::make($payload, $request->rules(), $request->messages());
    }

    /**
     * Combina el payload base con los cambios del caso; UNSET elimina la clave.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if ($value === self::UNSET) {
                unset($base[$key]);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertRule(FormRequest $request, array $payload, ?string $errorField): void
    {
        $validator = $this->validate($request, $payload);

        if ($errorField === null) {
            $this->assertSame([], $validator->errors()->toArray(), 'Se esperaba un payload válido.');

            return;
        }

        $this->assertTrue(
            $validator->errors()->has($errorField),
            "Se esperaba error en '{$errorField}'; errores: ".json_encode($validator->errors()->toArray()),
        );
    }

    // ------------------------------------------------------------------
    // Registro de alumno
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private static function studentBase(): array
    {
        return [
            'first_name' => 'Ana Sofía',
            'last_name' => 'García Núñez',
            'email' => 'ana.nueva@alumnos.acode-solutions.test',
            'phone' => '5512345678',
            'password' => 'Demo1234',
            'password_confirmation' => 'Demo1234',
            'accepts_privacy_notice' => true,
        ];
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: ?string}>
     */
    public static function studentCases(): array
    {
        $unset = self::UNSET;

        return [
            'payload completo' => [[], null],
            'sin teléfono (opcional para alumno)' => [['phone' => $unset], null],
            'teléfono nulo' => [['phone' => null], null],
            'nombre con acentos y compuesto' => [['first_name' => 'José Ángel', 'last_name' => 'Peña Ibarra'], null],
            'nombre exactamente 100 caracteres' => [['first_name' => str_repeat('a', 100)], null],
            'apellidos exactamente 100 caracteres' => [['last_name' => str_repeat('a', 100)], null],
            'contraseña de exactamente 8 caracteres' => [
                ['password' => 'Abcd1234', 'password_confirmation' => 'Abcd1234'],
                null,
            ],
            'aviso de privacidad como "on"' => [['accepts_privacy_notice' => 'on'], null],
            'aviso de privacidad como 1' => [['accepts_privacy_notice' => 1], null],
            'nombre ausente' => [['first_name' => $unset], 'first_name'],
            'nombre vacío' => [['first_name' => ''], 'first_name'],
            'nombre de 101 caracteres' => [['first_name' => str_repeat('a', 101)], 'first_name'],
            'apellidos ausentes' => [['last_name' => $unset], 'last_name'],
            'apellidos de 101 caracteres' => [['last_name' => str_repeat('a', 101)], 'last_name'],
            'correo ausente' => [['email' => $unset], 'email'],
            'correo vacío' => [['email' => ''], 'email'],
            'correo sin arroba' => [['email' => 'correo.sin.arroba'], 'email'],
            'correo sin dominio' => [['email' => 'ana@'], 'email'],
            'correo con espacios' => [['email' => 'ana perez@correo.test'], 'email'],
            'correo de más de 255 caracteres' => [['email' => str_repeat('a', 250).'@b.test'], 'email'],
            'teléfono de 9 dígitos' => [['phone' => '551234567'], 'phone'],
            'teléfono de 11 dígitos' => [['phone' => '55123456789'], 'phone'],
            'teléfono con letras' => [['phone' => '55123abc78'], 'phone'],
            'teléfono con espacios' => [['phone' => '55 1234 5678'], 'phone'],
            'teléfono con lada internacional' => [['phone' => '+525512345678'], 'phone'],
            'teléfono con guiones' => [['phone' => '55-1234-5678'], 'phone'],
            'contraseña de 7 caracteres' => [
                ['password' => 'Abcd123', 'password_confirmation' => 'Abcd123'],
                'password',
            ],
            'contraseña ausente' => [['password' => $unset, 'password_confirmation' => $unset], 'password'],
            'contraseña sin confirmación' => [['password_confirmation' => $unset], 'password'],
            'confirmación distinta' => [['password_confirmation' => 'Otra1234'], 'password'],
            'confirmación con distinta capitalización' => [['password_confirmation' => 'demo1234'], 'password'],
            'aviso de privacidad rechazado' => [['accepts_privacy_notice' => false], 'accepts_privacy_notice'],
            'aviso de privacidad ausente' => [['accepts_privacy_notice' => $unset], 'accepts_privacy_notice'],
            'aviso de privacidad "no"' => [['accepts_privacy_notice' => 'no'], 'accepts_privacy_notice'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('studentCases')]
    public function test_register_student_rules(array $overrides, ?string $errorField): void
    {
        $this->assertRule(
            new RegisterStudentRequest,
            $this->merge(self::studentBase(), $overrides),
            $errorField,
        );
    }

    public function test_register_student_rejects_an_email_that_already_exists(): void
    {
        User::factory()->create(['email' => 'repetido@alumnos.acode-solutions.test']);

        $this->assertRule(
            new RegisterStudentRequest,
            $this->merge(self::studentBase(), ['email' => 'repetido@alumnos.acode-solutions.test']),
            'email',
        );
    }

    // ------------------------------------------------------------------
    // Registro de docente (teléfono obligatorio)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1: ?string}>
     */
    public static function teacherCases(): array
    {
        $unset = self::UNSET;

        return [
            'payload completo' => [[], null],
            'sin teléfono' => [['phone' => $unset], 'phone'],
            'teléfono nulo' => [['phone' => null], 'phone'],
            'teléfono vacío' => [['phone' => ''], 'phone'],
            'teléfono de 9 dígitos' => [['phone' => '551234567'], 'phone'],
            'teléfono de 11 dígitos' => [['phone' => '55123456789'], 'phone'],
            'teléfono con letras' => [['phone' => 'cincocinco'], 'phone'],
            'aviso de privacidad rechazado' => [['accepts_privacy_notice' => false], 'accepts_privacy_notice'],
            'nombre de 101 caracteres' => [['first_name' => str_repeat('a', 101)], 'first_name'],
            'confirmación distinta' => [['password_confirmation' => 'Otra1234'], 'password'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('teacherCases')]
    public function test_register_teacher_rules(array $overrides, ?string $errorField): void
    {
        $this->assertRule(
            new RegisterTeacherRequest,
            $this->merge(self::studentBase(), $overrides),
            $errorField,
        );
    }

    // ------------------------------------------------------------------
    // Inicio de sesión
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1: ?string}>
     */
    public static function loginCases(): array
    {
        $unset = self::UNSET;

        return [
            'credenciales completas' => [[], null],
            'correo ausente' => [['email' => $unset], 'email'],
            'correo mal formado' => [['email' => 'sin-arroba'], 'email'],
            'contraseña ausente' => [['password' => $unset], 'password'],
            'contraseña vacía' => [['password' => ''], 'password'],
            'contraseña numérica (debe ser texto)' => [['password' => 12345678], 'password'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('loginCases')]
    public function test_login_rules(array $overrides, ?string $errorField): void
    {
        $this->assertRule(
            new LoginRequest,
            $this->merge(['email' => 'ana@alumnos.acode-solutions.test', 'password' => 'Demo1234'], $overrides),
            $errorField,
        );
    }

    // ------------------------------------------------------------------
    // Segundo factor (código de 6 dígitos)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{0: mixed, 1: ?string}>
     */
    public static function twoFactorCodes(): array
    {
        return [
            'seis dígitos' => ['123456', null],
            'ceros a la izquierda' => ['000000', null],
            'numérico entero de seis dígitos' => [123456, null],
            'cinco dígitos' => ['12345', 'code'],
            'siete dígitos' => ['1234567', 'code'],
            'letras' => ['abcdef', 'code'],
            'mezcla de letras y números' => ['12a456', 'code'],
            'con espacio en medio' => ['123 56', 'code'],
            'con espacio al inicio' => [' 12345', 'code'],
            'vacío' => ['', 'code'],
            'nulo' => [null, 'code'],
            'con signo' => ['+12345', 'code'],
            'decimal' => ['12.456', 'code'],
        ];
    }

    #[DataProvider('twoFactorCodes')]
    public function test_two_factor_code_rules(mixed $code, ?string $errorField): void
    {
        $this->assertRule(new VerifyTwoFactorRequest, ['code' => $code], $errorField);
    }

    public function test_two_factor_requires_the_code_field(): void
    {
        $this->assertRule(new VerifyTwoFactorRequest, [], 'code');
    }

    public function test_two_factor_user_id_must_exist_when_sent(): void
    {
        $this->assertRule(new VerifyTwoFactorRequest, ['code' => '123456', 'user_id' => 999999], 'user_id');
        $this->assertRule(new VerifyTwoFactorRequest, ['code' => '123456', 'user_id' => 'abc'], 'user_id');

        $user = User::factory()->create();
        $this->assertRule(new VerifyTwoFactorRequest, ['code' => '123456', 'user_id' => $user->id], null);
    }

    // ------------------------------------------------------------------
    // Unirse a un grupo (folio / QR)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1: ?string}>
     */
    public static function groupJoinCases(): array
    {
        return [
            'folio válido' => [['folio' => 'ABCD1234'], null],
            'folio de exactamente 12 caracteres' => [['folio' => str_repeat('A', 12)], null],
            'solo access_folio' => [['access_folio' => 'ABCD1234'], null],
            'solo qr_payload' => [['qr_payload' => 'ABCD1234'], null],
            'sin ningún identificador' => [[], 'folio'],
            'folio vacío y sin QR' => [['folio' => ''], 'folio'],
            'folio de 13 caracteres' => [['folio' => str_repeat('A', 13)], 'folio'],
            'access_folio de 13 caracteres' => [['access_folio' => str_repeat('A', 13)], 'access_folio'],
            'qr_payload de 256 caracteres' => [['qr_payload' => str_repeat('A', 256)], 'qr_payload'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('groupJoinCases')]
    public function test_group_join_rules(array $payload, ?string $errorField): void
    {
        $this->assertRule(new GroupJoinRequest, $payload, $errorField);
    }

    // ------------------------------------------------------------------
    // Crear grupo
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1: ?string}>
     */
    public static function groupStoreCases(): array
    {
        $unset = self::UNSET;

        return [
            'nombre y materia' => [[], null],
            'sin materia (opcional)' => [['subject' => $unset], null],
            'materia nula' => [['subject' => null], null],
            'nombre de exactamente 120 caracteres' => [['name' => str_repeat('a', 120)], null],
            'materia de exactamente 120 caracteres' => [['subject' => str_repeat('a', 120)], null],
            'nombre ausente' => [['name' => $unset], 'name'],
            'nombre vacío' => [['name' => ''], 'name'],
            'nombre de 121 caracteres' => [['name' => str_repeat('a', 121)], 'name'],
            'materia de 121 caracteres' => [['subject' => str_repeat('a', 121)], 'subject'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('groupStoreCases')]
    public function test_group_store_rules(array $overrides, ?string $errorField): void
    {
        $this->assertRule(
            new GroupStoreRequest,
            $this->merge(['name' => 'Auditoría de Sistemas - 7A', 'subject' => 'Auditoría'], $overrides),
            $errorField,
        );
    }

    // ------------------------------------------------------------------
    // Crear examen
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private static function examBase(): array
    {
        return [
            'title' => 'Parcial 1 - Auditoría de Sistemas',
            'description' => 'Unidades 1 y 2',
            'grading_scale' => '0-10',
            'result_visibility_mode' => 'final_score',
            'max_allowed_incidents' => 3,
            'starts_at' => '2026-11-10 08:00:00',
            'ends_at' => '2026-11-10 10:00:00',
        ];
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: ?string}>
     */
    public static function examCases(): array
    {
        $unset = self::UNSET;

        return [
            'examen completo' => [[], null],
            'escala 0-100' => [['grading_scale' => '0-100'], null],
            'visibilidad estimated_score' => [['result_visibility_mode' => 'estimated_score'], null],
            'visibilidad objective_only_pending' => [['result_visibility_mode' => 'objective_only_pending'], null],
            'visibilidad hidden' => [['result_visibility_mode' => 'hidden'], null],
            'cero incidentes permitidos' => [['max_allowed_incidents' => 0], null],
            'descripción nula' => [['description' => null], null],
            'título de exactamente 150 caracteres' => [['title' => str_repeat('a', 150)], null],
            'título ausente' => [['title' => $unset], 'title'],
            'título de 151 caracteres' => [['title' => str_repeat('a', 151)], 'title'],
            'escala inválida 0-5' => [['grading_scale' => '0-5'], 'grading_scale'],
            'escala ausente' => [['grading_scale' => $unset], 'grading_scale'],
            'visibilidad inválida' => [['result_visibility_mode' => 'publico'], 'result_visibility_mode'],
            'visibilidad ausente' => [['result_visibility_mode' => $unset], 'result_visibility_mode'],
            'incidentes negativos' => [['max_allowed_incidents' => -1], 'max_allowed_incidents'],
            'incidentes no numéricos' => [['max_allowed_incidents' => 'tres'], 'max_allowed_incidents'],
            'incidentes decimales' => [['max_allowed_incidents' => 2.5], 'max_allowed_incidents'],
            'incidentes ausentes' => [['max_allowed_incidents' => $unset], 'max_allowed_incidents'],
            'inicio ausente' => [['starts_at' => $unset], 'starts_at'],
            'inicio que no es fecha' => [['starts_at' => 'mañana temprano'], 'starts_at'],
            'cierre ausente' => [['ends_at' => $unset], 'ends_at'],
            'cierre antes del inicio' => [['ends_at' => '2026-11-10 07:59:00'], 'ends_at'],
            'cierre igual al inicio' => [['ends_at' => '2026-11-10 08:00:00'], 'ends_at'],
            'cierre que no es fecha' => [['ends_at' => '31/31/2026'], 'ends_at'],
            'group_ids que no es arreglo' => [['group_ids' => 'uno'], 'group_ids'],
            'group_ids con valores no enteros' => [['group_ids' => ['abc']], 'group_ids.0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('examCases')]
    public function test_exam_store_rules(array $overrides, ?string $errorField): void
    {
        $teacher = User::factory()->teacher()->create();
        $request = ExamStoreRequest::create('/exams', 'POST');
        $request->setUserResolver(fn () => $teacher);

        $this->assertRule($request, $this->merge(self::examBase(), $overrides), $errorField);
    }

    public function test_exam_store_only_accepts_groups_owned_by_the_teacher(): void
    {
        $teacher = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();

        $ownGroup = Group::create(['teacher_id' => $teacher->id, 'name' => 'Propio', 'access_folio' => 'PROPIO01']);
        $foreignGroup = Group::create(['teacher_id' => $other->id, 'name' => 'Ajeno', 'access_folio' => 'AJENO001']);

        $request = ExamStoreRequest::create('/exams', 'POST');
        $request->setUserResolver(fn () => $teacher);

        $this->assertRule($request, $this->merge(self::examBase(), ['group_ids' => [$ownGroup->id]]), null);
        $this->assertRule(
            $request,
            $this->merge(self::examBase(), ['group_ids' => [$foreignGroup->id]]),
            'group_ids.0',
        );
        $this->assertRule(
            $request,
            $this->merge(self::examBase(), ['group_ids' => [$ownGroup->id, $foreignGroup->id]]),
            'group_ids.1',
        );
        $this->assertRule($request, $this->merge(self::examBase(), ['group_ids' => [999999]]), 'group_ids.0');
    }

    // ------------------------------------------------------------------
    // Incidentes de integridad del intento
    // ------------------------------------------------------------------

    /**
     * Los tres primeros valores son los que envía hoy la app móvil
     * (ExamIncidentEnum.apiValue, MPM-V4 §7.7): si el backend dejara de
     * aceptarlos, la app móvil rompería.
     *
     * @return array<string, array{0: array<string, mixed>, 1: ?string}>
     */
    public static function incidentCases(): array
    {
        return [
            'móvil: app_left' => [['incident_type' => 'app_left'], null],
            'móvil: multi_window' => [['incident_type' => 'multi_window'], null],
            'móvil: focus_lost' => [['incident_type' => 'focus_lost'], null],
            'web: tab_switch' => [['incident_type' => 'tab_switch'], null],
            'screenshot_attempt' => [['incident_type' => 'screenshot_attempt'], null],
            'con descripción de 500 caracteres' => [
                ['incident_type' => 'focus_lost', 'description' => str_repeat('a', 500)],
                null,
            ],
            'tipo desconocido' => [['incident_type' => 'hackeo'], 'incident_type'],
            'tipo con otra capitalización' => [['incident_type' => 'APP_LEFT'], 'incident_type'],
            'tipo vacío' => [['incident_type' => ''], 'incident_type'],
            'sin tipo' => [[], 'incident_type'],
            'descripción de 501 caracteres' => [
                ['incident_type' => 'focus_lost', 'description' => str_repeat('a', 501)],
                'description',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('incidentCases')]
    public function test_attempt_incident_rules(array $payload, ?string $errorField): void
    {
        $this->assertRule(new AttemptIncidentStoreRequest, $payload, $errorField);
    }

    // ------------------------------------------------------------------
    // Registro de dispositivo (token push)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1: ?string}>
     */
    public static function deviceCases(): array
    {
        $unset = self::UNSET;

        return [
            'token y plataforma android' => [[], null],
            'plataforma ios' => [['platform' => 'ios'], null],
            'plataforma web' => [['platform' => 'web'], null],
            'token de exactamente 512 caracteres' => [['token' => str_repeat('t', 512)], null],
            'modelo y versión opcionales' => [['model' => 'Pixel 7', 'os_version' => '14'], null],
            'token ausente' => [['token' => $unset], 'token'],
            'token de 513 caracteres' => [['token' => str_repeat('t', 513)], 'token'],
            'plataforma desconocida' => [['platform' => 'windows'], 'platform'],
            'plataforma ausente' => [['platform' => $unset], 'platform'],
            'modelo de 121 caracteres' => [['model' => str_repeat('m', 121)], 'model'],
            'versión de SO de 61 caracteres' => [['os_version' => str_repeat('v', 61)], 'os_version'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('deviceCases')]
    public function test_device_register_rules(array $overrides, ?string $errorField): void
    {
        $this->assertRule(
            new DeviceRegisterRequest,
            $this->merge(['token' => 'fcm-token-de-prueba', 'platform' => 'android'], $overrides),
            $errorField,
        );
    }

    // ------------------------------------------------------------------
    // Mensajes: cada error visible debe estar en español (MPW-V2)
    // ------------------------------------------------------------------

    public function test_error_messages_are_in_spanish_for_registration(): void
    {
        $validator = $this->validate(
            new RegisterStudentRequest,
            $this->merge(
                self::studentBase(),
                ['first_name' => '', 'phone' => '123', 'accepts_privacy_notice' => false],
            ),
        );

        $this->assertSame('El campo nombre es obligatorio', $validator->errors()->first('first_name'));
        $this->assertSame('El teléfono debe contener 10 dígitos', $validator->errors()->first('phone'));
        $this->assertSame(
            'Debes aceptar el aviso de privacidad para continuar',
            $validator->errors()->first('accepts_privacy_notice'),
        );
    }
}
