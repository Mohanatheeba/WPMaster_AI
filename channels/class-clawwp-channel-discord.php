<?php
/**
 * Discord channel (Pro) — Discord Interactions integration.
 *
 * Receives messages via Discord Interactions endpoint (HTTP-based,
 * no persistent gateway needed), resolves the Discord user to a
 * WordPress user via pairing, and sends responses back via Discord API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Channel_Discord extends ClawWP_Channel {

    const API_BASE = 'https://discord.com/api/v10/';

    /** @var string */
    private $bot_token;

    /** @var string */
    private $application_id;

    public function __construct( $bot_token, $application_id = '' ) {
        $this->bot_token      = $bot_token;
        $this->application_id = $application_id;
    }

    public function get_name() {
        return 'discord';
    }

    /**
     * Initialize: hook into the Discord webhook action.
     */
    public function init() {
        add_action( 'clawwp_discord_webhook', array( $this, 'handle_incoming' ) );
    }

    /**
     * Handle an incoming Discord interaction.
     */
    public function handle_incoming( WP_REST_Request $request ) {
        $body = $request->get_json_params();
        $type = (int) ( $body['type'] ?? 0 );

        // Type 2 = Application command, Type 4 = Message component.
        // We handle both slash commands and message interactions.
        switch ( $type ) {
            case 2: // APPLICATION_COMMAND
                $this->handle_command( $body );
                break;

            case 4: // MESSAGE_COMPONENT (future use)
                break;
        }
    }

    /**
     * Handle a slash command interaction.
     */
    private function handle_command( $body ) {
        $data         = $body['data'] ?? array();
        $command_name = sanitize_text_field( $data['name'] ?? '' );
        $discord_user = sanitize_text_field( $body['member']['user']['id'] ?? ( $body['user']['id'] ?? '' ) );
        $channel_id   = sanitize_text_field( $body['channel_id'] ?? '' );
        $token        = $body['token'] ?? '';

        if ( empty( $discord_user ) || empty( $channel_id ) ) {
            return;
        }

        // Handle /pair command.
        if ( 'pair' === $command_name ) {
            $this->handle_pair( $discord_user, $channel_id, $token );
            return;
        }

        // Handle /ask command (main interaction).
        if ( 'ask' === $command_name || 'clawwp' === $command_name ) {
            $options = $data['options'] ?? array();
            $message = '';
            foreach ( $options as $opt ) {
                if ( 'message' === $opt['name'] ) {
                    $message = sanitize_text_field( $opt['value'] ?? '' );
                    break;
                }
            }

            if ( empty( $message ) ) {
                $this->send_followup( $token, 'Please include a message. Usage: `/ask <your question>`' );
                return;
            }

            // Look up WordPress user.
            $permissions = new ClawWP_Permissions();
            $wp_user_id  = $permissions->get_user_by_channel( 'discord', $discord_user );

            if ( ! $wp_user_id ) {
                $this->send_followup( $token,
                    "I don't recognize this Discord account. Use `/pair` to get a pairing code, then enter it in WordPress admin > ClawWP > Settings."
                );
                return;
            }

            // Process message through agent.
            try {
                $agent  = ClawWP::get_agent();
                $result = $agent->handle_message( $message, $wp_user_id, 'discord', $channel_id );

                if ( ! empty( $result['response'] ) ) {
                    $this->send_followup( $token, $result['response'] );
                }
            } catch ( Exception $e ) {
                $this->send_followup( $token, 'Sorry, an error occurred processing your message.' );
            }
        }
    }

    /**
     * Handle the /pair command.
     */
    private function handle_pair( $discord_user, $channel_id, $token ) {
        $permissions = new ClawWP_Permissions();

        $existing = $permissions->get_user_by_channel( 'discord', $discord_user );
        if ( $existing ) {
            $user = get_userdata( $existing );
            $this->send_followup( $token, sprintf(
                'This Discord account is already paired with WordPress user "%s".',
                $user ? $user->display_name : '#' . $existing
            ) );
            return;
        }

        $code = $permissions->generate_pair_code( 'discord', $discord_user, $channel_id );

        $this->send_followup( $token,
            "Your pairing code is: **{$code}**\n\nEnter this code in your WordPress admin:\nClawWP > Settings > Pair a Channel\n\nThis code expires in 5 minutes."
        );
    }

    /**
     * Send a message to a Discord channel.
     */
    public function send_message( $chat_id, $text ) {
        // Discord has a 2000 character limit.
        if ( mb_strlen( $text ) > 1950 ) {
            $text = mb_substr( $text, 0, 1950 ) . "\n\n*(message truncated)*";
        }

        return $this->api_call( 'channels/' . $chat_id . '/messages', array(
            'content' => $text,
        ) );
    }

    /**
     * Send a followup response to an interaction.
     *
     * @param string $token Interaction token.
     * @param string $text  Response text.
     */
    private function send_followup( $token, $text ) {
        if ( empty( $this->application_id ) ) {
            return;
        }

        // Discord has a 2000 character limit.
        if ( mb_strlen( $text ) > 1950 ) {
            $text = mb_substr( $text, 0, 1950 ) . "\n\n*(message truncated)*";
        }

        $url = self::API_BASE . 'webhooks/' . $this->application_id . '/' . $token;

        wp_remote_post( $url, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bot ' . $this->bot_token,
            ),
            'body'    => wp_json_encode( array(
                'content' => $text,
            ) ),
            'timeout' => 15,
        ) );
    }

    /**
     * Make a Discord API call.
     *
     * @param string $endpoint API endpoint (relative to API_BASE).
     * @param array  $params   Parameters.
     * @return array|WP_Error
     */
    private function api_call( $endpoint, $params = array() ) {
        $url = self::API_BASE . $endpoint;

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bot ' . $this->bot_token,
            ),
            'body'    => wp_json_encode( $params ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            return new WP_Error(
                'discord_api_error',
                $body['message'] ?? 'Discord API error (HTTP ' . $code . ')',
                $body
            );
        }

        return $body;
    }

    /**
     * Register slash commands with Discord.
     *
     * @return array|WP_Error
     */
    public function register_commands() {
        if ( empty( $this->application_id ) ) {
            return new WP_Error( 'missing_app_id', 'Discord Application ID is required.' );
        }

        $commands = array(
            array(
                'name'        => 'ask',
                'description' => 'Ask ClawWP a question about your WordPress site',
                'options'     => array(
                    array(
                        'name'        => 'message',
                        'description' => 'Your question or command',
                        'type'        => 3, // STRING
                        'required'    => true,
                    ),
                ),
            ),
            array(
                'name'        => 'pair',
                'description' => 'Link your Discord account to your WordPress user',
            ),
        );

        $url = self::API_BASE . 'applications/' . $this->application_id . '/commands';

        $results = array();
        foreach ( $commands as $command ) {
            $response = wp_remote_request( $url, array(
                'method'  => 'POST',
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bot ' . $this->bot_token,
                ),
                'body'    => wp_json_encode( $command ),
                'timeout' => 15,
            ) );

            if ( ! is_wp_error( $response ) ) {
                $results[] = json_decode( wp_remote_retrieve_body( $response ), true );
            }
        }

        return $results;
    }
}
