<?php
/**
 * SEO tool — generate and manage meta titles, descriptions, and schema markup.
 *
 * Works with Yoast SEO, Rank Math, or falls back to native WordPress meta.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Tool_SEO extends ClawWP_Tool {

    public function get_name() {
        return 'manage_seo';
    }

    public function get_description() {
        return 'Get or update SEO meta for posts and pages: meta title, meta description, focus keyword. Works with Yoast SEO, Rank Math, or stores in custom fields.';
    }

    public function get_parameters() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'action' => array(
                    'type' => 'string',
                    'enum' => array( 'get', 'update', 'audit' ),
                    'description' => 'get: read current SEO meta. update: set SEO meta. audit: check posts missing SEO meta.',
                ),
                'post_id' => array(
                    'type' => 'integer',
                    'description' => 'Post/page ID.',
                ),
                'meta_title' => array(
                    'type' => 'string',
                    'description' => 'SEO meta title (50-60 characters recommended).',
                ),
                'meta_description' => array(
                    'type' => 'string',
                    'description' => 'SEO meta description (150-160 characters recommended).',
                ),
                'focus_keyword' => array(
                    'type' => 'string',
                    'description' => 'Primary focus keyword.',
                ),
                'limit' => array(
                    'type' => 'integer',
                    'description' => 'Number of posts to audit (default 10).',
                ),
            ),
            'required' => array( 'action' ),
        );
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function execute( array $params ) {
        switch ( $params['action'] ) {
            case 'get':
                if ( empty( $params['post_id'] ) ) return array( 'error' => 'post_id required.' );
                return $this->get_seo( $params['post_id'] );

            case 'update':
                if ( empty( $params['post_id'] ) ) return array( 'error' => 'post_id required.' );
                return $this->update_seo( $params );

            case 'audit':
                return $this->audit_seo( $params );

            default:
                return array( 'error' => 'Unknown action.' );
        }
    }

    private function get_seo( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) return array( 'error' => 'Post not found.' );

        $seo = array(
            'post_id'    => $post_id,
            'post_title' => $post->post_title,
            'post_url'   => get_permalink( $post_id ),
            'seo_plugin' => $this->detect_seo_plugin(),
        );

        // Try Yoast SEO first.
        if ( $this->has_yoast() ) {
            $seo['meta_title']       = get_post_meta( $post_id, '_yoast_wpseo_title', true );
            $seo['meta_description'] = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
            $seo['focus_keyword']    = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
        }
        // Try Rank Math.
        elseif ( $this->has_rank_math() ) {
            $seo['meta_title']       = get_post_meta( $post_id, 'rank_math_title', true );
            $seo['meta_description'] = get_post_meta( $post_id, 'rank_math_description', true );
            $seo['focus_keyword']    = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
        }
        // Fallback to ClawWP custom fields.
        else {
            $seo['meta_title']       = get_post_meta( $post_id, '_clawwp_meta_title', true );
            $seo['meta_description'] = get_post_meta( $post_id, '_clawwp_meta_description', true );
            $seo['focus_keyword']    = get_post_meta( $post_id, '_clawwp_focus_keyword', true );
        }

        // Character counts.
        $seo['title_length']       = mb_strlen( $seo['meta_title'] );
        $seo['description_length'] = mb_strlen( $seo['meta_description'] );

        return $seo;
    }

    private function update_seo( $params ) {
        $post_id = $params['post_id'];
        $post = get_post( $post_id );
        if ( ! $post ) return array( 'error' => 'Post not found.' );

        $updated = array();

        if ( $this->has_yoast() ) {
            if ( isset( $params['meta_title'] ) ) {
                update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $params['meta_title'] ) );
                $updated[] = 'meta_title';
            }
            if ( isset( $params['meta_description'] ) ) {
                update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $params['meta_description'] ) );
                $updated[] = 'meta_description';
            }
            if ( isset( $params['focus_keyword'] ) ) {
                update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $params['focus_keyword'] ) );
                $updated[] = 'focus_keyword';
            }
        } elseif ( $this->has_rank_math() ) {
            if ( isset( $params['meta_title'] ) ) {
                update_post_meta( $post_id, 'rank_math_title', sanitize_text_field( $params['meta_title'] ) );
                $updated[] = 'meta_title';
            }
            if ( isset( $params['meta_description'] ) ) {
                update_post_meta( $post_id, 'rank_math_description', sanitize_text_field( $params['meta_description'] ) );
                $updated[] = 'meta_description';
            }
            if ( isset( $params['focus_keyword'] ) ) {
                update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $params['focus_keyword'] ) );
                $updated[] = 'focus_keyword';
            }
        } else {
            if ( isset( $params['meta_title'] ) ) {
                update_post_meta( $post_id, '_clawwp_meta_title', sanitize_text_field( $params['meta_title'] ) );
                $updated[] = 'meta_title';
            }
            if ( isset( $params['meta_description'] ) ) {
                update_post_meta( $post_id, '_clawwp_meta_description', sanitize_text_field( $params['meta_description'] ) );
                $updated[] = 'meta_description';
            }
            if ( isset( $params['focus_keyword'] ) ) {
                update_post_meta( $post_id, '_clawwp_focus_keyword', sanitize_text_field( $params['focus_keyword'] ) );
                $updated[] = 'focus_keyword';
            }
        }

        return array( 'success' => true, 'updated_fields' => $updated, 'seo_plugin' => $this->detect_seo_plugin() );
    }

    private function audit_seo( $params ) {
        $limit = min( (int) ( $params['limit'] ?? 10 ), 50 );
        $posts = get_posts( array(
            'post_type'      => array( 'post', 'page' ),
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $issues = array();
        foreach ( $posts as $post ) {
            $seo = $this->get_seo( $post->ID );
            $post_issues = array();
            if ( empty( $seo['meta_title'] ) ) $post_issues[] = 'missing_meta_title';
            if ( empty( $seo['meta_description'] ) ) $post_issues[] = 'missing_meta_description';
            if ( ! empty( $seo['meta_title'] ) && $seo['title_length'] > 60 ) $post_issues[] = 'title_too_long';
            if ( ! empty( $seo['meta_description'] ) && $seo['description_length'] > 160 ) $post_issues[] = 'description_too_long';
            if ( empty( $seo['focus_keyword'] ) ) $post_issues[] = 'missing_focus_keyword';

            if ( ! empty( $post_issues ) ) {
                $issues[] = array(
                    'post_id' => $post->ID,
                    'title'   => $post->post_title,
                    'url'     => get_permalink( $post ),
                    'issues'  => $post_issues,
                );
            }
        }

        return array(
            'audited'    => count( $posts ),
            'with_issues' => count( $issues ),
            'issues'     => $issues,
            'seo_plugin' => $this->detect_seo_plugin(),
        );
    }

    private function has_yoast() {
        return defined( 'WPSEO_VERSION' );
    }

    private function has_rank_math() {
        return class_exists( 'RankMath' );
    }

    private function detect_seo_plugin() {
        if ( $this->has_yoast() ) return 'Yoast SEO';
        if ( $this->has_rank_math() ) return 'Rank Math';
        return 'ClawWP (built-in)';
    }
}
