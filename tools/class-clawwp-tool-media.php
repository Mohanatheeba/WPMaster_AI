<?php
/**
 * Media tool — list, search, and manage the WordPress media library.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Tool_Media extends ClawWP_Tool {

    public function get_name() {
        return 'manage_media';
    }

    public function get_description() {
        return 'List, search, and get details about items in the WordPress media library. Can also update alt text and captions.';
    }

    public function get_parameters() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'action' => array(
                    'type' => 'string',
                    'enum' => array( 'list', 'search', 'get', 'update', 'delete' ),
                    'description' => 'The operation to perform.',
                ),
                'attachment_id' => array(
                    'type' => 'integer',
                    'description' => 'Attachment ID (for get, update, delete).',
                ),
                'search' => array(
                    'type' => 'string',
                    'description' => 'Search query.',
                ),
                'mime_type' => array(
                    'type' => 'string',
                    'description' => 'Filter by MIME type (e.g., image, video, application/pdf).',
                ),
                'alt_text' => array(
                    'type' => 'string',
                    'description' => 'Alt text to set (for update).',
                ),
                'caption' => array(
                    'type' => 'string',
                    'description' => 'Caption to set (for update).',
                ),
                'title' => array(
                    'type' => 'string',
                    'description' => 'Title to set (for update).',
                ),
                'limit' => array(
                    'type' => 'integer',
                    'description' => 'Number of items to return (default 10).',
                ),
            ),
            'required' => array( 'action' ),
        );
    }

    public function get_required_capability() {
        return 'upload_files';
    }

    public function execute( array $params ) {
        switch ( $params['action'] ) {
            case 'list':
            case 'search':
                $args = array(
                    'post_type'      => 'attachment',
                    'post_status'    => 'inherit',
                    'posts_per_page' => min( (int) ( $params['limit'] ?? 10 ), 50 ),
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                );
                if ( ! empty( $params['search'] ) ) $args['s'] = $params['search'];
                if ( ! empty( $params['mime_type'] ) ) $args['post_mime_type'] = sanitize_mime_type( $params['mime_type'] );
                $query = new WP_Query( $args );
                $items = array();
                foreach ( $query->posts as $att ) {
                    $items[] = $this->format_attachment( $att );
                }
                return array( 'total' => $query->found_posts, 'items' => $items );

            case 'get':
                if ( empty( $params['attachment_id'] ) ) return array( 'error' => 'attachment_id required.' );
                $att = get_post( $params['attachment_id'] );
                if ( ! $att || 'attachment' !== $att->post_type ) return array( 'error' => 'Attachment not found.' );
                return $this->format_attachment( $att, true );

            case 'update':
                if ( empty( $params['attachment_id'] ) ) return array( 'error' => 'attachment_id required.' );
                $att = get_post( $params['attachment_id'] );
                if ( ! $att || 'attachment' !== $att->post_type ) return array( 'error' => 'Attachment not found.' );
                if ( isset( $params['alt_text'] ) ) {
                    update_post_meta( $params['attachment_id'], '_wp_attachment_alt_text', sanitize_text_field( $params['alt_text'] ) );
                }
                $update = array( 'ID' => $params['attachment_id'] );
                if ( isset( $params['caption'] ) ) $update['post_excerpt'] = sanitize_text_field( $params['caption'] );
                if ( isset( $params['title'] ) )   $update['post_title']   = sanitize_text_field( $params['title'] );
                if ( count( $update ) > 1 ) wp_update_post( $update );
                return array( 'success' => true, 'message' => 'Attachment updated.' );

            case 'delete':
                if ( empty( $params['attachment_id'] ) ) return array( 'error' => 'attachment_id required.' );
                // Move to trash instead of permanent deletion (force_delete = false).
                $result = wp_delete_attachment( $params['attachment_id'], false );
                if ( ! $result ) return array( 'error' => 'Failed to delete attachment.' );
                return array( 'success' => true, 'message' => 'Attachment moved to trash.' );

            default:
                return array( 'error' => 'Unknown action.' );
        }
    }

    private function format_attachment( $att, $detailed = false ) {
        $data = array(
            'id'        => $att->ID,
            'title'     => $att->post_title,
            'filename'  => basename( get_attached_file( $att->ID ) ),
            'mime_type' => $att->post_mime_type,
            'url'       => wp_get_attachment_url( $att->ID ),
            'alt_text'  => get_post_meta( $att->ID, '_wp_attachment_alt_text', true ),
            'caption'   => $att->post_excerpt,
            'date'      => $att->post_date,
        );

        if ( $detailed ) {
            $meta = wp_get_attachment_metadata( $att->ID );
            if ( $meta ) {
                $data['width']  = $meta['width'] ?? null;
                $data['height'] = $meta['height'] ?? null;

                // Safely get file size — validate path is within uploads directory.
                $file_path = get_attached_file( $att->ID );
                if ( $file_path && file_exists( $file_path ) ) {
                    $upload_dir = wp_upload_dir();
                    $real_path  = realpath( $file_path );
                    $real_base  = realpath( $upload_dir['basedir'] );

                    // Ensure the file is within the uploads directory (prevent path traversal).
                    if ( $real_path && $real_base && 0 === strpos( $real_path, $real_base ) ) {
                        $data['file_size'] = size_format( filesize( $real_path ) );
                    }
                }
            }
        }

        return $data;
    }
}
