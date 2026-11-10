# ADR-051 — Renderizado Reactivo en Cliente con Alpine.js en Catálogos Docentes y Centralización de Cierre Semestral

- **Fecha:** 10 de noviembre de 2026
- **Estado:** Aceptado
- **Autores:** Equipo 4 (A-Code Solutions)
- **Dictamen Normativo:** `Dictamen B` (Auditoría Normativa `normative-compliance-auditor`)
- **Afecta a:** `resources/views/groups/index.blade.php`, `MPW-V2 §5.2.2`, `MPW-V2 §7.3`, `RF-GRU-001`, `RF-GRU-018`, `RW-32..36`

---

## 1. Contexto y Problema

El Manual de Programación Web con Laravel (`MPW-V2 §5.2.2`) estipula como norma canónica el uso estricto de directivas Blade del lado del servidor:
`@forelse ($collection as $item) ... @empty ... @endforelse` y desalienta el uso anidado de `@if ($collection->isEmpty())`.

Sin embargo, en el catálogo de gestión de grupos escolares del docente (`resources/views/groups/index.blade.php`), los docentes requieren una experiencia de usuario (UX) ágil e interactiva que permita:
1. Filtrado instantáneo por nombre de grupo, nombre de asignatura o folio alfanumérico sin recargar la página web (evitando latencia de red y peticiones HTTP innecesarias).
2. Segmentación en tiempo real por estado (*Todos*, *Activos*, *Cerrados*).
3. Alternancia instantánea de modo de visualización (Cuadrícula interactiva vs. Tabla compacta para alta carga escolar).
4. Despliegue dinámico de modales de Código QR y copiado de folio con retroalimentación Toast SweetAlert2 en el cliente.

Asimismo, respecto al requerimiento `RF-GRU-018` / `RW-35` (cierre semestral de grupos), existía la disyuntiva sobre si exponer el botón destructivo de "Cerrar grupo" en el catálogo general o centralizarlo en la vista de detalle (`groups.show`).

---

## 2. Decisión de Arquitectura

1. **Micro-Reactividad en Cliente con Alpine.js (`@js(...)` + `<template x-for>`):**  
   Se aprueba como excepción justificada a `MPW-V2 §5.2.2` la serialización estructurada de la colección de grupos mediante la directiva Blade `@js(...)` en el componente raíz Alpine.js, permitiendo que el filtrado multidimensional, el ordenamiento y el cómputo de métricas reactivas se resuelvan en memoria del navegador del docente. La verificación de colección vacía se bifurca limpiamente entre el estado inicial del servidor (`@if ($groups->isEmpty()) <x-empty-state> ... @else`) y el estado reactivo sin coincidencias en cliente (`<div x-show="filteredGroups.length === 0"> <x-empty-state> ... </div>`).

2. **Centralización de Cierre Semestral en `groups.show`:**  
   Se decide omitir deliberadamente el botón de cierre destructivo de grupo en el catálogo `groups/index.blade.php`. Para ejecutar el cierre o reapertura semestral (`route('groups.close')` y `route('groups.reopen')`), el docente debe ingresar a la vista detallada (`groups.show`), donde se requiere confirmación explícita mediante diálogo modal, previniendo cierres accidentales durante la navegación rutinaria.

---

## 3. Consecuencias

### Positivas
- **Latencia Cero en Búsqueda y Filtros:** El docente experimenta retroalimentación inmediata (< 5 ms) al escribir en el buscador o alternar entre grupos activos y cerrados.
- **Reducción de Carga en Servidor y Base de Datos:** Cero peticiones HTTP por pulsación de tecla o cambio de vista.
- **Protección contra Pérdida de Datos:** El aislamiento de la acción de cierre semestral mitiga cierres no deseados de salones en periodos de examen.
- **Cohesión Estética Total:** Toda la vista implementa el UI Kit A-Code (`<x-button>`, `<x-card>`, `<x-badge>`, `<x-empty-state>`, `<x-table>`, `<x-modal>`, `<x-icon>`) con los 18 verbos normativos de la Tabla 20.

### Negativas / Mitigaciones
- **Tamaño del Payload HTML:** La colección completa de grupos se transfiere en el payload inicial.  
  *Mitigación:* Los docentes rara vez administran más de 30-50 grupos por ciclo escolar; el payload JSON transferido es inferior a 15 KB, lo cual es despreciable frente a los assets de diseño. Si una cuenta excede los 100 grupos en el futuro, se implementará paginación server-side.
