<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kurso_GraphQL {

    /**
     * Evaluate Twig expressions in a JSON variables string and decode it.
     * Returns an empty array if $json is blank.
     */
    public static function preprocess_variables( string $json ): array|WP_Error {
        $json = trim( $json );
        if ( $json === '' ) {
            return [];
        }

        if ( class_exists( '\Twig\Environment' ) ) {
            try {
                $loader = new \Twig\Loader\ArrayLoader( [ 'v' => $json ] );
                $twig   = new \Twig\Environment( $loader, [ 'autoescape' => false ] );
                $json   = $twig->render( 'v', [] );
            } catch ( \Throwable $e ) {
                return new WP_Error( 'kurso_twig_variables', $e->getMessage() );
            }
        }

        $decoded = json_decode( $json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'kurso_variables_json', 'Invalid JSON in variables: ' . json_last_error_msg() );
        }

        return $decoded ?? [];
    }

    public static function query( string $graphql_query, string $variables_json = '' ): array|WP_Error {
        $variables = self::preprocess_variables( $variables_json );
        if ( is_wp_error( $variables ) ) {
            return $variables;
        }

        $url      = Kurso_Settings::get_graphql_url();
        $username = Kurso_Settings::get_username();
        $password = Kurso_Settings::get_password();

        if ( empty( $url ) || empty( $username ) || empty( $password ) ) {
            return new WP_Error( 'kurso_config', __( 'KURSO is not fully configured.', 'kurso-wordpress' ) );
        }

        $body = [ 'query' => $graphql_query ];
        if ( ! empty( $variables ) ) {
            $body['variables'] = $variables;
        }

        $response = wp_remote_post( $url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 ) {
            return new WP_Error( 'kurso_http', sprintf( 'HTTP %d: %s', $code, $body ) );
        }

        if ( isset( $data['errors'] ) ) {
            $msg = $data['errors'][0]['message'] ?? 'GraphQL-Fehler';
            return new WP_Error( 'kurso_graphql', $msg );
        }

        return $data['data'] ?? [];
    }

    public static function test_connection(): true|WP_Error {
        $result = self::query( '{ __typename }' );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return true;
    }
}
