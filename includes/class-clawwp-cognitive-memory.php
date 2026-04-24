<?php
/**
 * HiFriendbot Cognitive Memory integration (Pro).
 *
 * Replaces the basic local memory system with HiFriendbot's
 * three-layer Cognitive Memory architecture:
 * - Structured recall with importance scoring
 * - Semantic understanding for contextual retrieval
 * - Importance decay over time
 *
 * This is ClawWP's single biggest differentiator over OpenClaw.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Cognitive_Memory extends ClawWP_Memory {

    /** @var string HiFriendbot Cognitive Memory API base URL. */
    const API_URL = 'https://hifriendbot.com/api/v1/memory';

    /** @var string|null Cached API key. */
    private $api_key = null;

    /**
     * Get the HiFriendbot API key (uses the Pro license key).
     *
     * @return string
     */
    private function get_api_key() {
        if ( null === $this->api_key ) {
            $license_key = ClawWP::get_option( 'pro_license_key' );
            $this->api_key = ! empty( $license_key ) ? ClawWP::decrypt( $license_key ) : '';
        }
        return $this->api_key;
    }

    /**
     * Check if Cognitive Memory is available.
     *
     * @return bool
     */
    public function is_available() {
        return ClawWP_License::is_pro() && ! empty( $this->get_api_key() );
    }

    /**
     * Store a fact using Cognitive Memory API.
     *
     * Falls back to local storage if API is unavailable.
     *
     * @param int    $user_id
     * @param string $fact
     * @param string $category
     * @param float  $importance
     * @return int Memory ID.
     */
    public function store( $user_id, $fact, $category = 'general', $importance = 0.5 ) {
        if ( ! $this->is_available() ) {
            return parent::store( $user_id, $fact, $category, $importance );
        }

        // Sanitize inputs.
        $user_id    = (int) $user_id;
        $fact       = sanitize_textarea_field( $fact );
        $category   = sanitize_key( $category );
        $importance = max( 0.0, min( 1.0, (float) $importance ) );

        if ( strlen( $fact ) < 5 || strlen( $fact ) > 1000 ) {
            return 0;
        }

        $response = $this->api_request( 'store', array(
            'site_id'    => $this->get_site_id(),
            'user_id'    => $user_id,
            'fact'       => $fact,
            'category'   => $category,
            'importance' => $importance,
            'context'    => array(
                'site_name' => get_bloginfo( 'name' ),
                'site_url'  => home_url(),
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            // Fallback to local storage on API failure.
            return parent::store( $user_id, $fact, $category, $importance );
        }

        // Also store locally as a cache.
        parent::store( $user_id, $fact, $category, $importance );

        return (int) ( $response['memory_id'] ?? 0 );
    }

    /**
     * Recall relevant memories using Cognitive Memory's semantic search.
     *
     * Falls back to local keyword search if API is unavailable.
     *
     * @param int    $user_id
     * @param string $context
     * @param int    $limit
     * @return array
     */
    public function recall_relevant( $user_id, $context = '', $limit = 10 ) {
        if ( ! $this->is_available() ) {
            return parent::recall_relevant( $user_id, $context, $limit );
        }

        $response = $this->api_request( 'recall', array(
            'site_id' => $this->get_site_id(),
            'user_id' => (int) $user_id,
            'context' => sanitize_text_field( $context ),
            'limit'   => min( (int) $limit, 20 ),
        ) );

        if ( is_wp_error( $response ) ) {
            // Fallback to local recall.
            return parent::recall_relevant( $user_id, $context, $limit );
        }

        $memories = $response['memories'] ?? array();
        $result   = array();

        foreach ( $memories as $mem ) {
            $result[] = array(
                'id'            => $mem['id'] ?? 0,
                'fact'          => sanitize_textarea_field( $mem['fact'] ?? '' ),
                'category'      => sanitize_key( $mem['category'] ?? 'general' ),
                'importance'    => (float) ( $mem['importance'] ?? 0.5 ),
                'last_accessed' => $mem['last_accessed'] ?? '',
                'relevance'     => (float) ( $mem['relevance_score'] ?? 0 ),
            );
        }

        return $result;
    }

    /**
     * Extract and store facts with AI-powered extraction.
     *
     * The Cognitive Memory API uses AI to identify important facts
     * from conversations, rather than simple regex patterns.
     *
     * @param int    $user_id
     * @param string $user_message
     * @param string $agent_response
     */
    public function extract_and_store( $user_id, $user_message, $agent_response ) {
        if ( ! $this->is_available() ) {
            parent::extract_and_store( $user_id, $user_message, $agent_response );
            return;
        }

        // Send to the Cognitive Memory API for intelligent extraction.
        $this->api_request( 'extract', array(
            'site_id'        => $this->get_site_id(),
            'user_id'        => (int) $user_id,
            'user_message'   => sanitize_textarea_field( $user_message ),
            'agent_response' => sanitize_textarea_field( $agent_response ),
            'context'        => array(
                'site_name' => get_bloginfo( 'name' ),
            ),
        ) );

        // Also run local extraction as a fallback cache.
        parent::extract_and_store( $user_id, $user_message, $agent_response );
    }

    /**
     * Get all memories including cloud-stored ones.
     *
     * @param int    $user_id
     * @param string $category
     * @return array
     */
    public function get_all( $user_id, $category = '' ) {
        if ( ! $this->is_available() ) {
            return parent::get_all( $user_id, $category );
        }

        $params = array(
            'site_id' => $this->get_site_id(),
            'user_id' => (int) $user_id,
        );

        if ( ! empty( $category ) ) {
            $params['category'] = sanitize_key( $category );
        }

        $response = $this->api_request( 'list', $params );

        if ( is_wp_error( $response ) ) {
            return parent::get_all( $user_id, $category );
        }

        $memories = $response['memories'] ?? array();
        $result   = array();

        foreach ( $memories as $mem ) {
            $result[] = array(
                'id'            => $mem['id'] ?? 0,
                'fact'          => sanitize_textarea_field( $mem['fact'] ?? '' ),
                'category'      => sanitize_key( $mem['category'] ?? 'general' ),
                'importance'    => (float) ( $mem['importance'] ?? 0.5 ),
                'last_accessed' => $mem['last_accessed'] ?? '',
                'created_at'    => $mem['created_at'] ?? '',
            );
        }

        return $result;
    }

    /**
     * Delete a memory from both cloud and local storage.
     *
     * @param int $memory_id
     * @param int $user_id
     * @return bool
     */
    public function forget( $memory_id, $user_id ) {
        if ( $this->is_available() ) {
            $this->api_request( 'delete', array(
                'site_id'   => $this->get_site_id(),
                'user_id'   => (int) $user_id,
                'memory_id' => (int) $memory_id,
            ) );
        }

        return parent::forget( $memory_id, $user_id );
    }

    /**
     * Get a unique site identifier for the Cognitive Memory API.
     *
     * @return string
     */
    private function get_site_id() {
        $site_id = ClawWP::get_option( 'cognitive_memory_site_id' );

        if ( empty( $site_id ) ) {
            // Generate a stable site ID based on the site URL.
            $site_id = wp_hash( home_url() . wp_salt( 'auth' ) );
            ClawWP::update_option( 'cognitive_memory_site_id', $site_id );
        }

        return $site_id;
    }

    /**
     * Make an API request to the HiFriendbot Cognitive Memory server.
     *
     * @param string $action API action.
     * @param array  $body   Request body.
     * @return array|WP_Error
     */
    private function api_request( $action, $body = array() ) {
        $api_key = $this->get_api_key();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'Cognitive Memory API key not configured.' );
        }

        $url = trailingslashit( self::API_URL ) . sanitize_key( $action );

        $response = wp_remote_post( $url, array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'User-Agent'    => 'ClawWP/' . CLAWWP_VERSION,
            ),
            'body' => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'api_error',
                'Could not connect to Cognitive Memory API.'
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error(
                'api_error',
                sanitize_text_field( $data['message'] ?? 'Cognitive Memory API error.' )
            );
        }

        if ( ! is_array( $data ) ) {
            return new WP_Error( 'api_error', 'Invalid response from Cognitive Memory API.' );
        }

        return $data;
    }
}
