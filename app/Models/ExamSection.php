<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ExamSection.php
 * Ubicación:   app/Models/ExamSection.php
 * Descripción: Modelo Eloquent de acs_exam_sections (apartados, RW-12).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSection extends Model
{
    protected $table = 'acs_exam_sections';

    protected $fillable = [
        'exam_id',
        'title',
        'question_type',
        'requested_quantity',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Exam, ExamSection>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return HasMany<ExamQuestion>
     */
    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class);
    }
}
