<?php
/**
 * Genera archivos, ejecuta comandos y activa el plugin.
 * Corregido para crear subdirectorios necesarios.
 */
if ( ! defined( 'WPINC' ) ) die;

class Matrix_Architech_Generator {

    private $allowed_commands = ['npm', 'composer', 'git'];

	public function generate_files( $plugin_slug, $files ) {
		if ( empty( $plugin_slug ) || empty( $files ) ) {
			return new WP_Error( 'invalid_args', 'El slug y los archivos del plugin no pueden estar vacíos.' );
		}

		$plugins_dir = WP_PLUGIN_DIR;
		$plugin_dir_path = trailingslashit( $plugins_dir ) . $plugin_slug;

		if ( ! is_writable( $plugins_dir ) ) {
			return new WP_Error( 'fs_error', 'El directorio de plugins no es escribible.' );
		}

		if ( ! is_dir( $plugin_dir_path ) ) {
			if ( ! wp_mkdir_p( $plugin_dir_path ) ) {
				return new WP_Error( 'fs_error', 'No se pudo crear el directorio para el nuevo plugin.' );
			}
		}

        $main_plugin_file_path = '';

		foreach ( $files as $filename => $content ) {
			$file_path = trailingslashit( $plugin_dir_path ) . $filename;
            
            $file_dir = dirname($file_path);
            if (!is_dir($file_dir)) {
                if (!wp_mkdir_p($file_dir)) {
                    return new WP_Error('fs_error', "No se pudo crear el subdirectorio: {$file_dir}");
                }
            }

			if ( file_put_contents( $file_path, $content ) === false ) {
				return new WP_Error( 'fs_error', "No se pudo crear el archivo: {$filename}" );
			}

            if (empty($main_plugin_file_path) && pathinfo($filename, PATHINFO_EXTENSION) === 'php' && strpos($content, 'Plugin Name:') !== false) {
                $main_plugin_file_path = $plugin_slug . '/' . $filename;
            }
		}

		return ['dir_path' => $plugin_dir_path, 'main_file' => $main_plugin_file_path];
	}

    public function execute_commands($plugin_dir_path, $commands) {
        if (!is_dir($plugin_dir_path)) {
            return new WP_Error('invalid_path', 'El directorio del plugin no existe.');
        }

        $original_dir = getcwd();
        if (!@chdir($plugin_dir_path)) {
            return new WP_Error('chdir_failed', 'No se pudo cambiar al directorio del plugin.');
        }

        $output = '';
        foreach ($commands as $command) {
            $command_parts = explode(' ', $command);
            $base_command = $command_parts[0];

            if (!in_array($base_command, $this->allowed_commands)) {
                $output .= "Comando no permitido: " . esc_html($command) . "\n";
                continue;
            }
            
            $full_command = $command . ' 2>&1';
            $output .= "--- Ejecutando: " . esc_html($command) . " ---\n";
            $output .= shell_exec($full_command);
            $output .= "\n";
        }

        chdir($original_dir);
        return $output;
    }

    public function activate($main_plugin_file) {
        if (empty($main_plugin_file)) {
            return new WP_Error('file_not_found', 'No se pudo determinar el archivo principal del plugin para activar.');
        }

		$result = activate_plugin( $main_plugin_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
        return true;
    }
}
