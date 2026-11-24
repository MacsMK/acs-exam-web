<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * Reactivos y respuestas ficticios para los recorridos de demostración.
 */
final class GeminiDemoQuestions
{
    public const PROGRAMMING = 'Programación';

    public const DATABASES = 'Bases de datos';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function questions(): array
    {
        return [
            ...self::withDefaults(self::PROGRAMMING, [
                'prog-loop' => [
                    'question_type' => 'multiple_choice',
                    'title' => 'Iteración sobre colecciones',
                    'stem' => 'En PHP, ¿qué estructura recorre directamente los elementos de un arreglo?',
                    'options' => ['switch', 'foreach', 'try', 'match'],
                    'correct_option_index' => 1,
                ],
                'prog-result' => [
                    'question_type' => 'multiple_choice',
                    'title' => 'Acumulación en un bucle',
                    'stem' => 'Un acumulador comienza en cero. Un bucle suma solo los números impares de [2, 3, 4, 5]. ¿Cuál es el resultado?',
                    'options' => ['5', '14', '8', '6'],
                    'correct_option_index' => 2,
                ],
                'prog-types' => [
                    'question_type' => 'multiple_choice',
                    'title' => 'Tipos de datos',
                    'stem' => '¿Qué tipo de dato representa el resultado de una comprobación que puede ser verdadera o falsa?',
                    'options' => ['Booleano', 'Entero', 'Cadena', 'Arreglo'],
                    'correct_option_index' => 0,
                ],
                'prog-operators' => [
                    'question_type' => 'multiple_choice',
                    'title' => 'Operadores lógicos',
                    'stem' => '¿Qué operador lógico exige que sus dos condiciones sean verdaderas?',
                    'options' => ['Negación', 'O', 'Igualdad', 'Y'],
                    'correct_option_index' => 3,
                ],
                'prog-structures' => [
                    'question_type' => 'matching',
                    'title' => 'Estructuras de datos',
                    'stem' => 'Relaciona cada estructura de datos con su comportamiento.',
                    'concepts' => ['Pila', 'Cola', 'Diccionario'],
                    'definitions' => ['Sale primero el dato más reciente', 'Sale primero el dato más antiguo', 'Recupera valores mediante claves'],
                ],
                'prog-controls' => [
                    'question_type' => 'matching',
                    'title' => 'Control del programa',
                    'stem' => 'Relaciona cada instrucción de PHP con su efecto al ejecutarse.',
                    'concepts' => ['break', 'continue', 'return'],
                    'definitions' => ['Sale del bucle actual', 'Pasa a la siguiente iteración', 'Finaliza la función y puede devolver un valor'],
                ],
                'prog-pure' => [
                    'question_type' => 'open',
                    'title' => 'Funciones puras',
                    'stem' => 'Explica qué es una función pura y da un ejemplo.',
                    'reference_answer' => 'Con los mismos argumentos devuelve siempre el mismo resultado y no produce efectos secundarios. Una función que recibe dos números y devuelve su suma es un ejemplo.',
                    'evaluation_rubric' => 'Busca determinismo, ausencia de efectos secundarios y un ejemplo coherente. Acepta explicaciones equivalentes en palabras del estudiante.',
                ],
                'prog-validation' => [
                    'question_type' => 'open',
                    'title' => 'Validación de entradas',
                    'stem' => 'Explica por qué el servidor debe validar un formulario aunque el navegador ya valide sus campos.',
                    'reference_answer' => 'El servidor recibe solicitudes de distintos clientes y debe garantizar sus propias reglas. La validación en el navegador mejora la experiencia y la validación en el servidor protege la consistencia de los datos.',
                    'evaluation_rubric' => 'Distingue la validación de interfaz de la garantía de integridad que corresponde al servidor.',
                ],
                'prog-sum-even' => [
                    'question_type' => 'code',
                    'title' => 'Suma de números pares',
                    'stem' => 'Escribe una función PHP que reciba un arreglo de enteros y devuelva la suma de los pares. Para un arreglo vacío debe devolver cero.',
                    'programming_language' => 'php',
                    'reference_solution' => <<<'PHP'
function sumarPares(array $numeros): int
{
    $total = 0;
    foreach ($numeros as $numero) {
        if ($numero % 2 === 0) {
            $total += $numero;
        }
    }

    return $total;
}
PHP,
                    'instructions' => 'Devuelve el resultado sin imprimirlo. Se aceptan soluciones equivalentes.',
                    'evaluation_rubric' => 'Califica el filtro de paridad, la acumulación y el resultado cero para la entrada vacía. Acepta enteros negativos y cero.',
                ],
                'prog-maximum' => [
                    'question_type' => 'code',
                    'title' => 'Máximo en una lista',
                    'stem' => 'Escribe una función PHP que devuelva el mayor elemento de un arreglo no vacío de enteros.',
                    'programming_language' => 'php',
                    'reference_solution' => 'function mayor(array $numeros): int { return max($numeros); }',
                    'instructions' => 'La función debe resolver también listas formadas exclusivamente por números negativos.',
                    'evaluation_rubric' => 'Acepta max o un recorrido equivalente. Una solución que parte de cero falla cuando todos los valores son negativos.',
                ],
            ]),
            ...self::withDefaults(self::DATABASES, [
                'db-is-null' => [
                    'question_type' => 'multiple_choice',
                    'title' => 'Valores nulos en SQL',
                    'stem' => '¿Qué condición selecciona únicamente las filas cuya columna fecha_entrega contiene un valor nulo?',
                    'options' => ['fecha_entrega = NULL', 'fecha_entrega = 0', 'fecha_entrega IS NULL', "fecha_entrega = ''"],
                    'correct_option_index' => 2,
                ],
                'db-count' => [
                    'question_type' => 'multiple_choice',
                    'title' => 'Conteo de filas',
                    'stem' => '¿Qué expresión cuenta todas las filas, incluso cuando algún campo de la fila contiene un valor nulo?',
                    'options' => ['COUNT(*)', 'SUM(*)', 'COUNT(campo)', 'MIN(campo)'],
                    'correct_option_index' => 0,
                ],
                'db-unique' => [
                    'question_type' => 'multiple_choice',
                    'title' => 'Restricción de unicidad',
                    'stem' => 'La tabla de cursos ya tiene su clave primaria y cada código de curso debe ser distinto. ¿Qué restricción añadirías a la columna codigo?',
                    'options' => ['DEFAULT', 'UNIQUE', 'FOREIGN KEY', 'CHECK (1 = 1)'],
                    'correct_option_index' => 1,
                ],
                'db-order' => [
                    'question_type' => 'multiple_choice',
                    'title' => 'Orden de resultados',
                    'stem' => '¿Qué cláusula de SQL ordena las filas por la fecha más reciente primero?',
                    'options' => ['GROUP BY fecha', 'ORDER BY fecha ASC', 'WHERE fecha DESC', 'ORDER BY fecha DESC'],
                    'correct_option_index' => 3,
                ],
                'db-constraints' => [
                    'question_type' => 'matching',
                    'title' => 'Restricciones relacionales',
                    'stem' => 'Relaciona cada restricción con su propósito.',
                    'concepts' => ['PRIMARY KEY', 'FOREIGN KEY', 'NOT NULL'],
                    'definitions' => ['Identifica una fila de forma única', 'Mantiene una referencia a otra fila', 'Exige un valor en la columna'],
                ],
                'db-clauses' => [
                    'question_type' => 'matching',
                    'title' => 'Cláusulas de consulta',
                    'stem' => 'Relaciona cada cláusula SQL con su operación.',
                    'concepts' => ['WHERE', 'GROUP BY', 'ORDER BY'],
                    'definitions' => ['Filtra filas según un predicado', 'Agrupa filas para aplicar agregaciones', 'Establece el orden del resultado'],
                ],
                'db-primary-key' => [
                    'question_type' => 'open',
                    'title' => 'Claves primarias y foráneas',
                    'stem' => 'Explica la diferencia entre una clave primaria y una clave foránea usando el ejemplo de grupos y alumnos.',
                    'reference_answer' => 'Una clave primaria identifica una fila de forma única. Una clave foránea referencia una clave de otra tabla. Por ejemplo, en una tabla de inscripciones, grupo_id puede referenciar el identificador de la tabla grupos.',
                    'evaluation_rubric' => 'La explicación distingue identificación de referencia y aplica la distinción al ejemplo. Acepta una tabla intermedia de inscripciones.',
                ],
                'db-transactions' => [
                    'question_type' => 'open',
                    'title' => 'Atomicidad de una transacción',
                    'stem' => 'Explica cómo ayuda una transacción cuando se registra una inscripción y se actualiza el número de lugares disponibles.',
                    'reference_answer' => 'Ambos cambios se confirman juntos. Si uno falla, se revierte la transacción para evitar una inscripción sin lugar reservado o un lugar ocupado sin inscripción.',
                    'evaluation_rubric' => 'Busca la idea de confirmar o revertir el conjunto de cambios y relacionarla con la consistencia del ejemplo.',
                ],
                'db-inner-join' => [
                    'question_type' => 'code',
                    'title' => 'Consulta de inscripciones',
                    'stem' => 'Con las tablas alumnos(id, nombre) e inscripciones(alumno_id, grupo_id), escribe una consulta que devuelva el nombre de los alumnos inscritos en el grupo 7.',
                    'programming_language' => 'sql',
                    'reference_solution' => <<<'SQL'
SELECT a.nombre
FROM alumnos AS a
INNER JOIN inscripciones AS i ON i.alumno_id = a.id
WHERE i.grupo_id = 7;
SQL,
                    'instructions' => 'Incluye únicamente los nombres. Puedes usar JOIN o una subconsulta equivalente.',
                    'evaluation_rubric' => 'La solución debe vincular la inscripción con el alumno, filtrar el grupo 7 y devolver el nombre.',
                ],
                'db-group-count' => [
                    'question_type' => 'code',
                    'title' => 'Conteo de inscripciones por grupo',
                    'stem' => 'Con la tabla inscripciones(alumno_id, grupo_id), escribe una consulta que devuelva el identificador y la cantidad de inscripciones de cada grupo con al menos una inscripción.',
                    'programming_language' => 'sql',
                    'reference_solution' => <<<'SQL'
SELECT grupo_id, COUNT(*) AS total
FROM inscripciones
GROUP BY grupo_id;
SQL,
                    'instructions' => 'Devuelve una fila por grupo. Puedes elegir el alias de la columna con el total.',
                    'evaluation_rubric' => 'Requiere agrupar por grupo_id y contar las filas de cada grupo.',
                ],
            ]),
        ];
    }

    /**
     * Respuestas completas, parciales y vacías, en ese orden.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function responses(): array
    {
        return [
            'prog-pure' => [
                'Con los mismos datos siempre entrega el mismo resultado y no modifica variables externas. Por ejemplo, sumar dos argumentos y devolver el total.',
                'Es una función que devuelve un valor repetible para la misma entrada.',
                '',
            ],
            'prog-validation' => [
                'El navegador ayuda a detectar errores pronto. El servidor vuelve a validar para aplicar sus reglas a todas las solicitudes y conservar datos consistentes.',
                'Sirve para avisar al usuario cuando una entrada tiene un error.',
                '',
            ],
            'prog-sum-even' => [
                'function sumarPares(array $lista): int { return array_sum(array_filter($lista, fn (int $n): bool => $n % 2 === 0)); }',
                'function sumarPares(array $lista): int { return array_sum($lista); }',
                '',
            ],
            'prog-maximum' => [
                'function mayor(array $lista): int { return max($lista); }',
                'function mayor(array $lista): int { $mayor = 0; foreach ($lista as $n) { $mayor = max($mayor, $n); } return $mayor; }',
                '',
            ],
            'db-primary-key' => [
                'La clave primaria distingue una fila del resto. La foránea apunta a una clave de otra tabla. Por ejemplo, inscripciones.grupo_id apunta a grupos.id.',
                'La clave primaria identifica una fila de la tabla.',
                '',
            ],
            'db-transactions' => [
                'Se confirman los dos cambios juntos. Si la actualización de lugares falla, se deshace la inscripción y quedan los datos consistentes.',
                'Una transacción sirve para agrupar varias operaciones.',
                '',
            ],
            'db-inner-join' => [
                'SELECT a.nombre FROM alumnos a JOIN inscripciones i ON i.alumno_id = a.id WHERE i.grupo_id = 7;',
                'SELECT a.nombre FROM alumnos a JOIN inscripciones i ON i.alumno_id = a.id;',
                '',
            ],
            'db-group-count' => [
                'SELECT grupo_id, COUNT(*) AS total FROM inscripciones GROUP BY grupo_id;',
                'SELECT COUNT(*) AS total FROM inscripciones;',
                '',
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $questions
     * @return array<string, array<string, mixed>>
     */
    private static function withDefaults(string $subject, array $questions): array
    {
        $settings = [
            'multiple_choice' => [2.0, 60],
            'matching' => [3.0, 120],
            'open' => [4.0, 180],
            'code' => [6.0, 300],
        ];

        foreach ($questions as $id => $question) {
            [$points, $seconds] = $settings[$question['question_type']];
            $questions[$id] = [
                ...$question,
                'subject_tag' => $subject,
                'default_points' => $points,
                'default_time_seconds' => $seconds,
            ];
        }

        return $questions;
    }
}
