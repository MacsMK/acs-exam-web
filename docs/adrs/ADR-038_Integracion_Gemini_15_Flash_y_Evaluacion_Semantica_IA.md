# ADR-038 — Integración de Google Gemini 1.5 Flash para Evaluación Semántica de Reactivos con Privacidad LFPDPPP y Fallback Resiliente (`RF-EVA-004`, `RF-EVA-005`, `RF-IAR-002`, `RNF-PRI-001`, `RNF-CON-002`)

> **Estado:** Aceptado  
> **Fecha:** 08 de Noviembre de 2026  
> **Decisores:** `normative-compliance-auditor`, `architecture-quality-auditor`, `security-vulnerability-auditor`, `backend-laravel-specialist`, `qa-testing-engineer`  
> **Normativa de Referencia:** Manual `MPW-V2` (§3.8.1, §5.5, §6.6, Tabla 8), Manual `MC-V2`, `RF-EVA-004`, `RF-EVA-005`, `RF-IAR-001`, `RF-IAR-002`, `RNF-PRI-001` (LFPDPPP / Minimización de Datos), `RNF-CON-002` (Resiliencia en Servicios Externos)

---

## 1. Contexto y Problemática

1. **Limitaciones del Evaluador Simulado:** La plataforma contaba únicamente con un `MockAiEvaluator` basado en algoritmos de distancia léxica (`similar_text`) para calificar respuestas libres (`OpenItem`) y código fuente (`CodeItem`). Este enfoque penalizaba respuestas correctas expresadas con sinónimos o sintaxis alternativa y no ofrecía justificaciones pedagógicas reales.
2. **Requisitos de Privacidad y LFPDPPP (`RNF-PRI-001`):** De acuerdo con la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP) y los requerimientos no funcionales de seguridad, **queda estrictamente prohibido transmitir datos de identificación del estudiante** (tales como nombre, matrícula, correo electrónico, ID de usuario o IP) a proveedores de nubes externas de inteligencia artificial.
3. **Resiliencia Operativa y Tolerancia a Fallas (`RNF-CON-002`):** La entrega o calificación de un examen no puede quedar bloqueada ni arrojar error de servidor (`HTTP 500`) en caso de saturación de red, agotamiento de cuota de la API externa o ausencia de llaves en entornos de desarrollo local o pruebas.

---

## 2. Decisión de Arquitectura

### 2.1. Implementación de `GeminiAiEvaluator` ([GeminiAiEvaluator.php](file:///home/laambws/Escritorio/acs-exam-web/app/Services/Ai/GeminiAiEvaluator.php))
Se desarrolló un evaluador de dominio bajo el contrato [`App\Contracts\AiEvaluatorContract`](file:///home/laambws/Escritorio/acs-exam-web/app/Contracts/AiEvaluatorContract.php) conectado a la API de **Google Gemini 1.5 Flash**:
- **Salida JSON Estructurada:** Se configuró `responseMimeType: 'application/json'` y `responseSchema` obligando al modelo a responder con un objeto estricto:
  ```json
  {
    "similarity_score": 88.5,
    "meets_goal": true,
    "feedback": "El estudiante explica con precisión el concepto y sus implicaciones."
  }
  ```
- **Límites Numéricos:** El puntaje devuelto es normalizado y acotado matemáticamente al intervalo `[0.0, 100.0]`.

### 2.2. Minimización Estricta de Datos Personales (`RNF-PRI-001`)
El payload transmitido al endpoint `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent` contiene exclusivamente:
- La instrucción pedagógica del sistema (`prompt`).
- El texto de la respuesta de referencia redactada por el docente (`referenceAnswer`).
- El texto capturado de la respuesta del alumno (`studentAnswer`).
Ningún identificador de base de datos, metadato de usuario o dato personal es incluido en la llamada.

### 2.3. Mecanismo de Fallback Transparente (*Graceful Degradation*)
- **Falta de API Key:** Si `GEMINI_API_KEY` se encuentra vacía (por ejemplo en instalaciones iniciales o entornos offline), delega instantáneamente la evaluación a `MockAiEvaluator`.
- **Excepciones de Red, Errores HTTP (4xx / 5xx) o JSON Malformado:** Se capturan las excepciones de la llamada HTTP (`Http::timeout()`), se registra un aviso en los logs del sistema (`Log::warning`), y se ejecuta la evaluación de respaldo mediante `MockAiEvaluator`, anteponiendo la marca institucional `[Degradación por Contingencia]` en el campo de retroalimentación docente.

### 2.4. Configuración y Registro en el Contenedor
- Se agregaron las variables `GEMINI_API_KEY`, `GEMINI_MODEL=gemini-1.5-flash` y `GEMINI_TIMEOUT=15` en `config/services.php`, `.env` y `.env.example`.
- En [`app/Providers/AppServiceProvider.php`](file:///home/laambws/Escritorio/acs-exam-web/app/Providers/AppServiceProvider.php), el contrato `AiEvaluatorContract` se resuelve dinámicamente mediante el selector `services.ai.driver` (`gemini` o `mock`).

---

## 3. Consecuencias

### Positivas
- Evaluación semántica moderna y precisa con justificación constructiva en lenguaje natural para preguntas abiertas y código.
- Pleno cumplimiento de `RNF-PRI-001` (privacidad conforme a LFPDPPP) garantizado por pruebas automatizadas de inspección de payload.
- Alta disponibilidad certificada: el examen nunca se interrumpe aunque el servicio de IA externa presente latencia o contingencias.
- Suite de pruebas automatizadas al 100% en [`GeminiAiEvaluatorTest.php`](file:///home/laambws/Escritorio/acs-exam-web/tests/Feature/Ai/GeminiAiEvaluatorTest.php).
