<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ExamController.php
 * Ubicación:   app/Http/Controllers/Api/V1/ExamController.php
 * Descripción: Exámenes publicados y asignados al alumno autenticado
 *              (para que la app móvil muestre su lista de exámenes).
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function assignedToMe(Request $request): JsonResponse
    {
        $student = $request->user();

        $exams = Exam::where('is_published', true)
            ->whereHas('groups.students', fn ($q) => $q->where('acs_users.id', $student->id))
            ->with('sections')
            ->get();

        return response()->json(['exams' => $exams]);
    }
}
