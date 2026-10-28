<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     QuestionController.php
 * Ubicación:   app/Http/Controllers/QuestionController.php
 * Descripción: Controlador delgado del Banco de Preguntas (Módulo 5, MPW-V2
 *              §6.1, §6.3): delega la creación polimórfica y las paráfrasis
 *              anti-copia a QuestionBankService.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\QuestionParaphraseStoreRequest;
use App\Http\Requests\QuestionStoreRequest;
use App\Models\Question;
use App\Services\QuestionBankService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function __construct(private readonly QuestionBankService $questionBankService)
    {
    }

    public function index(Request $request): View
    {
        $questions = $this->questionBankService->paginateForTeacher($request->user(), 20);

        return view('questions.index', ['questions' => $questions]);
    }

    public function create(): View
    {
        return view('questions.create');
    }

    public function store(QuestionStoreRequest $request): RedirectResponse
    {
        $this->questionBankService->createQuestion($request->user(), $request->validated());

        return redirect()->route('questions.index')->with('status', 'Reactivo creado.');
    }

    /**
     * Genera (o guarda) una paráfrasis del reactivo: redacción distinta,
     * misma respuesta (RF-BAN-010, anti-copia).
     */
    public function storeParaphrase(QuestionParaphraseStoreRequest $request, Question $question): RedirectResponse
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $data = $request->validated();
        $this->questionBankService->createParaphrase($question, (string) $data['stem']);

        return back()->with('status', 'Paráfrasis guardada.');
    }
}
