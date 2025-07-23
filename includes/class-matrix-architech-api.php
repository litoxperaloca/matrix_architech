<?php
/**
 * Maneja el endpoint de la API para el chat.
 * CORRECCIÓN: Se ha mejorado el prompt del sistema para asegurar que la IA genere un JSON válido
 * escapando los saltos de línea en el contenido.
 */
if ( ! defined( 'WPINC' ) ) die;

class Matrix_Architech_API {
	protected $namespace = 'matrix-architech/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/chat', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'handle_chat_stream' ),
			'permission_callback' => array( $this, 'permissions_check' ),
		) );
	}

	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

    private function stream_message($data) {
        if (isset($data['chunk'])) {
            $data['chunk'] = base64_encode($data['chunk']);
        }
        echo "data: " . json_encode($data) . "\n\n";
        if (ob_get_level() > 0) ob_flush();
        flush();
    }

	public function handle_chat_stream( WP_REST_Request $request ) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');

		$params = $request->get_json_params();
		$chat_history = $params['history'] ?? [];
        $action = $params['action'] ?? 'create_plugin';

        $options = get_option('matrix_architech_options');
        $api_key = $options['gemini_api_key'] ?? '';

        if (empty($api_key)) {
            $this->stream_message(['chunk' => 'Error: La API Key de Gemini no está configurada.']);
            $this->stream_message(['status' => 'done']);
            exit;
        }

        $this->stream_message(['chunk' => 'Contactando al Oráculo de la Matriz...']);

        $site_context = $this->get_site_context();

        switch ($action) {
            case 'create_page':
                $system_prompt = "Tu rol es 'El Oráculo de la Matriz'. El usuario quiere crear una página. Tu objetivo es obtener el título y el contenido. Debes proponer un diseño usando bloques de Gutenberg. Además, genera un prompt para una imagen destacada. La respuesta FINAL DEBE ser un único objeto JSON. IMPORTANTE: El valor del campo 'content' debe ser una cadena de texto JSON válida, con todos los saltos de línea escapados como \\n. La estructura es: {\"title\": \"...\", \"content\": \"...\", \"post_type\": \"page\", \"image_prompt\": \"...\"}";
                break;
            case 'create_post':
                 $system_prompt = "Tu rol es 'El Oráculo de la Matriz'. El usuario quiere crear una entrada de blog. Tu objetivo es obtener el título y el contenido. Debes proponer un diseño usando bloques de Gutenberg. Además, genera un prompt para una imagen destacada. La respuesta FINAL DEBE ser un único objeto JSON. IMPORTANTE: El valor del campo 'content' debe ser una cadena de texto JSON válida, con todos los saltos de línea escapados como \\n. La estructura es: {\"title\": \"...\", \"content\": \"...\", \"post_type\": \"post\", \"image_prompt\": \"...\"}";
                break;
            default:
                $system_prompt = "Tu rol es 'El Oráculo de la Matriz'. El usuario quiere crear un plugin. Tu respuesta FINAL DEBE ser un JSON: {\"name\": \"...\", \"slug\": \"...\", \"files\": {\"filename.php\": \"...codigo...\"}}";
                break;
        }
        
        $messages_for_api = [['role' => 'user', 'parts' => [['text' => $system_prompt]]]];
        $messages_for_api[] = ['role' => 'user', 'parts' => [['text' => "CONTEXTO DEL SITIO ACTUAL:\n" . $site_context]]];
        foreach ($chat_history as $message) {
            $role = ($message['sender'] === 'ai') ? 'model' : 'user';
            $messages_for_api[] = ['role' => $role, 'parts' => [['text' => $message['text']]]];
        }

        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$api_key}";
        $payload = ['contents' => $messages_for_api];

        $api_response = wp_remote_post($api_url, ['method'  => 'POST', 'headers' => ['Content-Type' => 'application/json'], 'body' => json_encode($payload), 'timeout' => 120]);

        if (is_wp_error($api_response)) {
            $this->stream_message(['chunk' => 'Error al contactar la API: ' . $api_response->get_error_message()]);
            $this->stream_message(['status' => 'done']);
            exit;
        }

        $body = wp_remote_retrieve_body($api_response);
        $data = json_decode($body, true);
        $ai_text_response = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        preg_match('/```json(.*)```/s', $ai_text_response, $matches);
        $json_string = $matches[1] ?? $ai_text_response;
        $json_data = json_decode(trim($json_string), true);

        if ($json_data && (isset($json_data['slug']) || isset($json_data['post_type']))) {
            switch ($action) {
                case 'create_page': case 'create_post': $this->create_post_from_ia($json_data); break;
                default:
                    $this->create_or_update_plugin_from_ia($json_data, $chat_history);
                    break;
            }
        } else {
            $this->stream_message(['chunk' => $ai_text_response]);
        }

        $this->stream_message(['status' => 'done']);
		exit;
	}

    private function create_or_update_plugin_from_ia($json_data, $chat_history) {
        $this->stream_message(['chunk' => "Generando/Actualizando archivos...\n"]);
        $generator = new Matrix_Architech_Generator();
        $generation_result = $generator->generate_files($json_data['slug'], $json_data['files']);

        if (is_wp_error($generation_result)) {
            $this->stream_message(['chunk' => 'Error: ' . $generation_result->get_error_message()]);
        } else {
            update_option('ma_chat_history_' . $json_data['slug'], $chat_history);
            $activation_result = $generator->activate($generation_result['main_file']);
            if (is_wp_error($activation_result)) {
                $this->stream_message(['chunk' => "\n¡Archivos creados/actualizados! Pero hubo un error al reactivar: " . $activation_result->get_error_message()]);
            } else {
                $this->stream_message(['chunk' => "\n¡Listo! El plugin '{$json_data['name']}' ha sido creado/actualizado y activado."]);
            }
        }
    }

    private function create_post_from_ia($json_data) {
        $this->stream_message(['chunk' => "Generando contenido...\n"]);
        
        $post_type = $json_data['post_type'] === 'page' ? 'page' : 'post';
        $post_title = sanitize_text_field($json_data['title']);
        $post_content = wp_kses_post($json_data['content']);

        $post_id = wp_insert_post(['post_title' => $post_title, 'post_content' => $post_content, 'post_type' => $post_type, 'post_status' => 'publish']);

        if (is_wp_error($post_id)) {
            $this->stream_message(['chunk' => 'Error al crear el contenido: ' . $post_id->get_error_message()]);
            return;
        }

        $this->stream_message(['chunk' => "Contenido creado. Generando imagen destacada...\n"]);
        
        $image_prompt = sanitize_text_field($json_data['image_prompt']);
        $this->generate_and_set_featured_image($post_id, $image_prompt);

        $edit_link = get_edit_post_link($post_id, 'raw');
        $view_link = get_permalink($post_id);
        $this->stream_message(['chunk' => "\n¡Listo! Se ha creado la {$post_type} '{$post_title}'.\n<a href='{$edit_link}' target='_blank'>Editar</a> | <a href='{$view_link}' target='_blank'>Ver</a>"]);
    }

    private function generate_and_set_featured_image($post_id, $prompt) {
        $options = get_option('matrix_architech_options');
        $api_key = $options['gemini_api_key'] ?? '';
        if (empty($api_key)) return;

        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/imagen-2.0-generate-002:predict?key={$api_key}";
        
        $payload = ['instances' => [['prompt' => "Fotografía profesional de: " . $prompt]], 'parameters' => ['sampleCount' => 1]];
        $response = wp_remote_post($api_url, ['method'  => 'POST', 'headers' => ['Content-Type' => 'application/json'], 'body' => json_encode($payload), 'timeout' => 120]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            error_log('Error en la API de Imagen: ' . wp_remote_retrieve_body($response));
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $image_data_base64 = $body['predictions'][0]['bytesBase64Encoded'] ?? null;

        if (!$image_data_base64) {
            error_log('No se recibió la data de la imagen en base64.');
            return;
        }

        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        $temp_file = wp_tempnam($prompt);
        file_put_contents($temp_file, base64_decode($image_data_base64));

        $file = ['name' => sanitize_title($prompt) . '.png', 'tmp_name' => $temp_file, 'type' => 'image/png', 'error' => 0, 'size' => filesize($temp_file)];
        $attachment_id = media_handle_sideload($file, $post_id, $prompt);

        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            error_log('Error al subir imagen: ' . $attachment_id->get_error_message());
            return;
        }

        if ($post_id > 0) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    private function get_site_context() {
        $theme = wp_get_theme();
        $context = "Tema Activo: " . $theme->get('Name') . " v" . $theme->get('Version') . ".\n";
        $context .= "Plugins Activos Relevantes: ";
        $relevant_plugins = [];
        if (is_plugin_active('woocommerce/woocommerce.php')) $relevant_plugins[] = 'WooCommerce';
        if (is_plugin_active('advanced-custom-fields-pro/acf.php') || is_plugin_active('advanced-custom-fields/acf.php')) $relevant_plugins[] = 'Advanced Custom Fields';
        if (is_plugin_active('elementor/elementor.php')) $relevant_plugins[] = 'Elementor';
        $context .= empty($relevant_plugins) ? "Ninguno detectado." : implode(', ', $relevant_plugins) . ".";
        return $context;
    }
}
