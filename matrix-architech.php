<?php
/**
 * Plugin Name:       Matrix's Architech
 * Description:       Un asistente de IA para WordPress que crea, modifica y gestiona plugins a través de una interfaz de chat conversacional, impulsado por Gemini.
 * Version:           3.2.1
 * Author:            Lito (p.pignolo@ironplatform.com.uy) y Gemini 2.5 (como 'El Oráculo de la Matriz')
 * Author URI:        https://ironplatform.com.uy
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       matrix-architech
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) die;

define( 'MATRIX_ARCHITECH_VERSION', '3.2.1' );
define( 'MATRIX_ARCHITECH_PATH', plugin_dir_path( __FILE__ ) );
define( 'MATRIX_ARCHITECH_URL', plugin_dir_url( __FILE__ ) );

require_once MATRIX_ARCHITECH_PATH . 'includes/class-matrix-architech-api.php';
require_once MATRIX_ARCHITECH_PATH . 'includes/class-matrix-architech-generator.php';
require_once MATRIX_ARCHITECH_PATH . 'includes/class-matrix-architech-settings.php';
require_once MATRIX_ARCHITECH_PATH . 'includes/class-matrix-architech-dashboard.php';
require_once MATRIX_ARCHITECH_PATH . 'includes/class-matrix-architech-downloader.php';
require_once MATRIX_ARCHITECH_PATH . 'includes/class-matrix-architech-actions.php';

final class Matrix_Architech_Main {
    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

    public function init() {
        new Matrix_Architech_API();
        new Matrix_Architech_Settings();
        new Matrix_Architech_Dashboard();
        new Matrix_Architech_Downloader();
        new Matrix_Architech_Actions();
        add_action( 'admin_menu', array( $this, 'ma_add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'ma_enqueue_admin_scripts' ) );
    }

    public function ma_add_admin_menu() {
        add_menu_page('Matrix\'s Architech', 'Architech', 'manage_options', 'matrix-architech', array( $this, 'ma_render_chat_page' ), 'dashicons-laptop', 6);
        add_submenu_page('matrix-architech', 'Mis Plugins', 'Mis Plugins', 'manage_options', 'matrix-architech-dashboard', array( 'Matrix_Architech_Dashboard', 'render_page' ));
        add_submenu_page('matrix-architech', 'Ajustes', 'Ajustes', 'manage_options', 'matrix-architech-settings', array( 'Matrix_Architech_Settings', 'render_settings_page' ));
    }

    public function ma_render_chat_page() {
        echo '<div id="matrix-architech-app" class="wrap"><h1>Matrix\'s Architech</h1><p>Selecciona una acción para comenzar.</p></div>';
    }

    public function ma_enqueue_admin_scripts( $hook ) {
        $current_screen = get_current_screen();
        if ( strpos($current_screen->id, 'matrix-architech') === false ) {
            return;
        }

        wp_enqueue_style('matrix-architech-admin-css', MATRIX_ARCHITECH_URL . 'assets/css/admin.css', array(), MATRIX_ARCHITECH_VERSION);

        if ( 'toplevel_page_matrix-architech' === $hook ) {
            wp_enqueue_script( 'vue', 'https://unpkg.com/vue@3/dist/vue.global.prod.js', array(), '3.2.47', true );
            wp_enqueue_script('matrix-architech-admin-js', MATRIX_ARCHITECH_URL . 'assets/js/admin.js', array( 'vue', 'wp-api-fetch' ), MATRIX_ARCHITECH_VERSION, true);

            $config_array = ['nonce' => wp_create_nonce('wp_rest'), 'rest_url' => rest_url('matrix-architech/v1/')];

            if (isset($_GET['action']) && $_GET['action'] === 'evaluate_content' && isset($_GET['plugin_slug'])) {
                $plugin_slug = sanitize_file_name($_GET['plugin_slug']);
                $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
                $all_files = [];

                if (is_dir($plugin_dir)) {
                    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS));
                    foreach ($iterator as $file) {
                        if ($file->isFile()) {
                            $all_files[$file->getBasename()] = file_get_contents($file->getRealPath());
                        }
                    }
                }

                $config_array['evaluate_plugin'] = [
                    'slug' => $plugin_slug,
                    'files' => $all_files,
                ];
            }
            wp_localize_script('matrix-architech-admin-js', 'ma_config', $config_array);
        }
    }
}
Matrix_Architech_Main::get_instance();
