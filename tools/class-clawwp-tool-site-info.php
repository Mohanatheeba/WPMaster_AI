<?php
/**
 * Site Info tool — get WordPress site information, stats, and health data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Tool_Site_Info extends ClawWP_Tool {

    public function get_name() {
        return 'get_site_info';
    }

    public function get_description() {
        return 'Get WordPress site information including stats, health status, active plugins, theme, recent activity, and content counts.';
    }

    public function get_parameters() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'section' => array(
                    'type' => 'string',
                    'enum' => array( 'overview', 'plugins', 'stats', 'recent_activity' ),
                    'description' => 'Which information to retrieve. Default: overview.',
                ),
            ),
            'required' => array(),
        );
    }

    public function get_required_capability() {
        return 'read';
    }

    public function execute( array $params ) {
        $section = $params['section'] ?? 'overview';

        switch ( $section ) {
            case 'overview':
                return $this->get_overview();
            case 'plugins':
                return $this->get_plugins();
            case 'stats':
                return $this->get_stats();
            case 'recent_activity':
                return $this->get_recent_activity();
            default:
                return $this->get_overview();
        }
    }

    private function get_overview() {
        $theme = wp_get_theme();
        $users = count_users();
        $post_counts = wp_count_posts();
        $page_counts = wp_count_posts( 'page' );
        $comment_counts = wp_count_comments();

        return array(
            'site_name'    => get_bloginfo( 'name' ),
            'tagline'      => get_bloginfo( 'description' ),
            'url'          => home_url(),
            'admin_email'  => get_bloginfo( 'admin_email' ),
            'wp_version'   => get_bloginfo( 'version' ),
            'php_version'  => phpversion(),
            'theme'        => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
            'timezone'     => wp_timezone_string(),
            'language'     => get_locale(),
            'multisite'    => is_multisite(),
            'users'        => $users['total_users'],
            'posts'        => array(
                'published' => $post_counts->publish,
                'draft'     => $post_counts->draft,
                'pending'   => $post_counts->pending,
                'trash'     => $post_counts->trash,
            ),
            'pages'        => array(
                'published' => $page_counts->publish,
                'draft'     => $page_counts->draft,
            ),
            'comments'     => array(
                'approved' => $comment_counts->approved,
                'pending'  => $comment_counts->moderated,
                'spam'     => $comment_counts->spam,
            ),
            'woocommerce'  => class_exists( 'WooCommerce' ),
        );
    }

    private function get_plugins() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = get_plugins();
        $active_plugins = get_option( 'active_plugins', array() );
        $plugins        = array();

        foreach ( $all_plugins as $file => $data ) {
            if ( $file === CLAWWP_PLUGIN_BASENAME ) continue;
            $plugins[] = array(
                'name'    => $data['Name'],
                'version' => $data['Version'],
                'active'  => in_array( $file, $active_plugins, true ),
                'author'  => $data['AuthorName'] ?? '',
            );
        }

        usort( $plugins, function ( $a, $b ) {
            return $b['active'] <=> $a['active'] ?: strcmp( $a['name'], $b['name'] );
        } );

        return array(
            'total'  => count( $plugins ),
            'active' => count( $active_plugins ) - 1, // Exclude ClawWP.
            'plugins' => $plugins,
        );
    }

    private function get_stats() {
        global $wpdb;

        // Database size.
        $db_size = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = %s",
                DB_NAME
            )
        );

        // Upload directory size.
        $upload_dir = wp_upload_dir();

        return array(
            'database_size'  => size_format( (int) $db_size ),
            'upload_path'    => $upload_dir['basedir'],
            'total_posts'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'post' ) ),
            'total_pages'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'page' ) ),
            'total_media'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'attachment' ) ),
            'total_comments' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            'total_users'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        );
    }

    private function get_recent_activity() {
        // Recent posts.
        $recent_posts = get_posts( array(
            'post_type'      => 'post',
            'posts_per_page' => 5,
            'post_status'    => 'any',
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ) );

        $posts = array();
        foreach ( $recent_posts as $p ) {
            $posts[] = array(
                'id'       => $p->ID,
                'title'    => $p->post_title,
                'status'   => $p->post_status,
                'modified' => $p->post_modified,
                'author'   => get_the_author_meta( 'display_name', $p->post_author ),
            );
        }

        // Recent comments.
        $recent_comments = get_comments( array(
            'number'  => 5,
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
        ) );

        $comments = array();
        foreach ( $recent_comments as $c ) {
            $comments[] = array(
                'id'      => $c->comment_ID,
                'author'  => $c->comment_author,
                'content' => wp_trim_words( $c->comment_content, 15 ),
                'post'    => get_the_title( $c->comment_post_ID ),
                'date'    => $c->comment_date,
                'status'  => wp_get_comment_status( $c ),
            );
        }

        return array(
            'recent_posts'    => $posts,
            'recent_comments' => $comments,
        );
    }
}
