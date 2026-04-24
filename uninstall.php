<?php
/**
 * ClawWP uninstall handler.
 *
 * Removes all plugin data when the plugin is deleted
 * (not just deactivated) from WordPress.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove custom tables.
$table_suffixes = array(
    'clawwp_conversations',
    'clawwp_messages',
    'clawwp_memories',
    'clawwp_pairings',
    'clawwp_audit_log',
    'clawwp_usage',
);

foreach ( $table_suffixes as $suffix ) {
    // Table name is safe: $wpdb->prefix is from WordPress core, suffix is hardcoded above.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}{$suffix}`" );
}

// Remove all plugin options.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like( 'clawwp_' ) . '%'
    )
);

// Remove user meta.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like( 'clawwp_' ) . '%'
    )
);

// Remove scheduled events.
wp_clear_scheduled_hook( 'clawwp_daily_cleanup' );
wp_clear_scheduled_hook( 'clawwp_cost_alert_check' );
wp_clear_scheduled_hook( 'clawwp_license_revalidate' );

// Remove transients (license cache, rate limits, pairing codes).
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like( '_transient_clawwp_' ) . '%',
        $wpdb->esc_like( '_transient_timeout_clawwp_' ) . '%'
    )
);
