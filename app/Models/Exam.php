<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        Exam.php
 * Ubicación:      app/Models/Exam.php
 * Requerimiento:  RF-EXA (Armado y Aplicación de Exámenes, RW-12 a RW-24)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.4.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_exams con relaciones, scopes de
 *                 aislamiento multi-docente, filtros de tablero y métodos de
 *                 ciclo de vida operativo (MPW-V3 §2.3.4, §6.6).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Scopes\TenantTeacherScope;

/**
 * Examen configurado y armado por un docente.
 *
 * @package App\Models
 */
#[ScopedBy([TenantTeacherScope::class])]
class Exam extends Model
{
    /**
     * Estados operativos calculados del ciclo de vida del examen.
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V3 §3.3).
     *
     * @var string
     */
    protected $table = 'acs_exams';

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'teacher_id',
        'title',
        'description',
        'grading_scale',
        'randomize_question_order',
        'result_visibility_mode',
        'require_webcam',
        'max_allowed_incidents',
        'starts_at',
        'ends_at',
        'share_token',
        'is_published',
    ];

    /**
     * Resuelve el enlace de ruta sin scopes globales para permitir que la capa de
     * políticas/controlador emita 403 Forbidden ante intentos de IDOR (MPW-V3 §6.6).
     *
     * @param mixed       $value Valor recibido en la ruta.
     * @param string|null $field Columna de búsqueda alternativa.
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->withoutGlobalScopes()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Docente autor del examen.
     *
     * @return BelongsTo<User, Exam>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Grupos a los que se asignó este examen.
     *
     * @return BelongsToMany<Group>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'acs_exam_group');
    }

    /**
     * Apartados del examen (RW-12).
     *
     * @return HasMany<ExamSection>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class);
    }

    /**
     * Reactivos sorteados/aceptados en el armado (tabla enriquecida).
     *
     * @return HasMany<ExamQuestion>
     */
    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class);
    }

    /**
     * Intentos de alumnos sobre este examen.
     *
     * @return HasMany<StudentAttempt>
     */
    public function studentAttempts(): HasMany
    {
        return $this->hasMany(StudentAttempt::class);
    }

    /**
     * Scope local de aislamiento multi-docente (MPW-V3 §2.3.4, §6.6).
     *
     * @param Builder<Exam> $query     Constructor de la consulta Eloquent.
     * @param int           $teacherId Identificador del docente propietario.
     * @return Builder<Exam>
     */
    public function scopeForTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where($this->qualifyColumn('teacher_id'), $teacherId);
    }

    /**
     * Scope local de aislamiento multi-docente con filtros de búsqueda, estado
     * operativo y grupo asignado, compatible con PostgreSQL y SQLite.
     *
     * @param Builder<Exam>        $query     Constructor de la consulta Eloquent.
     * @param int                  $teacherId Identificador del docente propietario.
     * @param array<string, mixed> $filters   Filtros opcionales (search, status, group_id).
     * @return Builder<Exam>
     */
    public function scopeForTeacherFilter(Builder $query, int $teacherId, array $filters = []): Builder
    {
        $query->forTeacher($teacherId);

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $driver = $query->getConnection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';
            $pattern = '%' . $search . '%';
            $normalized = Subject::normalizeName($search);
            $titleColumn = $this->qualifyColumn('title');
            $descriptionColumn = $this->qualifyColumn('description');

            $query->where(function (Builder $subQuery) use (
                $driver,
                $likeOperator,
                $pattern,
                $normalized,
                $titleColumn,
                $descriptionColumn
            ): void {
                $subQuery->where($titleColumn, $likeOperator, $pattern)
                    ->orWhere($descriptionColumn, $likeOperator, $pattern);

                if ($normalized !== '') {
                    $normalizedPattern = '%' . $normalized . '%';
                    $subQuery
                        ->orWhereRaw(
                            $this->foldAccentsSqlExpression($titleColumn, $driver) . ' LIKE ?',
                            [$normalizedPattern]
                        )
                        ->orWhereRaw(
                            $this->foldAccentsSqlExpression($descriptionColumn, $driver) . ' LIKE ?',
                            [$normalizedPattern]
                        );
                }
            });
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '' && $status !== 'all') {
            $now = now();

            match ($status) {
                self::STATUS_DRAFT => $query->where($this->qualifyColumn('is_published'), false),
                self::STATUS_SCHEDULED, 'published_upcoming' => $query
                    ->where($this->qualifyColumn('is_published'), true)
                    ->where($this->qualifyColumn('starts_at'), '>', $now)
                    ->where($this->qualifyColumn('ends_at'), '>', $now),
                self::STATUS_ACTIVE, 'published_active' => $query
                    ->where($this->qualifyColumn('is_published'), true)
                    ->where($this->qualifyColumn('starts_at'), '<=', $now)
                    ->where($this->qualifyColumn('ends_at'), '>', $now),
                self::STATUS_CLOSED => $query
                    ->where($this->qualifyColumn('is_published'), true)
                    ->where($this->qualifyColumn('ends_at'), '<=', $now),
                'pending_review' => $query->whereHas('studentAttempts', static function (Builder $attemptQuery): void {
                    $attemptQuery->where('status', 'submitted')
                        ->where('pending_ai_count', '>', 0);
                }),
                default => null,
            };
        }

        if (! empty($filters['group_id']) && is_numeric($filters['group_id'])) {
            $groupId = (int) $filters['group_id'];
            if ($groupId > 0) {
                $query->whereHas('groups', static function (Builder $groupQuery) use ($groupId): void {
                    $groupQuery->where('acs_groups.id', $groupId);
                });
            }
        }

        return $query;
    }

    /**
     * Scope local para filtrar exámenes publicados (MPW-V3 §2.3.4).
     *
     * @param Builder<Exam> $query Constructor de la consulta Eloquent.
     * @return Builder<Exam>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_published'), true);
    }

    /**
     * Scope local para filtrar exámenes publicados y vigentes en ventana de tiempo (MPW-V3 §2.3.4).
     *
     * @param Builder<Exam> $query Constructor de la consulta Eloquent.
     * @return Builder<Exam>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where($this->qualifyColumn('is_published'), true)
            ->where($this->qualifyColumn('starts_at'), '<=', $now)
            ->where($this->qualifyColumn('ends_at'), '>=', $now);
    }

    /**
     * Indica si la ventana de aplicación del examen ya dio inicio.
     *
     * @return bool
     */
    public function hasStartedWindow(): bool
    {
        return $this->starts_at !== null && $this->starts_at->lte(now());
    }

    /**
     * Zona horaria con la que se interpretan las fechas y horas de los exámenes,
     * para mostrarla a docentes y estudiantes (RF-EXA-018).
     */
    public static function timezoneLabel(): string
    {
        $timezone = (string) config('app.timezone');
        $name = $timezone === 'America/Mexico_City' ? 'Hora del centro de México' : $timezone;

        return sprintf('%s (UTC%s)', $name, now()->format('P'));
    }

    /**
     * Tiempo máximo del examen para el alumno, en minutos: suma de los tiempos
     * por pregunta (RF-EXA-013). El sistema no tiene tiempo total (RF-EXA-014).
     */
    public function maxDurationMinutes(): int
    {
        return (int) ceil(((int) $this->examQuestions()->sum('time_limit_seconds')) / 60);
    }

    /**
     * Indica si la ventana de aplicación del examen ya concluyó o fue cerrada.
     *
     * @return bool
     */
    public function isWindowClosed(): bool
    {
        return $this->ends_at !== null && $this->ends_at->lte(now());
    }

    /**
     * Determina el estado operativo calculado del examen para tablero y acciones.
     *
     * @return string Uno de: draft | scheduled | active | closed.
     */
    public function operationalStatus(): string
    {
        if (! $this->is_published) {
            return self::STATUS_DRAFT;
        }

        if ($this->isWindowClosed()) {
            return self::STATUS_CLOSED;
        }

        if (! $this->hasStartedWindow()) {
            return self::STATUS_SCHEDULED;
        }

        return self::STATUS_ACTIVE;
    }

    /**
     * Indica si el examen admite modificaciones estructurales (apartados y reactivos)
     * sin comprometer intentos registrados ni exámenes en curso.
     *
     * @return bool
     */
    public function supportsStructuralEdit(): bool
    {
        $hasAttempts = array_key_exists('student_attempts_count', $this->attributes)
            ? (int) $this->attributes['student_attempts_count'] > 0
            : ($this->relationLoaded('studentAttempts')
                ? $this->studentAttempts->isNotEmpty()
                : $this->studentAttempts()->exists());

        if ($hasAttempts) {
            return false;
        }

        if (! $this->is_published) {
            return true;
        }

        return ! $this->hasStartedWindow() && ! $this->isWindowClosed();
    }

    /**
     * Construye una expresión SQL portable (PostgreSQL y SQLite/MySQL) para
     * búsqueda textual insensible a mayúsculas y acentos en español.
     *
     * @param string $column Columna SQL calificada.
     * @param string $driver Nombre del driver de base de datos activo.
     * @return string
     */
    private function foldAccentsSqlExpression(string $column, string $driver): string
    {
        if ($driver === 'pgsql') {
            return "translate(lower(COALESCE({$column}, '')), 'áéíóúàèìòùäëïöüâêîôûãõñçÁÉÍÓÚÀÈÌÒÙÄËÏÖÜÂÊÎÔÛÃÕÑÇ', 'aeiouaeiouaeiouaeiouaoncaeiouaeiouaeiouaeiouaonc')";
        }

        $expr = "LOWER(COALESCE({$column}, ''))";
        $replacements = [
            'Á' => 'a',
            'á' => 'a',
            'É' => 'e',
            'é' => 'e',
            'Í' => 'i',
            'í' => 'i',
            'Ó' => 'o',
            'ó' => 'o',
            'Ú' => 'u',
            'ú' => 'u',
            'Ü' => 'u',
            'ü' => 'u',
            'Ñ' => 'n',
            'ñ' => 'n',
        ];

        foreach ($replacements as $from => $to) {
            $expr = "REPLACE({$expr}, '{$from}', '{$to}')";
        }

        return $expr;
    }

    /**
     * Conversión de atributos (MPW-V3 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'randomize_question_order' => 'boolean',
            'require_webcam' => 'boolean',
            'is_published' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
