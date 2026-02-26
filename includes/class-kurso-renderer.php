<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Error\Error as TwigError;

class Kurso_Renderer {

    public static function render( string $slug, string $template = '', string $css_class = '' ): string {
        // Daten aus Cache holen
        $data = get_transient( 'kurso_query_' . $slug );

        if ( $data === false ) {
            // Kein Cache vorhanden → einmalig direkt abrufen
            $query_config = Kurso_Settings::get_query( $slug );
            if ( ! $query_config ) {
                return self::error( sprintf( __( 'KURSO: Query "%s" not found.', 'kurso-wordpress' ), esc_html( $slug ) ) );
            }
            $result = Kurso_GraphQL::query( $query_config['graphql'] ?? '' );
            if ( is_wp_error( $result ) ) {
                return self::error( sprintf( __( 'KURSO: Connection error – %s', 'kurso-wordpress' ), esc_html( $result->get_error_message() ) ) );
            }
            $interval = max( 1, (int) ( $query_config['interval'] ?? 60 ) );
            set_transient( 'kurso_query_' . $slug, $result, $interval * 60 );
            $data = $result;
        }

        // Template bestimmen
        if ( empty( $template ) ) {
            $query_config = Kurso_Settings::get_query( $slug );
            $template     = $query_config['template'] ?? '';
        }

        if ( empty( $template ) ) {
            return self::error( __( 'KURSO: No template configured.', 'kurso-wordpress' ) );
        }

        // Twig rendern
        $html = self::render_twig( $template, $data );
        if ( is_wp_error( $html ) ) {
            return self::error( sprintf( __( 'KURSO template error: %s', 'kurso-wordpress' ), esc_html( $html->get_error_message() ) ) );
        }

        $class = ! empty( $css_class ) ? ' class="' . esc_attr( $css_class ) . '"' : '';
        return '<div' . $class . '>' . $html . '</div>';
    }

    public static function render_twig( string $template, array $data ): string|WP_Error {
        if ( ! class_exists( Environment::class ) ) {
            return new WP_Error( 'kurso_twig', __( 'Twig is not available. Please install Composer dependencies.', 'kurso-wordpress' ) );
        }

        try {
            $loader = new ArrayLoader( [ 'template' => $template ] );
            $twig   = new Environment( $loader, [ 'autoescape' => 'html' ] );
            return $twig->render( 'template', $data );
        } catch ( TwigError $e ) {
            return new WP_Error( 'kurso_twig', $e->getMessage() );
        }
    }

    public static function get_raw_data( string $slug ): array|null {
        $data = get_transient( 'kurso_query_' . $slug );
        return $data !== false ? $data : null;
    }

    private static function error( string $message ): string {
        if ( current_user_can( 'manage_options' ) ) {
            return '<div style="background:#fff3cd;border:1px solid #ffc107;padding:10px;margin:10px 0;font-family:monospace;">'
                . esc_html( $message ) . '</div>';
        }
        return '';
    }
}
