# ADR-047 — Estandarización UI Kit A-Code: Microinteracciones y Transiciones Suaves con Alpine.js

**Estado:** Aceptado  
**Fecha:** 2026-11-09  
**Autores:** Especialista Frontend UI/UX y Calidad (A-Code Solutions)  
**Requerimientos Relacionados:** MC-V2, MPW-V2 §7.3, §7.4, §7.5, §8.1..8.5, Tabla 13, Tabla 14, Tabla 15, Tabla 16  

---

## Contexto

La auditoría de calidad de código y diseño detectó que diversas pantallas troncales sufrían de dos defectos visuales importantes:
1. **Conmutación Abrupta de Estado (*Layout Shifts*):** El cambio entre pestañas en `groups/show`, la alternancia de tipos de preguntas en el banco de reactivos y la apertura de modales ocurrían en un único fotograma (corte instantáneo sin animación), provocando fatiga visual y saltos en la altura del documento.
2. **Duplicación de Marcado Crudo y Desviaciones Cromáticas:** Vistas como `questions/index` o `exams/assemble` empleaban botones planos de 44px (`h-11`), colores hexadecimales arbitrarios (`#DC2626`, `#0e1f36`) y estilos inline `style="..."` en lugar de apegarse al sistema de diseño unificado.

## Decisión

1. **Aceleración por Hardware y Transiciones a 60 FPS con Alpine.js:**
   - Estandarizar el uso de `x-transition` en toda conmutación de pestañas o paneles condicionales:
     ```html
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-1.5"
     x-transition:enter-end="opacity-100 translate-y-0"
     ```
   - En modales y diálogos, aplicar transiciones compuestas de opacidad en el fondo (`bg-slate-900/60 backdrop-blur-sm`) y escalado sutil en el contenedor (`opacity-0 scale-95` a `opacity-100 scale-100` en 200 ms).
2. **Microinteracciones en Botones `<x-button>`:**
   - Incorporar en `$baseClasses` la separación automática `gap-2` para iconos SVG y textos.
   - Añadir respuesta táctil inmediata mediante la pseudo-clase `:active` (`active:scale-[0.98]`) y transición suave `transition-all duration-200 ease-in-out hover:shadow-sm`.
3. **Erradicación de Estilos Inline y Clases Arbitrarias:**
   - Sustituir `style="..."` por clases de utilidad institucional en `resources/css/app.css` (`.acode-auth-radial-bg`).
   - Normalizar los códigos hexadecimales a los tokens oficiales de Tailwind v4 (`acode-primary`, `acode-tech`, `acode-accent`, `acode-warning`, `acode-danger`, `acode-surface`).
4. **Adopción de Componentes Atómicos:**
   - Migrar tablas nativas al componente `<x-table>` con soporte para cebra institucional y columnas `sticky left-0` para navegación móvil.
   - Sustituir diálogos nativos del navegador (`window.prompt`) por modales institucionales `<x-modal>`.

## Consecuencias

- **Positivas:**
  - Navegación natural y refinada sin parpadeos ni cortes bruscos en toda la plataforma web.
  - Consistencia geométrica y visual al 100% con las tablas 13, 14, 15 y 16 del Manual de Programación `MPW-V2`.
  - Confort táctil en dispositivos móviles y retroalimentación inmediata en cada interacción.
- **Negativas:**
  - Las transiciones requieren la carga de Alpine.js en el cliente (ya contemplado e integrado de forma nativa en el stack).
