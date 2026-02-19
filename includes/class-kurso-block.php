<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kurso_Block {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'register_block' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    public function register_block(): void {
        if ( ! function_exists( 'register_block_type' ) ) return;

        wp_register_script(
            'kurso-block-editor',
            KURSO_PLUGIN_URL . 'assets/js/block.js',
            [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
            KURSO_VERSION,
            true
        );

        // Queries an Block-Editor übergeben
        $queries = array_map( fn( $q ) => [
            'slug'  => $q['slug'] ?? '',
            'label' => $q['name'] ?? $q['slug'] ?? '',
        ], Kurso_Settings::get_queries() );

        wp_localize_script( 'kurso-block-editor', 'kursoBlockData', [
            'queries'  => $queries,
            'restUrl'  => rest_url( 'kurso/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
        ] );

        wp_register_style(
            'kurso-frontend',
            KURSO_PLUGIN_URL . 'assets/css/kurso-frontend.css',
            [],
            KURSO_VERSION
        );

        register_block_type( 'kurso/anzeige', [
            'editor_script'   => 'kurso-block-editor',
            'style'           => 'kurso-frontend',
            'attributes'      => [
                'query'    => [ 'type' => 'string', 'default' => '' ],
                'template' => [ 'type' => 'string', 'default' => '' ],
                'cssClass' => [ 'type' => 'string', 'default' => '' ],
            ],
            'render_callback' => [ $this, 'render_block' ],
        ] );
    }

    public function render_block( array $attributes ): string {
        $slug     = $attributes['query']    ?? '';
        $template = $attributes['template'] ?? '';
        $class    = $attributes['cssClass'] ?? '';

        if ( empty( $slug ) ) return '';

        wp_enqueue_style( 'kurso-frontend' );
        return Kurso_Renderer::render( $slug, $template, $class );
    }

    public function register_rest_routes(): void {
        // Vorschau-Endpunkt für Block-Editor
        register_rest_route( 'kurso/v1', '/preview', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_preview' ],
            'permission_callback' => fn() => current_user_can( 'edit_posts' ),
        ] );

        // Rohdaten-Endpunkt ("Rohdaten anzeigen")
        register_rest_route( 'kurso/v1', '/rawdata/(?P<slug>[a-z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_rawdata' ],
            'permission_callback' => fn() => current_user_can( 'edit_posts' ),
        ] );
    }

    public function rest_preview( WP_REST_Request $request ): WP_REST_Response {
        $slug     = sanitize_key( $request->get_param( 'query' ) ?? '' );
        $template = $request->get_param( 'template' ) ?? '';

        if ( empty( $slug ) ) {
            return new WP_REST_Response( [ 'html' => '<em>Kein Query ausgewählt.</em>' ] );
        }

        $html = Kurso_Renderer::render( $slug, $template );
        return new WP_REST_Response( [ 'html' => $html ] );
    }

    public function rest_rawdata( WP_REST_Request $request ): WP_REST_Response {
        $slug = sanitize_key( $request->get_param( 'slug' ) ?? '' );
        $data = Kurso_Renderer::get_raw_data( $slug );
        return new WP_REST_Response( [ 'data' => $data ] );
    }
}
