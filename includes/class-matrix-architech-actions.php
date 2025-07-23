<?php
/**
 * Maneja acciones del dashboard como duplicar plugins.
 */
if ( ! defined( 'WPINC' ) ) die;

class Matrix_Architech_Actions {
    public function __construct() {
        add_action('admin_init', array($this, 'handle_duplicate_request'));
    }

    public function handle_duplicate_request() {
        if (isset($_GET['action']) && $_GET['action'] === 'ma_duplicate' && isset($_GET['plugin_slug'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ma_duplicate_nonce_' . $_GET['plugin_slug']) || !current_user_can('manage_options')) {
                wp_die('No tienes permiso para realizar esta acción.');
            }

            $original_slug = sanitize_file_name($_GET['plugin_slug']);
            $original_dir = WP_PLUGIN_DIR . '/' . $original_slug;

            if (!is_dir($original_dir)) {
                wp_die('El directorio del plugin original no existe.');
            }

            $new_slug = $original_slug . '-copia';
            $i = 2;
            while (is_dir(WP_PLUGIN_DIR . '/' . $new_slug)) {
                $new_slug = $original_slug . '-copia-' . $i;
                $i++;
            }
            $new_dir = WP_PLUGIN_DIR . '/' . $new_slug;

            $this->copy_directory($original_dir, $new_dir);

            $original_main_file = $new_dir . '/' . $original_slug . '.php';
            if (file_exists($original_main_file)) {
                $file_contents = file_get_contents($original_main_file);
                $plugin_data = get_plugin_data($original_dir . '/' . $original_slug . '.php');
                $original_name = $plugin_data['Name'];
                $new_name = $original_name . ' (Copia)';

                $file_contents = preg_replace("/^(\s*?\*\s*?Plugin Name:).*$/m", "$1 " . $new_name, $file_contents);
                file_put_contents($original_main_file, $file_contents);

                rename($original_main_file, $new_dir . '/' . $new_slug . '.php');
            }

            wp_redirect(admin_url('admin.php?page=matrix-architech-dashboard&message=duplicated'));
            exit;
        }
    }

    private function copy_directory($source, $destination) {
        if (!is_dir($destination)) {
            wp_mkdir_p($destination);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $dest_path = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                wp_mkdir_p($dest_path);
            } else {
                copy($item, $dest_path);
            }
        }
    }
}
