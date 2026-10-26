<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CodeItem;
use App\Models\MatchingItem;
use App\Models\MultipleChoiceItem;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\QuestionParaphrase;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Banco de preguntas de demo: 3 opción múltiple, 2 relación de columnas,
 * 2 abiertas y 2 de código, para poder armar un examen completo sin que
 * el profesor tenga que capturar nada a mano antes de la expo.
 */
class QuestionBankSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::where('email', 'docente.demo@acode-solutions.test')->firstOrFail();

        $this->multipleChoice($teacher, '¿Cuál es la capital de México?', [
            'Ciudad de México', 'Guadalajara', 'Monterrey', 'Puebla',
        ], 0);

        $this->multipleChoice($teacher, '¿Cuál de las siguientes es una norma de auditoría de sistemas?', [
            'COBIT', 'HTML', 'TCP/IP', 'CSS',
        ], 0);

        $question = $this->multipleChoice($teacher, '¿Qué significa SaaS?', [
            'Software as a Service', 'System as a Solution', 'Server as a Storage', 'Security as a Standard',
        ], 0);

        QuestionParaphrase::create([
            'question_id' => $question->id,
            'stem' => '¿A qué se refieren las siglas SaaS en el contexto de software?',
        ]);

        $this->matching($teacher, 'Relaciona cada concepto de auditoría con su definición.',
            [['id' => 1, 'text' => 'Riesgo'], ['id' => 2, 'text' => 'Control interno']],
            [['id' => 'a', 'text' => 'Probabilidad de que ocurra un evento adverso'], ['id' => 'b', 'text' => 'Mecanismo para mitigar un riesgo']],
            [['left_id' => 1, 'right_id' => 'a'], ['left_id' => 2, 'right_id' => 'b']],
        );

        $this->matching($teacher, 'Relaciona el rol con su responsabilidad.',
            [['id' => 1, 'text' => 'Docente'], ['id' => 2, 'text' => 'Alumno']],
            [['id' => 'a', 'text' => 'Arma y publica exámenes'], ['id' => 'b', 'text' => 'Contesta el examen asignado']],
            [['left_id' => 1, 'right_id' => 'a'], ['left_id' => 2, 'right_id' => 'b']],
        );

        $this->open($teacher, '¿Por qué es importante la auditoría de sistemas en una organización?',
            'La auditoría de sistemas evalúa los controles, riesgos y cumplimiento de los sistemas de información para proteger los activos de la organización y asegurar la integridad de los datos.');

        $this->open($teacher, 'Explica la diferencia entre un riesgo y una vulnerabilidad.',
            'El riesgo es la probabilidad de que una amenaza explote una vulnerabilidad causando un impacto negativo; la vulnerabilidad es la debilidad en sí misma que puede ser explotada.');

        $this->code($teacher, 'Encuentra el error en la siguiente función de suma en PHP.', 'php', true,
            "function suma(\$a, \$b) {\n    return \$a - \$b;\n}",
            "function suma(\$a, \$b) {\n    return \$a + \$b;\n}",
            'La función debe regresar la suma de los dos parámetros, no la resta.');

        $this->code($teacher, 'Escribe una función que regrese el factorial de un número entero positivo.', 'php', false,
            null,
            "function factorial(int \$n): int {\n    return \$n <= 1 ? 1 : \$n * factorial(\$n - 1);\n}",
            'Debe manejar correctamente el caso base (0 o 1) y ser recursiva o iterativa.');
    }

    private function multipleChoice(User $teacher, string $stem, array $options, int $correctIndex): Question
    {
        $item = MultipleChoiceItem::create([
            'options_payload' => collect($options)->values()->map(fn (string $text, int $index) => ['index' => $index, 'text' => $text])->all(),
            'correct_option_index' => $correctIndex,
        ]);

        return Question::create([
            'teacher_id' => $teacher->id,
            'subject_tag' => 'Auditoría de Sistemas',
            'title' => 'Opción múltiple',
            'stem' => $stem,
            'question_type' => 'multiple_choice',
            'default_points' => 1.0,
            'default_time_seconds' => 30,
            'questionable_type' => MultipleChoiceItem::class,
            'questionable_id' => $item->id,
        ]);
    }

    private function matching(User $teacher, string $stem, array $left, array $right, array $pairs): Question
    {
        $item = MatchingItem::create([
            'left_column_payload' => $left,
            'right_column_payload' => $right,
            'pairs_payload' => $pairs,
        ]);

        return Question::create([
            'teacher_id' => $teacher->id,
            'subject_tag' => 'Auditoría de Sistemas',
            'title' => 'Relación de columnas',
            'stem' => $stem,
            'question_type' => 'matching',
            'default_points' => 2.0,
            'default_time_seconds' => 60,
            'questionable_type' => MatchingItem::class,
            'questionable_id' => $item->id,
        ]);
    }

    private function open(User $teacher, string $stem, string $referenceAnswer): Question
    {
        $item = OpenItem::create([
            'reference_answer' => $referenceAnswer,
        ]);

        return Question::create([
            'teacher_id' => $teacher->id,
            'subject_tag' => 'Auditoría de Sistemas',
            'title' => 'Pregunta abierta',
            'stem' => $stem,
            'question_type' => 'open',
            'default_points' => 3.0,
            'default_time_seconds' => 900,
            'questionable_type' => OpenItem::class,
            'questionable_id' => $item->id,
        ]);
    }

    private function code(User $teacher, string $stem, string $language, bool $isFindError, ?string $starterCode, string $referenceSolution, string $instructions): Question
    {
        $item = CodeItem::create([
            'programming_language' => $language,
            'is_find_error' => $isFindError,
            'starter_code' => $starterCode,
            'reference_solution' => $referenceSolution,
            'instructions' => $instructions,
        ]);

        return Question::create([
            'teacher_id' => $teacher->id,
            'subject_tag' => 'Auditoría de Sistemas',
            'title' => 'Programación',
            'stem' => $stem,
            'question_type' => 'code',
            'default_points' => 4.0,
            'default_time_seconds' => 900,
            'questionable_type' => CodeItem::class,
            'questionable_id' => $item->id,
        ]);
    }
}
