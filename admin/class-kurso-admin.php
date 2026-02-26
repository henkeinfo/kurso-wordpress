<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kurso_Admin {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_post_kurso_save_settings', [ $this, 'handle_save_settings' ] );
        add_action( 'admin_post_kurso_test_connection', [ $this, 'handle_test_connection' ] );
        add_action( 'admin_post_kurso_save_query', [ $this, 'handle_save_query' ] );
        add_action( 'admin_post_kurso_delete_query', [ $this, 'handle_delete_query' ] );
        add_action( 'admin_post_kurso_fetch_now', [ $this, 'handle_fetch_now' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function add_menu(): void {
        add_options_page(
            __( 'KURSO Settings', 'kurso-wordpress' ),
            'KURSO',
            'manage_options',
            'kurso-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( $hook !== 'settings_page_kurso-settings' ) return;
        wp_enqueue_style( 'kurso-admin', KURSO_PLUGIN_URL . 'assets/css/kurso-admin.css', [], KURSO_VERSION );
        wp_enqueue_script( 'kurso-admin', KURSO_PLUGIN_URL . 'assets/js/kurso-admin.js', [ 'jquery' ], KURSO_VERSION, true );
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $active_tab = $_GET['tab'] ?? 'settings';
        $notice     = $_GET['kurso_notice'] ?? '';
        $notice_type = $_GET['kurso_notice_type'] ?? 'success';
        ?>
        <div class="wrap kurso-admin">
            <h1>⚙️ KURSO for WordPress</h1>

            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
                    <p><?php echo esc_html( urldecode( $notice ) ); ?></p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper">
                <a href="?page=kurso-settings&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Connection', 'kurso-wordpress' ); ?>
                </a>
                <a href="?page=kurso-settings&tab=queries" class="nav-tab <?php echo $active_tab === 'queries' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Queries', 'kurso-wordpress' ); ?>
                </a>
                <?php if ( $active_tab === 'query_edit' ) : ?>
                <a href="#" class="nav-tab nav-tab-active"><?php esc_html_e( 'Edit Query', 'kurso-wordpress' ); ?></a>
                <?php endif; ?>
            </nav>

            <div class="kurso-tab-content">
            <?php
            match ( $active_tab ) {
                'queries'    => $this->render_queries_tab(),
                'query_edit' => $this->render_query_edit_tab(),
                default      => $this->render_settings_tab(),
            };
            ?>
            </div>
        </div>
        <?php
    }

    private function render_settings_tab(): void {
        $url  = Kurso_Settings::get_graphql_url();
        $user = Kurso_Settings::get_username();
        ?>
        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" class="kurso-form">
            <?php wp_nonce_field( 'kurso_save_settings' ); ?>
            <input type="hidden" name="action" value="kurso_save_settings">

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="graphql_url"><?php esc_html_e( 'GraphQL URL', 'kurso-wordpress' ); ?></label></th>
                    <td>
                        <input type="url" id="graphql_url" name="graphql_url"
                               value="<?php echo esc_attr( $url ); ?>"
                               placeholder="https://my-system.kurso.de/api/graphql"
                               class="regular-text" required>
                        <p class="description"><?php printf( esc_html__( 'Format: %s', 'kurso-wordpress' ), '<code>https://&lt;systemname&gt;.kurso.de/api/graphql</code>' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="username"><?php esc_html_e( 'Username', 'kurso-wordpress' ); ?></label></th>
                    <td>
                        <input type="text" id="username" name="username"
                               value="<?php echo esc_attr( $user ); ?>"
                               class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="password"><?php esc_html_e( 'Password', 'kurso-wordpress' ); ?></label></th>
                    <td>
                        <input type="password" id="password" name="password"
                               placeholder="<?php echo Kurso_Settings::get_password() ? esc_attr__( '(saved)', 'kurso-wordpress' ) : ''; ?>"
                               class="regular-text" autocomplete="new-password">
                        <p class="description"><?php esc_html_e( 'Leave blank to keep the existing password.', 'kurso-wordpress' ); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <?php submit_button( __( 'Save Settings', 'kurso-wordpress' ), 'primary', 'submit', false ); ?>
                &nbsp;
                <a href="<?php echo admin_url( 'admin-post.php?action=kurso_test_connection&_wpnonce=' . wp_create_nonce( 'kurso_test_connection' ) ); ?>"
                   class="button button-secondary"><?php esc_html_e( 'Test Connection', 'kurso-wordpress' ); ?></a>
            </p>
        </form>
        <?php
    }

    private function render_queries_tab(): void {
        $queries = Kurso_Settings::get_queries();
        ?>
        <div class="kurso-queries-header">
            <a href="?page=kurso-settings&tab=query_edit" class="button button-primary">+ <?php esc_html_e( 'New Query', 'kurso-wordpress' ); ?></a>
        </div>

        <?php if ( empty( $queries ) ) : ?>
            <div class="kurso-empty">
                <p><?php printf( esc_html__( 'No queries configured yet. Click %s to get started.', 'kurso-wordpress' ), '<strong>' . esc_html__( 'New Query', 'kurso-wordpress' ) . '</strong>' ); ?></p>
            </div>
        <?php else : ?>
        <table class="wp-list-table widefat fixed striped kurso-query-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'kurso-wordpress' ); ?></th>
                    <th><?php esc_html_e( 'Slug', 'kurso-wordpress' ); ?></th>
                    <th><?php esc_html_e( 'Interval', 'kurso-wordpress' ); ?></th>
                    <th><?php esc_html_e( 'Cache', 'kurso-wordpress' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'kurso-wordpress' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $queries as $q ) :
                $slug     = $q['slug'] ?? '';
                $cached   = get_transient( 'kurso_query_' . $slug );
                $has_cache = $cached !== false;
            ?>
                <tr>
                    <td><strong><?php echo esc_html( $q['name'] ?? $slug ); ?></strong></td>
                    <td><code><?php echo esc_html( $slug ); ?></code></td>
                    <td><?php echo esc_html( $q['interval'] ?? 60 ); ?> min</td>
                    <td>
                        <?php if ( $has_cache ) : ?>
                            <span class="kurso-badge kurso-badge--ok"><?php esc_html_e( 'Present', 'kurso-wordpress' ); ?></span>
                        <?php else : ?>
                            <span class="kurso-badge kurso-badge--warn"><?php esc_html_e( 'Empty', 'kurso-wordpress' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=kurso-settings&tab=query_edit&slug=<?php echo esc_attr( $slug ); ?>"
                           class="button button-small"><?php esc_html_e( 'Edit', 'kurso-wordpress' ); ?></a>

                        <a href="<?php echo admin_url( 'admin-post.php?action=kurso_fetch_now&slug=' . urlencode( $slug ) . '&_wpnonce=' . wp_create_nonce( 'kurso_fetch_now_' . $slug ) ); ?>"
                           class="button button-small"><?php esc_html_e( 'Fetch now', 'kurso-wordpress' ); ?></a>

                        <a href="<?php echo admin_url( 'admin-post.php?action=kurso_delete_query&slug=' . urlencode( $slug ) . '&_wpnonce=' . wp_create_nonce( 'kurso_delete_query_' . $slug ) ); ?>"
                           class="button button-small button-link-delete"
                           onclick="return confirm('<?php echo esc_js( sprintf( __( 'Really delete query "%s"?', 'kurso-wordpress' ), $slug ) ); ?>')"><?php esc_html_e( 'Delete', 'kurso-wordpress' ); ?></a>

                        <?php if ( $has_cache ) : ?>
                        <details class="kurso-shortcode-hint">
                            <summary><?php esc_html_e( 'Shortcode', 'kurso-wordpress' ); ?></summary>
                            <code>[kurso query="<?php echo esc_html( $slug ); ?>"]</code>
                        </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php
    }

    private function render_query_edit_tab(): void {
        $slug  = $_GET['slug'] ?? '';
        $query = $slug ? Kurso_Settings::get_query( $slug ) : null;
        $is_new = ! $query;

        $default_template = <<<'TWIG'
{% for course in allCourses %}
<div class="kurso-card">
  <h3>{{ course.name }}</h3>
  <p>{{ course.startDate|date("d.m.Y") }} – {{ course.endDate|date("d.m.Y") }}</p>
  {% if course.onlineEnrollmentUrl %}
    <a href="{{ course.onlineEnrollmentUrl }}" class="kurso-button">Book now</a>
  {% endif %}
</div>
{% endfor %}
TWIG;
        ?>
        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" class="kurso-form">
            <?php wp_nonce_field( 'kurso_save_query' ); ?>
            <input type="hidden" name="action" value="kurso_save_query">
            <input type="hidden" name="original_slug" value="<?php echo esc_attr( $slug ); ?>">

            <table class="form-table">
                <tr>
                    <th><label for="q_name"><?php esc_html_e( 'Display Name', 'kurso-wordpress' ); ?></label></th>
                    <td>
                        <input type="text" id="q_name" name="q_name"
                               value="<?php echo esc_attr( $query['name'] ?? '' ); ?>"
                               class="regular-text" required placeholder="<?php esc_attr_e( 'e.g. Current Courses', 'kurso-wordpress' ); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="q_slug"><?php esc_html_e( 'Slug (ID)', 'kurso-wordpress' ); ?></label></th>
                    <td>
                        <input type="text" id="q_slug" name="q_slug"
                               value="<?php echo esc_attr( $query['slug'] ?? '' ); ?>"
                               class="regular-text" required placeholder="<?php esc_attr_e( 'e.g. current-courses', 'kurso-wordpress' ); ?>"
                               pattern="[a-z0-9_-]+"
                               <?php echo ! $is_new ? 'readonly' : ''; ?>>
                        <p class="description"><?php printf( esc_html__( 'Lowercase letters, numbers, hyphens only. Used in the shortcode: %s', 'kurso-wordpress' ), '<code>[kurso query="..."]</code>' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="q_interval"><?php esc_html_e( 'Polling Interval', 'kurso-wordpress' ); ?></label></th>
                    <td>
                        <input type="number" id="q_interval" name="q_interval"
                               value="<?php echo esc_attr( $query['interval'] ?? 60 ); ?>"
                               min="1" max="1440" class="small-text"> <?php esc_html_e( 'minutes', 'kurso-wordpress' ); ?>
                        <p class="description"><?php esc_html_e( 'How often the KURSO API is queried (1 = every minute, 60 = hourly).', 'kurso-wordpress' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="q_graphql"><?php esc_html_e( 'GraphQL Query', 'kurso-wordpress' ); ?></label></th>
                    <td>
                        <textarea id="q_graphql" name="q_graphql" rows="12"
                                  class="large-text code" required
                                  placeholder="query { allCourses { name startDate } }"><?php echo esc_textarea( $query['graphql'] ?? '' ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Full GraphQL query text. The structure determines which variables are available in the template.', 'kurso-wordpress' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="q_template"><?php esc_html_e( 'Twig Template', 'kurso-wordpress' ); ?></label></th>
                    <td>
                        <textarea id="q_template" name="q_template" rows="16"
                                  class="large-text code"><?php echo esc_textarea( $query['template'] ?? $default_template ); ?></textarea>
                        <p class="description">
                            <?php printf(
                                esc_html__( 'Twig syntax: %1$s, %2$s, %3$s, %4$s', 'kurso-wordpress' ),
                                '<code>{{ variable }}</code>',
                                '<code>{% for item in list %}</code>',
                                '<code>{% if condition %}</code>',
                                '<code>{{ value|date("d.m.Y") }}</code>'
                            ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <?php submit_button( $is_new ? __( 'Create Query', 'kurso-wordpress' ) : __( 'Save Query', 'kurso-wordpress' ), 'primary', 'submit', false ); ?>
                &nbsp;
                <a href="?page=kurso-settings&tab=queries" class="button"><?php esc_html_e( 'Cancel', 'kurso-wordpress' ); ?></a>
            </p>
        </form>
        <?php
    }

    public function handle_save_settings(): void {
        check_admin_referer( 'kurso_save_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Insufficient permissions.', 'kurso-wordpress' ) );

        $url      = esc_url_raw( $_POST['graphql_url'] ?? '' );
        $username = sanitize_text_field( $_POST['username'] ?? '' );
        $password = $_POST['password'] ?? '';

        Kurso_Settings::save( [ 'graphql_url' => $url, 'username' => $username ] );
        if ( ! empty( $password ) ) {
            Kurso_Settings::set_password( $password );
        }

        wp_redirect( admin_url( 'options-general.php?page=kurso-settings&tab=settings&kurso_notice=' . urlencode( __( 'Settings saved.', 'kurso-wordpress' ) ) ) );
        exit;
    }

    public function handle_test_connection(): void {
        check_admin_referer( 'kurso_test_connection' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Insufficient permissions.', 'kurso-wordpress' ) );

        $result = Kurso_GraphQL::test_connection();
        if ( is_wp_error( $result ) ) {
            $msg  = sprintf( __( 'Connection failed: %s', 'kurso-wordpress' ), $result->get_error_message() );
            $type = 'error';
        } else {
            $msg  = __( 'Connection successful! KURSO API is reachable.', 'kurso-wordpress' );
            $type = 'success';
        }

        wp_redirect( admin_url( 'options-general.php?page=kurso-settings&tab=settings&kurso_notice=' . urlencode( $msg ) . '&kurso_notice_type=' . $type ) );
        exit;
    }

    public function handle_save_query(): void {
        check_admin_referer( 'kurso_save_query' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Insufficient permissions.', 'kurso-wordpress' ) );

        $slug          = sanitize_key( $_POST['q_slug'] ?? '' );
        $original_slug = sanitize_key( $_POST['original_slug'] ?? '' );

        if ( empty( $slug ) ) {
            wp_redirect( admin_url( 'options-general.php?page=kurso-settings&tab=queries&kurso_notice=' . urlencode( __( 'Slug must not be empty.', 'kurso-wordpress' ) ) . '&kurso_notice_type=error' ) );
            exit;
        }

        $query = [
            'slug'     => $slug,
            'name'     => sanitize_text_field( $_POST['q_name'] ?? $slug ),
            'interval' => max( 1, (int) ( $_POST['q_interval'] ?? 60 ) ),
            'graphql'  => wp_unslash( $_POST['q_graphql'] ?? '' ),
            'template' => wp_unslash( $_POST['q_template'] ?? '' ),
        ];

        // Delete old query if slug changed
        if ( $original_slug && $original_slug !== $slug ) {
            Kurso_Settings::delete_query( $original_slug );
        }

        Kurso_Settings::save_query( $query );
        Kurso_Cron::unschedule( $slug );
        Kurso_Cron::schedule( $slug, $query['interval'] );

        wp_redirect( admin_url( 'options-general.php?page=kurso-settings&tab=queries&kurso_notice=' . urlencode( sprintf( __( 'Query "%s" saved.', 'kurso-wordpress' ), $slug ) ) ) );
        exit;
    }

    public function handle_delete_query(): void {
        $slug = sanitize_key( $_GET['slug'] ?? '' );
        check_admin_referer( 'kurso_delete_query_' . $slug );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Insufficient permissions.', 'kurso-wordpress' ) );

        Kurso_Cron::unschedule( $slug );
        Kurso_Settings::delete_query( $slug );

        wp_redirect( admin_url( 'options-general.php?page=kurso-settings&tab=queries&kurso_notice=' . urlencode( __( 'Query deleted.', 'kurso-wordpress' ) ) ) );
        exit;
    }

    public function handle_fetch_now(): void {
        $slug = sanitize_key( $_GET['slug'] ?? '' );
        check_admin_referer( 'kurso_fetch_now_' . $slug );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Insufficient permissions.', 'kurso-wordpress' ) );

        $result = Kurso_Cron::fetch_now( $slug );
        if ( is_wp_error( $result ) ) {
            $msg  = sprintf( __( 'Error: %s', 'kurso-wordpress' ), $result->get_error_message() );
            $type = 'error';
        } else {
            $msg  = sprintf( __( 'Data for "%s" fetched successfully.', 'kurso-wordpress' ), $slug );
            $type = 'success';
        }

        wp_redirect( admin_url( 'options-general.php?page=kurso-settings&tab=queries&kurso_notice=' . urlencode( $msg ) . '&kurso_notice_type=' . $type ) );
        exit;
    }
}
