<?php
/**
 * Comments tool — moderate, reply to, and manage WordPress comments.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Tool_Comments extends ClawWP_Tool {

    public function get_name() {
        return 'manage_comments';
    }

    public function get_description() {
        return 'List, approve, spam, trash, or reply to WordPress comments. Great for content moderation.';
    }

    public function get_parameters() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'action' => array(
                    'type' => 'string',
                    'enum' => array( 'list', 'approve', 'spam', 'trash', 'reply' ),
                    'description' => 'The operation to perform.',
                ),
                'comment_id' => array(
                    'type' => 'integer',
                    'description' => 'Comment ID (required for approve, spam, trash, reply).',
                ),
                'reply_content' => array(
                    'type' => 'string',
                    'description' => 'Reply text (for reply action).',
                ),
                'status_filter' => array(
                    'type' => 'string',
                    'enum' => array( 'all', 'hold', 'approve', 'spam', 'trash' ),
                    'description' => 'Filter by status (for list). Default: hold (pending).',
                ),
                'limit' => array(
                    'type' => 'integer',
                    'description' => 'Number of comments to return (default 10).',
                ),
            ),
            'required' => array( 'action' ),
        );
    }

    public function get_required_capability() {
        return 'moderate_comments';
    }

    public function execute( array $params ) {
        switch ( $params['action'] ) {
            case 'list':
                $args = array(
                    'status' => $params['status_filter'] ?? 'hold',
                    'number' => min( (int) ( $params['limit'] ?? 10 ), 50 ),
                    'orderby' => 'comment_date_gmt',
                    'order'   => 'DESC',
                );
                $comments = get_comments( $args );
                $result = array();
                foreach ( $comments as $c ) {
                    $result[] = array(
                        'id'      => $c->comment_ID,
                        'author'  => $c->comment_author,
                        'email'   => $c->comment_author_email,
                        'content' => wp_trim_words( $c->comment_content, 30 ),
                        'date'    => $c->comment_date,
                        'status'  => wp_get_comment_status( $c ),
                        'post'    => get_the_title( $c->comment_post_ID ),
                        'post_id' => $c->comment_post_ID,
                    );
                }
                $counts = wp_count_comments();
                return array(
                    'comments' => $result,
                    'counts'   => array(
                        'pending'  => $counts->moderated,
                        'approved' => $counts->approved,
                        'spam'     => $counts->spam,
                        'trash'    => $counts->trash,
                        'total'    => $counts->total_comments,
                    ),
                );

            case 'approve':
                if ( empty( $params['comment_id'] ) ) return array( 'error' => 'comment_id required.' );
                wp_set_comment_status( $params['comment_id'], 'approve' );
                return array( 'success' => true, 'message' => 'Comment approved.' );

            case 'spam':
                if ( empty( $params['comment_id'] ) ) return array( 'error' => 'comment_id required.' );
                wp_spam_comment( $params['comment_id'] );
                return array( 'success' => true, 'message' => 'Comment marked as spam.' );

            case 'trash':
                if ( empty( $params['comment_id'] ) ) return array( 'error' => 'comment_id required.' );
                wp_trash_comment( $params['comment_id'] );
                return array( 'success' => true, 'message' => 'Comment trashed.' );

            case 'reply':
                if ( empty( $params['comment_id'] ) ) return array( 'error' => 'comment_id required.' );
                if ( empty( $params['reply_content'] ) ) return array( 'error' => 'reply_content required.' );
                $parent = get_comment( $params['comment_id'] );
                if ( ! $parent ) return array( 'error' => 'Parent comment not found.' );
                $reply_id = wp_insert_comment( array(
                    'comment_post_ID'  => $parent->comment_post_ID,
                    'comment_content'  => sanitize_text_field( $params['reply_content'] ),
                    'comment_parent'   => $params['comment_id'],
                    'comment_approved' => 1,
                    'user_id'          => get_current_user_id(),
                    'comment_author'   => wp_get_current_user()->display_name,
                    'comment_author_email' => wp_get_current_user()->user_email,
                ) );
                return array( 'success' => true, 'reply_id' => $reply_id, 'message' => 'Reply posted.' );

            default:
                return array( 'error' => 'Unknown action.' );
        }
    }
}
