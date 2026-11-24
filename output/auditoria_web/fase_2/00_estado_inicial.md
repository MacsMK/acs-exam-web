# Estado inicial de la Fase 2

2026-11-23T12:56:13-06:00 · Ciudad de México

Rama `feature/cumplimiento-requerimientos`. Commit `e0a89af1d774769e1d00deb5423329f632199944`.

```text
On branch feature/cumplimiento-requerimientos
Your branch is up to date with 'origin/feature/cumplimiento-requerimientos'.

Untracked files:
  (use "git add <file>..." to include in what will be committed)
	output/

nothing added to commit but untracked files present (use "git add" to track)
```

| Fuente | SHA-256 observada | Comparación con §3 del traspaso |
|---|---|---|
| Lista_maestra_requerimientos.xlsx | `45711e75f798b4f88dd78cf0d0eda3e33ee8a9215e80e65d4cf6e7c4cf36f219` | Coincide |
| Manual de Calidad (MC-V3).pdf | `0860ff19ccf01ed17c5309a395f81f1ebf989ea4e1a3f04908944e15eee61184` | Coincide |
| Manual de Desarrollo (MD-V3).pdf | `b5b65b1de51e3d524794702c2f62f3b1daffe7df45a65adced6dabfbc12e7adf` | Coincide |
| Manual de Programación Web (MPW-V3).pdf | `24c8a50c2afa862630cd8e1126f4855c1b1bb6a2d597597b9a2b943bd0524fd5` | Coincide |
| Manual de Base de Datos (MBD-V3).pdf | `3f0ee89f23d34f2c0f29784d14c8ccb851533a063a5a8d64b6955cb720948783` | Coincide |

## Comprobación de continuación · 2026-11-23T18:53:35-06:00

Rama `feature/cumplimiento-requerimientos`. Commit `e0a89af1d774769e1d00deb5423329f632199944`. El commit coincide con el corte inicial. El árbol de trabajo contiene cambios locales en archivos que sirven de evidencia para la matriz. Las citas de los módulos cerrados corresponden al commit auditado.

```text
On branch feature/cumplimiento-requerimientos
Your branch is up to date with 'origin/feature/cumplimiento-requerimientos'.

Changes not staged for commit:
  (use "git add <file>..." to update what will be committed)
  (use "git restore <file>..." to discard changes in working directory)
	modified:   .env.example
	modified:   README.md
	modified:   app/Http/Controllers/Api/V1/AttemptController.php
	modified:   app/Http/Controllers/Api/V1/AuthController.php
	modified:   app/Http/Controllers/Api/V1/ExamController.php
	modified:   app/Http/Controllers/AttemptController.php
	modified:   app/Http/Controllers/Auth/LoginController.php
	modified:   app/Http/Controllers/Auth/RegisterController.php
	modified:   app/Http/Controllers/DashboardController.php
	modified:   app/Http/Controllers/EvaluationPeriodController.php
	modified:   app/Http/Controllers/ExamController.php
	modified:   app/Http/Controllers/GroupController.php
	modified:   app/Http/Controllers/QuestionController.php
	modified:   app/Http/Controllers/QuestionFileController.php
	modified:   app/Http/Controllers/StudentGradeController.php
	modified:   app/Http/Controllers/TeacherReviewController.php
	modified:   app/Http/Controllers/WebPrivacyController.php
	modified:   app/Http/Requests/AttemptAnswerWebRequest.php
	modified:   app/Http/Requests/ExamConfigurationUpdateRequest.php
	modified:   app/Http/Requests/GradebookSaveRequest.php
	modified:   app/Models/EvaluationPeriod.php
	modified:   app/Models/Exam.php
	modified:   app/Models/Group.php
	modified:   app/Models/StudentAnswer.php
	modified:   app/Models/StudentAttempt.php
	modified:   app/Models/StudentPeriodGrade.php
	modified:   app/Models/User.php
	modified:   app/Repositories/Eloquent/EloquentExamRepository.php
	modified:   app/Services/Ai/GeminiAiEvaluator.php
	modified:   app/Services/AiEvaluationService.php
	modified:   app/Services/AttemptService.php
	modified:   app/Services/AuthService.php
	modified:   app/Services/ExamAssemblyService.php
	modified:   app/Services/GradeSheetExportService.php
	modified:   app/Services/GroupEnrollmentService.php
	modified:   app/Services/GroupRetentionService.php
	modified:   app/Services/QuestionBankService.php
	modified:   app/Services/QuestionFileService.php
	modified:   app/Services/StudentResultService.php
	modified:   app/Services/SubscriptionLimitService.php
	modified:   config/auth.php
	modified:   resources/views/admin/teachers/index.blade.php
	modified:   resources/views/auth/register-student.blade.php
	modified:   resources/views/auth/register-teacher.blade.php
	modified:   resources/views/auth/verify-2fa.blade.php
	modified:   resources/views/dashboard/student.blade.php
	modified:   resources/views/evaluation-periods/gradebook.blade.php
	modified:   resources/views/evaluation-periods/index.blade.php
	modified:   resources/views/exams/configuration.blade.php
	modified:   resources/views/exams/edit.blade.php
	modified:   resources/views/exams/review-attempt.blade.php
	modified:   resources/views/exams/take-exam.blade.php
	modified:   resources/views/layouts/app.blade.php
	modified:   resources/views/student/exams/instructions.blade.php
	modified:   resources/views/student/grades/index.blade.php
	modified:   resources/views/student/grades/show.blade.php
	modified:   routes/console.php
	modified:   routes/web.php
	modified:   tests/Feature/Ai/AiOnDemandAndCreditsTest.php
	modified:   tests/Feature/Api/ExamQuestionTimingTest.php
	modified:   tests/Feature/Api/StudentResultsTest.php
	modified:   tests/Feature/Api/TwoFactorResendTest.php
	modified:   tests/Feature/Api/TwoFactorSecurityTest.php
	modified:   tests/Feature/Auth/TwoFactorAttemptLimitTest.php
	modified:   tests/Feature/BackendArchitectureAndSecurityTest.php
	modified:   tests/Feature/EvaluationPeriodGradebookTest.php
	modified:   tests/Feature/Exam/TeacherReviewRecotejeTest.php
	modified:   tests/Feature/FrontendViewRenderingTest.php
	modified:   tests/Feature/GroupRetentionTest.php
	modified:   tests/Feature/GroupStudentRemovalTest.php
	modified:   tests/Feature/QuestionBankManagementTest.php
	modified:   tests/Feature/WebExamIncidentAndErrorsTest.php

Untracked files:
  (use "git add <file>..." to include in what will be committed)
	app/Console/Commands/CloseDueFreeExams.php
	app/Http/Controllers/AdminOperationsController.php
	app/Http/Controllers/AdminTermsController.php
	app/Http/Controllers/GradePlanController.php
	app/Http/Requests/GradePlanRequest.php
	app/Http/Requests/PublishTermsRequest.php
	app/Models/TermsVersion.php
	app/Services/Ai/EvaluationPrivacyFilter.php
	app/Services/GradePlanService.php
	app/Services/GradebookEntryService.php
	app/Services/GroupGradeResults.php
	app/Services/TwoFactorDelivery.php
	config/exams.php
	config/grading.php
	database/migrations/2026_11_23_000002_bd01_2026_039_track_trial_usage_and_terms.php
	database/migrations/2026_11_23_000003_bd01_2026_040_add_grading_configuration.php
	database/migrations/2026_11_23_000004_bd01_2026_041_add_free_exam_timing.php
	database/migrations/2026_11_23_000005_bd01_2026_042_restrict_academic_evidence_deletion.php
	database/migrations/2026_11_23_000006_bd01_2026_043_track_two_factor_challenges.php
	database/migrations/2026_11_23_000007_bd01_2026_044_persist_exam_drafts_and_presenter.php
	output/
	resources/views/admin/operations/
	resources/views/admin/terms/
	resources/views/evaluation-periods/confirm-plan.blade.php
	resources/views/evaluation-periods/plan.blade.php
	resources/views/exams/answer-fields.blade.php
	resources/views/exams/free-answer-form.blade.php
	resources/views/legal/terms-student-original.blade.php
	resources/views/legal/terms-teacher-original.blade.php
	resources/views/legal/terms.blade.php
	tests/Feature/Admin/OperationalComplianceTest.php
	tests/Feature/ComplianceSchemaUpgradeTest.php
	tests/Feature/Exam/FreeNavigationTest.php
	tests/Feature/GradePlanComplianceTest.php

no changes added to commit (use "git add" and/or "git commit -a")
```

| Fuente | SHA-256 observada en esta continuación | Comparación con §3 del traspaso |
|---|---|---|
| Lista_maestra_requerimientos.xlsx | `45711e75f798b4f88dd78cf0d0eda3e33ee8a9215e80e65d4cf6e7c4cf36f219` | Coincide |
| Manual de Base de Datos (MBD-V3).pdf | `3f0ee89f23d34f2c0f29784d14c8ccb851533a063a5a8d64b6955cb720948783` | Coincide |
| Manual de Calidad (MC-V3).pdf | `0860ff19ccf01ed17c5309a395f81f1ebf989ea4e1a3f04908944e15eee61184` | Coincide |
| Manual de Desarrollo (MD-V3).pdf | `b5b65b1de51e3d524794702c2f62f3b1daffe7df45a65adced6dabfbc12e7adf` | Coincide |
| Manual de Programación Web (MPW-V3).pdf | `24c8a50c2afa862630cd8e1126f4855c1b1bb6a2d597597b9a2b943bd0524fd5` | Coincide |
