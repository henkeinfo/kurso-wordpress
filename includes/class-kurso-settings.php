<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kurso_Settings {

    private static ?self $instance = null;
    const OPTION_KEY = 'kurso_settings';
    const QUERIES_KEY = 'kurso_queries';

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public static function get( string $key, mixed $default = '' ): mixed {
        $settings = get_option( self::OPTION_KEY, [] );
        return $settings[ $key ] ?? $default;
    }

    public static function save( array $data ): void {
        $settings = get_option( self::OPTION_KEY, [] );
        $settings = array_merge( $settings, $data );
        update_option( self::OPTION_KEY, $settings );
    }

    public static function get_graphql_url(): string {
        return self::get( 'graphql_url', '' );
    }

    public static function get_username(): string {
        return self::get( 'username', '' );
    }

    public static function get_password(): string {
        $enc = self::get( 'password_enc', '' );
        return $enc ? base64_decode( $enc ) : '';
    }

    public static function set_password( string $password ): void {
        self::save( [ 'password_enc' => base64_encode( $password ) ] );
    }

    // --- Query-Verwaltung ---

    public static function get_queries(): array {
        return get_option( self::QUERIES_KEY, [] );
    }

    public static function get_query( string $slug ): ?array {
        $queries = self::get_queries();
        foreach ( $queries as $q ) {
            if ( ( $q['slug'] ?? '' ) === $slug ) {
                return $q;
            }
        }
        return null;
    }

    public static function save_query( array $query ): void {
        $queries = self::get_queries();
        $slug    = $query['slug'];
        $found   = false;
        foreach ( $queries as &$q ) {
            if ( ( $q['slug'] ?? '' ) === $slug ) {
                $q     = $query;
                $found = true;
                break;
            }
        }
        if ( ! $found ) {
            $queries[] = $query;
        }
        update_option( self::QUERIES_KEY, $queries );
    }

    public static function delete_query( string $slug ): void {
        $queries = array_filter(
            self::get_queries(),
            fn( $q ) => ( $q['slug'] ?? '' ) !== $slug
        );
        update_option( self::QUERIES_KEY, array_values( $queries ) );
        delete_transient( 'kurso_query_' . $slug );
    }
}
