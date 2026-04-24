<?php
/**
 * Permission and security system.
 *
 * Handles capability checks, channel-to-user pairing,
 * rate limiting, and action confirmation. This is where
 * ClawWP differentiates from OpenClaw's 512-CVE security posture.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Permissions {

    /** @var int Rate limit: max requests per hour per user. */
    const RATE_LIMIT_PER_HOUR = 60;

    /** @var int Pairing code expiration in seconds. */
    const PAIR_CODE_EXPIRY = 300;

    /**
     * Check if a user has permission to execute a tool.
     *
     * @param int    $user_id    WordPress user ID.
     * @param string $capability Required WordPress capability.
     * @return bool
     */
    public function can_execute( $user_id, $capability ) {
        if ( ! $user_id ) {
            return false;
        }
        return user_can( $user_id, $capability );
    }

    /**
     * Check if a tool action requires confirmation.
     *
     * @param string $tool_name
     * @param array  $params    Tool parameters.
     * @return bool
     */
    public function requires_confirmation( $tool_name, $params = array() ) {
        $action = $params['action'] ?? '';

        // Destructive content operations.
        if ( in_array( $tool_name, array( 'manage_posts', 'manage_pages', 'manage_media', 'manage_comments' ), true )
            && in_array( $action, array( 'delete', 'trash' ), true ) ) {
            return true;
        }

        // Financial transactions.
        if ( 'wallet' === $tool_name && 'send_transaction' === $action ) {
            return true;
        }

        // Publishing content from draft.
        if ( in_array( $tool_name, array( 'manage_posts', 'manage_pages' ), true )
            && isset( $params['status'] ) && 'publish' === $params['status'] ) {
            return true;
        }

        // Bulk operations with many items.
        if ( isset( $params['count'] ) && (int) $params['count'] > 5 ) {
            return true;
        }

        return false;
    }

    /**
     * Check rate limit for a user.
     *
     * @param int $user_id
     * @return bool True if within limits.
     */
    public function check_rate_limit( $user_id ) {
        $user_id       = (int) $user_id;
        $transient_key = 'clawwp_rl_' . wp_hash( $user_id . '_' . gmdate( 'YmdH' ) );
        $count         = (int) get_transient( $transient_key );

        if ( $count >= self::RATE_LIMIT_PER_HOUR ) {
            return false;
        }

        set_transient( $transient_key, $count + 1, HOUR_IN_SECONDS );
        return true;
    }

    /**
     * Get remaining rate limit for a user.
     *
     * @param int $user_id
     * @return int
     */
    public function get_remaining_rate( $user_id ) {
        $user_id       = (int) $user_id;
        $transient_key = 'clawwp_rl_' . wp_hash( $user_id . '_' . gmdate( 'YmdH' ) );
        $count         = (int) get_transient( $transient_key );
        return max( 0, self::RATE_LIMIT_PER_HOUR - $count );
    }

    // -------------------------------------------------------------------------
    // Channel Pairing
    // -------------------------------------------------------------------------

    /**
     * Generate a one-time pairing code for a channel.
     *
     * @param string $channel         Channel name (telegram, slack, etc.).
     * @param string $channel_user_id Channel-specific user identifier.
     * @param string $channel_chat_id Channel-specific chat identifier.
     * @return string 6-digit pairing code.
     */
    public function generate_pair_code( $channel, $channel_user_id, $channel_chat_id ) {
        // Use cryptographically secure randomness for pairing codes.
        try {
            $code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
        } catch ( \Exception $e ) {
            // Fallback if random_int fails (should not happen on PHP 7+).
            $code = str_pad( wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
        }

        // Use a hashed transient key to prevent code enumeration.
        $transient_key = 'clawwp_pair_' . wp_hash( $code );

        set_transient( $transient_key, array(
            'channel'         => sanitize_text_field( $channel ),
            'channel_user_id' => sanitize_text_field( $channel_user_id ),
            'channel_chat_id' => sanitize_text_field( $channel_chat_id ),
            'created'         => time(),
        ), self::PAIR_CODE_EXPIRY );

        return $code;
    }

    /**
     * Complete the pairing process with a code.
     *
     * @param int    $user_id WordPress user ID (from admin).
     * @param string $code    6-digit pairing code.
     * @return array|WP_Error Pairing data on success, WP_Error on failure.
     */
    public function complete_pairing( $user_id, $code ) {
        // Validate code format before lookup.
        $code = sanitize_text_field( $code );
        if ( ! preg_match( '/^\d{6}$/', $code ) ) {
            return new WP_Error( 'invalid_code', __( 'Invalid pairing code format.', 'clawwp' ) );
        }

        $transient_key = 'clawwp_pair_' . wp_hash( $code );
        $pair_data     = get_transient( $transient_key );

        if ( ! $pair_data ) {
            return new WP_Error( 'invalid_code', __( 'Invalid or expired pairing code.', 'clawwp' ) );
        }

        // Remove the transient so the code can't be reused.
        delete_transient( $transient_key );

        global $wpdb;

        // Check if this channel user is already paired.
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}clawwp_pairings WHERE channel = %s AND channel_user_id = %s",
            $pair_data['channel'],
            $pair_data['channel_user_id']
        ) );

        if ( $existing ) {
            // Update existing pairing.
            $wpdb->update(
                $wpdb->prefix . 'clawwp_pairings',
                array(
                    'user_id'   => $user_id,
                    'paired_at' => current_time( 'mysql', true ),
                ),
                array( 'id' => $existing ),
                array( '%d', '%s' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'clawwp_pairings',
                array(
                    'user_id'         => $user_id,
                    'channel'         => $pair_data['channel'],
                    'channel_user_id' => $pair_data['channel_user_id'],
                    'channel_chat_id' => $pair_data['channel_chat_id'],
                    'paired_at'       => current_time( 'mysql', true ),
                ),
                array( '%d', '%s', '%s', '%s', '%s' )
            );
        }

        ClawWP::audit_log( $user_id, 'channel_paired', array(
            'channel'         => $pair_data['channel'],
            'channel_user_id' => $pair_data['channel_user_id'],
        ), $pair_data['channel'] );

        return $pair_data;
    }

    /**
     * Look up a WordPress user by their channel identity.
     *
     * @param string $channel         Channel name.
     * @param string $channel_user_id Channel-specific user identifier.
     * @return int|null WordPress user ID, or null if not paired.
     */
    public function get_user_by_channel( $channel, $channel_user_id ) {
        global $wpdb;

        $user_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}clawwp_pairings WHERE channel = %s AND channel_user_id = %s",
            $channel,
            $channel_user_id
        ) );

        return $user_id ? (int) $user_id : null;
    }

    /**
     * Get all pairings for a user.
     *
     * @param int $user_id
     * @return array
     */
    public function get_user_pairings( $user_id ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT channel, channel_user_id, channel_chat_id, paired_at
             FROM {$wpdb->prefix}clawwp_pairings
             WHERE user_id = %d
             ORDER BY paired_at DESC",
            $user_id
        ), ARRAY_A );
    }

    /**
     * Remove a channel pairing.
     *
     * @param int    $user_id
     * @param string $channel
     * @param string $channel_user_id
     * @return bool
     */
    public function remove_pairing( $user_id, $channel, $channel_user_id ) {
        global $wpdb;

        $deleted = $wpdb->delete(
            $wpdb->prefix . 'clawwp_pairings',
            array(
                'user_id'         => $user_id,
                'channel'         => $channel,
                'channel_user_id' => $channel_user_id,
            ),
            array( '%d', '%s', '%s' )
        );

        if ( $deleted ) {
            ClawWP::audit_log( $user_id, 'channel_unpaired', array(
                'channel'         => $channel,
                'channel_user_id' => $channel_user_id,
            ), $channel );
        }

        return (bool) $deleted;
    }

    /**
     * Verify a webhook request signature.
     *
     * @param string $channel   Channel name.
     * @param string $payload   Raw request body.
     * @param string $signature Signature header value.
     * @return bool
     */
    public function verify_webhook_signature( $channel, $payload, $signature ) {
        switch ( $channel ) {
            case 'telegram':
                // Telegram uses a secret_token set during setWebhook.
                $secret = ClawWP::get_option( 'telegram_webhook_secret' );
                return ! empty( $secret ) && hash_equals( $secret, $signature );

            case 'slack':
                // Slack uses HMAC-SHA256 with signing secret.
                $secret = ClawWP::get_option( 'slack_signing_secret' );
                if ( empty( $secret ) ) {
                    return false;
                }
                $computed = 'v0=' . hash_hmac( 'sha256', $payload, $secret );
                return hash_equals( $computed, $signature );

            case 'discord':
                // Discord uses Ed25519 signature verification.
                // Requires sodium extension.
                $public_key = ClawWP::get_option( 'discord_public_key' );
                if ( empty( $public_key ) || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
                    return false;
                }
                try {
                    return sodium_crypto_sign_verify_detached(
                        hex2bin( $signature ),
                        $payload,
                        hex2bin( $public_key )
                    );
                } catch ( \Exception $e ) {
                    return false;
                }

            default:
                return false;
        }
    }
}
