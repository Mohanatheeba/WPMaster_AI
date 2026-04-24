<?php
/**
 * Posts tool — create, edit, list, schedule, and delete posts.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Tool_Posts extends ClawWP_Tool {

    public function get_name() {
        return 'manage_posts';
    }

    public function get_description() {
        return 'Create, edit, list, search, schedule, or delete WordPress posts. Use action parameter to specify the operation.';
    }

    public function get_parameters() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'action' => array(
                    'type'        => 'string',
                    'enum'        => array( 'create', 'edit', 'list', 'search', 'schedule', 'delete', 'get' ),
                    'description' => 'The operation to perform.',
                ),
                'post_id' => array(
                    'type'        => 'integer',
                    'description' => 'Post ID (required for edit, delete, get).',
                ),
                'title' => array(
                    'type'        => 'string',
                    'description' => 'Post title.',
                ),
                'content' => array(
                    'type'        => 'string',
                    'description' => 'Post content (HTML).',
                ),
                'excerpt' => array(
                    'type'        => 'string',
                    'description' => 'Post excerpt.',
                ),
                'status' => array(
                    'type'        => 'string',
                    'enum'        => array( 'draft', 'publish', 'pending', 'private', 'future' ),
                    'description' => 'Post status. Default: draft.',
                ),
                'categories' => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Category names to assign.',
                ),
                'tags' => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Tag names to assign.',
                ),
                'date' => array(
                    'type'        => 'string',
                    'description' => 'Publication date (ISO 8601 format, for scheduling).',
                ),
                'search' => array(
                    'type'        => 'string',
                    'description' => 'Search query (for list/search action).',
                ),
                'limit' => array(
                    'type'        => 'integer',
                    'description' => 'Number of posts to return (default 10, max 50).',
                ),
                'post_status_filter' => array(
                    'type'        => 'string',
                    'enum'        => array( 'any', 'publish', 'draft', 'pending', 'private', 'trash' ),
                    'description' => 'Filter posts by status (for list action). Default: any.',
                ),
            ),
            'required' => array( 'action' ),
        );
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function execute( array $params ) {
        $action = $params['action'];

        switch ( $action ) {
            case 'create':
                return $this->create_post( $params );
            case 'edit':
                return $this->edit_post( $params );
            case 'list':
            case 'search':
                return $this->list_posts( $params );
            case 'schedule':
                return $this->schedule_post( $params );
            case 'delete':
                return $this->delete_post( $params );
            case 'get':
                return $this->get_post( $params );
            default:
                return array( 'error' => 'Unknown action: ' . $action );
        }
    }

    /** @var array Allowed post statuses. */
    private static $allowed_statuses = array( 'draft', 'publish', 'pending', 'private', 'future' );

    private function create_post( $params ) {
        // Force draft status for POC to ensure HITL workflow.
        $status = 'draft';

        $post_data = array(
            'post_title'   => sanitize_text_field( $params['title'] ?? 'Untitled' ),
            'post_content' => wp_kses_post( $params['content'] ?? '' ),
            'post_excerpt' => sanitize_text_field( $params['excerpt'] ?? '' ),
            'post_status'  => $status,
            'post_type'    => 'post',
        );

        $post_id = wp_insert_post( $post_data, true );
        if ( is_wp_error( $post_id ) ) {
            return array( 'error' => $post_id->get_error_message() );
        }

        // Assign categories.
        if ( ! empty( $params['categories'] ) && is_array( $params['categories'] ) ) {
            $categories = array_map( 'sanitize_text_field', $params['categories'] );
            wp_set_post_terms( $post_id, $categories, 'category' );
        }

        // Assign tags.
        if ( ! empty( $params['tags'] ) && is_array( $params['tags'] ) ) {
            $tags = array_map( 'sanitize_text_field', $params['tags'] );
            wp_set_post_tags( $post_id, $tags );
        }

        return array(
            'success' => true,
            'post_id' => $post_id,
            'title'   => $post_data['post_title'],
            'status'  => $post_data['post_status'],
            'url'     => get_permalink( $post_id ),
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
        );
    }

    private function edit_post( $params ) {
        if ( empty( $params['post_id'] ) ) {
            return array( 'error' => 'post_id is required for edit action.' );
        }

        $post = get_post( $params['post_id'] );
        if ( ! $post ) {
            return array( 'error' => 'Post not found.' );
        }

        $update = array( 'ID' => $params['post_id'] );

        if ( isset( $params['title'] ) )   $update['post_title']   = sanitize_text_field( $params['title'] );
        if ( isset( $params['content'] ) ) $update['post_content'] = wp_kses_post( $params['content'] );
        if ( isset( $params['excerpt'] ) ) $update['post_excerpt'] = sanitize_text_field( $params['excerpt'] );
        if ( isset( $params['status'] ) ) {
            $update['post_status'] = in_array( $params['status'], self::$allowed_statuses, true ) ? $params['status'] : $post->post_status;
        }

        $result = wp_update_post( $update, true );
        if ( is_wp_error( $result ) ) {
            return array( 'error' => $result->get_error_message() );
        }

        if ( ! empty( $params['categories'] ) && is_array( $params['categories'] ) ) {
            $categories = array_map( 'sanitize_text_field', $params['categories'] );
            wp_set_post_terms( $params['post_id'], $categories, 'category' );
        }
        if ( ! empty( $params['tags'] ) && is_array( $params['tags'] ) ) {
            $tags = array_map( 'sanitize_text_field', $params['tags'] );
            wp_set_post_tags( $params['post_id'], $tags );
        }

        return array(
            'success' => true,
            'post_id' => $params['post_id'],
            'message' => 'Post updated successfully.',
            'url'     => get_permalink( $params['post_id'] ),
        );
    }

    private function list_posts( $params ) {
        $limit = min( (int) ( $params['limit'] ?? 10 ), 50 );

        $args = array(
            'post_type'      => 'post',
            'posts_per_page' => $limit,
            'post_status'    => $params['post_status_filter'] ?? 'any',
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( ! empty( $params['search'] ) ) {
            $args['s'] = $params['search'];
        }

        $query = new WP_Query( $args );
        $posts = array();

        foreach ( $query->posts as $post ) {
            $posts[] = array(
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'status'     => $post->post_status,
                'date'       => $post->post_date,
                'excerpt'    => wp_trim_words( $post->post_content, 25 ),
                'url'        => get_permalink( $post ),
                'categories' => wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) ),
            );
        }

        return array(
            'total' => $query->found_posts,
            'count' => count( $posts ),
            'posts' => $posts,
        );
    }

    private function schedule_post( $params ) {
        if ( empty( $params['date'] ) ) {
            return array( 'error' => 'date is required for schedule action (ISO 8601 format).' );
        }

        // Validate date to prevent unexpected behavior.
        $timestamp = strtotime( sanitize_text_field( $params['date'] ) );
        if ( false === $timestamp || $timestamp <= 0 ) {
            return array( 'error' => 'Invalid date format. Please use ISO 8601 (e.g., 2025-12-25T10:00:00).' );
        }

        // Ensure the date is in the future.
        if ( $timestamp <= time() ) {
            return array( 'error' => 'Scheduled date must be in the future.' );
        }

        $post_data = array(
            'post_title'   => sanitize_text_field( $params['title'] ?? 'Untitled' ),
            'post_content' => wp_kses_post( $params['content'] ?? '' ),
            'post_status'  => 'future',
            'post_date'    => gmdate( 'Y-m-d H:i:s', $timestamp ),
            'post_type'    => 'post',
        );

        $post_id = wp_insert_post( $post_data, true );
        if ( is_wp_error( $post_id ) ) {
            return array( 'error' => $post_id->get_error_message() );
        }

        return array(
            'success'        => true,
            'post_id'        => $post_id,
            'title'          => $post_data['post_title'],
            'scheduled_date' => $post_data['post_date'],
            'url'            => get_permalink( $post_id ),
        );
    }

    private function delete_post( $params ) {
        if ( empty( $params['post_id'] ) ) {
            return array( 'error' => 'post_id is required for delete action.' );
        }

        $post = get_post( $params['post_id'] );
        if ( ! $post ) {
            return array( 'error' => 'Post not found.' );
        }

        // Move to trash (not permanent delete).
        $result = wp_trash_post( $params['post_id'] );
        if ( ! $result ) {
            return array( 'error' => 'Failed to delete post.' );
        }

        return array(
            'success' => true,
            'message' => sprintf( 'Post "%s" moved to trash.', $post->post_title ),
        );
    }

    private function get_post( $params ) {
        if ( empty( $params['post_id'] ) ) {
            return array( 'error' => 'post_id is required for get action.' );
        }

        $post = get_post( $params['post_id'] );
        if ( ! $post ) {
            return array( 'error' => 'Post not found.' );
        }

        return array(
            'id'         => $post->ID,
            'title'      => $post->post_title,
            'content'    => $post->post_content,
            'excerpt'    => $post->post_excerpt,
            'status'     => $post->post_status,
            'date'       => $post->post_date,
            'modified'   => $post->post_modified,
            'author'     => get_the_author_meta( 'display_name', $post->post_author ),
            'url'        => get_permalink( $post ),
            'categories' => wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) ),
            'tags'       => wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) ),
        );
    }
}
