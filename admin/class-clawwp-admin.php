<?php
/**
 * Admin pages controller.
 *
 * Registers admin menus, pages, and coordinates
 * all admin-facing components.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Admin {

    /**
     * Initialize admin components.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_notices', array( $this, 'show_setup_notice' ) );
        add_action( 'update_option_clawwp_telegram_bot_token', array( $this, 'on_telegram_token_saved' ), 10, 2 );
        add_action( 'add_option_clawwp_telegram_bot_token', array( $this, 'on_telegram_token_added' ), 10, 2 );
    }

    /**
     * Register admin menu pages.
     */
    public function register_menus() {
        // Top-level menu.
        add_menu_page(
            __( 'WPMaster AI', 'clawwp' ),
            __( 'WPMaster AI', 'clawwp' ),
            'manage_options',
            'clawwp',
            array( $this, 'render_settings' ),
            'dashicons-format-chat',
            30
        );

        // Settings (Main Page).
        add_submenu_page(
            'clawwp',
            __( 'Settings', 'clawwp' ),
            __( 'Settings', 'clawwp' ),
            'manage_options',
            'clawwp',
            array( $this, 'render_settings' )
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        // AI Provider settings (Gemini Flash etc will be handled by Brain, but we keep keys for now or simplify).
        register_setting( 'clawwp_settings', 'clawwp_ai_provider', array(
            'type'              => 'string',
            'default'           => 'claude',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'clawwp_settings', 'clawwp_anthropic_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_api_key' ),
        ) );
        register_setting( 'clawwp_settings', 'clawwp_claude_model', array(
            'type'              => 'string',
            'default'           => 'claude-sonnet-4-5-20250929',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        // OpenAI settings (Pro).
        register_setting( 'clawwp_settings', 'clawwp_openai_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_api_key' ),
        ) );
        register_setting( 'clawwp_settings', 'clawwp_openai_model', array(
            'type'              => 'string',
            'default'           => 'gpt-4o',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        // Telegram settings.
        register_setting( 'clawwp_settings', 'clawwp_telegram_bot_token', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_api_key' ),
        ) );

        // Budget settings.
        register_setting( 'clawwp_settings', 'clawwp_monthly_budget', array(
            'type'              => 'number',
            'default'           => 0,
            'sanitize_callback' => 'absint',
        ) );
    }

    /**
     * Encrypt API keys before storing.
     */
    public function sanitize_api_key( $value ) {
        if ( empty( $value ) ) {
            return '';
        }
        // If the user didn't change the masked field, keep the existing value.
        if ( str_contains( $value, '••' ) || str_contains( $value, '********' ) ) {
            // Return the existing stored value unchanged.
            $option_name = current_filter();
            $option_name = str_replace( 'sanitize_option_', '', $option_name );
            return get_option( $option_name, '' );
        }
        return ClawWP::encrypt( $value );
    }

    /**
     * Auto-register Telegram webhook when bot token is saved.
     */
    public function on_telegram_token_saved( $old_value, $new_value ) {
        $this->register_telegram_webhook( $new_value );
    }

    public function on_telegram_token_added( $option, $value ) {
        $this->register_telegram_webhook( $value );
    }

    private function register_telegram_webhook( $encrypted_token ) {
        if ( empty( $encrypted_token ) ) {
            return;
        }
        $token   = ClawWP::decrypt( $encrypted_token );
        $channel = new ClawWP_Channel_Telegram( $token );
        $result  = $channel->register_webhook();

        if ( is_wp_error( $result ) ) {
            add_settings_error( 'clawwp_settings', 'telegram_webhook', 'Telegram webhook failed: ' . esc_html( $result->get_error_message() ), 'error' );
        } else {
            add_settings_error( 'clawwp_settings', 'telegram_webhook', 'Telegram webhook registered successfully!', 'success' );
        }
    }

    /**
     * Show setup notice if API key is not configured.
     */
    public function show_setup_notice() {
        // No notice needed when using HFB Proxy (no API key required).
        $provider = ClawWP::get_option( 'ai_provider', 'claude' );
        if ( 'proxy' === $provider && ClawWP_License::is_pro() ) {
            return;
        }

        $api_key = ClawWP::get_option( 'anthropic_api_key' );
        if ( empty( $api_key ) ) {
            $settings_url = admin_url( 'admin.php?page=clawwp-settings' );
            printf(
                '<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__( 'ClawWP needs an API key to work.', 'clawwp' ),
                esc_url( $settings_url ),
                esc_html__( 'Configure now', 'clawwp' )
            );
        }
    }

    /**
     * Render the dashboard page.
     */
    public function render_dashboard() {
        $tracker = new ClawWP_Cost_Tracker();
        $user_id = get_current_user_id();
        $usage   = $tracker->get_usage_summary( $user_id, 'month' );

        $conversation_mgr = new ClawWP_Conversation();
        $recent            = $conversation_mgr->list_conversations( $user_id, '', 5 );

        $permissions    = new ClawWP_Permissions();
        $pairings       = $permissions->get_user_pairings( $user_id );
        $has_api_key    = ! empty( ClawWP::get_option( 'anthropic_api_key' ) );
        $has_telegram   = ! empty( ClawWP::get_option( 'telegram_bot_token' ) );

        include CLAWWP_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render the settings page.
     */
    public function render_settings() {
        include CLAWWP_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render the conversations page.
     */
    public function render_conversations() {
        include CLAWWP_PLUGIN_DIR . 'admin/views/logs.php';
    }

    /**
     * Render the costs page.
     */
    public function render_costs() {
        include CLAWWP_PLUGIN_DIR . 'admin/views/costs.php';
    }

    /**
     * Render the audit log page.
     */
    public function render_audit_log() {
        include CLAWWP_PLUGIN_DIR . 'admin/views/audit-log.php';
    }

    /**
     * Render the sidebar chat widget in admin footer.
     */
    public function render_sidebar_chat() {
        if ( ! ClawWP::get_option( 'sidebar_enabled', true ) ) {
            return;
        }

        // Proxy mode doesn't need an API key — license key is sufficient.
        $provider = ClawWP::get_option( 'ai_provider', 'claude' );
        if ( 'proxy' !== $provider ) {
            $api_key = ClawWP::get_option( 'anthropic_api_key' );
            if ( empty( $api_key ) ) {
                return;
            }
        }

        include CLAWWP_PLUGIN_DIR . 'admin/views/sidebar-chat.php';
    }
}
