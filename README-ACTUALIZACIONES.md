# Sistema de Actualizaciones - Visor PDF Crisman

Este plugin incluye un sistema completo de actualizaciones que permite actualizar el plugin directamente desde el administrador de WordPress.

## 🔧 Configuración Inicial

### 1. Configurar URLs en el archivo principal

Edita las siguientes líneas en `visor-pdf-crisman.php`:

```php
* Plugin URI: https://github.com/TU-USUARIO/visor-pdf-crisman
* Update URI: https://github.com/TU-USUARIO/visor-pdf-crisman
```

### 2. Configurar el updater

En `includes/class-plugin-updater.php`, actualiza:

```php
private $github_username = 'TU-USUARIO';
private $github_repo = 'visor-pdf-crisman';
```

## 📦 Métodos de Actualización

### Método 1: GitHub Releases (Recomendado)

1. **Crear repositorio en GitHub**
   - Sube tu plugin a un repositorio GitHub
   - Ve a **Settings → General → Features**
   - Habilita "Releases"

2. **Crear un Release**
   ```bash
   # Crear tag
   git tag v2.0.2
   git push origin v2.0.2
   ```
   
   - Ve a tu repositorio → **Releases** → **Create a new release**
   - Tag version: `v2.0.2`
   - Title: `Versión 2.0.2`
   - Description: Changelog de la versión
   - Adjunta el ZIP del plugin (opcional)

3. **Configurar en WordPress**
   - Ve a **Visor PDF → Actualizaciones**
   - Método: `GitHub`
   - Usuario: `tu-usuario`
   - Repositorio: `visor-pdf-crisman`

### Método 2: Servidor Personalizado

1. **Subir archivo de información**
   - Sube `update-info.json` a tu servidor
   - URL ejemplo: `https://tu-servidor.com/plugins/visor-pdf-crisman/update-info.json`

2. **Subir archivo ZIP**
   - Crear ZIP del plugin actualizado
   - Subir a: `https://tu-servidor.com/plugins/visor-pdf-crisman/visor-pdf-crisman-v2.0.2.zip`

3. **Actualizar update-info.json**
   ```json
   {
     "version": "2.0.2",
     "download_url": "https://tu-servidor.com/plugins/visor-pdf-crisman/visor-pdf-crisman-v2.0.2.zip",
     "body": "Cambios en esta versión...",
     "tested": "6.4",
     "requires_php": "7.4"
   }
   ```

4. **Configurar en WordPress**
   - Método: `Servidor Personalizado`
   - Servidor de Actualización: `https://tu-servidor.com/plugins/visor-pdf-crisman/`

### Método 3: Manual

- Selecciona este método si prefieres actualizar manualmente
- No verificará actualizaciones automáticamente

## 🚀 Proceso de Actualización

### Para usuarios finales:

1. **Verificación automática**
   - WordPress verifica actualizaciones cada 12 horas
   - También puedes ir a **Plugins** y hacer clic en "Verificar actualizaciones"

2. **Notificación**
   - Aparecerá notificación en **Plugins** si hay actualizaciones
   - También en **Visor PDF → Actualizaciones**

3. **Actualizar**
   - Clic en "Actualizar ahora"
   - WordPress descarga e instala automáticamente

### Para desarrolladores:

1. **Crear nueva versión**
   ```bash
   # Actualizar versión en visor-pdf-crisman.php
   # Version: 2.0.2
   
   # Si usas GitHub:
   git add .
   git commit -m "Versión 2.0.2"
   git tag v2.0.2
   git push origin main
   git push origin v2.0.2
   ```

2. **Crear Release en GitHub**
   - Ve a tu repositorio
   - **Releases** → **Create a new release**
   - Tag: `v2.0.2`
   - Describe los cambios

3. **Verificar**
   - Los usuarios recibirán notificación de actualización
   - Pueden actualizar desde **Plugins**

## 🔐 Seguridad

### Repositorios Privados

Si tu repositorio es privado, necesitas un token:

1. **Crear token en GitHub**
   - **Settings** → **Developer settings** → **Personal access tokens**
   - Permisos: `repo` (para repositorios privados)

2. **Configurar en WordPress**
   - **Visor PDF → Actualizaciones**
   - **Token de Acceso**: Pegar tu token

### Validación de Updates

El sistema valida:
- ✅ Permisos de administrador
- ✅ Nonces de seguridad
- ✅ Versiones válidas
- ✅ URLs de descarga seguras

## 🐛 Solución de Problemas

### Error: "No se pueden verificar actualizaciones"

1. Verifica URLs en configuración
2. Asegúrate de que el repositorio/servidor es accesible
3. Revisa logs de WordPress (`wp-content/debug.log`)

### Error: "Falla al descargar actualización"

1. Verifica que el archivo ZIP existe
2. Comprueba permisos del servidor
3. Asegúrate de que no hay firewall bloqueando

### Error: "Token de acceso inválido"

1. Regenera el token en GitHub
2. Verifica que tiene permisos `repo`
3. Actualiza en la configuración del plugin

## 📋 Checklist de Release

- [ ] Actualizar versión en `visor-pdf-crisman.php`
- [ ] Actualizar `update-info.json` si usas servidor personalizado
- [ ] Crear tag en Git
- [ ] Crear Release en GitHub (si usas GitHub)
- [ ] Probar actualización en sitio de desarrollo
- [ ] Documentar cambios en changelog
- [ ] Notificar a usuarios si es necesario

## 🔗 Enlaces Útiles

- [WordPress Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
- [GitHub Releases API](https://docs.github.com/en/rest/releases/releases)
- [WordPress Plugin Headers](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/)

---

**Nota**: Recuerda actualizar todas las URLs con tu información real antes de usar en producción.