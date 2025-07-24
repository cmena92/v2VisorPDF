<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1>Visor PDF Crisman - Lista de Actas</h1>
    
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
        <div class="notice notice-success is-dismissible">
            <p>Acta eliminada correctamente.</p>
        </div>
    <?php endif; ?>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=visor-pdf-crisman-upload'); ?>" class="button button-primary">
                Subir Nueva Acta
            </a>
        </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column">Título</th>
                <th scope="col" class="manage-column">Archivo Original</th>
                <th scope="col" class="manage-column">Páginas</th>
                <th scope="col" class="manage-column">Tamaño</th>
                <th scope="col" class="manage-column">Fecha de Subida</th>
                <th scope="col" class="manage-column">Subido por</th>
                <th scope="col" class="manage-column">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($actas)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px;">
                        No hay actas disponibles. <a href="<?php echo admin_url('admin.php?page=visor-pdf-crisman-upload'); ?>">Subir la primera acta</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($actas as $acta): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($acta->title ?: 'Sin título'); ?></strong>
                            <?php if ($acta->description): ?>
                                <br><small><?php echo esc_html(wp_trim_words($acta->description, 15)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($acta->original_name); ?></td>
                        <td><?php echo intval($acta->total_pages); ?> páginas</td>
                        <td><?php echo size_format($acta->file_size); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($acta->upload_date)); ?></td>
                        <td>
                            <?php 
                            $user = get_user_by('id', $acta->uploaded_by);
                            echo $user ? esc_html($user->display_name) : 'Usuario eliminado';
                            ?>
                        </td>
                        <td>
                            <form method="post" style="display: inline-block;" 
                                  onsubmit="return confirm('¿Está seguro de que desea eliminar esta acta?');">
                                <?php wp_nonce_field('delete_acta'); ?>
                                <input type="hidden" name="acta_id" value="<?php echo $acta->id; ?>">
                                <button type="submit" name="delete_acta" class="button button-small button-link-delete">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 20px;">
        <h3>Instrucciones de Uso</h3>
        <p><strong>Shortcode para mostrar el visor en el frontend:</strong></p>
        <code>[actas_viewer]</code>
        
        <p style="margin-top: 15px;"><strong>Parámetros opcionales:</strong></p>
        <ul>
            <li><code>[actas_viewer limite="5"]</code> - Limitar número de actas mostradas</li>
            <li><code>[actas_viewer categoria="general"]</code> - Filtrar por categoría (funcionalidad futura)</li>
        </ul>
        
        <p style="margin-top: 15px;"><strong>Requisitos importantes:</strong></p>
        <ul>
            <li>Los usuarios deben estar logueados para ver las actas</li>
            <li>Cada usuario debe tener un número de colegiado asignado en su perfil</li>
            <li>Se requiere la extensión Imagick de PHP para la generación de imágenes</li>
            <li>Las actas se almacenan de forma segura y no son accesibles directamente por URL</li>
        </ul>
    </div>
</div>