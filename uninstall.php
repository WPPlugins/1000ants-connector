<?php
/**
 * Removing the plugin removes what it stored: its redirects, its robots.txt
 * lines and the undo it kept for a schema change. The SEO plugin's fields
 * and settings are the SEO plugin's and stay as they are.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'thants_connector_redirects' );
delete_option( 'thants_connector_robots_lines' );
delete_post_meta_by_key( '_thants_schema_undo' );
