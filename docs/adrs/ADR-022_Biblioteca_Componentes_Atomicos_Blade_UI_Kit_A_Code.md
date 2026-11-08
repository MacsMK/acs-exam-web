# ADR-022 — Biblioteca de Componentes Atómicos Blade y Sistema de Formularios Modulares (`UI Kit A-Code`)

> **Estado:** Aceptado  
> **Fecha:** 02 de Noviembre de 2026  
> **Decisores:** `frontend-ui-specialist`, `architecture-quality-auditor`, `normative-compliance-auditor`  
> **Normativa de Referencia:** Manual `MPW-V2` (§7.3, §8.1.2, §8.2, §8.3, §8.4, §8.5, §8.12, Tablas 24, 25, 26, 27, 28, 29, 31)

---

## 1. Contexto y Problemática
A pesar de las mejoras preliminares de Sprint 2, la capa de presentación de `acs-exam-web` todavía adolecía de una fragmentación severa en su marcado HTML:
1. **Duplicación masiva en controles de entrada:** Los elementos `<input>`, `<textarea>`, `<select>` y grupos de formulario con etiquetas y mensajes de error estaban duplicados con HTML plano inline a lo largo de las 19 vistas, violando la regla de oro de `MPW-V2 §7.3` (*"Queda formalmente prohibida la duplicación de marcado HTML para elementos comunes de la interfaz"*).
2. **Inconsistencias dimensionales:** Los botones e inputs no se apegaban a las escalas oficiales de las Tablas 27 y 28 de `MPW-V2` (`sm`: 32px, `md`: 40px, `lg`: 48px).
3. **Ausencia de identidad vectorial institucional:** No existía un componente corporativo para el logotipo de A-Code Solutions; se utilizaban textos planos sin área de protección ni versiones monocromáticas negativas (`MPW-V2 §8.1.2`).
4. **Dispersión iconográfica:** No existía un catálogo centralizado de iconos, provocando uso de SVGs inline heterogéneos o ausencia de iconografía semántica según la Tabla 31.

---

## 2. Decisión de Arquitectura
Se implementó una **biblioteca de 18 componentes atómicos y modulares en Blade** dentro del directorio `resources/views/components/`, estructurada bajo los principios del Atomic Design:

### 2.1. Componentes Base y de Identidad (`Atoms`)
1. **`<x-logo>` ([components/logo.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/logo.blade.php)):**
   - Variante `'full'`: Imagotipo horizontal con isotipo poligonal en SVG (`#142B4A`, `#2563A6`, `#16A6A1`) y leyenda corporativa *"Analizamos, diseñamos, transformamos"*.
   - Variante `'compact'` / `'symbol'`: Isotipo geométrico aislado para sidebar colapsado y layout de examen.
   - Variante `'negative'`: Logotipo monocromático blanco puro (`#FFFFFF`) para barra superior institucional oscura.
   - Margen perimetral normativo $\ge 16$ px respetado en todos los layouts (`MPW-V2 §8.1.2`).
2. **`<x-icon>` ([components/icon.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/icon.blade.php)):**
   - Catálogo vectorial SVG Heroicons v2 con 24 alias unificados: `userTie`, `user`, `userGroup`, `shield`, `add`, `edit`, `delete`, `view`, `save`, `search`, `filter`, `downloadReport`, `clock`, `qrCode`, `key`, `lock`, `success`, `warning`, `error`, `reportBar`, `chevronDown`, `check`, `xMark`, `eye`, `eyeSlash` (Tabla 31).
3. **`<x-button>` ([components/button.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/button.blade.php)):**
   - 6 variantes institucionales: `primary` (`#142B4A`), `secondary` (`#2563A6`), `accent` (`#16A6A1`), `warning` (`#F4B942 text-slate-900`), `danger` (`#DC2626`), `neutral` (borde `slate-300`).
   - 3 medidas oficiales de la Tabla 27: `sm` (h-8 / 32px), `md` (h-10 / 40px, default), `lg` (h-12 / 48px).
   - Renderizado dinámico como `<button>` o `<a>` (`$href`), con soporte para spinner animado de carga (`loading`).
4. **`<x-badge>` ([components/badge.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/badge.blade.php)):**
   - Contorno `rounded-full`, microtexto `text-xs font-medium` y variantes cromáticas de estado con alto contraste WCAG 2.1 AA.

### 2.2. Sistema de Formularios Modulares (`components/form/`)
1. **`<x-form.input>` ([components/form/input.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/form/input.blade.php)):**
   - Escalas oficiales de Tabla 28: `sm` (32px), `md` (40px), `lg` (48px).
   - Radio de esquinas `rounded-lg` (8px, Tabla 29).
   - Soporte para icono o elemento prefijo y sufijo (ej. conmutador de visibilidad de contraseña con icono interactivo).
   - Detección reactiva de errores con borde `#DC2626`.
2. **`<x-form.textarea>` ([components/form/textarea.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/form/textarea.blade.php)):**
   - Soporte de tipografía monoespaciada Fira Code (`mono="true"`) para preguntas de programación.
3. **`<x-form.select>` ([components/form/select.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/form/select.blade.php)):**
   - Selector estilizado con icono de flecha *chevron* integrado.
4. **`<x-form.group>` ([components/form/group.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/form/group.blade.php)):**
   - Envoltorio con etiqueta (13px `text-slate-800 font-medium`, Tabla 26), asterisco indicador de obligatoriedad, texto auxiliar y visualizador del mensaje normativo de error (Tabla 30).

### 2.3. Estructuras de Contenido y Tablas (`Molecules`)
1. **`<x-card>` ([components/card.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/card.blade.php)):**
   - Fondo blanco `bg-white`, radio `rounded-xl` (12px), borde `border-slate-200`, elevación `shadow-sm`, padding `p-6`. Cabecera modular con H3 semibold en `text-acode-primary`, ranuras `$actions` y `$footer`.
2. **`<x-modal>` ([components/modal.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/modal.blade.php)):**
   - Modal accesible en Alpine.js con fondo `backdrop-blur-sm bg-slate-900/60`, contenedor `rounded-2xl` (16px), elevación `shadow-xl`, cabecera con botón 'X' y pie de acciones.
3. **`<x-table.*>` ([components/table/](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/table/)):**
   - Tabla con encabezado institucional Azul Marino `#142B4A` (`bg-acode-primary text-white text-xs uppercase tracking-wide`), filas alternas `even:bg-acode-surface` e iluminación `hover:bg-acode-tech/5`.
4. **`<x-search-filter>` ([components/search-filter.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/search-filter.blade.php)):**
   - Barra con búsqueda predictiva con debounce de 300 ms en Alpine.js, selectores de filtrado y botón "Limpiar filtros" (MPW-V2 §8.8).

---

## 3. Consecuencias
- **Positivas:**
  - Erradicación del 100% del marcado HTML repetitivo en vistas de la aplicación.
  - Apego estricto a las matrices de dimensiones (Tablas 27 y 28), colores oficiales y radios de curvatura (Tabla 29).
  - Facilidad de mantenimiento: cualquier ajuste de estilos se realiza en el componente Blade centralizado.
  - Cumplimiento de accesibilidad WCAG 2.1 nivel AA en contraste tonal.
- **Negativas/Compromisos:**
  - Ligero incremento en la cantidad de archivos de vista que deben compilarse mediante `php artisan view:cache` (mitigado por el motor de caché nativo de Laravel).

---

## 4. Trazabilidad y Requerimientos Asociados
- **Requerimientos:** `RF-AUT-001`, `RF-AUT-003`, `RF-GRU-001`, `RF-BAN-001`, `RF-EXA-001`.
- **Manuales:** `MPW-V2` (§7.3, §8.1 a §8.5, §8.12), `MC-V2` (§4.2).
- **Pruebas de Certificación:** `tests/Feature/FrontendViewRenderingTest.php`.
