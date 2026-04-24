<?php
/**
 * Core plugin orchestrator.
 *
 * Registers hooks, loads dependencies, initializes components,
 * and coordinates all plugin subsystems.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP {

    /**
     * @var ClawWP_Webhook_Handler
     */
    private $webhook_handler;

    /**
     * @var ClawWP_Admin
     */
    private $admin;

    /**
     * Initialize the plugin.
     */
    public function init() {
        $this->load_textdomain();
        $this->register_hooks();

        if ( is_admin() ) {
            $this->init_admin();
        }

        $this->init_rest_api();
        $this->init_channels();
        $this->schedule_events();
    }

    /**
     * Load plugin text domain for translations.
     */
    private function load_textdomain() {
        load_plugin_textdomain( 'clawwp', false, dirname( CLAWWP_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Register core WordPress hooks.
     */
    private function register_hooks() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'clawwp_daily_cleanup', array( $this, 'daily_cleanup' ) );
        add_action( 'clawwp_cost_alert_check', array( $this, 'check_cost_alerts' ) );
        add_action( 'clawwp_license_revalidate', array( $this, 'revalidate_license' ) );
        add_filter( 'plugin_action_links_' . CLAWWP_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
    }

    /**
     * Initialize admin components.
     */
    private function init_admin() {
        $this->admin = new ClawWP_Admin();
        $this->admin->init();
    }

    /**
     * Initialize REST API routes.
     */
    private function init_rest_api() {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Register all REST API routes.
     */
    public function register_rest_routes() {
        $this->webhook_handler = new ClawWP_Webhook_Handler();
        $this->webhook_handler->register_routes();
    }

    /**
     * Initialize messaging channels.
     */
    private function init_channels() {
        $channels = $this->get_active_channels();
        foreach ( $channels as $channel ) {
            $channel->init();
        }
    }

    /**
     * Get active channel instances based on configuration.
     *
     * @return ClawWP_Channel[]
     */
    private function get_active_channels() {
        $channels = array();

        // Telegram — active if bot token is configured.
        $telegram_token = self::get_option( 'telegram_bot_token' );
        if ( ! empty( $telegram_token ) ) {
            $channels[] = new ClawWP_Channel_Telegram( self::decrypt( $telegram_token ) );
        }

        // Pro channels — require active license.
        if ( ClawWP_License::is_pro() ) {
            // Slack — active if bot token is configured.
            $slack_token = self::get_option( 'slack_bot_token' );
            if ( ! empty( $slack_token ) ) {
                $channels[] = new ClawWP_Channel_Slack( self::decrypt( $slack_token ) );
            }

            // Discord — active if bot token is configured.
            $discord_token = self::get_option( 'discord_bot_token' );
            if ( ! empty( $discord_token ) ) {
                $app_id     = self::get_option( 'discord_application_id' );
                $channels[] = new ClawWP_Channel_Discord( self::decrypt( $discord_token ), $app_id );
            }
        }

        /**
         * Filter the active channels.
         *
         * @param ClawWP_Channel[] $channels Active channel instances.
         */
        return apply_filters( 'clawwp_active_channels', $channels );
    }

    /**
     * Schedule recurring events.
     */
    private function schedule_events() {
        if ( ! wp_next_scheduled( 'clawwp_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'clawwp_daily_cleanup' );
        }
        if ( ! wp_next_scheduled( 'clawwp_cost_alert_check' ) ) {
            wp_schedule_event( time(), 'hourly', 'clawwp_cost_alert_check' );
        }
    }

    /**
     * Enqueue admin CSS and JS.
     */
    public function enqueue_admin_assets( $hook ) {
        // Admin page styles only on ClawWP pages.
        if ( strpos( $hook, 'clawwp' ) !== false ) {
            wp_enqueue_style(
                'clawwp-admin',
                CLAWWP_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                CLAWWP_VERSION
            );
            wp_enqueue_script(
                'clawwp-admin',
                CLAWWP_PLUGIN_URL . 'assets/js/admin.js',
                array(),
                CLAWWP_VERSION,
                true
            );
            wp_localize_script( 'clawwp-admin', 'clawwpChat', array(
                'restUrl'  => esc_url_raw( rest_url( 'clawwp/v1/' ) ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'userId'   => get_current_user_id(),
                'siteName' => get_bloginfo( 'name' ),
            ) );
        }
    }

    /**
     * Add settings link on plugin list page.
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=clawwp-settings' ) ) . '">'
            . esc_html__( 'Settings', 'clawwp' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Daily cleanup task: prune old conversations (free tier), compact audit log.
     */
    public function daily_cleanup() {
        $conversation = new ClawWP_Conversation();
        $conversation->prune_expired();

        $audit_days = (int) self::get_option( 'audit_log_retention_days', 90 );
        if ( $audit_days > 0 ) {
            global $wpdb;
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}clawwp_audit_log WHERE created_at < %s",
                gmdate( 'Y-m-d H:i:s', strtotime( "-{$audit_days} days" ) )
            ) );
        }
    }

    /**
     * Check if any user has exceeded their cost budget.
     */
    public function check_cost_alerts() {
        $tracker = new ClawWP_Cost_Tracker();
        $tracker->check_budget_alerts();
    }

    /**
     * Revalidate the Pro license against the server.
     */
    public function revalidate_license() {
        ClawWP_License::revalidate();
    }

    /**
     * Re-encrypt any legacy-format secrets with the modern HMAC-authenticated format.
     * Called on plugin upgrade.
     */
    public static function migrate_encryption() {
        $encrypted_keys = array(
            'anthropic_api_key', 'openai_api_key', 'pro_license_key',
            'telegram_bot_token', 'slack_bot_token', 'slack_signing_secret',
            'discord_bot_token', 'discord_public_key',
        );
        foreach ( $encrypted_keys as $key ) {
            $stored = self::get_option( $key );
            if ( empty( $stored ) ) {
                continue;
            }
            // Try to decrypt — if it's legacy format, re-encrypt with modern format.
            $raw = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
            // Modern format is HMAC(32) + IV(16) + ciphertext, so raw length > 48.
            // If raw length <= 48 or HMAC check fails, it's legacy format.
            if ( false === $raw || strlen( $raw ) <= 48 ) {
                $decrypted = self::decrypt( $stored );
                if ( ! empty( $decrypted ) ) {
                    self::update_option( $key, self::encrypt( $decrypted ) );
                }
            }
        }
    }

    /**
     * Get a plugin option with optional default.
     *
     * @param string $key     Option key (without prefix).
     * @param mixed  $default Default value.
     * @return mixed
     */
    public static function get_option( $key, $default = '' ) {
        return get_option( 'clawwp_' . $key, $default );
    }

    /**
     * Update a plugin option.
     *
     * @param string $key   Option key (without prefix).
     * @param mixed  $value Option value.
     * @return bool
     */
    public static function update_option( $key, $value ) {
        return update_option( 'clawwp_' . $key, $value );
    }

    /**
     * Encrypt a sensitive value before storing.
     *
     * @param string $value Plain text value.
     * @return string Encrypted value.
     */
    public static function encrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }
        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        $iv  = random_bytes( 16 );
        $encrypted = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        if ( false === $encrypted ) {
            return '';
        }
        // Append HMAC for authenticated encryption.
        $hmac_key  = hash( 'sha256', wp_salt( 'secure_auth' ), true );
        $hmac      = hash_hmac( 'sha256', $iv . $encrypted, $hmac_key, true );
        return base64_encode( $hmac . $iv . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for AES-256-CBC ciphertext encoding.
    }

    /**
     * Decrypt a sensitive value after retrieval.
     *
     * @param string $value Encrypted value.
     * @return string Decrypted plain text.
     */
    public static function decrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }
        $raw = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required to decode AES-256-CBC ciphertext.
        if ( false === $raw ) {
            return '';
        }

        $key      = hash( 'sha256', wp_salt( 'auth' ), true );
        $hmac_key = hash( 'sha256', wp_salt( 'secure_auth' ), true );

        // New format: 32-byte HMAC + 16-byte IV + ciphertext.
        if ( strlen( $raw ) > 48 ) {
            $hmac       = substr( $raw, 0, 32 );
            $iv         = substr( $raw, 32, 16 );
            $ciphertext = substr( $raw, 48 );
            $expected   = hash_hmac( 'sha256', $iv . $ciphertext, $hmac_key, true );
            if ( hash_equals( $expected, $hmac ) ) {
                $decrypted = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
                if ( false !== $decrypted ) {
                    return $decrypted;
                }
            }
        }

        // Legacy fallback: static IV, base64-encoded ciphertext (pre-security-audit format).
        // Re-encrypts with the current (HMAC-authenticated) format on successful decrypt.
        $legacy_key = wp_salt( 'auth' );
        $legacy_iv  = substr( hash( 'sha256', wp_salt( 'secure_auth' ) ), 0, 16 );
        $decrypted  = openssl_decrypt( base64_decode( $value ), 'AES-256-CBC', $legacy_key, 0, $legacy_iv ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Legacy decryption fallback.
        if ( false !== $decrypted && '' !== $decrypted ) {
            // Trigger re-encryption to modern format on next save opportunity.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[ClawWP] Legacy encryption detected — value will be re-encrypted on next save.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            return $decrypted;
        }
        return '';
    }

    /**
     * Log an audit event.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $action  Action performed.
     * @param array  $details Additional details.
     * @param string $channel Channel the action originated from.
     */
    public static function audit_log( $user_id, $action, $details = array(), $channel = 'system' ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'clawwp_audit_log',
            array(
                'user_id'    => $user_id,
                'channel'    => $channel,
                'action'     => $action,
                'details'    => wp_json_encode( $details ),
                'created_at' => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );
    }
}
