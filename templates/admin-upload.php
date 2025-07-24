<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1>Subir Nueva Acta</h1>
    
    <?php if (!empty($message)): ?>
        <div class="notice notice-<?php echo $result['success'] ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data" class="actas-upload-form">
        <?php wp_nonce_field('upload_acta'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="acta_title">Título del Acta *</label>
                </th>
                <td>
                    <input type="text" name="acta_title" id="acta_title" 
                           class="regular-text" required 
                           placeholder="Ej: Acta de Sesión Ordinaria - Enero 2025">
                    <p class="description">Ingrese un título descriptivo para identificar el acta.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acta_description">Descripción</label>
                </th>
                <td>
                    <textarea name="acta_description" id="acta_description" 
                              rows="4" cols="50" class="large-text"
                              placeholder="Descripción opcional del contenido del acta..."></textarea>
                    <p class="description">Descripción opcional que aparecerá en la lista de actas.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acta_pdf">Archivo PDF *</label>
                </th>
                <td>
                    <input type="file" name="acta_pdf" id="acta_pdf" 
                           accept=".pdf" required>
                    <p class="description">
                        Solo se permiten archivos PDF. Tamaño máximo: <?php echo size_format(wp_max_upload_size()); ?>
                    </p>
                    
                    <div id="file-preview" style="display: none; margin-top: 10px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9;">
                        <strong>Archivo seleccionado:</strong>
                        <div id="file-info"></div>
                    </div>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="upload_acta" class="button button-primary" value="Subir Acta">
            <a href="<?php echo admin_url('admin.php?page=visor-pdf-crisman'); ?>" class="button">Cancelar</a>
        </p>
    </form>
    
    <div class="actas-upload-info">
        <h3>Información Importante</h3>
        <div style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
            <h4>Consideraciones de Seguridad:</h4>
            <ul>
                <li>Los archivos se almacenan en una carpeta protegida del servidor</li>
                <li>Solo usuarios autenticados pueden ver las actas</li>
                <li>Cada visualización se registra con marca de agua del número de colegiado</li>
                <li>No es posible descargar los archivos directamente</li>
                <li>Se registran todos los accesos y actividades sospechosas</li>
            </ul>
            
            <h4>Requisitos Técnicos:</h4>
            <ul>
                <li>Formato: Solo archivos PDF</li>
                <li>Tamaño máximo: <?php echo size_format(wp_max_upload_size()); ?></li>
                <li>El servidor debe tener Imagick instalado para procesar los PDF</li>
            </ul>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#acta_pdf').on('change', function() {
        const file = this.files[0];
        if (file) {
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            const maxSize = <?php echo wp_max_upload_size() / 1024 / 1024; ?>;
            
            let info = `
                <strong>Nombre:</strong> ${file.name}<br>
                <strong>Tamaño:</strong> ${fileSize} MB<br>
                <strong>Tipo:</strong> ${file.type}
            `;
            
            if (file.type !== 'application/pdf') {
                info += '<br><span style="color: red;">⚠️ Solo se permiten archivos PDF</span>';
                $(this).val('');
            } else if (fileSize > maxSize) {
                info += '<br><span style="color: red;">⚠️ El archivo es demasiado grande</span>';
                $(this).val('');
            } else {
                info += '<br><span style="color: green;">✓ Archivo válido</span>';
            }
            
            $('#file-info').html(info);
            $('#file-preview').show();
        } else {
            $('#file-preview').hide();
        }
    });
});
</script>