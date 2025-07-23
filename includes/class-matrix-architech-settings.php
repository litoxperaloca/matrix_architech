<?php
/**
 * Clase para manejar la página de ajustes del plugin.
 * Añadido selector de modelo de IA.
 */
if ( ! defined( 'WPINC' ) ) die;

class Matrix_Architech_Settings {

    private static $settings_page_hook = 'matrix-architech-settings';
    private $models = [
        'gemini-1.5-flash' => 'Gemini 1.5 Flash (Rápido)',
        'gemini-1.5-pro'   => 'Gemini 1.5 Pro (Avanzado)',
    ];

    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function register_settings() {
        register_setting(
            'matrix_architech_options_group',
            'matrix_architech_options',
            array( $this, 'sanitize' )
        );

        add_settings_section(
            'ma_api_settings_section',
            'Ajustes de API',
            array( $this, 'print_section_info' ),
            self::$settings_page_hook
        );

        add_settings_field(
            'gemini_api_key',
            'API Key de Gemini',
            array( $this, 'api_key_callback' ),
            self::$settings_page_hook,
            'ma_api_settings_section'
        );

        add_settings_field(
            'gemini_model',
            'Modelo de Gemini',
            array( $this, 'model_callback' ),
            self::$settings_page_hook,
            'ma_api_settings_section'
        );
    }

    public function sanitize( $input ) {
        $new_input = array();
        if ( isset( $input['gemini_api_key'] ) ) {
            $new_input['gemini_api_key'] = sanitize_text_field( $input['gemini_api_key'] );
        }
        if ( isset( $input['gemini_model'] ) && array_key_exists($input['gemini_model'], $this->models) ) {
            $new_input['gemini_model'] = $input['gemini_model'];
        }
        return $new_input;
    }

    public function print_section_info() {
        echo '<p>Introduce tus credenciales de API y selecciona el modelo de IA a utilizar.</p>';
    }

    public function api_key_callback() {
        $options = get_option( 'matrix_architech_options' );
        printf(
            '<input type="password" id="gemini_api_key" name="matrix_architech_options[gemini_api_key]" value="%s" class="regular-text" />',
            isset( $options['gemini_api_key'] ) ? esc_attr( $options['gemini_api_key'] ) : ''
        );
    }

    public function model_callback() {
        $options = get_option( 'matrix_architech_options' );
        $current_model = $options['gemini_model'] ?? 'gemini-1.5-flash';
        
        echo '<select id="gemini_model" name="matrix_architech_options[gemini_model]">';
        foreach ($this->models as $model_key => $model_name) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($model_key),
                selected($current_model, $model_key, false),
                esc_html($model_name)
            );
        }
        echo '</select>';
    }

    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'matrix_architech_options_group' );
                do_settings_sections( self::$settings_page_hook );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
