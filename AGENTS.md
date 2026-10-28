# Ecosistema de Subagentes Especializados — A-Code Solutions (`MSA-V1`)

Este repositorio cuenta con **8 subagentes especializados organizados en 3 anillos** para garantizar calidad normativa (`MPW-V2`, `MPM-V1`, `MC-V2`, `MD-V2`), seguridad OWASP Top 10, paridad de contratos API v1 ↔ Flutter y cero colisiones de archivos.

> 📖 **Manual completo de operación y flujo de trabajo**:
> - [`Manual_de_Subagentes_y_Flujo_de_Trabajo_MSA-V1.md`](/home/laambws/Descargas/manuales/acode-solutions/07_Manuales_Oficiales_V2/Manual_de_Subagentes_y_Flujo_de_Trabajo_MSA-V1.md)
> - [`05_MANUAL_SUBAGENTES_Y_FLUJO.md`](/home/laambws/Escritorio/acs-exam-web/docs/05_MANUAL_SUBAGENTES_Y_FLUJO.md)

## Catálogo de Subagentes (`/.agents/agents/`)

| # | Subagente | Anillo | Permisos | Dominio Exclusivo |
| :-: | :--- | :--- | :-: | :--- |
| 1 | `normative-compliance-auditor` | Anillo 1 (Auditoría) | Solo Lectura | Contraste Código ↔ Manuales `.docx` ↔ 226 Requerimientos `.xlsx` (Dictamen A/B/C) |
| 2 | `architecture-quality-auditor` | Anillo 1 (Auditoría) | Solo Lectura | Antipatrones Backend/Frontend, N+1, SRP, 16 bloques PHP, tokens `acode-*` y 18 verbos |
| 3 | `security-vulnerability-auditor` | Anillo 1 (Auditoría) | Solo Lectura | OWASP Top 10, IDOR multi-docente, cronómetro server-side, 2FA, Sanctum, anti-trampa |
| 4 | `api-mobile-contract-verifier` | Anillo 1 (Auditoría) | Solo Lectura | Paridad 1:1 entre `acs-exam-web` (`/api/v1/*`) y `acs-exam-mobile` (Flutter) |
| 5 | `db-eloquent-architect` | Anillo 2 (Ingeniería) | Escritura | `database/migrations/**`, `database/seeders/**`, `app/Models/**`, `app/Repositories/**` |
| 6 | `backend-laravel-specialist` | Anillo 2 (Ingeniería) | Escritura | `app/Http/**`, `app/Services/**`, `app/Contracts/**`, `app/Jobs/**`, `routes/**`, `docs/**` |
| 7 | `frontend-ui-specialist` | Anillo 2 (Ingeniería) | Escritura | `resources/views/**`, `resources/js/**`, `resources/css/**`, `public/**` |
| 8 | `qa-testing-engineer` | Anillo 3 (Certificación) | Escritura | `acs-exam-web/tests/**`, `acs-exam-mobile/test/**` |
