<?php
/**
 * Slack channel (Pro) — Slack Events API integration.
 *
 * Receives messages via Slack Events API webhook, resolves
 * the Slack user to a WordPress user via pairing, processes
 * through the agent, and sends responses back via Slack API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Channel_Slack extends ClawWP_Channel {

    const API_BASE = 'https://slack.com/api/';

    /** @var string */
    private $bot_token;

    public function __construct( $bot_token ) {
        $this->bot_token = $bot_token;
    }

    public function get_name() {
        return 'slack';
    }

    /**
     * Initialize: hook into the Slack webhook action.
     */
    public function init() {
        add_action( 'clawwp_slack_webhook', array( $this, 'handle_incoming' ) );
    }

    /**
     * Handle an incoming Slack Events API webhook.
     */
    public function handle_incoming( WP_REST_Request $request ) {
        $body = $request->get_json_params();

        // Handle event_callback type.
        $event_type = $body['type'] ?? '';
        if ( 'event_callback' !== $event_type ) {
            return;
        }

        $event = $body['event'] ?? array();

        // Only handle direct messages (im) and mentions.
        $type = $event['type'] ?? '';
        if ( 'message' !== $type && 'app_mention' !== $type ) {
            return;
        }

        // Ignore bot messages to prevent loops.
        if ( ! empty( $event['bot_id'] ) || ! empty( $event['subtype'] ) ) {
            return;
        }

        $text         = sanitize_text_field( $event['text'] ?? '' );
        $slack_user   = sanitize_text_field( $event['user'] ?? '' );
        $channel_id   = sanitize_text_field( $event['channel'] ?? '' );

        if ( empty( $text ) || empty( $slack_user ) || empty( $channel_id ) ) {
            return;
        }

        // Strip bot mention from text if present.
        $text = preg_replace( '/<@[A-Z0-9]+>\s*/', '', $text );
        $text = trim( $text );

        if ( empty( $text ) ) {
            return;
        }

        // Handle /pair command.
        if ( strtolower( $text ) === 'pair' || strtolower( $text ) === '/pair' ) {
            $this->handle_pair_command( $channel_id, $slack_user );
            return;
        }

        // Look up WordPress user.
        $permissions = new ClawWP_Permissions();
        $wp_user_id  = $permissions->get_user_by_channel( 'slack', $slack_user );

        if ( ! $wp_user_id ) {
            $this->send_message( $channel_id,
                "I don't recognize this Slack account. Type `pair` to get a pairing code, then enter it in WordPress admin > ClawWP > Settings."
            );
            return;
        }

        // Process the message.
        $this->process_and_respond( $text, $wp_user_id, $channel_id );
    }

    /**
     * Handle the pair command.
     */
    private function handle_pair_command( $channel_id, $slack_user ) {
        $permissions = new ClawWP_Permissions();

        $existing = $permissions->get_user_by_channel( 'slack', $slack_user );
        if ( $existing ) {
            $user = get_userdata( $existing );
            $this->send_message( $channel_id, sprintf(
                'This Slack account is already paired with WordPress user "%s".',
                $user ? $user->display_name : '#' . $existing
            ) );
            return;
        }

        $code = $permissions->generate_pair_code( 'slack', $slack_user, $channel_id );

        $this->send_message( $channel_id,
            "Your pairing code is: *{$code}*\n\nEnter this code in your WordPress admin:\nClawWP > Settings > Pair a Channel\n\nThis code expires in 5 minutes."
        );
    }

    /**
     * Send a message to a Slack channel via chat.postMessage.
     */
    public function send_message( $chat_id, $text ) {
        // Slack has a 40,000 character limit but we keep it reasonable.
        if ( mb_strlen( $text ) > 3900 ) {
            $text = mb_substr( $text, 0, 3900 ) . "\n\n_(message truncated)_";
        }

        return $this->api_call( 'chat.postMessage', array(
            'channel' => $chat_id,
            'text'    => $text,
            'mrkdwn'  => true,
        ) );
    }

    /**
     * Make a Slack API call.
     *
     * @param string $method API method name.
     * @param array  $params Parameters.
     * @return array|WP_Error
     */
    private function api_call( $method, $params = array() ) {
        $url = self::API_BASE . $method;

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Content-Type'  => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $this->bot_token,
            ),
            'body'    => wp_json_encode( $params ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['ok'] ) ) {
            return new WP_Error(
                'slack_api_error',
                $body['error'] ?? 'Unknown Slack API error',
                $body
            );
        }

        return $body;
    }
}
