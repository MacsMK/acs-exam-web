<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AdminTermsController.php
 * Ubicación:      app/Http/Controllers/AdminTermsController.php
 * Requerimiento:  RF-ADM-015
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Permite publicar y consultar versiones de los términos institucionales.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\PublishTermsRequest;
use App\Models\TermsVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AdminTermsController extends Controller
{
    /**
     * Lista el historial de versiones ordenado por entrada en vigor.
     *
     * @return View Vista que contiene los datos autorizados.
     */
    public function index(): View
    {
        return view('admin.terms.index', ['versions' => TermsVersion::orderByDesc('effective_at')->paginate(15)]);
    }

    /**
     * Publica una nueva versión conservando las entradas anteriores.
     *
     * @param PublishTermsRequest $request Solicitud web validada.
     * @return RedirectResponse Redirección posterior al guardado.
     *
     * @throws ValidationException Si la versión o la vigencia ya existe.
     */
    public function store(PublishTermsRequest $request): RedirectResponse
    {
        try {
            TermsVersion::create($request->validated() + ['published_by' => $request->user()->id]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['version' => 'La versión o la fecha de vigencia ya está registrada']);
        }

        return to_route('admin.terms.index')->with('status', 'Versión publicada');
    }
}
