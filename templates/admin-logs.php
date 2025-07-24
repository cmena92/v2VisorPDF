<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1>Logs de Visualización</h1>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" style="display: inline-block;">
                <input type="hidden" name="page" value="visor-pdf-crisman-logs">
                <input type="text" name="search_user" placeholder="Buscar por usuario..." 
                       value="<?php echo esc_attr($_GET['search_user'] ?? ''); ?>">
                <input type="text" name="search_colegiado" placeholder="Número de colegiado..." 
                       value="<?php echo esc_attr($_GET['search_colegiado'] ?? ''); ?>">
                <input type="submit" class="button" value="Filtrar">
                <?php if (!empty($_GET['search_user']) || !empty($_GET['search_colegiado'])): ?>
                    <a href="<?php echo admin_url('admin.php?page=visor-pdf-crisman-logs'); ?>" class="button">Limpiar</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="alignright actions">
            <span class="displaying-num"><?php echo count($logs); ?> registros</span>
        </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col">Usuario</th>
                <th scope="col">Nº Colegiado</th>
                <th scope="col">Acta</th>
                <th scope="col">Página Vista</th>
                <th scope="col">Fecha/Hora</th>
                <th scope="col">IP</th>
                <th scope="col">Navegador</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px;">
                        No hay registros de visualización disponibles.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <?php echo esc_html($log->display_name ?: 'Usuario eliminado'); ?>
                            <br><small>ID: <?php echo $log->user_id; ?></small>
                        </td>
                        <td><strong><?php echo esc_html($log->numero_colegiado); ?></strong></td>
                        <td>
                            <?php echo esc_html($log->acta_title ?: $log->acta_filename); ?>
                            <br><small><?php echo esc_html($log->acta_filename); ?></small>
                        </td>
                        <td>Página <?php echo intval($log->page_viewed); ?></td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($log->viewed_at)); ?></td>
                        <td><?php echo esc_html($log->ip_address); ?></td>
                        <td>
                            <small title="<?php echo esc_attr($log->user_agent); ?>">
                                <?php echo esc_html(wp_trim_words($log->user_agent, 8)); ?>
                            </small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 20px;">
        <h3>Estadísticas</h3>
        <?php
        if (!empty($logs)) {
            $usuarios_unicos = array_unique(array_column($logs, 'user_id'));
            $actas_vistas = array_unique(array_column($logs, 'acta_filename'));
            ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                    <h4 style="margin-top: 0;">Total Visualizaciones</h4>
                    <p style="font-size: 24px; font-weight: bold; color: #0073aa; margin: 0;">
                        <?php echo count($logs); ?>
                    </p>
                </div>
                <div style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                    <h4 style="margin-top: 0;">Usuarios Únicos</h4>
                    <p style="font-size: 24px; font-weight: bold; color: #00a32a; margin: 0;">
                        <?php echo count($usuarios_unicos); ?>
                    </p>
                </div>
                <div style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                    <h4 style="margin-top: 0;">Actas Visualizadas</h4>
                    <p style="font-size: 24px; font-weight: bold; color: #d63638; margin: 0;">
                        <?php echo count($actas_vistas); ?>
                    </p>
                </div>
            </div>
        <?php } ?>
    </div>
</div>