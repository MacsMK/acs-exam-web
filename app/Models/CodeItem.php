<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        CodeItem.php
 * Ubicación:      app/Models/CodeItem.php
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico, Variante 4)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.2.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_code_items (variante polimórfica de
 *                 programación / código fuente, Módulo 5).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Variante de programación vinculada polimórficamente a Question.
 *
 * @package App\Models
 */
class CodeItem extends Model
{
    /**
     * Extensión de archivo por lenguaje (clave en minúsculas). Fuente única para
     * el detalle del reactivo y la vista previa del formulario.
     *
     * @var array<string, string>
     */
    public const FILE_EXTENSIONS = [
        'python' => 'py',
        'py' => 'py',
        'javascript' => 'js',
        'js' => 'js',
        'typescript' => 'ts',
        'ts' => 'ts',
        'php' => 'php',
        'java' => 'java',
        'sql' => 'sql',
        'c' => 'c',
        'c++' => 'cpp',
        'cpp' => 'cpp',
        'c#' => 'cs',
        'csharp' => 'cs',
        'go' => 'go',
        'golang' => 'go',
    ];

    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V3 §3.3).
     *
     * @var string
     */
    protected $table = 'acs_code_items';

    /**
     * Columnas asignables en masa (MPW-V3 §2.3.1).
     *
     * @var list<string>
     */
    protected $fillable = [
        'programming_language',
        'is_find_error',
        'starter_code',
        'reference_solution',
        'instructions',
    ];

    /**
     * Reactivo genérico asociado (lado inverso del morphTo).
     *
     * @return MorphOne<Question, CodeItem>
     */
    public function question(): MorphOne
    {
        return $this->morphOne(Question::class, 'questionable');
    }

    /**
     * Extensión de archivo del lenguaje configurado; "txt" si no está en el catálogo.
     */
    public function fileExtension(): string
    {
        $language = mb_strtolower(trim((string) $this->programming_language));

        return self::FILE_EXTENSIONS[$language] ?? 'txt';
    }

    /**
     * Conversión de atributos (MPW-V3 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_find_error' => 'boolean',
        ];
    }
}
