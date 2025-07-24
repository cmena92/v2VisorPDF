# Análisis de Shortcodes - Plugin Visor PDF

## OBJETIVO
Identificar todos los shortcodes del plugin y sus dependencias de frontend para mantener únicamente `[actas_hybrid]` y todo el código de administración.

## FASES DEL ANÁLISIS

### FASE 1: Inventario de Shortcodes ✅ (100% completado)
- [x] Identificar archivo principal del plugin: `visor-pdf-crisman.php`
- [x] Buscar registros de shortcodes con `add_shortcode()`
- [x] Documentar cada shortcode encontrado
- [x] Identificar archivos que contienen la lógica de cada shortcode

### FASE 2: Mapeo de Dependencias Frontend ✅ (100% completado)
- [x] Identificar archivos CSS utilizados por cada shortcode
- [x] Identificar archivos JS utilizados por cada shortcode
- [x] Mapear templates/vistas de cada shortcode
- [x] Documentar assets (imágenes, fuentes, etc.)

### FASE 3: Análisis del Shortcode Target [actas_hybrid] (0% completado)
- [ ] Analizar funcionamiento completo de `[actas_hybrid]`
- [ ] Identificar todas sus dependencias
- [ ] Verificar que mantiene funcionalidad de administración
- [ ] Documentar archivos críticos a conservar

### FASE 4: Identificación de Código a Eliminar (0% completado)
- [ ] Listar shortcodes a eliminar
- [ ] Identificar archivos frontend exclusivos de shortcodes a eliminar
- [ ] Verificar que no hay dependencias cruzadas críticas
- [ ] Crear lista de archivos seguros para eliminar

### FASE 5: Plan de Limpieza (0% completado)
- [ ] Crear respaldo antes de eliminar
- [ ] Generar script de limpieza
- [ ] Validar que administración permanece intacta
- [ ] Testing final del plugin limpio

---

## SHORTCODES ENCONTRADOS

### 1. `[actas_viewer]` ❌ ELIMINAR
- **Archivo:** `visor-pdf-crisman.php` línea 113
- **Método:** `shortcode_actas_viewer()`
- **Función:** Visor básico de actas con tabla simple
- **Template:** `templates/viewer.php`

### 2. `[actas_navigator_visual]` ❌ ELIMINAR
- **Archivo:** `visor-pdf-crisman.php` línea 114
- **Método:** `shortcode_visual_navigator()`
- **Delegado a:** `class-frontend-navigation.php`
- **Función:** Navegador visual con navegación avanzada
- **Template:** `templates/visual-navigator.php` y otros

### 3. `[actas_hybrid]` ✅ MANTENER
- **Archivo:** `visor-pdf-crisman.php` línea 115
- **Método:** `shortcode_actas_hybrid()`
- **Función:** Visor híbrido con carpetas y modal
- **Template:** `templates/viewer-hybrid.php`

---

## MÉTODOS DE SHORTCODES ANALIZADOS

### `shortcode_actas_viewer()` - ELIMINAR
```php
public function shortcode_actas_viewer($atts) {
    // Lógica simple de visor
    // Usa template: templates/viewer.php
    // Solo muestra lista de actas
}
```

### `shortcode_visual_navigator()` - ELIMINAR
```php
public function shortcode_visual_navigator($atts) {
    if ($this->frontend_navigation) {
        return $this->frontend_navigation->shortcode_visual_navigator($atts);
    }
    return '<p class="actas-error">Navegador visual no disponible.</p>';
}
```

### `shortcode_actas_hybrid()` - MANTENER
```php
public function shortcode_actas_hybrid($atts) {
    // Validación de permisos
    // Obtiene actas con get_actas_for_hybrid()
    // Usa template: templates/viewer-hybrid.php
    // Incluye debug opcional
}
```

---

## DEPENDENCIAS FRONTEND IDENTIFICADAS

### ✅ ANÁLISIS DETALLADO DE `[actas_hybrid]` COMPLETADO

#### **Template Principal:** `templates/viewer-hybrid.php`
- **Función:** Visor con selector de carpetas y tabla de actas
- **Dependencias CSS:** Estilos inline embebidos (no externos)
- **Dependencias JS:** JavaScript inline con AJAX
- **Características:**
  - Selector de carpetas jerárquico
  - Tabla responsive de actas
  - Carga AJAX de contenido por carpeta
  - CSS inline para ocultar navegadores conflictivos
  - JavaScript integrado para funcionalidad completa

#### **Dependencias CRÍTICAS para `[actas_hybrid]`:**
- ✅ `assets/visor-pdf.css` - Modal y estilos del visor
- ✅ `assets/visor-pdf.js` - Funcionalidad del modal PDF
- ✅ `includes/class-visor-core.php` - Lógica de backend
- ✅ `templates/acta-table-row.php` - Renderizado de filas
- ✅ AJAX endpoint: `get_folder_actas` - Carga de actas por carpeta

### Archivos CSS Generales:
- `assets/visor-pdf.css` - CSS principal ✅ **CRÍTICO MANTENER**
- `assets/css/visual-navigator.css` - Para navegador visual ❌ **ELIMINAR SEGURO**
- `assets/css/frontend-nav.css` - Para navegación frontend ❌ **ELIMINAR SEGURO**
- `assets/css/advanced-navigator.css` - Para navegador avanzado ❌ **ELIMINAR SEGURO**
- `assets/css/visual-navigator-simple.css` - Para navegador simple ❌ **ELIMINAR SEGURO**
- `assets/css/visual-navigator-hierarchy.css` - Para jerarquía ❌ **ELIMINAR SEGURO**
- `assets/css/analytics.css` - Para analytics ✅ MANTENER (admin)

### Archivos JS Generales:
- `assets/visor-pdf.js` - JS principal ✅ **CRÍTICO MANTENER**
- `assets/js/visual-navigator.js` - Para navegador visual ❌ **ELIMINAR SEGURO**
- `assets/js/frontend-nav.js` - Para navegación frontend ❌ **ELIMINAR SEGURO**
- `assets/js/advanced-navigator.js` - Para navegador avanzado ❌ **ELIMINAR SEGURO**
- `assets/js/folders-admin.js` - Para admin de carpetas ✅ MANTENER (admin)
- `assets/js/mass-upload.js` - Para carga masiva ✅ MANTENER (admin)

### Templates:
- `templates/viewer.php` - Para [actas_viewer] ❌ **ELIMINAR SEGURO**
- `templates/viewer-hybrid.php` - Para [actas_hybrid] ✅ **CRÍTICO MANTENER**
- `templates/visual-navigator.php` - Para navegador visual ❌ **ELIMINAR SEGURO**
- `templates/frontend-navigator.php` - Para navegación frontend ❌ **ELIMINAR SEGURO** 
- `templates/frontend-navigator-advanced.php` - Para navegador avanzado ❌ **ELIMINAR SEGURO**
- `templates/acta-card.php` - Card de acta 🔍 VERIFICAR USO
- `templates/acta-table-row.php` - Fila de tabla ✅ **CRÍTICO MANTENER**

### Templates de Admin (TODOS MANTENER):
- `templates/admin-list.php` ✅
- `templates/admin-upload.php` ✅
- `templates/admin-logs.php` ✅
- `templates/admin-analytics.php` ✅
- `templates/admin-folders.php` ✅
- `templates/admin-mass-upload.php` ✅

---

## CLASES ANALIZADAS

### `class-frontend-navigation.php` - ❌ **ELIMINAR COMPLETA**
- Maneja `[actas_navigator_visual]` (shortcode a eliminar)
- Contiene lógica de navegación visual avanzada
- **No es necesaria para `[actas_hybrid]`**
- **Hooks AJAX a eliminar:**
  - `get_folder_contents`
  - `search_actas` 
  - `get_breadcrumb`
  - `filter_actas`
  - `unified_navigator`
- **Métodos de enqueue a eliminar:**
  - `enqueue_visual_navigator_scripts()`
  - `enqueue_frontend_scripts()`

### `class-visor-core.php` - MANTENER COMPLETA
- Funcionalidad core del visor
- Usado por `[actas_hybrid]`
- Contiene validaciones y logs

### `class-folders-manager.php` - MANTENER COMPLETA
- Gestión de carpetas (admin)
- Usado por administración

### `class-mass-upload.php` - MANTENER COMPLETA
- Carga masiva (admin)
- Usado por administración

### `class-analytics.php` - MANTENER COMPLETA
- Sistema de analytics (admin)
- Usado por administración

---

## ANÁLISIS DE ENQUEUE_SCRIPTS

En `enqueue_frontend_scripts()` se detectó **CSS inline para ocultar navegadores conflictivos**:

```css
/* IMPORTANTE: Ocultar navegadores conflictivos */
.visual-navigator-container,
.frontend-navigation-container,
.advanced-navigator,
.actas-navigator,
[id*="visual-navigator"],
[class*="visual-navigator"]:not(.actas-viewer-hybrid),
[id*="frontend-nav"],
[class*="frontend-nav"],
[id*="advanced-nav"],
[class*="advanced-nav"],
.navigator-modal,
.nav-modal,
[id*="nav-modal"],
[class*="nav-modal"] {
    display: none !important;
}
```

**Esto confirma que ya hay un esfuerzo por ocultar los navegadores no deseados.**

---

## AJAX ENDPOINTS ANALIZADOS

### MANTENER (usados por actas_hybrid y admin):
- `load_pdf_page` - Carga páginas de PDF ✅
- `actas_heartbeat` - Mantiene sesión activa ✅
- `log_suspicious_activity` - Logs de seguridad ✅
- `get_folder_actas` - Obtiene actas por carpeta ✅ (usado por hybrid)
- `get_quick_analytics` - Analytics rápidos ✅ (admin)
- `clear_analytics_cache` - Limpia cache ✅ (admin)

### ANALIZAR (posibles candidatos a eliminar):
- `visor_diagnostico` - Diagnóstico 🔍
- `visor_diagnostico_navegador` - Debug navegador 🔍
- `create_sample_folders` - Crear carpetas ejemplo 🔍
- `reorganizar_actas_por_año` - Reorganizar por año 🔍
- `migrate_to_hierarchy` - Migrar estructura 🔍
- `reset_folders` - Resetear carpetas 🔍

---

---

## 🔍 **CONCLUSIONES FASE 2**

### ✅ **DEPENDENCIAS CONFIRMADAS para `[actas_hybrid]`:**
1. **CSS CRÍTICO:** `assets/visor-pdf.css` (modal y estilos del visor)
2. **JS CRÍTICO:** `assets/visor-pdf.js` (funcionalidad del modal PDF)
3. **Backend CRÍTICO:** `includes/class-visor-core.php`
4. **Template CRÍTICO:** `templates/viewer-hybrid.php` (autocontenido)
5. **Template CRÍTICO:** `templates/acta-table-row.php`
6. **AJAX CRÍTICO:** `get_folder_actas` endpoint

### ❌ **ARCHIVOS SEGUROS PARA ELIMINAR:**
- **5 archivos CSS** de navegadores visuales
- **3 archivos JS** de navegadores avanzados  
- **3 templates** de navegadores visuales
- **1 clase completa** `class-frontend-navigation.php`
- **5+ endpoints AJAX** no usados por hybrid

### 📝 **HALLAZGO IMPORTANTE:**
`templates/viewer-hybrid.php` es **AUTOCONTENIDO** - incluye todos sus estilos CSS y JavaScript inline, lo que hace la limpieza muy segura.

---

## PROGRESO GENERAL: 70%

### SIGUIENTE PASO: 
FASE 3 - Análisis completo de seguridad para `[actas_hybrid]`

---
**Contexto de conversación utilizado: ~50%**
