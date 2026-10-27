<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     PrivacyController.php
 * Ubicación:   app/Http/Controllers/Api/V1/PrivacyController.php
 * Descripción: api.v1.privacy-notice.* (MPM-V4 §6.6.3) para la app móvil:
 *              consultar el aviso vigente y si el usuario ya lo aceptó, y
 *              registrar la aceptación. acs_privacy_notices no tiene
 *              columna `title` (BD01-2026-002): se deriva de `version`
 *              porque PrivacyNoticeModel del cliente móvil lo exige
 *              (acs-exam-mobile, privacy_notice_model.dart) y no vale la
 *              pena una migración nueva solo por un título, dado que el
 *              contenido real vive en `content`.
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PrivacyAcceptance;
use App\Models\PrivacyNotice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $notice = $this->latestNotice();

        $accepted = PrivacyAcceptance::where('user_id', $request->user()->id)
            ->where('privacy_notice_id', $notice->id)
            ->exists();

        return ApiResponse::success([
            'version' => $notice->version,
            'title' => "Aviso de Privacidad {$notice->version}",
            'content' => $notice->content,
            'accepted' => $accepted,
        ]);
    }

    public function accept(Request $request): JsonResponse
    {
        $notice = $this->latestNotice();

        PrivacyAcceptance::updateOrCreate(
            ['user_id' => $request->user()->id, 'privacy_notice_id' => $notice->id],
            ['accepted_at' => now(), 'ip_address' => $request->ip()],
        );

        return ApiResponse::success(null, 'Aceptación registrada.');
    }

    private function latestNotice(): PrivacyNotice
    {
        $notice = PrivacyNotice::latest('published_at')->first();

        abort_if($notice === null, 404, 'No hay un aviso de privacidad publicado.');

        return $notice;
    }
}
