# Visor PDF Crisman - Plugin WordPress

Plugin seguro para gestionar, visualizar y controlar acceso a documentos PDF (actas) con marcas de agua personalizadas y sistema completo de auditoría.

## 🚀 Características Principales

- **Carga Segura**: Subida de archivos PDF a carpeta protegida del servidor
- **Visualización Controlada**: Los usuarios pueden ver pero no descargar las actas
- **Marcas de Agua**: Cada página se marca automáticamente con el número de colegiado del usuario
- **Auditoría Completa**: Registro detallado de todas las visualizaciones y actividades
- **Protección Anti-descarga**: Múltiples capas de seguridad para prevenir descargas no autorizadas
- **Navegación Fluida**: Interfaz intuitiva para navegar por las páginas del documento
- **Responsive**: Totalmente adaptable a dispositivos móviles

## 📋 Requisitos del Sistema

### Requisitos del Servidor
- WordPress 5.0 o superior
- PHP 7.4 o superior
- **Extensión Imagick de PHP** (requerida para procesar PDF)
- MySQL 5.7 o superior
- Mínimo 256MB de memoria PHP (recomendado 512MB)

### Verificar Imagick
Para verificar si Imagick está instalado:
```php
<?php
if (extension_loaded('imagick')) {
    echo "Imagick está instalado";
    $imagick = new Imagick();
    $formats = $imagick->queryFormats('PDF');
    if (in_array('PDF', $formats)) {
        echo "PDF es compatible";
    }
} else {
    echo "Imagick NO está instalado";
}
?>
```

## 📦 Instalación

### Instalación Manual

1. **Copia la carpeta del plugin**:
   - Copia la carpeta `visor_pdf_crisman` a `/wp-content/plugins/`

2. **Activa el plugin**:
   - Ve a Plugins → Plugins Instalados
   - Busca "Visor PDF Crisman" y haz clic en "Activar"

3. **Verifica requisitos**:
   - Ve a **Visor PDF → Requisitos del Sistema**
   - Asegúrate de que todos los requisitos estén cumplidos

## ⚙️ Configuración Inicial

### 1. Verificar Permisos de Carpetas
El plugin creará automáticamente:
- `/wp-content/uploads/actas-pdf/` (carpeta protegida para PDFs)
- Archivo `.htaccess` en la carpeta para denegar acceso directo

### 2. Configurar Números de Colegiado
Para cada usuario que necesite acceso:
1. Ve a Usuarios → Todos los usuarios
2. Edita el usuario
3. En la sección "Información de Colegiado", ingresa el número correspondiente
4. Guarda los cambios

### 3. Instalar Imagick (si es necesario)

#### En Ubuntu/Debian:
```bash
sudo apt-get update
sudo apt-get install php-imagick
sudo service apache2 restart
```

#### En CentOS/RHEL:
```bash
sudo yum install php-imagick
sudo service httpd restart
```

#### En Windows (XAMPP):
1. Descarga la extensión Imagick para tu versión de PHP
2. Copia los archivos DLL a la carpeta ext/ de PHP
3. Agrega "extension=imagick" en php.ini
4. Reinicia Apache

## 🎯 Uso del Plugin

### Para Administradores

#### Subir Actas
1. Ve a **Visor PDF → Subir Actas**
2. Completa el formulario:
   - **Título**: Nombre descriptivo del acta
   - **Descripción**: (Opcional) Descripción del contenido
   - **Archivo PDF**: Selecciona el archivo PDF
3. Haz clic en "Subir Acta"

#### Gestionar Actas
1. Ve a **Visor PDF** para ver todas las actas
2. Puedes eliminar actas desde la lista
3. Usa **Visor PDF → Logs** para revisar accesos y actividades

### Para Usuarios Finales

#### Mostrar Actas en el Frontend
Usa el shortcode en cualquier página o entrada:

```
[actas_viewer]
```

**Parámetros opcionales**:
- `[actas_viewer limite="5"]` - Limitar número de actas mostradas
- `[actas_viewer categoria="general"]` - Filtrar por categoría (funcionalidad futura)

#### Visualizar Actas
1. Los usuarios deben estar logueados
2. Deben tener un número de colegiado asignado
3. Hacen clic en "Ver Acta" para abrir el visor
4. Navegan usando los controles de página

## 🔒 Características de Seguridad

### Protección de Archivos
- Los PDFs se almacenan en carpeta protegida con `.htaccess`
- No es posible acceder directamente por URL
- Los archivos tienen nombres únicos generados automáticamente

### Marcas de Agua
- Cada página se marca con el número de colegiado del usuario
- Marca de agua diagonal repetida en toda la página
- Incluye fecha y hora de visualización

### Auditoría y Logs
- Se registra cada página vista por cada usuario
- Logs de actividades sospechosas (intentos de descarga, etc.)
- Información de IP y navegador para cada acceso

### Protecciones Frontend
- Deshabilitación de herramientas de desarrollador
- Bloqueo de clic derecho y selección de texto
- Detección de cambio de ventana/pestaña
- Ofuscación de console.log
- Prevención de drag & drop

## 📊 Monitoreo y Análisis

### Panel de Administración
El plugin incluye tres secciones administrativas:

1. **Lista de Actas**: Gestión de documentos subidos
2. **Subir Actas**: Formulario para nuevos documentos
3. **Logs**: Auditoría completa de accesos
4. **Requisitos del Sistema**: Verificación de dependencias

### Métricas Disponibles
- Total de visualizaciones
- Usuarios únicos que han accedido
- Actas más consultadas
- Actividades sospechosas detectadas

## 🛠️ Personalización

### Modificar Estilos
Edita `/assets/visor-pdf.css` para personalizar:
- Colores del tema
- Diseño de las tarjetas de actas
- Estilo del visor modal

### Configuración de Marcas de Agua
Modifica la función `generate_page_with_watermark()` para:
- Cambiar posición de la marca de agua
- Modificar transparencia
- Usar diferentes fuentes
- Agregar logos o imágenes

## 🐛 Resolución de Problemas

### Error: "Imagick no está disponible"
1. Instala la extensión Imagick siguiendo las instrucciones arriba
2. Reinicia el servidor web
3. Verifica en **Visor PDF → Requisitos del Sistema**

### Error: "No se puede cargar la página"
1. Verifica permisos de la carpeta `/wp-content/uploads/actas-pdf/`
2. Asegúrate de que Imagick puede procesar PDFs
3. Revisa los logs de error de PHP

### Error: "Usuario sin número de colegiado"
1. Ve a Usuarios → Editar usuario
2. Completa el campo "Número de Colegiado"
3. Guarda los cambios

### Problemas de Rendimiento
1. Aumenta el límite de memoria PHP:
```php
ini_set('memory_limit', '512M');
```

2. Optimiza la resolución de las imágenes en `generate_page_with_watermark()`:
```php
$imagick->setResolution(100, 100); // Menor resolución = mejor rendimiento
```

## 📝 Estructura de Archivos

```
visor_pdf_crisman/
├── visor-pdf-crisman.php          # Archivo principal del plugin
├── README.md                      # Esta documentación
├── assets/
│   ├── visor-pdf.js               # JavaScript del visor
│   └── visor-pdf.css              # Estilos CSS
├── includes/
│   ├── install-utils.php          # Utilidades de instalación
│   └── security-config.php        # Configuraciones de seguridad
└── templates/
    ├── admin-list.php             # Lista de actas (admin)
    ├── admin-upload.php           # Formulario de subida (admin)
    ├── admin-logs.php             # Logs de visualización (admin)
    └── viewer.php                 # Visor frontend
```

## 📞 Soporte

Para soporte técnico o consultas:
- Revisa **Visor PDF → Requisitos del Sistema** en el admin
- Verifica los logs de WordPress en `/wp-content/debug.log`
- Asegúrate de que Imagick esté instalado y configurado
- Consulta la documentación de WordPress para resolución de problemas con plugins

## 📄 Licencia

Este plugin se distribuye bajo la licencia GPL v2 o posterior.

---

**Versión**: 1.0.0  
**Autor**: Crisman  
**Compatibilidad**: WordPress 5.0+, PHP 7.4+

## 🎉 ¡Instalación Completada!

Ya tienes todos los archivos del plugin **Visor PDF Crisman** creados en:
`C:\dev_cloud\visor_pdf_crisman\`

### Próximos pasos:

1. **Copia la carpeta** a tu instalación de WordPress: `/wp-content/plugins/`
2. **Activa el plugin** en WordPress Admin
3. **Verifica requisitos** en Visor PDF → Requisitos del Sistema
4. **Configura usuarios** con números de colegiado
5. **Sube tu primera acta** y prueba el sistema

¡El plugin está listo para usar! 🚀