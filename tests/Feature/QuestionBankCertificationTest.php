<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionBankCertificationTest.php
 * Ubicación:      tests/Feature/QuestionBankCertificationTest.php
 * Requerimiento:  Certificación integral Anillo 3 del Banco de Preguntas:
 *                 catálogo normalizado acs_subjects, fuzzy/merge, Excel/CSV
 *                 dry-run atómico, captura rápida en lote, blindaje anti-copia
 *                 (paráfrasis 1+N + snapshots, matching opaco [CRÍTICA-02]),
 *                 CoW, límites SaaS y ExamAssemblyService.
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * ============================================================================
 */

namespace Tests\Feature;

use App\Exceptions\SubscriptionLimitExceededException;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\MatchingItem;
use App\Models\MultipleChoiceItem;
use App\Models\Question;
use App\Models\QuestionParaphrase;
use App\Models\Scopes\TenantTeacherScope;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\Subject;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use App\Services\AttemptService;
use App\Services\ExamAssemblyService;
use App\Services\QuestionBankService;
use App\Services\StudentResultService;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class QuestionBankCertificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_normalization_on_create_edit_and_import_prevents_duplicates_and_filters_tolerantly(): void
    {
        $teacher = $this->createTeacher();

        // 1. Creación con espacios múltiples y acentos
        $this->actingAs($teacher)->post(route('questions.store'), [
            'title' => 'Pregunta Materia Sucia',
            'stem' => '¿Qué estudia la biología celular?',
            'question_type' => 'open',
            'subject_tag' => '  ciéncias   naturales ',
            'default_points' => 2.0,
            'default_time_seconds' => 60,
            'reference_answer' => 'La estructura y función de las células.',
        ])->assertRedirect(route('questions.index'));

        // 2. Creación con mayúsculas/sin acentos (debe reutilizar la misma materia)
        $this->actingAs($teacher)->post(route('questions.store'), [
            'title' => 'Pregunta Materia Mayúsculas',
            'stem' => '¿Qué es la mitosis?',
            'question_type' => 'open',
            'subject_tag' => 'CIENCIAS NATURALES',
            'default_points' => 2.0,
            'default_time_seconds' => 60,
            'reference_answer' => 'División celular equitativa.',
        ])->assertRedirect(route('questions.index'));

        // 3. Edición cambiando subject_tag a variante con Unicode no-separable
        $editable = $this->createMcQuestion($teacher, 'Otra Materia', 'Reactivo a Reasignar');
        $this->actingAs($teacher)->put(route('questions.update', $editable), [
            'title' => 'Reactivo Reasignado',
            'stem' => 'Seleccione el reino biológico correspondiente.',
            'question_type' => 'multiple_choice',
            'subject_tag' => "Ciencias\u{00A0}  Naturales",
            'default_points' => 2.0,
            'default_time_seconds' => 60,
            'options' => ['Plantae', 'Mineral'],
            'correct_option_index' => 0,
        ])->assertRedirect(route('questions.show', $editable));

        // 4. Importación CSV con variante en minúsculas
        $csv = implode("\n", [
            'tipo,titulo,enunciado,materia,puntos,tiempo_segundos,opciones_o_definiciones,respuesta_correcta_o_solucion,instrucciones_o_rubrica',
            'open,Reactivo Importado Materia,Explique el ciclo del agua,ciencias   naturales,3.00,90,,Evaporación condensación y precipitación,Mencionar fases.',
        ]);
        $this->actingAs($teacher)->post(route('questions.import'), [
            'csv_file' => UploadedFile::fake()->createWithContent('materias.csv', $csv),
        ])->assertRedirect(route('questions.index'));

        $subjects = Subject::withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacher->id)
            ->where('normalized_name', 'ciencias naturales')
            ->get();

        $this->assertCount(1, $subjects);
        $canonicalSubject = $subjects->firstOrFail();

        $linkedCount = Question::withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacher->id)
            ->where('subject_id', $canonicalSubject->id)
            ->count();
        $this->assertSame(4, $linkedCount);

        // 5. Filtro tolerante en questions.index?subject_tag=ciencias naturales encuentra los 4
        $indexResponse = $this->actingAs($teacher)->get(route('questions.index', [
            'subject_tag' => 'ciencias naturales',
        ]));
        $indexResponse->assertOk();
        $indexResponse->assertViewHas('questions', fn ($questions): bool => $questions->total() === 4);
    }

    public function test_subject_suggestions_uses_fuzzy_matching_and_isolates_teachers(): void
    {
        $teacherA = $this->createTeacher();
        $teacherB = $this->createTeacher();

        $this->createMcQuestion($teacherA, 'Ciencias Naturales', 'Reactivo Bio A');
        $this->createMcQuestion($teacherA, 'Física I', 'Reactivo Física I');
        $this->createMcQuestion($teacherA, 'Física II', 'Reactivo Física II');

        // Docente B posee materia homónima y otra similar que jamás deben filtrarse a Docente A
        $foreignSubject = Subject::create([
            'teacher_id' => $teacherB->id,
            'name' => 'Ciencias Naturales',
            'normalized_name' => 'ciencias naturales',
        ]);
        $foreignSimilar = Subject::create([
            'teacher_id' => $teacherB->id,
            'name' => 'Ciencias Experimentales',
            'normalized_name' => 'ciencias experimentales',
        ]);

        $response = $this->actingAs($teacherA)->getJson(route('questions.subjects.suggestions', [
            'name' => 'ciencas natarls',
        ]));

        $response->assertOk();
        $suggestions = collect($response->json('suggestions'));

        $this->assertTrue($suggestions->contains('name', 'Ciencias Naturales'));
        $this->assertFalse($suggestions->contains('id', $foreignSubject->id));
        $this->assertFalse($suggestions->contains('id', $foreignSimilar->id));

        // Materias seriadas (Física I vs Física II) no deben sugerirse como duplicados entre sí
        $serialResponse = $this->actingAs($teacherA)->getJson(route('questions.subjects.suggestions', [
            'name' => 'Física I',
        ]));
        $serialResponse->assertOk();
        $serialNames = collect($serialResponse->json('suggestions'))->pluck('name')->all();
        $this->assertContains('Física I', $serialNames);
        $this->assertNotContains('Física II', $serialNames);
    }

    public function test_merge_subjects_unifies_questions_and_exam_sections_and_rejects_foreign_ids(): void
    {
        $teacherA = $this->createTeacher();
        $teacherB = $this->createTeacher();

        $q1 = $this->createMcQuestion($teacherA, 'Ciencas Naturales', 'Reactivo Typo 1');
        $q2 = $this->createMcQuestion($teacherA, 'Ciencia Natural', 'Reactivo Typo 2');
        $qSoftDeleted = $this->createMcQuestion($teacherA, 'Ciencas Naturales', 'Reactivo Histórico Borrado');
        $qSoftDeleted->delete();

        $sub1 = Subject::findOrFail($q1->subject_id);
        $sub2 = Subject::findOrFail($q2->subject_id);

        $exam = $this->createDraftExam($teacherA);
        $section1 = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Apartado Typo 1',
            'question_type' => 'multiple_choice',
            'subject_id' => $sub1->id,
            'subject_tag' => $sub1->name,
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);
        $section2 = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Apartado Typo 2',
            'question_type' => 'multiple_choice',
            'subject_id' => $sub2->id,
            'subject_tag' => $sub2->name,
            'requested_quantity' => 1,
            'sort_order' => 1,
        ]);

        $foreignSubject = Subject::create([
            'teacher_id' => $teacherB->id,
            'name' => 'Materia Ajena',
            'normalized_name' => 'materia ajena',
        ]);

        // Rechaza IDs de materias pertenecientes a otro docente (422)
        $foreignMergeResponse = $this->actingAs($teacherA)->postJson(route('questions.subjects.merge'), [
            'source_subject_ids' => [$sub1->id, $foreignSubject->id],
            'target_name' => 'Ciencias Naturales',
        ]);
        $foreignMergeResponse->assertStatus(422);
        $foreignMergeResponse->assertJsonValidationErrors(['source_subject_ids.1']);

        // Fusión autorizada unifica acs_questions (incluyendo trashed) y acs_exam_sections
        $mergeResponse = $this->actingAs($teacherA)->post(route('questions.subjects.merge'), [
            'source_subject_ids' => [$sub1->id, $sub2->id],
            'target_name' => 'Ciencias Naturales',
        ]);

        $merged = Subject::withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacherA->id)
            ->where('normalized_name', 'ciencias naturales')
            ->firstOrFail();

        $mergeResponse->assertRedirect(route('questions.index', ['subject_id' => $merged->id]));

        $this->assertSame($merged->id, $q1->fresh()->subject_id);
        $this->assertSame('Ciencias Naturales', $q1->fresh()->subject_tag);
        $this->assertSame($merged->id, $q2->fresh()->subject_id);
        $this->assertSame('Ciencias Naturales', $q2->fresh()->subject_tag);
        $this->assertSame($merged->id, Question::withTrashed()->findOrFail($qSoftDeleted->id)->subject_id);

        $this->assertSame($merged->id, $section1->fresh()->subject_id);
        $this->assertSame('Ciencias Naturales', $section1->fresh()->subject_tag);
        $this->assertSame($merged->id, $section2->fresh()->subject_id);
        $this->assertSame('Ciencias Naturales', $section2->fresh()->subject_tag);

        $this->assertDatabaseMissing('acs_subjects', ['id' => $sub1->id]);
        $this->assertDatabaseMissing('acs_subjects', ['id' => $sub2->id]);
    }

    public function test_exam_assembly_service_persists_subject_links_and_reshuffles_within_subject(): void
    {
        $teacher = $this->createTeacher();
        $assemblyService = app(ExamAssemblyService::class);

        $bio1 = $this->createMcQuestion($teacher, 'Biología', 'Biología 1');
        $bio2 = $this->createMcQuestion($teacher, 'Biología', 'Biología 2');
        $bio3 = $this->createMcQuestion($teacher, 'Biología', 'Biología 3');
        $chem1 = $this->createMcQuestion($teacher, 'Química', 'Química 1');
        $chem2 = $this->createMcQuestion($teacher, 'Química', 'Química 2');

        $bioSubjectId = $bio1->subject_id;
        $this->assertNotNull($bioSubjectId);

        $exam = $this->createDraftExam($teacher);

        $section = $assemblyService->createSectionAndDraw($exam, [
            'title' => 'Apartado Biología',
            'question_type' => 'multiple_choice',
            'subject_tag' => 'biologia',
            'requested_quantity' => 2,
        ]);

        $this->assertSame($bioSubjectId, $section->subject_id);
        $this->assertSame('Biología', $section->subject_tag);

        $drawnExamQuestions = $section->examQuestions()->with('question')->get();
        $this->assertCount(2, $drawnExamQuestions);
        foreach ($drawnExamQuestions as $eq) {
            $this->assertSame($bioSubjectId, $eq->question->subject_id);
            $this->assertNotContains($eq->question_id, [$chem1->id, $chem2->id]);
        }

        // Re-sortear uno de los 2 reactivos: únicamente puede entrar el 3er reactivo de Biología
        $targetEq = $drawnExamQuestions->first();
        $drawnIdsBefore = $drawnExamQuestions->pluck('question_id')->all();
        $expectedReplacementId = collect([$bio1->id, $bio2->id, $bio3->id])
            ->diff($drawnIdsBefore)
            ->first();

        $reshuffled = $assemblyService->reshuffleQuestion($targetEq);

        $this->assertSame($expectedReplacementId, $reshuffled->question_id);
        $this->assertSame($bioSubjectId, $reshuffled->question->subject_id);
    }

    public function test_xlsx_template_download_is_valid_spreadsheet_with_canonical_headers_and_cwe1236_protection(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->actingAs($teacher)->get(route('questions.template.xlsx'));
        $response->assertOk();
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->assertStringContainsString(
            'plantilla_reactivos_acode.xlsx',
            (string) $response->headers->get('Content-Disposition')
        );

        $tmpPath = tempnam(sys_get_temp_dir(), 'tpl_verify_') . '.xlsx';
        file_put_contents($tmpPath, $response->getContent());

        try {
            $spreadsheet = IOFactory::load($tmpPath);
            $this->assertSame('Plantilla Reactivos', $spreadsheet->getSheet(0)->getTitle());
            $this->assertSame('Instrucciones', $spreadsheet->getSheet(1)->getTitle());

            $headers = [];
            foreach (range('A', 'K') as $col) {
                $headers[] = (string) $spreadsheet->getSheet(0)->getCell("{$col}1")->getValue();
            }

            $this->assertSame([
                'tipo',
                'titulo',
                'enunciado',
                'materia',
                'puntos',
                'tiempo_segundos',
                'opciones_o_definiciones',
                'respuesta_correcta_o_solucion',
                'instrucciones_o_rubrica',
                'es_encuentra_error',
                'codigo_inicial',
            ], $headers);
            $this->assertSame('1', (string) $spreadsheet->getSheet(0)->getCell('J6')->getValue());
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }

        // Protección contra inyección de fórmulas CWE-1236
        $service = app(QuestionBankService::class);
        $this->assertSame("'=CMD()", $service->sanitizeSpreadsheetCellForExport('=CMD()'));
        $this->assertSame("'+1+1", $service->sanitizeSpreadsheetCellForExport('+1+1'));
        $this->assertSame("'-2+3", $service->sanitizeSpreadsheetCellForExport('-2+3'));
        $this->assertSame("'@SUM(A1:A2)", $service->sanitizeSpreadsheetCellForExport('@SUM(A1:A2)'));
        $this->assertSame('Texto normal', $service->sanitizeSpreadsheetCellForExport('Texto normal'));
    }

    public function test_import_preview_returns_dry_run_json_without_persisting_anything(): void
    {
        $teacher = $this->createTeacher();
        $this->createMcQuestion($teacher, 'Ciencias Naturales', 'Existente');
        $initialQuestions = Question::count();
        $initialSubjects = Subject::withoutGlobalScope(TenantTeacherScope::class)->count();

        $validCsv = implode("\n", [
            'tipo,titulo,enunciado,materia,puntos,tiempo_segundos,opciones_o_definiciones,respuesta_correcta_o_solucion,instrucciones_o_rubrica',
            'open,Preview 1,¿Qué es la fotosíntesis?,Ciencas Naturales,3.00,90,,Proceso anabólico de plantas.,',
            'multiple_choice,Preview 2,Capital de Francia,Geografía,2.00,60,París|Lyon|Niza,0,',
        ]);

        $previewOk = $this->actingAs($teacher)->postJson(route('questions.import.preview'), [
            'csv_file' => UploadedFile::fake()->createWithContent('preview_ok.csv', $validCsv),
        ]);

        $previewOk->assertOk();
        $previewOk->assertJson([
            'total_rows' => 2,
            'valid_count' => 2,
            'error_count' => 0,
            'can_commit' => true,
        ]);
        $this->assertCount(2, $previewOk->json('rows'));
        $this->assertNotEmpty($previewOk->json('subject_warnings'));
        $this->assertSame($initialQuestions, Question::count());
        $this->assertSame($initialSubjects, Subject::withoutGlobalScope(TenantTeacherScope::class)->count());

        // Con fila errónea: can_commit debe ser false y seguir sin insertar nada
        $mixedCsv = implode("\n", [
            'tipo,titulo,enunciado,materia,puntos,tiempo_segundos,opciones_o_definiciones,respuesta_correcta_o_solucion,instrucciones_o_rubrica',
            'open,Preview Válida,¿Qué es ATP?,Biología,2.00,60,,Adenosín trifosfato,',
            'multiple_choice,Preview Inválida,Elige una opción,Biología,2.00,60,Opción A|Opción B,9,',
        ]);

        $previewErr = $this->actingAs($teacher)->postJson(route('questions.import.preview'), [
            'csv_file' => UploadedFile::fake()->createWithContent('preview_err.csv', $mixedCsv),
        ]);

        $previewErr->assertOk();
        $previewErr->assertJson([
            'total_rows' => 2,
            'valid_count' => 1,
            'error_count' => 1,
            'can_commit' => false,
        ]);
        $this->assertSame($initialQuestions, Question::count());
        $this->assertSame($initialSubjects, Subject::withoutGlobalScope(TenantTeacherScope::class)->count());
    }

    public function test_import_supports_semicolon_csv_optional_find_error_columns_and_real_xlsx(): void
    {
        $teacher = $this->createTeacher();

        // 1. CSV con delimitador ';' y columnas opcionales es_encuentra_error y codigo_inicial
        $semicolonCsv = implode("\n", [
            'tipo;titulo;enunciado;materia;puntos;tiempo_segundos;opciones_o_definiciones;respuesta_correcta_o_solucion;instrucciones_o_rubrica;es_encuentra_error;codigo_inicial',
            "code;Depurar Condición Python;Corrige el operador de igualdad;Programación;4.00;180;python;\"def ok(x):\n    return x == 10\";Corrige = por ==;1;\"def ok(x):\n    return x = 10\"",
            'open;Explicar Polimorfismo;Defina polimorfismo dinámico;Programación;3.00;120;;Enlace tardío en tiempo de ejecución;Mencione herencia;;',
        ]);

        $csvResponse = $this->actingAs($teacher)->post(route('questions.import'), [
            'csv_file' => UploadedFile::fake()->createWithContent('lote_punto_coma.csv', $semicolonCsv),
        ]);
        $csvResponse->assertRedirect(route('questions.index'));
        $csvResponse->assertSessionHas('status', 'Se importaron 2 reactivos exitosamente.');

        $codeQuestion = Question::where('teacher_id', $teacher->id)
            ->where('title', 'Depurar Condición Python')
            ->with('questionable')
            ->firstOrFail();
        $this->assertTrue((bool) $codeQuestion->questionable->is_find_error);
        $this->assertSame("def ok(x):\n    return x = 10", $codeQuestion->questionable->starter_code);

        // 2. Archivo Excel (.xlsx) real generado con PhpSpreadsheet
        $xlsxPath = $this->createXlsxFixture([
            [
                'tipo', 'titulo', 'enunciado', 'materia', 'puntos', 'tiempo_segundos',
                'opciones_o_definiciones', 'respuesta_correcta_o_solucion', 'instrucciones_o_rubrica',
                'es_encuentra_error', 'codigo_inicial',
            ],
            [
                'multiple_choice', 'XLSX MC', 'Protocolo sin estado web', 'Redes', '2.50', '60',
                'HTTP|FTP|SSH', '0', 'Elija uno', '0', '',
            ],
            [
                'matching', 'XLSX Matching', 'Relacione puertos', 'Redes', '3.00', '120',
                'HTTP=80|HTTPS=443|SSH=22', '', 'Empareje correctamente', '0', '',
            ],
            [
                'code', 'XLSX Find Error', 'Corrija bucle infinito', 'Algoritmos', '5.00', '240',
                'python', "i = 0\nwhile i < 3:\n    i += 1", 'Incremente i', '1', "i = 0\nwhile i < 3:\n    pass",
            ],
        ]);

        try {
            $xlsxUpload = new UploadedFile(
                $xlsxPath,
                'reactivos_reales.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

            $xlsxResponse = $this->actingAs($teacher)->post(route('questions.import'), [
                'csv_file' => $xlsxUpload,
            ]);
            $xlsxResponse->assertRedirect(route('questions.index'));
            $xlsxResponse->assertSessionHas('status', 'Se importaron 3 reactivos exitosamente.');

            $this->assertDatabaseHas('acs_questions', ['teacher_id' => $teacher->id, 'title' => 'XLSX MC']);
            $this->assertDatabaseHas('acs_questions', ['teacher_id' => $teacher->id, 'title' => 'XLSX Matching']);
            $xlsxCode = Question::where('teacher_id', $teacher->id)->where('title', 'XLSX Find Error')->with('questionable')->firstOrFail();
            $this->assertTrue((bool) $xlsxCode->questionable->is_find_error);
            $this->assertSame("i = 0\nwhile i < 3:\n    pass", $xlsxCode->questionable->starter_code);
        } finally {
            if (is_file($xlsxPath)) {
                @unlink($xlsxPath);
            }
        }
    }

    public function test_import_rejects_invalid_rows_atomically_without_silent_coercions(): void
    {
        $teacher = $this->createTeacher();
        $header = 'tipo,titulo,enunciado,materia,puntos,tiempo_segundos,opciones_o_definiciones,respuesta_correcta_o_solucion,instrucciones_o_rubrica,es_encuentra_error,codigo_inicial';
        $validRow = 'open,Fila Válida Del Lote,Explique la normalización 3FN,BD,2.00,60,,Elimina dependencias transitivas,,0,';

        $invalidBatches = [
            'correct_option_index no numérico' => 'multiple_choice,MC Malo,Enunciado MC,BD,2.00,60,A|B,abc,,0,',
            'correct_option_index fuera de rango' => 'multiple_choice,MC Rango,Enunciado MC,BD,2.00,60,A|B,9,,0,',
            'matching sin separador =' => 'matching,Match Malo,Relacione conceptos,BD,2.00,60,ConceptoSinIgual|B=2,,,0,',
            'puntos inválidos' => 'open,Puntos Malos,Enunciado válido,BD,abc,60,,Respuesta,,0,',
            'find_error sin codigo_inicial' => 'code,Code Sin Inicial,Encuentre el error,BD,3.00,120,php,echo 1;,Instrucción,1,',
        ];

        foreach ($invalidBatches as $case => $badRow) {
            $csv = implode("\n", [$header, $validRow, $badRow]);
            $response = $this->actingAs($teacher)
                ->from(route('questions.index'))
                ->post(route('questions.import'), [
                    'csv_file' => UploadedFile::fake()->createWithContent('lote_invalido.csv', $csv),
                ]);

            $response->assertRedirect(route('questions.index'));
            $response->assertSessionHasErrors('csv_file');
            $this->assertSame(
                0,
                Question::where('teacher_id', $teacher->id)->count(),
                "No debía insertarse ningún reactivo parcial en caso: {$case}"
            );
        }
    }

    public function test_import_and_batch_store_enforce_freemium_quota_50_atomically(): void
    {
        $freemiumTeacher = $this->createTeacher();
        $this->seedQuestions($freemiumTeacher, 48);
        $this->assertSame(48, Question::where('teacher_id', $freemiumTeacher->id)->count());

        $csvFourValid = implode("\n", [
            'tipo,titulo,enunciado,materia,puntos,tiempo_segundos,opciones_o_definiciones,respuesta_correcta_o_solucion,instrucciones_o_rubrica',
            'open,Import 49,Enunciado 49,Mat,2.00,60,,Resp 49,',
            'open,Import 50,Enunciado 50,Mat,2.00,60,,Resp 50,',
            'open,Import 51,Enunciado 51,Mat,2.00,60,,Resp 51,',
            'open,Import 52,Enunciado 52,Mat,2.00,60,,Resp 52,',
        ]);

        // 1. Importar 4 válidos teniendo 48 debe rechazarse completo (queda exactamente en 48)
        $importWeb = $this->actingAs($freemiumTeacher)
            ->from(route('questions.index'))
            ->post(route('questions.import'), [
                'csv_file' => UploadedFile::fake()->createWithContent('excede_cuota.csv', $csvFourValid),
            ]);
        $importWeb->assertSessionHasErrors('subscription');
        $this->assertSame(48, Question::where('teacher_id', $freemiumTeacher->id)->count());

        // 2. Captura rápida en lote de 4 ítems también se rechaza completa (queda exactamente en 48)
        $batchItems4 = [];
        for ($i = 1; $i <= 4; $i++) {
            $batchItems4[] = [
                'title' => "Batch {$i}",
                'stem' => "Enunciado batch {$i}",
                'question_type' => 'open',
                'default_points' => 2.0,
                'default_time_seconds' => 60,
                'reference_answer' => "Respuesta {$i}",
            ];
        }

        $batchOverQuota = $this->actingAs($freemiumTeacher)
            ->from(route('questions.batch.create'))
            ->post(route('questions.batch.store'), [
                'shared_subject_tag' => 'Matemáticas',
                'items' => $batchItems4,
            ]);
        $batchOverQuota->assertSessionHasErrors('subscription');
        $this->assertSame(48, Question::where('teacher_id', $freemiumTeacher->id)->count());

        // 3. Lote de 2 ítems exactos (48 + 2 = 50) sí procede atómicamente
        $batchOk = $this->actingAs($freemiumTeacher)->post(route('questions.batch.store'), [
            'shared_subject_tag' => 'Matemáticas',
            'items' => array_slice($batchItems4, 0, 2),
        ]);
        $batchOk->assertRedirect(route('questions.index'));
        $this->assertSame(50, Question::where('teacher_id', $freemiumTeacher->id)->count());
    }

    public function test_batch_store_and_save_and_new_preserve_context(): void
    {
        $teacher = $this->createTeacher();

        // 1. Captura rápida en lote con materia compartida y herencia
        $batchResponse = $this->actingAs($teacher)->post(route('questions.batch.store'), [
            'shared_subject_tag' => 'Cálculo Diferencial',
            'items' => [
                [
                    'title' => 'Lote MC',
                    'stem' => 'Derivada de x^2',
                    'question_type' => 'multiple_choice',
                    'default_points' => 2.0,
                    'default_time_seconds' => 60,
                    'options' => ['2x', 'x'],
                    'correct_option_index' => 0,
                ],
                [
                    'title' => 'Lote Matching',
                    'stem' => 'Relacione derivadas',
                    'question_type' => 'matching',
                    'default_points' => 3.0,
                    'default_time_seconds' => 90,
                    'concepts' => ['sin(x)', 'cos(x)'],
                    'definitions' => ['cos(x)', '-sin(x)'],
                ],
                [
                    'title' => 'Lote Open Propia Materia',
                    'stem' => 'Defina matriz identidad',
                    'question_type' => 'open',
                    'subject_tag' => 'Álgebra Lineal',
                    'default_points' => 4.0,
                    'default_time_seconds' => 120,
                    'reference_answer' => 'Matriz cuadrada con 1s en la diagonal principal.',
                ],
            ],
        ]);

        $batchResponse->assertRedirect(route('questions.index'));
        $this->assertDatabaseHas('acs_questions', [
            'teacher_id' => $teacher->id,
            'title' => 'Lote MC',
            'subject_tag' => 'Cálculo Diferencial',
        ]);
        $this->assertDatabaseHas('acs_questions', [
            'teacher_id' => $teacher->id,
            'title' => 'Lote Matching',
            'subject_tag' => 'Cálculo Diferencial',
        ]);
        $this->assertDatabaseHas('acs_questions', [
            'teacher_id' => $teacher->id,
            'title' => 'Lote Open Propia Materia',
            'subject_tag' => 'Álgebra Lineal',
        ]);

        // 2. save_and_new=1 redirige a questions.create preservando contexto
        $saveAndNewResponse = $this->actingAs($teacher)->post(route('questions.store'), [
            'title' => 'Reactivo Guardar y Nuevo',
            'stem' => 'Enunciado para flujo continuo',
            'question_type' => 'open',
            'subject_tag' => 'Cálculo Diferencial',
            'default_points' => 3.5,
            'default_time_seconds' => 150,
            'reference_answer' => 'Respuesta de referencia',
            'save_and_new' => '1',
        ]);

        $saveAndNewResponse->assertRedirect();
        $location = (string) $saveAndNewResponse->headers->get('Location');
        $this->assertSame(route('questions.create', [], false), parse_url($location, PHP_URL_PATH));
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('Cálculo Diferencial', $query['subject_tag'] ?? null);
        $this->assertSame('open', $query['question_type'] ?? null);
        $this->assertSame('150', $query['default_time_seconds'] ?? null);
        $this->assertSame('3.50', $query['default_points'] ?? null);

        $this->actingAs($teacher)->get($location)->assertOk()->assertSee('Cálculo Diferencial');
    }

    public function test_paraphrases_enforce_uniqueness_limit_10_rotation_1_plus_n_and_snapshot_immutability(): void
    {
        $teacher = $this->createTeacher();
        $question = $this->createMcQuestion($teacher, 'Redes', 'Pregunta Anti-Copia');
        $baseStem = $question->stem;

        // 1. Prohibido registrar texto igual al stem (variando espacios/mayúsculas/Unicode)
        $sameAsStem = $this->actingAs($teacher)->postJson(route('questions.paraphrase.store', $question), [
            'stem' => '  ' . mb_strtoupper($baseStem, 'UTF-8') . "\u{00A0}",
        ]);
        $sameAsStem->assertStatus(422);
        $sameAsStem->assertJsonValidationErrors(['stem']);

        // 2. Registrar 10 paráfrasis y bloquear duplicado + límite 10
        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($teacher)->post(route('questions.paraphrase.store', $question), [
                'stem' => "Redacción alterna número {$i} para mitigar copia",
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame(10, $question->paraphrases()->count());

        // Intento 11 -> bloqueado (422)
        $eleventh = $this->actingAs($teacher)->postJson(route('questions.paraphrase.store', $question), [
            'stem' => 'Redacción número 11 que supera el límite permitido',
        ]);
        $eleventh->assertStatus(422);
        $eleventh->assertJsonValidationErrors(['stem']);

        // Verificar duplicado en otro reactivo con < 10 paráfrasis
        $qWithDuplicateCheck = $this->createMcQuestion($teacher, 'Redes', 'Pregunta Duplicado Paráfrasis');
        $this->actingAs($teacher)->post(route('questions.paraphrase.store', $qWithDuplicateCheck), [
            'stem' => 'Paráfrasis única inicial',
        ])->assertSessionHasNoErrors();

        $duplicatePara = $this->actingAs($teacher)->postJson(route('questions.paraphrase.store', $qWithDuplicateCheck), [
            'stem' => "  PARÁFRASIS\u{00A0} única   INICIAL ",
        ]);
        $duplicatePara->assertStatus(422);
        $duplicatePara->assertJsonValidationErrors(['stem']);

        // 3. Rotación 1 + N cuando hay exactamente 1 paráfrasis (debe salir tanto stem base como la paráfrasis)
        $rotationQuestion = $this->createMcQuestion($teacher, 'Redes', 'Pregunta Rotación 1+N', 'Enunciado base original 1+N');
        $rotationParaphrase = $rotationQuestion->paraphrases()->create([
            'stem' => 'Paráfrasis alternativa única 1+N',
        ]);

        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Grupo Rotación',
            'access_folio' => 'ROT1001',
            'is_closed' => false,
        ]);

        $exam = Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Examen Snapshot Paráfrasis',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 2,
            'starts_at' => now()->subMinutes(30),
            'ends_at' => now()->addMinutes(30),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);
        $exam->groups()->attach($group->id);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Sección Única',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);
        $examQuestion = ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $rotationQuestion->id,
            'time_limit_seconds' => 120,
            'points_weight' => 10,
            'sort_order' => 0,
        ]);

        $attemptService = app(AttemptService::class);
        $seenBase = false;
        $seenParaphrase = false;
        $sampleAttempt = null;

        for ($s = 1; $s <= 20; $s++) {
            $student = $this->createStudent();
            $group->students()->attach($student->id, ['joined_at' => now()]);
            $attempt = $attemptService->start($exam, $student);

            $snapshotEntry = $attempt->paraphrase_snapshot_payload[(string) $examQuestion->id] ?? null;
            $this->assertIsArray($snapshotEntry);

            if ($snapshotEntry['paraphrase_id'] === null && $snapshotEntry['stem'] === $rotationQuestion->stem) {
                $seenBase = true;
            }
            if ($snapshotEntry['paraphrase_id'] === $rotationParaphrase->id && $snapshotEntry['stem'] === $rotationParaphrase->stem) {
                $seenParaphrase = true;
                $sampleAttempt ??= [$student, $attempt, $snapshotEntry['stem']];
            }
        }

        $this->assertTrue($seenBase, 'La rotación 1+N debe incluir el stem base cuando hay 1 paráfrasis.');
        $this->assertTrue($seenParaphrase, 'La rotación 1+N debe asignar la paráfrasis registrada.');
        $this->assertNotNull($sampleAttempt);

        // 4. Inmutabilidad histórica por snapshot [ALTA-09 / MEDIA-05]
        [$sampleStudent, $attempt, $frozenStem] = $sampleAttempt;
        $attemptService->submitAnswer($attempt, $examQuestion->id, ['selected_option_index' => 0]);
        $finishedAttempt = $attemptService->submitFinal($attempt->fresh());

        $storedAnswer = StudentAnswer::where('student_attempt_id', $finishedAttempt->id)->firstOrFail();
        $this->assertSame($rotationParaphrase->id, $storedAnswer->question_paraphrase_id);
        $this->assertSame($frozenStem, $storedAnswer->served_stem_snapshot);

        // Docente muta enunciado principal y reemplaza paráfrasis después del intento
        $rotationQuestion->update(['stem' => 'ENUNCIADO EDITADO POSTERIORMENTE']);
        $rotationParaphrase->update(['stem' => 'PARÁFRASIS MUTADA POSTERIORMENTE']);
        $rotationQuestion->paraphrases()->create(['stem' => 'PARÁFRASIS NUEVA POSTERIOR']);

        // Cerrar ventana del examen para habilitar revisión estudiantil
        $exam->update(['ends_at' => now()->subMinutes(5)]);

        $reviewData = app(StudentResultService::class)->review($finishedAttempt->fresh());
        $this->assertSame($frozenStem, $reviewData['questions'][0]['stem']);

        // Vista de revisión estudiantil
        $studentReviewResponse = $this->actingAs($sampleStudent)->get(route('attempts.review', $finishedAttempt));
        $studentReviewResponse->assertOk();
        $studentReviewResponse->assertSee($frozenStem);
        $studentReviewResponse->assertDontSee('ENUNCIADO EDITADO POSTERIORMENTE');
        $studentReviewResponse->assertDontSee('PARÁFRASIS MUTADA POSTERIORMENTE');

        // Vista docente de recoteje (exams/review-attempt.blade.php)
        $teacherReviewResponse = $this->actingAs($teacher)->get(route('exams.revision.show', [$exam, $finishedAttempt]));
        $teacherReviewResponse->assertOk();
        $teacherReviewResponse->assertSee($frozenStem);
        $teacherReviewResponse->assertDontSee('ENUNCIADO EDITADO POSTERIORMENTE');
        $teacherReviewResponse->assertDontSee('PARÁFRASIS MUTADA POSTERIORMENTE');
    }

    public function test_matching_creation_generates_opaque_random_ids_never_sequential(): void
    {
        $teacher = $this->createTeacher();
        $service = app(QuestionBankService::class);

        $q1 = $service->createQuestion($teacher, [
            'title' => 'Matching Opaco Servicio',
            'stem' => 'Relacione conceptos con definiciones',
            'question_type' => 'matching',
            'subject_tag' => 'Sistemas',
            'default_points' => 4.0,
            'default_time_seconds' => 120,
            'concepts' => ['CPU', 'RAM', 'SSD', 'GPU'],
            'definitions' => ['Procesador', 'Memoria Volátil', 'Almacenamiento Sólido', 'Gráficos'],
        ]);

        /** @var MatchingItem $item1 */
        $item1 = $q1->questionable;
        $leftIds1 = collect($item1->left_column_payload)->pluck('id')->all();
        $rightIds1 = collect($item1->right_column_payload)->pluck('id')->all();

        foreach ($leftIds1 as $leftId) {
            $this->assertMatchesRegularExpression('/^L_[0-9a-f]{10}$/', (string) $leftId);
            $this->assertDoesNotMatchRegularExpression('/^L\d+$/', (string) $leftId);
        }
        foreach ($rightIds1 as $rightId) {
            $this->assertMatchesRegularExpression('/^R_[0-9a-f]{10}$/', (string) $rightId);
            $this->assertDoesNotMatchRegularExpression('/^R\d+$/', (string) $rightId);
        }

        $this->assertCount(4, $item1->pairs_payload);
        foreach ($item1->pairs_payload as $pair) {
            $this->assertContains($pair['left_id'], $leftIds1);
            $this->assertContains($pair['right_id'], $rightIds1);
        }

        // Segunda creación vía HTTP debe generar IDs aleatorios distintos
        $this->actingAs($teacher)->post(route('questions.store'), [
            'title' => 'Matching Opaco HTTP',
            'stem' => 'Relacione protocolos con capas',
            'question_type' => 'matching',
            'default_points' => 3.0,
            'default_time_seconds' => 90,
            'concepts' => ['TCP', 'IP'],
            'definitions' => ['Transporte', 'Red'],
        ])->assertRedirect(route('questions.index'));

        $q2 = Question::where('teacher_id', $teacher->id)->where('title', 'Matching Opaco HTTP')->with('questionable')->firstOrFail();
        $leftIds2 = collect($q2->questionable->left_column_payload)->pluck('id')->all();
        $this->assertEmpty(array_intersect($leftIds1, $leftIds2));
    }

    public function test_duplicate_truncates_150_char_title_and_enforces_cow_and_duplicate_saas_quotas(): void
    {
        $this->seed(SubscriptionPackageSeeder::class);
        $proPackage = SubscriptionPackage::where('tier', 'pro')->firstOrFail();

        // 1. Título de 150 caracteres exactos en PostgreSQL con plan Pro (sin límite)
        $proTeacher = $this->createTeacher();
        TeacherSubscription::create([
            'teacher_id' => $proTeacher->id,
            'subscription_package_id' => $proPackage->id,
            'payment_status' => 'paid',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        $longTitle = str_repeat('Á', 150);
        $longQuestion = $this->createMcQuestion($proTeacher, 'Literatura', $longTitle);

        $dupResponse = $this->actingAs($proTeacher)->post(route('questions.duplicate', $longQuestion));
        $dupResponse->assertRedirect(route('questions.index'));

        $clone = Question::where('teacher_id', $proTeacher->id)
            ->where('id', '!=', $longQuestion->id)
            ->firstOrFail();
        $this->assertLessThanOrEqual(150, mb_strlen($clone->title, 'UTF-8'));
        $this->assertStringEndsWith(' (Copia)', $clone->title);

        // 2. Cuotas SaaS en Freemium (50) bloquean duplicate() y Copy-On-Write
        $freemiumTeacher = $this->createTeacher();
        $seeded = $this->seedQuestions($freemiumTeacher, 50);
        $linkedQuestion = $seeded[0];

        $exam = $this->createDraftExam($freemiumTeacher);
        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Sección Límite',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);
        ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $linkedQuestion->id,
            'time_limit_seconds' => 60,
            'points_weight' => 2.0,
            'sort_order' => 0,
        ]);

        // duplicate() bloqueado al llegar a 50
        $dupBlocked = $this->actingAs($freemiumTeacher)
            ->from(route('questions.index'))
            ->post(route('questions.duplicate', $linkedQuestion));
        $dupBlocked->assertSessionHasErrors('subscription');
        $this->assertSame(50, Question::where('teacher_id', $freemiumTeacher->id)->count());

        // Copy-On-Write bloqueado al llegar a 50 (ni se crea v2 ni se altera el original)
        $cowBlocked = $this->actingAs($freemiumTeacher)
            ->from(route('questions.edit', $linkedQuestion))
            ->put(route('questions.update', $linkedQuestion), [
                'title' => 'Intento CoW Sin Cuota',
                'stem' => 'Enunciado que intentaría crear v2',
                'question_type' => 'multiple_choice',
                'default_points' => 5.0,
                'default_time_seconds' => 90,
                'options' => ['Nueva A', 'Nueva B'],
                'correct_option_index' => 1,
            ]);
        $cowBlocked->assertSessionHasErrors('subscription');
        $this->assertSame(50, Question::where('teacher_id', $freemiumTeacher->id)->count());
        $this->assertSame('Reactivo Semilla 1', $linkedQuestion->fresh()->title);
    }

    public function test_exam_assembly_service_never_mixes_cow_parent_and_child_in_same_exam(): void
    {
        $teacher = $this->createTeacher();
        $bankService = app(QuestionBankService::class);
        $assemblyService = app(ExamAssemblyService::class);

        $parent = $this->createMcQuestion($teacher, 'Redes', 'Pregunta Padre CoW');

        // Examen 1 contiene al padre
        $exam1 = $this->createDraftExam($teacher);
        $section1 = ExamSection::create([
            'exam_id' => $exam1->id,
            'title' => 'Apartado 1',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);
        ExamQuestion::create([
            'exam_id' => $exam1->id,
            'exam_section_id' => $section1->id,
            'question_id' => $parent->id,
            'time_limit_seconds' => 60,
            'points_weight' => 5.0,
            'sort_order' => 0,
        ]);

        // Editar padre genera hijo CoW vinculado
        $updated = $bankService->updateQuestion($teacher, $parent, [
            'title' => 'Pregunta Hija CoW v2',
            'stem' => 'Enunciado modificado en v2',
            'question_type' => 'multiple_choice',
            'subject_tag' => 'Redes',
            'default_points' => 2.0,
            'default_time_seconds' => 60,
            'options' => ['Opción 1', 'Opción 2'],
            'correct_option_index' => 0,
        ]);
        $this->assertTrue($updated['version_created']);
        $child = $updated['question'];
        $this->assertSame($parent->id, $child->parent_question_id);

        // El examen 1 NO puede proponer ni sortear al hijo CoW porque el padre ya está en el examen
        try {
            $assemblyService->generateProposal($exam1, 'multiple_choice', 1, 'Redes');
            $this->fail('Se esperaba ValidationException al no haber candidatos fuera del linaje CoW.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('requested_quantity', $e->errors());
        }

        // Caso inverso: Examen 2 incluye al hijo -> NO puede sortear ni proponer al padre
        $exam2 = $this->createDraftExam($teacher);
        $section2 = ExamSection::create([
            'exam_id' => $exam2->id,
            'title' => 'Apartado Hijo',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);
        ExamQuestion::create([
            'exam_id' => $exam2->id,
            'exam_section_id' => $section2->id,
            'question_id' => $child->id,
            'time_limit_seconds' => 60,
            'points_weight' => 5.0,
            'sort_order' => 0,
        ]);

        try {
            $assemblyService->createSectionAndDraw($exam2, [
                'title' => 'Segundo Apartado',
                'question_type' => 'multiple_choice',
                'subject_tag' => 'Redes',
                'requested_quantity' => 1,
            ]);
            $this->fail('Se esperaba ValidationException evitando mezclar hijo CoW con padre CoW.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('requested_quantity', $e->errors());
        }
    }

    // =========================================================================
    // Auxiliares
    // =========================================================================

    private function createTeacher(): User
    {
        return User::create([
            'role' => 'teacher',
            'first_name' => 'Docente',
            'last_name' => 'Certificación',
            'email' => uniqid('teacher-cert-', true) . '@acode.test',
            'password' => 'Docente123!',
            'is_active' => true,
        ]);
    }

    private function createStudent(): User
    {
        return User::create([
            'role' => 'student',
            'first_name' => 'Alumno',
            'last_name' => 'Certificación',
            'email' => uniqid('student-cert-', true) . '@acode.test',
            'password' => 'Alumno123!',
            'is_active' => true,
        ]);
    }

    private function createMcQuestion(
        User $teacher,
        ?string $subjectTag,
        string $title,
        string $stem = 'Seleccione la respuesta correcta.'
    ): Question {
        return app(QuestionBankService::class)->createQuestion($teacher, [
            'title' => $title,
            'stem' => $stem,
            'question_type' => 'multiple_choice',
            'subject_tag' => $subjectTag,
            'default_points' => 2.0,
            'default_time_seconds' => 60,
            'options' => ['Alternativa A', 'Alternativa B'],
            'correct_option_index' => 0,
        ]);
    }

    private function createDraftExam(User $teacher): Exam
    {
        return Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Examen Borrador Certificación',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 2,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => false,
        ]);
    }

    /**
     * @return array<int, Question>
     */
    private function seedQuestions(User $teacher, int $count): array
    {
        $variant = MultipleChoiceItem::create([
            'options_payload' => [
                ['index' => 0, 'text' => 'A'],
                ['index' => 1, 'text' => 'B'],
            ],
            'correct_option_index' => 0,
        ]);

        $created = [];
        for ($i = 1; $i <= $count; $i++) {
            $created[] = Question::create([
                'teacher_id' => $teacher->id,
                'title' => "Reactivo Semilla {$i}",
                'stem' => "Enunciado semilla {$i}",
                'question_type' => 'multiple_choice',
                'default_points' => 1.0,
                'default_time_seconds' => 60,
                'questionable_type' => MultipleChoiceItem::class,
                'questionable_id' => $variant->id,
            ]);
        }

        return $created;
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function createXlsxFixture(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIdx => $row) {
            $excelRow = $rowIdx + 1;
            foreach ($row as $colIdx => $value) {
                $colLetter = chr(65 + $colIdx);
                $sheet->setCellValueExplicit("{$colLetter}{$excelRow}", (string) $value, DataType::TYPE_STRING);
            }
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'acode_fixture_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $tmpPath;
    }
}
