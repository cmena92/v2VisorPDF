# 📄 Visor PDF Crisman - Sistema de Gestión de Actas

Un plugin completo de WordPress para la gestión segura de documentos PDF con control de acceso, marcas de agua y analytics avanzados.

## 🚀 Instalación y Configuración Inicial

### Requisitos del Sistema

- **WordPress**: 5.0 o superior
- **PHP**: 7.4 o superior
- **MySQL**: 5.7 o superior
- **Extensiones PHP requeridas**:
  - `imagick` o `gd` (para procesamiento de imágenes)
  - `mysql` o `mysqli`
  - `fileinfo`
- **Memoria PHP**: Mínimo 128MB, recomendado 256MB
- **Permisos de escritura** en `/wp-content/uploads/`

### Proceso de Instalación

#### 1. Subir el Plugin

1. Descarga o clona el repositorio del plugin
2. Sube la carpeta `v2VisorPDF` a `/wp-content/plugins/`
3. O instala a través del panel de WordPress (Plugins > Añadir nuevo > Subir plugin)

#### 2. Activar el Plugin

1. Ve a **Plugins > Plugins instalados** en tu panel de WordPress
2. Localiza "Visor PDF Crisman" en la lista
3. Haz clic en **"Activar"**

⚠️ **IMPORTANTE**: El plugin se configura automáticamente durante la activación:

- ✅ **Tablas de base de datos**: Se crean automáticamente 6 tablas
- ✅ **Carpetas del sistema**: Se crean las carpetas predefinidas
- ✅ **Directorios de archivos**: Se configuran con protección de seguridad
- ✅ **Roles de usuario**: Se añaden capacidades personalizadas
- ✅ **Opciones por defecto**: Se establecen configuraciones optimizadas

#### 3. Verificar la Instalación

Después de activar, ve a **Visor PDF > Estado Sistema** para verificar que todo esté correcto:

- ✅ Todas las tablas creadas
- ✅ Directorios con permisos correctos
- ✅ Carpetas base configuradas
- ✅ Opciones del sistema establecidas

## 📁 Estructura de Carpetas Creadas Automáticamente

El plugin crea automáticamente la siguiente estructura jerárquica:

```
📂 Actas de Junta Directiva
├── 📄 2021 (subcarpeta)
├── 📄 2022 (subcarpeta)
├── 📄 2023 (subcarpeta)
├── 📄 2024 (subcarpeta)
├── 📄 2025 (subcarpeta)
└── 📄 2026 (subcarpeta)

📂 Actas de Asamblea
├── 📄 Asamblea 2023 (subcarpeta)
├── 📄 Asamblea 2024 (subcarpeta)
└── 📄 Asamblea 2025 (subcarpeta)

📂 Sin Clasificar (oculta en frontend)
```

### Características de las Carpetas:

- **Estructura jerárquica**: Carpetas padre e hijas
- **Auto-organización**: Las actas se asignan automáticamente por año
- **Visibilidad configurable**: Algunas carpetas pueden ocultarse del frontend
- **Iconos y colores**: Cada carpeta tiene su identidad visual
- **Orden personalizable**: Se pueden reordenar según necesidades

## 🗃️ Tablas de Base de Datos Creadas

### Tabla Principal de Metadatos
- **`wp_actas_metadata`**: Información de cada acta PDF
  - ID, filename, título, descripción
  - Carpeta asignada, fecha de subida, usuario
  - Páginas totales, tamaño de archivo, estado
  - Hash del archivo, etiquetas, nivel de acceso

### Tabla de Carpetas Jerárquicas  
- **`wp_actas_folders`**: Sistema de carpetas organizadas
  - ID, nombre, slug, descripción
  - Relación padre-hijo, orden, visibilidad
  - Icono, color, nivel de acceso
  - Fechas de creación, usuario creador

### Tabla de Logs de Visualización
- **`wp_actas_logs`**: Registro completo de accesos
  - Usuario, número de colegiado, archivo visualizado
  - Página vista, timestamp, IP, user agent
  - ID de sesión, duración de visualización

### Tabla de Actividades Sospechosas
- **`wp_actas_suspicious_logs`**: Sistema de seguridad
  - Usuario, actividad detectada, severidad
  - Página afectada, IP, timestamp
  - Descripción detallada del evento

### Tabla de Analytics
- **`wp_actas_analytics`**: Métricas y estadísticas
  - Nombre de métrica, valor, categoría
  - Períodos de tiempo, fechas de creación/actualización

### Tabla de Sesiones de Usuario
- **`wp_actas_user_sessions`**: Control de sesiones activas
  - Usuario, token de sesión, timestamps
  - IP, user agent, estado de sesión

## 🔧 Configuración Post-Instalación

### 1. Configurar Usuarios

**Añadir Número de Colegiado a Usuarios:**
1. Ve a **Usuarios > Todos los usuarios**
2. Edita cada usuario que necesite acceso
3. En la sección **"Información de Colegiado"**, añade el número correspondiente
4. Guarda los cambios

**Roles de Usuario Disponibles:**
- **Administrator**: Acceso completo al sistema
- **Actas Manager**: Puede subir y gestionar actas
- **Actas Viewer**: Solo puede visualizar actas

### 2. Subir Primeras Actas

1. Ve a **Visor PDF > Subir Actas**
2. Selecciona un archivo PDF
3. Añade título y descripción
4. La carpeta se asignará automáticamente por año

### 3. Configurar el Shortcode

Añade el shortcode en cualquier página o entrada:

```php
[actas_hybrid]
```

**Parámetros disponibles:**
```php
[actas_hybrid carpeta="5" limite="10" mostrar_debug="false"]
```

- `carpeta`: ID de carpeta específica (0 = todas)
- `limite`: Número máximo de actas a mostrar
- `mostrar_debug`: Mostrar información de depuración

## 🛠️ Configuración Avanzada

### Opciones del Sistema

El plugin incluye 20+ opciones configurables automáticamente:

**Seguridad:**
- Requiere login y número de colegiado
- Logging de todas las actividades
- Detección de actividad sospechosa
- Tiempo máximo de sesión: 60 minutos

**Visualización:**
- Marca de agua habilitada con número de colegiado
- Zoom por defecto: 1.0x (0.5x - 3.0x)
- 5 páginas por carga (lazy loading)
- Cache habilitado (24 horas)

**Archivos:**
- Tamaño máximo: 50MB
- Solo archivos PDF permitidos
- Calidad de imagen: 150 DPI
- Compresión habilitada

### Directorios del Sistema

```
/wp-content/uploads/
└── actas-pdf/
    ├── 📁 temp/          # Archivos temporales
    ├── 📁 cache/         # Cache de imágenes
    ├── 📁 thumbnails/    # Miniaturas de páginas
    ├── 📁 watermarks/    # Marcas de agua generadas
    ├── 📁 backups/       # Respaldos del sistema
    ├── .htaccess         # Protección de acceso directo
    └── index.php         # Protección adicional
```

## 🔍 Verificación y Diagnóstico

### Panel de Estado del Sistema

Ve a **Visor PDF > Estado Sistema** para verificar:

- ✅ **Información general**: Versiones, fechas de instalación
- ✅ **Estado de tablas**: 6 tablas con conteo de registros
- ✅ **Estado de directorios**: Existencia y permisos
- ✅ **Carpetas del sistema**: Estructura jerárquica
- ✅ **Acciones de mantenimiento**: Reinstalar, actualizar, reparar

### Herramientas de Diagnóstico

**Información del sistema:**
```
Visor PDF Crisman - Diagnóstico
================================
Versión Plugin: 2.0.8-MODAL-VISIBLE
WordPress: [versión]
PHP: [versión]
MySQL: [versión]
Tablas: 6/6 creadas
Carpetas: [cantidad] | Actas: [cantidad]
```

## 🚨 Solución de Problemas Comunes

### Error: "Tablas no existen"
**Solución:**
1. Ve a **Visor PDF > Estado Sistema**
2. Haz clic en **"🔄 Reinstalar Completamente"**
3. Verifica que todas las tablas se crearon correctamente

### Error: "Directorios sin permisos"
**Solución:**
1. Verifica permisos del directorio `/wp-content/uploads/`
2. Haz clic en **"📁 Crear Directorios Faltantes"**
3. Configurar permisos 755 para directorios y 644 para archivos

### Error: "Carpetas no visibles"
**Solución:**
1. Ve a **Visor PDF > Estado Sistema**
2. Si no hay carpetas creadas, haz clic en **"Reinstalar"**
3. Las carpetas se crearán automáticamente con la estructura jerárquica

### Error: "Usuario sin acceso"
**Solución:**
1. Verifica que el usuario tenga número de colegiado asignado
2. Ve a **Usuarios > [usuario] > Información de Colegiado**
3. Añade un número de colegiado válido

## 📊 Características del Sistema

### Seguridad Avanzada
- 🔐 **Control de acceso**: Solo usuarios con número de colegiado
- 🖼️ **Marcas de agua**: Personalizadas por usuario y fecha
- 🕒 **Sesiones controladas**: Tiempo límite y heartbeat
- 📋 **Logging completo**: Todas las visualizaciones registradas
- ⚠️ **Detección de anomalías**: Actividades sospechosas monitoreadas

### Gestión de Archivos
- 📁 **Organización automática**: Por años y categorías
- 🔍 **Búsqueda avanzada**: Por carpeta, título, fecha
- 📱 **Responsive**: Funciona en todos los dispositivos
- ⚡ **Carga optimizada**: Lazy loading y cache inteligente

### Analytics y Reportes
- 📈 **Estadísticas de uso**: Visualizaciones, usuarios activos
- 🎯 **Métricas detalladas**: Por usuario, archivo, período
- 📊 **Dashboard widget**: Resumen en tiempo real
- 📋 **Reportes exportables**: Datos para análisis

## 🔄 Actualizaciones Automáticas

El plugin incluye un sistema de actualización automática:

- ✅ **Detección automática**: Verifica versiones al cargar
- ✅ **Actualización de esquema**: Tablas y opciones se actualizan
- ✅ **Migración de datos**: Los datos existentes se preservan
- ✅ **Logging de cambios**: Todos los cambios se registran

## 📞 Soporte y Mantenimiento

### Logs del Sistema
Los logs se guardan en el error log de WordPress:
```
[Visor PDF] Plugin activado correctamente - Versión: 2.0.8
[Visor PDF] Tablas de base de datos creadas/actualizadas
[Visor PDF] Estructura de carpetas creada: 9 carpetas
[Visor PDF] Opciones predeterminadas configuradas
```

### Información de Contacto
- **Autor**: Crisman
- **Versión**: 2.0.8-MODAL-VISIBLE
- **Soporte**: A través del panel de WordPress

---

## 🎉 ¡Instalación Completada!

Si has seguido estos pasos, tu sistema Visor PDF Crisman debería estar completamente operativo con:

- ✅ **6 tablas** de base de datos creadas
- ✅ **9 carpetas** jerárquicas configuradas  
- ✅ **6 directorios** del sistema con seguridad
- ✅ **20+ opciones** del sistema optimizadas
- ✅ **Roles de usuario** personalizados
- ✅ **Sistema de analytics** activado

**Próximos pasos recomendados:**
1. Añadir números de colegiado a los usuarios
2. Subir las primeras actas de prueba
3. Añadir el shortcode `[actas_hybrid]` a una página
4. Revisar los analytics en el dashboard

¡El sistema está listo para uso en producción! 🚀
