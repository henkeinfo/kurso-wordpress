<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kurso_GraphQL {

    public static function preprocess( string $graphql_query ): string|WP_Error {
        try {
            $loader = new \Twig\Loader\ArrayLoader( [ 'q' => $graphql_query ] );
            $twig   = new \Twig\Environment( $loader, [ 'autoescape' => false ] );
            return $twig->render( 'q', [] );
        } catch ( \Exception $e ) {
            return new WP_Error( 'kurso_twig_query', $e->getMessage() );
        }
    }

    public static function query( string $graphql_query ): array|WP_Error {
        $graphql_query = self::preprocess( $graphql_query );
        if ( is_wp_error( $graphql_query ) ) {
            return $graphql_query;
        }

        $url      = Kurso_Settings::get_graphql_url();
        $username = Kurso_Settings::get_username();
        $password = Kurso_Settings::get_password();

        if ( empty( $url ) || empty( $username ) || empty( $password ) ) {
            return new WP_Error( 'kurso_config', __( 'KURSO is not fully configured.', 'kurso-wordpress' ) );
        }

        $response = wp_remote_post( $url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
            ],
            'body'    => wp_json_encode( [ 'query' => $graphql_query ] ),
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
