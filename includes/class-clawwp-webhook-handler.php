<?php
/**
 * Webhook handler — REST API route registration.
 *
 * Registers all ClawWP REST endpoints and routes
 * incoming requests to the appropriate handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Webhook_Handler {

    const NAMESPACE = 'clawwp/v1';

    /**
     * Register all REST API routes.
     */
    public function register_routes() {
        // Admin sidebar chat.
        register_rest_route( self::NAMESPACE, '/chat', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_chat' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
            'args'                => array(
                'message'         => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
                'conversation_id' => array( 'required' => false, 'type' => 'integer' ),
                'channel'         => array( 'required' => false, 'type' => 'string', 'default' => 'webchat' ),
            ),
        ) );

        // Telegram webhook.
        register_rest_route( self::NAMESPACE, '/telegram', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_telegram' ),
            'permission_callback' => '__return_true', // Telegram verifies via secret token.
        ) );

        // Slack webhook (Pro).
        register_rest_route( self::NAMESPACE, '/slack', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_slack' ),
            'permission_callback' => '__return_true',
        ) );

        // Discord interactions (Pro).
        register_rest_route( self::NAMESPACE, '/discord', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_discord' ),
            'permission_callback' => '__return_true',
        ) );

        // Pairing endpoint.
        register_rest_route( self::NAMESPACE, '/pair', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_pair' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
            'args'                => array(
                'code' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );

        // Usage data.
        register_rest_route( self::NAMESPACE, '/usage', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_usage' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
            'args'                => array(
                'period' => array( 'required' => false, 'type' => 'string', 'default' => 'month' ),
            ),
        ) );

        // Tool execution (The Gateway Arms).
        register_rest_route( self::NAMESPACE, '/tools', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_tools_execution' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
            'args'                => array(
                'tool'   => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
                'params' => array( 'required' => true, 'type' => 'object' ),
            ),
        ) );

        // Conversations list.
        register_rest_route( self::NAMESPACE, '/conversations', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_conversations' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
        ) );

        // License activation.
        register_rest_route( self::NAMESPACE, '/license/activate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_license_activate' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
            'args'                => array(
                'license_key' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );

        // License deactivation.
        register_rest_route( self::NAMESPACE, '/license/deactivate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_license_deactivate' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
        ) );

        // Health check.
        register_rest_route( self::NAMESPACE, '/health', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_health' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Handle Telegram webhook.
     */
    public function handle_telegram( WP_REST_Request $request ) {
        // Verify the secret token — reject ALL requests if secret is not configured.
        $secret = ClawWP::get_option( 'telegram_webhook_secret' );
        $header = $request->get_header( 'X-Telegram-Bot-Api-Secret-Token' );

        if ( empty( $secret ) || empty( $header ) || ! hash_equals( $secret, $header ) ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }

        $body = $request->get_json_params();
        if ( empty( $body ) ) {
            return new WP_REST_Response( array( 'ok' => true ), 200 );
        }

        // Route to the Telegram channel handler.
        $telegram_token = ClawWP::get_option( 'telegram_bot_token' );
        if ( empty( $telegram_token ) ) {
            return new WP_REST_Response( array( 'error' => 'Telegram not configured' ), 500 );
        }

        $channel = new ClawWP_Channel_Telegram( ClawWP::decrypt( $telegram_token ) );
        $channel->handle_incoming( $request );

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    /**
     * Handle Slack webhook.
     */
    public function handle_slack( WP_REST_Request $request ) {
        // Verify Slack request signature.
        $signing_secret = ClawWP::get_option( 'slack_signing_secret' );
        if ( empty( $signing_secret ) ) {
            return new WP_REST_Response( array( 'error' => 'Slack not configured' ), 500 );
        }

        $timestamp = $request->get_header( 'X-Slack-Request-Timestamp' );
        $signature = $request->get_header( 'X-Slack-Signature' );

        // Reject requests older than 5 minutes to prevent replay attacks.
        if ( empty( $timestamp ) || abs( time() - (int) $timestamp ) > 300 ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }

        $raw_body  = $request->get_body();
        $sig_base  = 'v0:' . $timestamp . ':' . $raw_body;
        $computed  = 'v0=' . hash_hmac( 'sha256', $sig_base, ClawWP::decrypt( $signing_secret ) );

        if ( empty( $signature ) || ! hash_equals( $computed, $signature ) ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }

        // URL verification challenge.
        $body = $request->get_json_params();
        if ( isset( $body['type'] ) && 'url_verification' === $body['type'] ) {
            return new WP_REST_Response( array( 'challenge' => sanitize_text_field( $body['challenge'] ?? '' ) ), 200 );
        }

        /**
         * Route to Slack channel handler (Pro).
         *
         * @see ClawWP_Channel_Slack
         */
        do_action( 'clawwp_slack_webhook', $request );

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    /**
     * Handle Discord interactions.
     */
    public function handle_discord( WP_REST_Request $request ) {
        // Verify Discord Ed25519 signature.
        $public_key = ClawWP::get_option( 'discord_public_key' );
        if ( empty( $public_key ) ) {
            return new WP_REST_Response( array( 'error' => 'Discord not configured' ), 500 );
        }

        $signature = $request->get_header( 'X-Signature-Ed25519' );
        $timestamp = $request->get_header( 'X-Signature-Timestamp' );
        $raw_body  = $request->get_body();

        if ( empty( $signature ) || empty( $timestamp ) || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }

        try {
            $verified = sodium_crypto_sign_verify_detached(
                hex2bin( $signature ),
                $timestamp . $raw_body,
                hex2bin( $public_key )
            );
        } catch ( \Exception $e ) {
            $verified = false;
        }

        if ( ! $verified ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }

        $body = $request->get_json_params();

        // Discord ping verification.
        if ( isset( $body['type'] ) && 1 === (int) $body['type'] ) {
            return new WP_REST_Response( array( 'type' => 1 ), 200 );
        }

        /**
         * Route to Discord channel handler (Pro).
         *
         * @see ClawWP_Channel_Discord
         */
        do_action( 'clawwp_discord_webhook', $request );

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    /**
     * Handle pairing code submission.
     */
    public function handle_pair( WP_REST_Request $request ) {
        $code    = $request->get_param( 'code' );
        $user_id = get_current_user_id();

        $permissions = new ClawWP_Permissions();
        $result      = $permissions->complete_pairing( $user_id, $code );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'channel' => $result['channel'],
            'message' => sprintf( __( 'Successfully paired with %s!', 'clawwp' ), ucfirst( $result['channel'] ) ),
        ), 200 );
    }

    /**
     * Handle usage data request.
     */
    public function handle_usage( WP_REST_Request $request ) {
        $period  = $request->get_param( 'period' );
        $user_id = get_current_user_id();
        $tracker = new ClawWP_Cost_Tracker();

        return new WP_REST_Response( array(
            'summary'   => $tracker->get_usage_summary( $user_id, $period ),
            'daily'     => $tracker->get_daily_breakdown( $user_id ),
            'by_model'  => $tracker->get_model_breakdown( $user_id, $period ),
        ), 200 );
    }

    /**
     * Handle conversations list request.
     */
    public function handle_conversations( WP_REST_Request $request ) {
        $user_id      = get_current_user_id();
        $conversation = new ClawWP_Conversation();
        $list         = $conversation->list_conversations( $user_id );

        return new WP_REST_Response( array( 'conversations' => $list ), 200 );
    }

    /**
     * Handle license activation.
     */
    public function handle_license_activate( WP_REST_Request $request ) {
        $license_key = $request->get_param( 'license_key' );
        $result      = ClawWP_License::activate( $license_key );

        $status_code = $result['success'] ? 200 : 400;
        return new WP_REST_Response( $result, $status_code );
    }

    /**
     * Handle license deactivation.
     */
    public function handle_license_deactivate( WP_REST_Request $request ) {
        $result = ClawWP_License::deactivate();
        return new WP_REST_Response( $result, 200 );
    }

    /**
     * Health check endpoint.
     */
    public function handle_health( WP_REST_Request $request ) {
        return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
    }

    /**
     * Handle tool execution requests from the external Brain.
     */
    public function handle_tools_execution( WP_REST_Request $request ) {
        $tool_name = $request->get_param( 'tool' );
        $params    = $request->get_param( 'params' );

        $tools_registry = new ClawWP_Tools();
        $all_tools      = $tools_registry->get_all();

        if ( ! isset( $all_tools[ $tool_name ] ) ) {
            return new WP_REST_Response( array( 'error' => "Tool '$tool_name' not found." ), 404 );
        }

        try {
            $tool_instance = $all_tools[ $tool_name ];
            $result        = $tool_instance->execute( (array) $params );
            
            // Audit log the external action.
            ClawWP::audit_log( get_current_user_id(), 'external_tool_exec', array(
                'tool'   => $tool_name,
                'params' => $params,
                'result' => $result,
            ), 'external_brain' );

            return new WP_REST_Response( $result, 200 );
        } catch ( Exception $e ) {
            return new WP_REST_Response( array( 'error' => $e->getMessage() ), 500 );
        }
    }

    /**
     * Permission callback: require authenticated admin user.
     */
    public function check_admin_auth( WP_REST_Request $request ) {
        return current_user_can( 'manage_options' );
    }
}
