<?php
/**
 * Webchat channel — the admin sidebar chat.
 *
 * This channel handles messages sent from the wp-admin sidebar
 * chat widget. It communicates via the REST API and doesn't need
 * external webhook registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Channel_Webchat extends ClawWP_Channel {

    public function get_name() {
        return 'webchat';
    }

    /**
     * Webchat doesn't receive webhooks — it's handled by the REST endpoint directly.
     */
    public function handle_incoming( WP_REST_Request $request ) {
        // Handled by ClawWP_Webhook_Handler::handle_chat().
    }

    /**
     * Webchat doesn't push messages — the response is returned via REST.
     */
    public function send_message( $chat_id, $text ) {
        // Response is returned inline in the REST response.
        return true;
    }
}
