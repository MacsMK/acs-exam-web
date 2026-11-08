<?php

use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Canal privado de monitoreo docente en vivo por examen (RW-12, RW-16, RNF-SEG-004).
 */
Broadcast::channel('exam.{examId}', function (User $user, int $examId): bool {
    if ($user->role !== 'teacher') {
        return false;
    }

    $exam = Exam::withoutGlobalScopes()->find($examId);

    return $exam !== null && (int) $exam->teacher_id === (int) $user->id;
});
