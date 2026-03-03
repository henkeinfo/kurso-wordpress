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

    private function __construct() {
        add_action( 'admin_init', [ $this, 'register' ] );
    }

    public function register(): void {
        register_setting( 'kurso_settings_group', self::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [ self::class, 'sanitize_settings' ],
        ] );
        register_setting( 'kurso_settings_group', self::QUERIES_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [ self::class, 'sanitize_queries' ],
        ] );
    }

    public static function sanitize_settings( $input ): array {
        if ( ! is_array( $input ) ) return [];
        $clean = [];
        if ( isset( $input['graphql_url'] ) ) {
            $clean['graphql_url'] = esc_url_raw( $input['graphql_url'] );
        }
        if ( isset( $input['username'] ) ) {
            $clean['username'] = sanitize_text_field( $input['username'] );
        }
        if ( isset( $input['password_encrypted'] ) ) {
            $clean['password_encrypted'] = $input['password_encrypted'];
        }
        return array_merge( get_option( self::OPTION_KEY, [] ), $clean );
    }

    public static function sanitize_queries( $input ): array {
        if ( ! is_array( $input ) ) return [];
        return $input;
    }

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

    private static function get_encryption_key(): string {
        if ( defined( 'AUTH_KEY' ) && AUTH_KEY !== '' ) {
            return hash( 'sha256', AUTH_KEY . 'kurso-password', true );
        }

        // Generate and persist a random key when AUTH_KEY is unavailable.
        $stored = get_option( 'kurso_encryption_key', '' );
        if ( empty( $stored ) ) {
            $stored = wp_generate_password( 64, true, true );
            update_option( 'kurso_encryption_key', $stored, false );
        }
        return hash( 'sha256', $stored . 'kurso-password', true );
    }

    private static function encrypt_password( string $password ): string {
        $key = self::get_encryption_key();
        $iv  = openssl_random_pseudo_bytes( 16 );
        $enc = openssl_encrypt( $password, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
        return 'v1:' . base64_encode( $iv . $enc );
    }

    private static function decrypt_password( string $stored ): string {
        if ( ! str_starts_with( $stored, 'v1:' ) ) {
            return '';
        }
        $key = self::get_encryption_key();
        $raw = base64_decode( substr( $stored, 3 ) );
        if ( $raw === false || strlen( $raw ) < 17 ) {
            return '';
        }
        $iv  = substr( $raw, 0, 16 );
        $enc = substr( $raw, 16 );
        $dec = openssl_decrypt( $enc, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
        return $dec !== false ? $dec : '';
    }

    public static function get_password(): string {
        // Try new encrypted format first.
        $encrypted = self::get( 'password_encrypted', '' );
        if ( $encrypted ) {
            return self::decrypt_password( $encrypted );
        }

        // Migrate legacy base64-encoded password.
        $legacy = self::get( 'password_enc', '' );
        if ( $legacy ) {
            $password = base64_decode( $legacy );
            if ( $password !== false && $password !== '' ) {
                self::set_password( $password );
                $settings = get_option( self::OPTION_KEY, [] );
                unset( $settings['password_enc'] );
                update_option( self::OPTION_KEY, $settings );
                return $password;
            }
        }

        return '';
    }

    public static function set_password( string $password ): void {
        self::save( [ 'password_encrypted' => self::encrypt_password( $password ) ] );
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
