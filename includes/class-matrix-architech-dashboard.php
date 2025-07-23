<?php
/**
 * Maneja el dashboard de plugins generados.
 */
if ( ! defined( 'WPINC' ) ) die;

class Matrix_Architech_Dashboard {
    public static function render_page() {
        ?>
        <div class="wrap ma-dashboard">
            <h1>Mis Plugins Generados</h1>
            
            <?php
            if (isset($_GET['message']) && $_GET['message'] === 'duplicated') {
                echo '<div class="notice notice-success is-dismissible"><p>Plugin duplicado con éxito.</p></div>';
            }
            ?>

            <p>Aquí puedes gestionar todos los plugins que has creado con Matrix's Architech.</p>
            <div class="ma-plugin-list">
                <?php
                if ( ! function_exists( 'get_plugins' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }
                $all_plugins = get_plugins();
                $generated_plugins = array_filter($all_plugins, function($plugin_data) {
                    return isset($plugin_data['Author']) && strpos($plugin_data['Author'], 'El Oráculo de la Matriz') !== false;
                });

                if (empty($generated_plugins)) {
                    echo '<p>Aún no has creado ningún plugin. ¡Ve al <a href="' . admin_url('admin.php?page=matrix-architech') . '">chat</a> para empezar!</p>';
                } else {
                    foreach ($generated_plugins as $plugin_file => $plugin_data) {
                        self::render_plugin_card($plugin_file, $plugin_data);
                    }
                }
                ?>
            </div>
        </div>
        <?php
    }

    private static function render_plugin_card($plugin_file, $plugin_data) {
        $is_active = is_plugin_active($plugin_file);
        $plugin_slug = dirname($plugin_file);
        ?>
        <div class="ma-plugin-card">
            <div class="ma-card-header">
                <h3><?php echo esc_html($plugin_data['Name']); ?></h3>
                <span class="ma-version-badge">v<?php echo esc_html($plugin_data['Version']); ?></span>
            </div>
            <div class="ma-card-body">
                <p><?php echo esc_html($plugin_data['Description']); ?></p>
            </div>
            <div class="ma-card-actions">
                <?php if ($is_active) : ?>
                    <a href="<?php echo wp_nonce_url(admin_url('plugins.php?action=deactivate&plugin=' . urlencode($plugin_file)), 'deactivate-plugin_' . $plugin_file); ?>" class="button">Desactivar</a>
                <?php else : ?>
                    <a href="<?php echo wp_nonce_url(admin_url('plugins.php?action=activate&plugin=' . urlencode($plugin_file)), 'activate-plugin_' . $plugin_file); ?>" class="button button-primary">Activar</a>
                <?php endif; ?>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=matrix-architech&action=evaluate_content&plugin_slug=' . urlencode($plugin_slug))); ?>" class="button">Evaluar y Mejorar</a>
                
                <?php
                $duplicate_url = wp_nonce_url(admin_url('admin.php?page=matrix-architech-dashboard&action=ma_duplicate&plugin_slug=' . urlencode($plugin_slug)), 'ma_duplicate_nonce_' . $plugin_slug);
                ?>
                <a href="<?php echo esc_url($duplicate_url); ?>" class="button">Duplicar</a>

                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=matrix-architech-dashboard&action=ma_download&plugin_slug=' . urlencode($plugin_slug)), 'ma_download_nonce'); ?>" class="button">Descargar (.zip)</a>
                
                <a href="<?php echo wp_nonce_url(admin_url('plugins.php?action=delete-selected&checked[]=' . urlencode($plugin_file)), 'delete-plugin_' . $plugin_file); ?>" class="button ma-delete-button">Eliminar</a>
            </div>
        </div>
        <?php
    }
}
