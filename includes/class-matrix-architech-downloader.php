<?php
/**
 * Maneja la descarga de plugins como .zip.
 */
if ( ! defined( 'WPINC' ) ) die;

class Matrix_Architech_Downloader {
    public function __construct() {
        add_action('admin_init', array($this, 'handle_download_request'));
    }

    public function handle_download_request() {
        if (isset($_GET['action']) && $_GET['action'] === 'ma_download' && isset($_GET['plugin_slug'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'ma_download_nonce') || !current_user_can('manage_options')) {
                wp_die('No tienes permiso para hacer esto.');
            }

            $plugin_slug = sanitize_file_name($_GET['plugin_slug']);
            $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;

            if (!is_dir($plugin_dir)) {
                wp_die('El directorio del plugin no existe.');
            }

            $zip_file = tempnam(sys_get_temp_dir(), 'plugin_') . '.zip';
            $zip = new ZipArchive();

            if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
                wp_die('No se pudo crear el archivo ZIP.');
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($plugin_dir) + 1);
                    $zip->addFile($filePath, $plugin_slug . '/' . $relativePath);
                }
            }

            $zip->close();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $plugin_slug . '.zip"');
            header('Content-Length: ' . filesize($zip_file));
            readfile($zip_file);
            unlink($zip_file);
            exit;
        }
    }
}
