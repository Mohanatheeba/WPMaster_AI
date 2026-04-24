<?php
/**
 * Telegram channel — Telegram Bot API integration.
 *
 * Receives messages via webhook, resolves the Telegram user
 * to a WordPress user via pairing, processes through the agent,
 * and sends responses back via the Telegram Bot API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Channel_Telegram extends ClawWP_Channel {

    const API_BASE = 'https://api.telegram.org/bot';

    /** @var string */
    private $bot_token;

    public function __construct( $bot_token ) {
        $this->bot_token = $bot_token;
    }

    public function get_name() {
        return 'telegram';
    }

    /**
     * Initialize: set up the webhook if not already configured.
     */
    public function init() {
        // Webhook setup is triggered from the settings page when the token is saved.
    }

    /**
     * Register the Telegram webhook with the Bot API.
     *
     * @return array|WP_Error
     */
    public function register_webhook() {
        $webhook_url = rest_url( 'clawwp/v1/telegram' );

        // Generate a secret token for webhook verification.
        $secret = wp_generate_password( 32, false );
        ClawWP::update_option( 'telegram_webhook_secret', $secret );

        return $this->api_call( 'setWebhook', array(
            'url'          => $webhook_url,
            'secret_token' => $secret,
            'allowed_updates' => array( 'message' ),
        ) );
    }

    /**
     * Remove the Telegram webhook.
     *
     * @return array|WP_Error
     */
    public function remove_webhook() {
        return $this->api_call( 'deleteWebhook' );
    }

    /**
     * Handle an incoming Telegram webhook update.
     */
    public function handle_incoming( WP_REST_Request $request ) {
        $update = $request->get_json_params();

        // We only handle text messages for now.
        if ( empty( $update['message']['text'] ) ) {
            return;
        }

        $message   = $update['message'];
        $text      = $message['text'];
        $chat_id   = (string) $message['chat']['id'];
        $tg_user_id = (string) $message['from']['id'];
        $tg_name   = sanitize_text_field( trim( ( $message['from']['first_name'] ?? '' ) . ' ' . ( $message['from']['last_name'] ?? '' ) ) );

        // Reject excessively long input to prevent abuse.
        if ( mb_strlen( $text ) > 4000 ) {
            $this->send_message( $chat_id, __( 'Message is too long. Please keep it under 4000 characters.', 'clawwp' ) );
            return;
        }

        // Handle commands.
        if ( strpos( $text, '/pair' ) === 0 ) {
            $this->handle_pair_command( $chat_id, $tg_user_id );
            return;
        }

        if ( '/start' === $text ) {
            $this->send_message( $chat_id, sprintf(
                __( "Welcome to ClawWP! I'm the AI agent for your WordPress site.\n\nTo get started, send /pair to get a pairing code, then enter it in your WordPress admin under ClawWP > Settings.", 'clawwp' )
            ) );
            return;
        }

        if ( '/help' === $text ) {
            $this->send_message( $chat_id, implode( "\n", array(
                __( 'Available commands:', 'clawwp' ),
                '/pair - Link this Telegram account to your WordPress user',
                '/new - Start a new conversation',
                '/help - Show this help message',
                '',
                __( 'Just type normally to talk to me about your WordPress site!', 'clawwp' ),
            ) ) );
            return;
        }

        if ( '/new' === $text ) {
            // Start a new conversation by not passing a conversation_id.
            $text = __( 'Starting a fresh conversation. How can I help?', 'clawwp' );
        }

        // Look up WordPress user from Telegram user ID.
        $permissions = new ClawWP_Permissions();
        $wp_user_id  = $permissions->get_user_by_channel( 'telegram', $tg_user_id );

        if ( ! $wp_user_id ) {
            $this->send_message( $chat_id, sprintf(
                __( "I don't recognize this Telegram account. Please pair it first:\n\n1. Send /pair to get a code\n2. Enter the code in WordPress admin > ClawWP > Settings", 'clawwp' )
            ) );
            return;
        }

        // Process the message through the agent.
        $this->process_and_respond( $text, $wp_user_id, $chat_id );
    }

    /**
     * Handle the /pair command — generate a pairing code.
     */
    private function handle_pair_command( $chat_id, $tg_user_id ) {
        $permissions = new ClawWP_Permissions();

        // Check if already paired.
        $existing = $permissions->get_user_by_channel( 'telegram', $tg_user_id );
        if ( $existing ) {
            $user = get_userdata( $existing );
            $this->send_message( $chat_id, sprintf(
                __( 'This Telegram account is already paired with WordPress user "%s". Send /pair again to generate a new code if you want to re-pair.', 'clawwp' ),
                $user ? $user->display_name : '#' . $existing
            ) );
            return;
        }

        $code = $permissions->generate_pair_code( 'telegram', $tg_user_id, $chat_id );

        $this->send_message( $chat_id, sprintf(
            __( "Your pairing code is: %s\n\nEnter this code in your WordPress admin:\nClawWP > Settings > Pair a Channel\n\nThis code expires in 5 minutes.", 'clawwp' ),
            $code
        ) );
    }

    /**
     * Send a text message via the Telegram Bot API.
     */
    public function send_message( $chat_id, $text ) {
        // Telegram has a 4096 character limit per message.
        if ( mb_strlen( $text ) > 4000 ) {
            $chunks = $this->split_message( $text, 4000 );
            foreach ( $chunks as $chunk ) {
                $this->api_call( 'sendMessage', array(
                    'chat_id'    => $chat_id,
                    'text'       => $chunk,
                    'parse_mode' => 'Markdown',
                ) );
            }
            return true;
        }

        $result = $this->api_call( 'sendMessage', array(
            'chat_id'    => $chat_id,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ) );

        return ! is_wp_error( $result );
    }

    /**
     * Make a Telegram Bot API call.
     *
     * @param string $method API method name.
     * @param array  $params Parameters.
     * @return array|WP_Error
     */
    private function api_call( $method, $params = array() ) {
        $url = self::API_BASE . $this->bot_token . '/' . $method;

        $response = wp_remote_post( $url, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $params ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['ok'] ) ) {
            return new WP_Error(
                'telegram_api_error',
                $body['description'] ?? 'Unknown Telegram API error',
                $body
            );
        }

        return $body['result'] ?? $body;
    }

    /**
     * Split a long message into chunks at line boundaries.
     *
     * @param string $text
     * @param int    $max_length
     * @return array
     */
    private function split_message( $text, $max_length ) {
        $chunks = array();
        $lines  = explode( "\n", $text );
        $chunk  = '';

        foreach ( $lines as $line ) {
            if ( mb_strlen( $chunk . $line . "\n" ) > $max_length ) {
                if ( ! empty( $chunk ) ) {
                    $chunks[] = trim( $chunk );
                }
                $chunk = $line . "\n";
            } else {
                $chunk .= $line . "\n";
            }
        }

        if ( ! empty( trim( $chunk ) ) ) {
            $chunks[] = trim( $chunk );
        }

        return $chunks;
    }

    /**
     * Get info about the bot.
     *
     * @return array|WP_Error
     */
    public function get_bot_info() {
        return $this->api_call( 'getMe' );
    }
}
