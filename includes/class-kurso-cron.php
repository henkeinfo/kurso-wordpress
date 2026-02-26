<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kurso_Cron {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'kurso_fetch_query', [ $this, 'fetch_query' ] );
        add_filter( 'cron_schedules', [ $this, 'add_schedules' ] );
    }

    public function add_schedules( array $schedules ): array {
        $queries = Kurso_Settings::get_queries();
        foreach ( $queries as $q ) {
            $interval = max( 1, (int) ( $q['interval'] ?? 60 ) );
            $key      = 'kurso_every_' . $interval . 'min';
            if ( ! isset( $schedules[ $key ] ) ) {
                $schedules[ $key ] = [
                    'interval' => $interval * 60,
                    /* translators: %d: interval in minutes */
                    'display'  => sprintf( __( 'Every %d minutes (KURSO)', 'kurso-wordpress' ), $interval ),
                ];
            }
        }
        return $schedules;
    }

    public function fetch_query( string $slug ): void {
        $query = Kurso_Settings::get_query( $slug );
        if ( ! $query ) {
            return;
        }

        $result = Kurso_GraphQL::query( $query['graphql'] ?? '' );
        if ( is_wp_error( $result ) ) {
            error_log( 'KURSO Cron Fehler [' . $slug . ']: ' . $result->get_error_message() );
            return;
        }

        $interval = max( 1, (int) ( $query['interval'] ?? 60 ) );
        set_transient( 'kurso_query_' . $slug, $result, $interval * 60 );
        update_option( 'kurso_last_fetch_' . $slug, time(), false );
    }

    public static function fetch_now( string $slug ): true|WP_Error {
        $query = Kurso_Settings::get_query( $slug );
        if ( ! $query ) {
            return new WP_Error( 'kurso_not_found', sprintf( __( 'Query not found: %s', 'kurso-wordpress' ), $slug ) );
        }

        $result = Kurso_GraphQL::query( $query['graphql'] ?? '' );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $interval = max( 1, (int) ( $query['interval'] ?? 60 ) );
        set_transient( 'kurso_query_' . $slug, $result, $interval * 60 );
        update_option( 'kurso_last_fetch_' . $slug, time(), false );
        return true;
    }

    public static function schedule_all(): void {
        $queries = Kurso_Settings::get_queries();
        foreach ( $queries as $q ) {
            self::schedule( $q['slug'] ?? '', (int) ( $q['interval'] ?? 60 ) );
        }
    }

    public static function schedule( string $slug, int $interval_minutes ): void {
        if ( empty( $slug ) ) return;
        $hook     = 'kurso_fetch_query';
        $args     = [ $slug ];
        $interval = max( 1, $interval_minutes );
        $schedule = 'kurso_every_' . $interval . 'min';

        if ( ! wp_next_scheduled( $hook, $args ) ) {
            wp_schedule_event( time(), $schedule, $hook, $args );
        }
    }

    public static function unschedule( string $slug ): void {
        $timestamp = wp_next_scheduled( 'kurso_fetch_query', [ $slug ] );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'kurso_fetch_query', [ $slug ] );
        }
    }

    public static function unschedule_all(): void {
        $queries = Kurso_Settings::get_queries();
        foreach ( $queries as $q ) {
            self::unschedule( $q['slug'] ?? '' );
        }
        wp_clear_scheduled_hook( 'kurso_fetch_query' );
    }
}
