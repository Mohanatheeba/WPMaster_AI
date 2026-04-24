<?php
/**
 * Conversation history manager.
 *
 * Handles CRUD operations for conversations and messages,
 * and manages context window assembly for AI requests.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Conversation {

    /** @var int Maximum messages to include in context. */
    const MAX_CONTEXT_MESSAGES = 50;

    /** @var int Default retention in days for free tier. */
    const FREE_RETENTION_DAYS = 30;

    /**
     * Get or create a conversation for a user on a channel.
     *
     * @param int    $user_id         WordPress user ID.
     * @param string $channel         Channel name (telegram, webchat, etc.).
     * @param string $channel_chat_id Channel-specific chat identifier.
     * @return int Conversation ID.
     */
    public function get_or_create( $user_id, $channel, $channel_chat_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'clawwp_conversations';

        $conversation_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d AND channel = %s AND channel_chat_id = %s ORDER BY updated_at DESC LIMIT 1",
            $user_id,
            $channel,
            $channel_chat_id
        ) );

        if ( $conversation_id ) {
            $wpdb->update( $table, array( 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $conversation_id ) );
            return (int) $conversation_id;
        }

        $wpdb->insert( $table, array(
            'user_id'         => $user_id,
            'channel'         => $channel,
            'channel_chat_id' => $channel_chat_id,
            'created_at'      => current_time( 'mysql', true ),
            'updated_at'      => current_time( 'mysql', true ),
        ), array( '%d', '%s', '%s', '%s', '%s' ) );

        return (int) $wpdb->insert_id;
    }

    /**
     * Add a message to a conversation.
     *
     * @param int    $conversation_id
     * @param string $role            user|assistant|system|tool
     * @param string $content         Message content.
     * @param array  $extra           Optional: tool_calls, tool_results, tokens_in, tokens_out, model.
     * @return int Message ID.
     */
    public function add_message( $conversation_id, $role, $content, $extra = array() ) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'clawwp_messages',
            array(
                'conversation_id' => $conversation_id,
                'role'            => $role,
                'content'         => $content,
                'tool_calls'      => isset( $extra['tool_calls'] ) ? wp_json_encode( $extra['tool_calls'] ) : null,
                'tool_results'    => isset( $extra['tool_results'] ) ? wp_json_encode( $extra['tool_results'] ) : null,
                'tokens_in'       => $extra['tokens_in'] ?? 0,
                'tokens_out'      => $extra['tokens_out'] ?? 0,
                'model'           => $extra['model'] ?? null,
                'created_at'      => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        // Update conversation timestamp.
        $wpdb->update(
            $wpdb->prefix . 'clawwp_conversations',
            array( 'updated_at' => current_time( 'mysql', true ) ),
            array( 'id' => $conversation_id )
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Get recent messages for a conversation, formatted for AI context.
     *
     * @param int $conversation_id
     * @param int $limit           Maximum messages to retrieve.
     * @return array Messages in AI-ready format.
     */
    public function get_context_messages( $conversation_id, $limit = self::MAX_CONTEXT_MESSAGES ) {
        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT role, content, tool_calls, tool_results
             FROM {$wpdb->prefix}clawwp_messages
             WHERE conversation_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $conversation_id,
            $limit
        ), ARRAY_A );

        if ( empty( $rows ) ) {
            return array();
        }

        // Reverse to chronological order.
        $rows = array_reverse( $rows );

        $messages = array();
        foreach ( $rows as $row ) {
            $msg = array(
                'role'    => $row['role'],
                'content' => $row['content'],
            );

            if ( ! empty( $row['tool_calls'] ) ) {
                $msg['tool_calls'] = json_decode( $row['tool_calls'], true );
            }

            if ( 'tool' === $row['role'] && ! empty( $row['tool_results'] ) ) {
                $results = json_decode( $row['tool_results'], true );
                $msg['tool_call_id'] = $results['tool_call_id'] ?? '';
            }

            $messages[] = $msg;
        }

        // Validate tool call chain — every tool_result must reference
        // a tool_use_id from the immediately preceding assistant message.
        // If the chain is broken, truncate history to the last valid point.
        return $this->validate_tool_chain( $messages );
    }

    /**
     * Validate tool call chain integrity.
     *
     * Anthropic requires that:
     * 1. Every tool_result references a tool_use_id from the immediately
     *    preceding assistant message.
     * 2. Every tool_use in an assistant message has a matching tool_result
     *    immediately following it.
     *
     * If either rule is violated (e.g. from a failed prior request),
     * the entire broken tool exchange is removed.
     *
     * @param array $messages Chronologically ordered messages.
     * @return array Validated messages with broken tool chains removed.
     */
    private function validate_tool_chain( $messages ) {
        $count = count( $messages );
        $keep  = array_fill( 0, $count, true );

        for ( $i = 0; $i < $count; $i++ ) {
            $msg = $messages[ $i ];

            // Only check assistant messages that have tool_calls.
            if ( 'assistant' !== $msg['role'] || empty( $msg['tool_calls'] ) ) {
                continue;
            }

            // Collect expected tool_use IDs from this assistant message.
            $expected_ids = array();
            foreach ( $msg['tool_calls'] as $tc ) {
                if ( ! empty( $tc['id'] ) ) {
                    $expected_ids[ $tc['id'] ] = true;
                }
            }

            // Look ahead for matching tool_result messages.
            $found_ids = array();
            $j         = $i + 1;
            while ( $j < $count && 'tool' === $messages[ $j ]['role'] ) {
                $tid = $messages[ $j ]['tool_call_id'] ?? '';
                if ( ! empty( $tid ) && isset( $expected_ids[ $tid ] ) ) {
                    $found_ids[ $tid ] = $j;
                }
                $j++;
            }

            // If all tool_use IDs have matching results, the chain is valid.
            if ( count( $found_ids ) === count( $expected_ids ) ) {
                continue;
            }

            // Chain is broken — mark the assistant message and all its
            // tool_result messages for removal.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $missing = array_diff_key( $expected_ids, $found_ids );
                error_log( '[ClawWP] Removing broken tool exchange: missing results for ' . implode( ', ', array_keys( $missing ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            $keep[ $i ] = false;
            foreach ( $found_ids as $idx ) {
                $keep[ $idx ] = false;
            }
            // Also mark any orphaned tool messages in the range.
            for ( $k = $i + 1; $k < $j; $k++ ) {
                $keep[ $k ] = false;
            }
        }

        // Also strip any standalone tool messages not preceded by an assistant
        // message with tool_calls (orphaned from a completely missing assistant).
        for ( $i = 0; $i < $count; $i++ ) {
            if ( ! $keep[ $i ] ) {
                continue;
            }
            if ( 'tool' !== $messages[ $i ]['role'] ) {
                continue;
            }
            // Walk back to find the preceding non-tool message.
            $prev = $i - 1;
            while ( $prev >= 0 && ! $keep[ $prev ] ) {
                $prev--;
            }
            if ( $prev < 0
                || 'assistant' !== $messages[ $prev ]['role']
                || empty( $messages[ $prev ]['tool_calls'] )
            ) {
                $keep[ $i ] = false;
            }
        }

        $validated = array();
        for ( $i = 0; $i < $count; $i++ ) {
            if ( $keep[ $i ] ) {
                $validated[] = $messages[ $i ];
            }
        }

        return $validated;
    }

    /**
     * Get conversation list for a user.
     *
     * @param int    $user_id
     * @param string $channel Optional filter by channel.
     * @param int    $limit
     * @param int    $offset
     * @return array
     */
    public function list_conversations( $user_id, $channel = '', $limit = 20, $offset = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'clawwp_conversations';

        // Sanitize pagination parameters.
        $limit  = max( 1, min( (int) $limit, 100 ) );
        $offset = max( 0, (int) $offset );

        // Build a single prepare call to avoid double-prepare issues.
        $args = array( $user_id );
        $channel_clause = '';
        if ( ! empty( $channel ) ) {
            $channel_clause = ' AND channel = %s';
            $args[]         = $channel;
        }

        $args[] = $limit;
        $args[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}clawwp_messages WHERE conversation_id = c.id) as message_count,
                    (SELECT content FROM {$wpdb->prefix}clawwp_messages WHERE conversation_id = c.id AND role = 'user' ORDER BY created_at ASC LIMIT 1) as first_message
             FROM {$table} c
             WHERE user_id = %d{$channel_clause}
             ORDER BY c.updated_at DESC
             LIMIT %d OFFSET %d",
            $args
        ), ARRAY_A );
    }

    /**
     * Start a new conversation (clear context for a channel chat).
     *
     * @param int    $user_id
     * @param string $channel
     * @param string $channel_chat_id
     * @return int New conversation ID.
     */
    public function start_new( $user_id, $channel, $channel_chat_id ) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'clawwp_conversations',
            array(
                'user_id'         => $user_id,
                'channel'         => $channel,
                'channel_chat_id' => $channel_chat_id,
                'created_at'      => current_time( 'mysql', true ),
                'updated_at'      => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Delete expired conversations (free tier cleanup).
     */
    public function prune_expired() {
        global $wpdb;

        $retention_days = (int) ClawWP::get_option( 'conversation_retention_days', self::FREE_RETENTION_DAYS );
        if ( $retention_days <= 0 ) {
            return; // Unlimited retention (Pro).
        }

        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

        // Get expired conversation IDs.
        $expired_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}clawwp_conversations WHERE updated_at < %s",
            $cutoff
        ) );

        if ( empty( $expired_ids ) ) {
            return;
        }

        $placeholders = implode( ',', array_fill( 0, count( $expired_ids ), '%d' ) );

        // Delete messages first, then conversations.
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}clawwp_messages WHERE conversation_id IN ({$placeholders})",
            $expired_ids
        ) );
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}clawwp_conversations WHERE id IN ({$placeholders})",
            $expired_ids
        ) );
    }

    /**
     * Get total message count for a conversation.
     *
     * @param int $conversation_id
     * @return int
     */
    public function get_message_count( $conversation_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}clawwp_messages WHERE conversation_id = %d",
            $conversation_id
        ) );
    }
}
