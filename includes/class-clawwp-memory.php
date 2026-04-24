<?php
/**
 * Local memory system (free tier).
 *
 * Stores facts the agent learns about the site and user preferences
 * in the WordPress database. Uses keyword-based recall with importance scoring.
 *
 * Pro tier replaces this with HiFriendbot's Cognitive Memory API
 * for three-layer semantic recall with importance decay.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Memory {

    /** @var int Maximum memories per user (free tier). */
    const MAX_MEMORIES = 100;

    /**
     * Store a fact in memory.
     *
     * @param int    $user_id
     * @param string $fact       The fact to remember.
     * @param string $category   Category (preference, site_info, workflow, general).
     * @param float  $importance 0.0 to 1.0 importance score.
     * @return int Memory ID.
     */
    public function store( $user_id, $fact, $category = 'general', $importance = 0.5 ) {
        global $wpdb;

        // Validate and sanitize inputs.
        $user_id    = (int) $user_id;
        $fact       = sanitize_textarea_field( $fact );
        $category   = sanitize_key( $category );
        $importance = max( 0.0, min( 1.0, (float) $importance ) );

        $allowed_categories = array( 'general', 'preference', 'site_info', 'workflow' );
        if ( ! in_array( $category, $allowed_categories, true ) ) {
            $category = 'general';
        }

        // Reject empty or too-short facts.
        if ( strlen( $fact ) < 5 || strlen( $fact ) > 1000 ) {
            return 0;
        }

        // Check for duplicate or very similar facts.
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}clawwp_memories WHERE user_id = %d AND fact = %s",
            $user_id,
            $fact
        ) );

        if ( $existing ) {
            // Update importance and access time instead of duplicating.
            $wpdb->update(
                $wpdb->prefix . 'clawwp_memories',
                array(
                    'importance'    => $importance,
                    'last_accessed' => current_time( 'mysql', true ),
                ),
                array( 'id' => $existing )
            );
            return (int) $existing;
        }

        // Enforce memory limit — remove least important if at cap.
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}clawwp_memories WHERE user_id = %d",
            $user_id
        ) );

        if ( $count >= self::MAX_MEMORIES ) {
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}clawwp_memories WHERE user_id = %d ORDER BY importance ASC, last_accessed ASC LIMIT 1",
                $user_id
            ) );
        }

        $wpdb->insert(
            $wpdb->prefix . 'clawwp_memories',
            array(
                'user_id'       => $user_id,
                'fact'          => $fact,
                'category'      => $category,
                'importance'    => $importance,
                'last_accessed' => current_time( 'mysql', true ),
                'created_at'    => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%f', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Recall relevant memories for a user.
     *
     * @param int    $user_id
     * @param string $context Optional context to match against (keyword search).
     * @param int    $limit   Maximum memories to return.
     * @return array
     */
    public function recall_relevant( $user_id, $context = '', $limit = 10 ) {
        global $wpdb;

        if ( ! empty( $context ) ) {
            // Extract keywords from context for matching.
            $keywords = $this->extract_keywords( $context );

            if ( ! empty( $keywords ) ) {
                $like_clauses = array();
                $values       = array( $user_id );

                foreach ( $keywords as $keyword ) {
                    $like_clauses[] = 'fact LIKE %s';
                    $values[]       = '%' . $wpdb->esc_like( $keyword ) . '%';
                }

                $where_likes = implode( ' OR ', $like_clauses );
                $values[]    = $limit;

                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, fact, category, importance, last_accessed
                         FROM {$wpdb->prefix}clawwp_memories
                         WHERE user_id = %d AND ({$where_likes})
                         ORDER BY importance DESC, last_accessed DESC
                         LIMIT %d",
                        ...$values
                    ),
                    ARRAY_A
                );

                // Update last_accessed for recalled memories.
                if ( ! empty( $results ) ) {
                    $ids = array_column( $results, 'id' );
                    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                    $wpdb->query( $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}clawwp_memories SET last_accessed = %s WHERE id IN ({$placeholders})",
                        array_merge( array( current_time( 'mysql', true ) ), $ids )
                    ) );
                }

                return $results;
            }
        }

        // Fallback: return most important memories.
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, fact, category, importance, last_accessed
             FROM {$wpdb->prefix}clawwp_memories
             WHERE user_id = %d
             ORDER BY importance DESC, last_accessed DESC
             LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A );
    }

    /**
     * Extract and store new facts from a conversation turn.
     *
     * Looks for patterns that indicate preferences or facts worth remembering.
     *
     * @param int    $user_id
     * @param string $user_message
     * @param string $agent_response
     */
    public function extract_and_store( $user_id, $user_message, $agent_response ) {
        // Pattern: "I prefer..." / "I always..." / "I like..." / "Remember that..."
        $preference_patterns = array(
            '/\b(?:I prefer|I always|I like|I want|always use|never use|remember that)\b\s+(.+)/i',
            '/\b(?:my|our)\s+(?:brand|company|business|store|shop|site)\s+(?:is called|is named|name is)\s+(.+)/i',
        );

        foreach ( $preference_patterns as $pattern ) {
            if ( preg_match( $pattern, $user_message, $matches ) ) {
                $fact = sanitize_textarea_field( trim( $matches[0] ) );
                if ( strlen( $fact ) > 10 && strlen( $fact ) < 500 ) {
                    $this->store( (int) $user_id, $fact, 'preference', 0.8 );
                }
            }
        }
    }

    /**
     * Get all memories for a user.
     *
     * @param int    $user_id
     * @param string $category Optional filter.
     * @return array
     */
    public function get_all( $user_id, $category = '' ) {
        global $wpdb;

        if ( ! empty( $category ) ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}clawwp_memories WHERE user_id = %d AND category = %s ORDER BY importance DESC",
                $user_id,
                $category
            ), ARRAY_A );
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}clawwp_memories WHERE user_id = %d ORDER BY importance DESC",
            $user_id
        ), ARRAY_A );
    }

    /**
     * Delete a specific memory.
     *
     * @param int $memory_id
     * @param int $user_id
     * @return bool
     */
    public function forget( $memory_id, $user_id ) {
        global $wpdb;
        return (bool) $wpdb->delete(
            $wpdb->prefix . 'clawwp_memories',
            array( 'id' => $memory_id, 'user_id' => $user_id ),
            array( '%d', '%d' )
        );
    }

    /**
     * Extract searchable keywords from text.
     *
     * @param string $text
     * @return array
     */
    private function extract_keywords( $text ) {
        // Remove common stop words and extract meaningful terms.
        $stop_words = array(
            'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
            'should', 'may', 'might', 'can', 'shall', 'to', 'of', 'in', 'for',
            'on', 'with', 'at', 'by', 'from', 'as', 'into', 'about', 'this',
            'that', 'it', 'not', 'but', 'and', 'or', 'if', 'then', 'so', 'my',
            'your', 'we', 'our', 'how', 'what', 'when', 'where', 'who', 'which',
            'me', 'i', 'you', 'he', 'she', 'they', 'them', 'all', 'any', 'some',
        );

        $words = preg_split( '/\W+/', strtolower( $text ) );
        $words = array_filter( $words, function ( $w ) use ( $stop_words ) {
            return strlen( $w ) > 2 && ! in_array( $w, $stop_words, true );
        } );

        return array_unique( array_slice( array_values( $words ), 0, 10 ) );
    }
}
