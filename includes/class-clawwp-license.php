<?php
/**
 * Pro license validation and management.
 *
 * Handles license activation, deactivation, periodic validation,
 * and local caching of license status. Validates against
 * HiFriendbot.com license API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_License {

    /** @var string HiFriendbot license API base URL. */
    const API_URL = 'https://hifriendbot.com/wp-json/hifriendbot/v1/license';

    /** @var int Cache duration in seconds (24 hours). */
    const CACHE_DURATION = DAY_IN_SECONDS;

    /** @var string Transient key for cached license data. */
    const CACHE_KEY = 'clawwp_license_cache';

    /**
     * Check if the current site has an active Pro license.
     *
     * Uses cached status first, falls back to remote check.
     *
     * @return bool
     */
    public static function is_pro() {
        // Check cached status first.
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached && is_array( $cached ) ) {
            return ! empty( $cached['active'] );
        }

        // Check stored option.
        $active = (bool) ClawWP::get_option( 'pro_license_active', false );
        if ( ! $active ) {
            return false;
        }

        // If we have a license key, revalidate in the background.
        $license_key = ClawWP::get_option( 'pro_license_key' );
        if ( ! empty( $license_key ) ) {
            // Schedule async revalidation instead of blocking the request.
            if ( ! wp_next_scheduled( 'clawwp_license_revalidate' ) ) {
                wp_schedule_single_event( time(), 'clawwp_license_revalidate' );
            }
        }

        return $active;
    }

    /**
     * Activate a license key.
     *
     * @param string $license_key The license key to activate.
     * @return array{success: bool, message: string, expires_at?: string, tier?: string}
     */
    public static function activate( $license_key ) {
        $license_key = sanitize_text_field( trim( $license_key ) );

        if ( empty( $license_key ) ) {
            return array(
                'success' => false,
                'message' => __( 'Please enter a license key.', 'clawwp' ),
            );
        }

        // Validate key format (alphanumeric + dashes, 16-64 chars).
        if ( ! preg_match( '/^[A-Za-z0-9\-]{16,64}$/', $license_key ) ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid license key format.', 'clawwp' ),
            );
        }

        $response = self::api_request( 'activate', array(
            'license_key' => $license_key,
            'site_url'    => home_url(),
            'plugin_ver'  => CLAWWP_VERSION,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        if ( ! empty( $response['valid'] ) ) {
            // Store encrypted license key and status.
            ClawWP::update_option( 'pro_license_key', ClawWP::encrypt( $license_key ) );
            ClawWP::update_option( 'pro_license_active', true );
            ClawWP::update_option( 'pro_license_expires', sanitize_text_field( $response['expires_at'] ?? '' ) );
            ClawWP::update_option( 'pro_license_tier', sanitize_text_field( $response['tier'] ?? 'pro' ) );

            // Cache the validated status.
            self::cache_status( true, $response );

            ClawWP::audit_log( get_current_user_id(), 'license_activated', array(
                'tier'       => $response['tier'] ?? 'pro',
                'expires_at' => $response['expires_at'] ?? '',
            ) );

            return array(
                'success'    => true,
                'message'    => __( 'Pro license activated successfully!', 'clawwp' ),
                'expires_at' => $response['expires_at'] ?? '',
                'tier'       => $response['tier'] ?? 'pro',
            );
        }

        return array(
            'success' => false,
            'message' => sanitize_text_field( $response['message'] ?? __( 'License validation failed.', 'clawwp' ) ),
        );
    }

    /**
     * Deactivate the current license.
     *
     * @return array{success: bool, message: string}
     */
    public static function deactivate() {
        $license_key = ClawWP::get_option( 'pro_license_key' );

        if ( ! empty( $license_key ) ) {
            // Notify the license server.
            self::api_request( 'deactivate', array(
                'license_key' => ClawWP::decrypt( $license_key ),
                'site_url'    => home_url(),
            ) );
        }

        // Clear local license data.
        ClawWP::update_option( 'pro_license_key', '' );
        ClawWP::update_option( 'pro_license_active', false );
        ClawWP::update_option( 'pro_license_expires', '' );
        ClawWP::update_option( 'pro_license_tier', '' );
        delete_transient( self::CACHE_KEY );

        ClawWP::audit_log( get_current_user_id(), 'license_deactivated' );

        return array(
            'success' => true,
            'message' => __( 'License deactivated.', 'clawwp' ),
        );
    }

    /**
     * Revalidate the license against the server.
     *
     * Called periodically to ensure the license is still valid.
     *
     * @return bool Whether the license is still valid.
     */
    public static function revalidate() {
        $license_key = ClawWP::get_option( 'pro_license_key' );
        if ( empty( $license_key ) ) {
            ClawWP::update_option( 'pro_license_active', false );
            delete_transient( self::CACHE_KEY );
            return false;
        }

        $response = self::api_request( 'validate', array(
            'license_key' => ClawWP::decrypt( $license_key ),
            'site_url'    => home_url(),
            'plugin_ver'  => CLAWWP_VERSION,
        ) );

        if ( is_wp_error( $response ) ) {
            // On network errors, keep the cached status for grace period.
            // Don't deactivate immediately — the server might just be down.
            $cached = get_transient( self::CACHE_KEY );
            if ( false !== $cached ) {
                return ! empty( $cached['active'] );
            }
            // If no cache exists and we can't reach the server, keep current status.
            return (bool) ClawWP::get_option( 'pro_license_active', false );
        }

        $is_valid = ! empty( $response['valid'] );

        ClawWP::update_option( 'pro_license_active', $is_valid );
        if ( $is_valid ) {
            ClawWP::update_option( 'pro_license_expires', sanitize_text_field( $response['expires_at'] ?? '' ) );
            ClawWP::update_option( 'pro_license_tier', sanitize_text_field( $response['tier'] ?? 'pro' ) );
        }

        self::cache_status( $is_valid, $response );

        if ( ! $is_valid ) {
            ClawWP::audit_log( 0, 'license_expired', array(
                'reason' => sanitize_text_field( $response['message'] ?? 'validation failed' ),
            ) );
        }

        return $is_valid;
    }

    /**
     * Get the current license status for display.
     *
     * @return array{active: bool, key_masked: string, tier: string, expires_at: string}
     */
    public static function get_status() {
        $license_key = ClawWP::get_option( 'pro_license_key' );
        $active      = (bool) ClawWP::get_option( 'pro_license_active', false );
        $expires     = ClawWP::get_option( 'pro_license_expires', '' );
        $tier        = ClawWP::get_option( 'pro_license_tier', '' );

        $key_masked = '';
        if ( ! empty( $license_key ) ) {
            $decrypted  = ClawWP::decrypt( $license_key );
            $key_masked = substr( $decrypted, 0, 4 ) . str_repeat( '*', max( 0, strlen( $decrypted ) - 8 ) ) . substr( $decrypted, -4 );
        }

        return array(
            'active'     => $active,
            'key_masked' => $key_masked,
            'tier'       => $tier,
            'expires_at' => $expires,
        );
    }

    /**
     * Cache the license status locally.
     *
     * @param bool  $active  Whether the license is active.
     * @param array $data    Additional data from API response.
     */
    private static function cache_status( $active, $data = array() ) {
        set_transient( self::CACHE_KEY, array(
            'active'     => $active,
            'tier'       => sanitize_text_field( $data['tier'] ?? '' ),
            'expires_at' => sanitize_text_field( $data['expires_at'] ?? '' ),
            'checked_at' => time(),
        ), self::CACHE_DURATION );
    }

    /**
     * Make an API request to the HiFriendbot license server.
     *
     * @param string $action  API action (activate, deactivate, validate).
     * @param array  $body    Request body parameters.
     * @return array|WP_Error Decoded response body or WP_Error.
     */
    private static function api_request( $action, $body = array() ) {
        $allowed_actions = array( 'activate', 'deactivate', 'validate' );
        if ( ! in_array( $action, $allowed_actions, true ) ) {
            return new WP_Error( 'invalid_action', 'Invalid API action.' );
        }

        $url = trailingslashit( self::API_URL ) . $action;

        $response = wp_remote_post( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'User-Agent'   => 'ClawWP/' . CLAWWP_VERSION . ' WordPress/' . get_bloginfo( 'version' ),
            ),
            'body'    => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'api_error',
                __( 'Could not connect to the license server. Please try again later.', 'clawwp' )
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error(
                'api_error',
                sanitize_text_field( $body['message'] ?? __( 'License server returned an error.', 'clawwp' ) )
            );
        }

        if ( ! is_array( $body ) ) {
            return new WP_Error( 'api_error', __( 'Invalid response from license server.', 'clawwp' ) );
        }

        return $body;
    }
}
