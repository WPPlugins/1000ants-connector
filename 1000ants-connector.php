<?php
/**
 * Plugin Name:       1000ants Connector
 * Description:       Lets 1000ants apply prepared fixes directly on your site. Nothing changes without your approval in 1000ants first, and every change is recorded and can be undone.
 * Version:           1.2.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            1000ants
 * Author URI:        https://1000ants.ai
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       1000ants-connector
 *
 * WHY THIS PLUGIN EXISTS. Rank Math and Yoast keep the SEO title and meta
 * description out of the WordPress REST API for writing, so an editor that
 * works through the REST API can prepare a better title but never set it.
 * This plugin does four things and nothing else:
 *
 *   1. It registers the SEO plugin's OWN fields for the REST API, so an
 *      application password with the right to edit a page can write them —
 *      the same check WordPress applies to the page's content.
 *   2. It keeps a short list of redirects for addresses that are gone. A
 *      redirect fires only on a request WordPress would answer with 404, so it
 *      can never take over a page that exists.
 *   3. It appends robots.txt rules to the virtual robots.txt WordPress serves.
 *   4. It sets a page's schema type in Rank Math (an article, or none), or
 *      the default a post type's pages get, with the state before kept for
 *      the undo.
 *
 * It sends nothing anywhere, loads nothing on the front end but the two hooks
 * above, and stores two options that uninstall.php removes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'THANTS_CONNECTOR_VERSION', '1.2.1' );
define( 'THANTS_REST_NAMESPACE', '1000ants/v1' );
define( 'THANTS_REDIRECTS_OPTION', 'thants_connector_redirects' );
define( 'THANTS_ROBOTS_OPTION', 'thants_connector_robots_lines' );
define( 'THANTS_MAX_REDIRECTS', 1000 );
define( 'THANTS_MAX_ROBOTS_LINES', 50 );

require_once __DIR__ . '/includes/fields.php';
require_once __DIR__ . '/includes/redirects.php';
require_once __DIR__ . '/includes/robots.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/rest.php';

add_action( 'init', 'thants_register_seo_fields', 99 );
add_action( 'rest_api_init', 'thants_register_routes' );
add_action( 'rest_api_init', 'thants_register_schema_routes' );
add_action( 'rest_api_init', 'thants_register_schema_default_routes' );
add_action( 'template_redirect', 'thants_maybe_redirect', 1 );
add_filter( 'robots_txt', 'thants_robots_txt', 100, 2 );
