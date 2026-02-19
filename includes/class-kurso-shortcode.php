<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kurso_Shortcode {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'kurso', [ $this, 'render' ] );
    }

    public function render( array $atts ): string {
        $atts = shortcode_atts( [
            'query'    => '',
            'template' => '',
            'class'    => '',
        ], $atts, 'kurso' );

        if ( empty( $atts['query'] ) ) {
            return current_user_can( 'manage_options' )
                ? '<div style="color:red;">' . __( 'KURSO Shortcode: Kein Query angegeben. Beispiel: [kurso query="mein-query"]', 'kurso-for-wordpress' ) . '</div>'
                : '';
        }

        wp_enqueue_style( 'kurso-frontend', KURSO_PLUGIN_URL . 'assets/css/kurso-frontend.css', [], KURSO_VERSION );

        return Kurso_Renderer::render( $atts['query'], $atts['template'], $atts['class'] );
    }
}
