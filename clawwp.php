<?php
/**
 * Plugin Name: WPMaster AI
 * Plugin URI: https://hifriendbot.com/wpmaster-ai
 * Description: The Agentic WordPress Webmaster Gateway. Connects your site to your external WPMaster AI Brain on Render for automated site health, content, and design management.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: WPMaster AI Team
 * Author URI: https://hifriendbot.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clawwp
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CLAWWP_VERSION', '1.0.0' );
define( 'CLAWWP_PLUGIN_FILE', __FILE__ );
define( 'CLAWWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLAWWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CLAWWP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load core class explicitly (not prefixed with ClawWP_).
 */
require_once CLAWWP_PLUGIN_DIR . 'includes/class-clawwp.php';

/**
 * Autoloader for ClawWP classes.
 */
spl_autoload_register( function ( $class ) {
    // Skip if not a ClawWP class.
    if ( 'ClawWP' === $class ) {
        return; // Already loaded above.
    }

    $prefix = 'ClawWP_';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    $relative = substr( $class, strlen( $prefix ) );
    $filename = 'class-clawwp-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';

    // Map classes that share a file with another class.
    $shared_files = array(
        'class-clawwp-ai-response.php' => 'class-clawwp-ai-provider.php',
    );
    if ( isset( $shared_files[ $filename ] ) ) {
        $filename = $shared_files[ $filename ];
    }

    $directories = array(
        CLAWWP_PLUGIN_DIR . 'includes/',
        CLAWWP_PLUGIN_DIR . 'channels/',
        CLAWWP_PLUGIN_DIR . 'tools/',
        CLAWWP_PLUGIN_DIR . 'admin/',
    );

    foreach ( $directories as $directory ) {
        $filepath = $directory . $filename;
        if ( file_exists( $filepath ) ) {
            require_once $filepath;
            return;
        }
    }
} );

/**
 * Plugin activation.
 */
function clawwp_activate() {
    clawwp_create_tables();
    add_option( 'clawwp_version', CLAWWP_VERSION );
    add_option( 'clawwp_activation_time', time() );
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'clawwp_activate' );

/**
 * Plugin deactivation.
 */
function clawwp_deactivate() {
    wp_clear_scheduled_hook( 'clawwp_daily_cleanup' );
    wp_clear_scheduled_hook( 'clawwp_cost_alert_check' );
    wp_clear_scheduled_hook( 'clawwp_license_revalidate' );
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'clawwp_deactivate' );

/**
 * Create custom database tables.
 */
function clawwp_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = array();

    $sql[] = "CREATE TABLE {$wpdb->prefix}clawwp_conversations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        channel VARCHAR(32) NOT NULL,
        channel_chat_id VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_channel (user_id, channel)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE {$wpdb->prefix}clawwp_messages (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        conversation_id BIGINT UNSIGNED NOT NULL,
        role ENUM('user', 'assistant', 'system', 'tool') NOT NULL,
        content LONGTEXT NOT NULL,
        tool_calls JSON DEFAULT NULL,
        tool_results JSON DEFAULT NULL,
        tokens_in INT UNSIGNED DEFAULT 0,
        tokens_out INT UNSIGNED DEFAULT 0,
        model VARCHAR(64) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conversation (conversation_id),
        INDEX idx_created (created_at)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE {$wpdb->prefix}clawwp_memories (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        fact TEXT NOT NULL,
        category VARCHAR(64) DEFAULT 'general',
        importance FLOAT DEFAULT 0.5,
        last_accessed DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_importance (importance)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE {$wpdb->prefix}clawwp_pairings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        channel VARCHAR(32) NOT NULL,
        channel_user_id VARCHAR(255) NOT NULL,
        channel_chat_id VARCHAR(255) NOT NULL,
        paired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY idx_channel_user (channel, channel_user_id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE {$wpdb->prefix}clawwp_audit_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        channel VARCHAR(32) NOT NULL DEFAULT 'system',
        action VARCHAR(128) NOT NULL,
        details JSON DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_date (user_id, created_at)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE {$wpdb->prefix}clawwp_usage (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        model VARCHAR(64) NOT NULL,
        tokens_in INT UNSIGNED NOT NULL,
        tokens_out INT UNSIGNED NOT NULL,
        estimated_cost DECIMAL(10,6) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_date (user_id, created_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ( $sql as $query ) {
        dbDelta( $query );
    }
}

/**
 * Run upgrade routines when plugin version changes.
 */
function clawwp_maybe_upgrade() {
    $stored_version = get_option( 'clawwp_version', '0' );
    if ( version_compare( $stored_version, CLAWWP_VERSION, '<' ) ) {
        clawwp_create_tables(); // Safe to re-run — uses dbDelta.
        ClawWP::migrate_encryption();
        update_option( 'clawwp_version', CLAWWP_VERSION );
    }
}

/**
 * Initialize the plugin.
 */
function clawwp_init() {
    clawwp_maybe_upgrade();
    $plugin = new ClawWP();
    $plugin->init();
}
add_action( 'plugins_loaded', 'clawwp_init' );
