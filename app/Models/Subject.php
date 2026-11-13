<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        Subject.php
 * Ubicación:      app/Models/Subject.php
 * Requerimiento:  RF-BAN (Normalización de Asignaturas / Materias, Módulo 5)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 12/11/2026
 * Descripción:    Modelo Eloquent de acs_subjects: catálogo normalizado de
 *                 materias por docente con aislamiento multi-tenant
 *                 (MPW-V2 §2.3.4, §6.6) y utilidades puras de limpieza y
 *                 normalización canónica Unicode/ASCII.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

use App\Models\Scopes\TenantTeacherScope;

/**
 * Asignatura o materia normalizada perteneciente al catálogo de un docente.
 *
 * @package App\Models
 */
#[ScopedBy([TenantTeacherScope::class])]
class Subject extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_subjects';

    /**
     * Columnas asignables en masa (MPW-V2 §2.3.1).
     *
     * @var list<string>
     */
    protected $fillable = [
        'teacher_id',
        'name',
        'normalized_name',
    ];

    /**
     * Resuelve el enlace de ruta omitiendo únicamente TenantTeacherScope para
     * permitir que la política/controlador emita 403 Forbidden ante IDOR.
     *
     * @param mixed       $value Valor recibido en la ruta.
     * @param string|null $field Columna de búsqueda alternativa.
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->withoutGlobalScope(TenantTeacherScope::class)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    /**
     * Docente propietario de la asignatura.
     *
     * @return BelongsTo<User, Subject>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Reactivos del banco vinculados a esta asignatura.
     *
     * @return HasMany<Question>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'subject_id');
    }

    /**
     * Apartados de examen que filtran por esta asignatura.
     *
     * @return HasMany<ExamSection>
     */
    public function examSections(): HasMany
    {
        return $this->hasMany(ExamSection::class, 'subject_id');
    }

    /**
     * Scope local de aislamiento multi-docente (MPW-V2 §2.3.4, §6.6).
     *
     * @param Builder<Subject> $query     Constructor de la consulta Eloquent.
     * @param int              $teacherId Identificador del docente propietario.
     * @return Builder<Subject>
     */
    public function scopeForTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where($this->qualifyColumn('teacher_id'), $teacherId);
    }

    /**
     * Limpia el nombre legible eliminando caracteres Unicode invisibles/no-breaking,
     * colapsando espacios consecutivos a uno solo y recortando extremos.
     *
     * @param string|null $value Nombre sin procesar ingresado por el docente.
     * @return string|null Nombre legible limpio o null si queda vacío.
     */
    public static function cleanDisplayName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $withoutInvisible = str_replace(["\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}"], '', $value);
        $withStandardSpaces = str_replace("\u{00A0}", ' ', $withoutInvisible);
        $collapsed = preg_replace('/\s+/u', ' ', $withStandardSpaces);
        $trimmed = trim($collapsed ?? $withStandardSpaces);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Construye la llave canónica de comparación eliminando caracteres invisibles,
     * acentos/diacríticos, mayúsculas y signos o espacios múltiples.
     *
     * @param string|null $value Nombre de materia a normalizar.
     * @return string Llave normalizada en minúsculas ASCII o cadena vacía.
     */
    public static function normalizeName(?string $value): string
    {
        $clean = self::cleanDisplayName($value);
        if ($clean === null) {
            return '';
        }

        $lower = mb_strtolower($clean, 'UTF-8');
        $ascii = Str::ascii($lower);
        $collapsed = preg_replace('/[^\p{L}\p{N}+#]+/u', ' ', $ascii);

        return trim($collapsed ?? $ascii);
    }

    /**
     * Conversión de atributos (MPW-V2 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'teacher_id' => 'integer',
        ];
    }
}
