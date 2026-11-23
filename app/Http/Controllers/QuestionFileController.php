<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionFileController.php
 * Ubicación:      app/Http/Controllers/QuestionFileController.php
 * Requerimiento:  RF-BAN-012, RF-BAN-013, RNF-SEG-008
 * Actividad:      ACT-WEB-026
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 22/11/2026
 * Descripción:    Entrega la imagen o el PDF de una pregunta desde el disco
 *                 privado, en web y en la API v1, solo a quien tiene permiso.
 *                 Resuelve también preguntas eliminadas del banco que siguen
 *                 en exámenes (RF-BAN-011).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Scopes\TenantTeacherScope;
use App\Services\QuestionFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionFileController extends Controller
{
    public function __invoke(Request $request, string $question, string $kind, QuestionFileService $files): StreamedResponse
    {
        $model = Question::withTrashed()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->findOrFail((int) $question);

        abort_unless($files->canView($request->user(), $model), 403);

        $path = $model->{QuestionFileService::COLUMNS[$kind === 'imagen' ? 'image' : $kind]};
        $disk = Storage::disk(QuestionFileService::DISK);

        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path, $kind === 'pdf' ? 'material-de-apoyo.pdf' : null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
