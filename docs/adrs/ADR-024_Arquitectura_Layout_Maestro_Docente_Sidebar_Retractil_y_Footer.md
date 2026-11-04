# ADR-024 — Arquitectura de Layouts Maestros: Sidebar Retráctil (260px/72px), Menú Drawer y Footer de Dos Secciones

> **Estado:** Aceptado  
> **Fecha:** 02 de Noviembre de 2026  
> **Decisores:** `frontend-ui-specialist`, `architecture-quality-auditor`  
> **Normativa de Referencia:** Manual `MPW-V2` (§7.2, §8.10.1, §8.10.4, §8.10.7)

---

## 1. Contexto y Problemática
El layout maestro original `layouts/app.blade.php` presentaba carencias arquitectónicas y de usabilidad notables frente a las especificaciones institucionales:
1. **Navegación horizontal rígida:** Contaba únicamente con una barra superior que agrupaba enlaces planos sin jerarquía, lo que limitaba la escalabilidad del sistema a medida que crecían las secciones docentes.
2. **Ausencia de Sidebar docente:** `MPW-V2 §8.10.1` y `§8.10.4` exigen explícitamente una barra lateral retráctil (*Sidebar*) de 260 px en modo expandido y 72 px en modo compacto con iconos institucionales.
3. **Pobre experiencia móvil:** En pantallas menores a 1024 px la navegación horizontal colapsaba o provocaba desbordamientos visuales, careciendo del menú *drawer* con desenfoque estipulado en la normativa.
4. **Perfil de usuario no interactivo:** El nombre de usuario se desplegaba como texto estático sin menú desplegable de opciones ni separación semántica para el cierre de sesión en Rojo Alerta (`#DC2626`).
5. **Pie de página incompleto:** No cumplía la distribución obligatoria de dos secciones requerida por `MPW-V2 §8.10.7`.

---

## 2. Decisión de Arquitectura

### 2.1. Barra Lateral Retráctil (*Sidebar*) en Alpine.js
Se reestructuró [`resources/views/layouts/app.blade.php`](file:///home/laambws/Escritorio/acs-exam-web/resources/views/layouts/app.blade.php) incorporando una barra lateral interactiva con gestión de estado reactivo mediante Alpine.js:
- **Modo Expandido (260 px / `w-64`):** Presenta el título del menú de navegación, enlaces con iconos Heroicons v2 (`<x-icon>`) y textos explicativos completos, botón para contraer la barra y badge de versión.
- **Modo Colapsado (72 px / `w-[72px]`):** Oculta los textos mediante directivas reactivas `x-show="sidebarOpen"`, centrando los iconos institucionales y desplegando tooltips informativos nativos o flotantes al pasar el cursor.
- **Navegación por Rol:**
  - **Docente (`role === 'teacher'`):** Inicio / Métricas (`reportBar`), Grupos Escolares (`userGroup`), Banco de Reactivos (`edit`), Catálogo de Exámenes (`clock`).
  - **Estudiante (`role === 'student'`):** Inicio / Métricas (`reportBar`), Mis Exámenes (`clock`), Unirme a un Grupo (`userGroup`).
- **Drawer Móvil Responsivo (`< 1024 px`):**
  - Panel deslizante lateral desde la izquierda accionado por el botón de hamburguesa.
  - Telón de fondo con filtro de desenfoque (`backdrop-blur-sm bg-slate-900/60`) que preserva el contexto operativo y botón táctil de cierre.

### 2.2. Barra Superior (*Navbar*) Institucional
- Cabecera fija superior (`h-16 bg-white border-b border-slate-200`) desacoplada con:
  - Botón interactivo para alternar el menú lateral en dispositivos móviles y de escritorio.
  - Imagotipo negativo corporativo [`<x-logo variant="negative" />`](file:///home/laambws/Escritorio/acs-exam-web/resources/views/components/logo.blade.php) en el área de marca.
  - Indicador de ruta activa y breadcrumb de ubicación.
  - Avatar circular con iniciales del usuario sobre fondo tecnológico `#2563A6` y badge de rol.
  - Menú desplegable (*Dropdown*) anclado con:
    - Nombre completo del usuario y correo electrónico.
    - Divisor horizontal.
    - Acción **"Cerrar sesión"** resaltada en Rojo Alerta (`text-acode-danger #DC2626`) protegida por confirmación modal SweetAlert2 antes de disparar la ruta `POST auth.logout`.

### 2.3. Pie de Página Institucional de Dos Secciones (`MPW-V2 §8.10.7`)
Se implementó un footer estructurado en dos secciones horizontales mediante `flex justify-between`:
- **Sección Izquierda:** Nombre del sistema, créditos institucionales *"© 2026 A-Code Solutions. Plataforma Evaluadora de Exámenes. Todos los derechos reservados."* e indicador de versión de software (`v1.0.0`).
- **Sección Derecha:** Menú de navegación secundaria con enlaces normativos espaciados: *"Términos del Servicio"*, *"Aviso de Privacidad"*, *"Centro de Ayuda"* y *"Soporte Técnico"*, con avisos Toast informativos.

---

## 3. Consecuencias
- **Positivas:**
  - Espacio de trabajo amplio y ergonómico para el docente, adaptable a flujos de trabajo extensos (banco de preguntas y armado de exámenes).
  - Experiencia móvil de primer nivel con cajón deslizable sin recargas de página.
  - Cumplimiento estricto de las directrices visuales del manual `MPW-V2 §8.10`.
- **Negativas/Compromisos:**
  - Requiere inicialización ligera de estado en Alpine.js en el elemento raíz del layout (`x-data="{ sidebarOpen: true, mobileMenuOpen: false }"`).

---

## 4. Trazabilidad y Requerimientos Asociados
- **Requerimientos:** `RF-AUT-001`, `RF-GRU-001`, `RF-BAN-001`, `RF-EXA-001`, `RF-CAL-005`.
- **Manuales:** `MPW-V2` (§7.2, §8.10.1, §8.10.4, §8.10.7).
- **Pruebas de Certificación:** `tests/Feature/FrontendViewRenderingTest.php`.
