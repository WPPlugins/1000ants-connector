<?php
/**
 * Redirects for addresses that are gone.
 *
 * Stored as one option (not autoloaded): `id => {from, to, code, created}`.
 * `from` is a path on this site, `to` a path or an address on this site.
 * A redirect fires ONLY when WordPress would answer 404 — a page that exists
 * always wins — and only to this site (`wp_safe_redirect`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** "/Old-Page/?x=1" → "/old-page" — the form two paths are compared in. */
function thants_normalize_path( $path ) {
	$path = (string) wp_parse_url( (string) $path, PHP_URL_PATH );
	$path = '/' . ltrim( rawurldecode( $path ), '/' );
	$path = untrailingslashit( $path );
	return '' === $path ? '/' : strtolower( $path );
}

/**
 * A request path relative to the site: on a site installed at
 * example.com/blog, "/blog/old" is the redirect stored as "/old" — the path
 * `to` is built from with home_url().
 */
function thants_site_path( $uri ) {
	$path = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );
	$base = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	if ( '' !== $base && ( 0 === stripos( $path, $base . '/' ) || 0 === strcasecmp( $path, $base ) ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	return '' === $path ? '/' : $path;
}

function thants_get_redirects() {
	$all = get_option( THANTS_REDIRECTS_OPTION, array() );
	return is_array( $all ) ? $all : array();
}

/**
 * Validate and store one redirect. Returns the stored row or a WP_Error.
 *
 * @param string $from A path on this site.
 * @param string $to   A path or an address on this site.
 * @param int    $code 301, 302 or 410.
 */
function thants_add_redirect( $from, $to, $code = 301 ) {
	$code = (int) $code;
	if ( ! in_array( $code, array( 301, 302, 410 ), true ) ) {
		return new WP_Error( 'thants_bad_code', 'code must be 301, 302 or 410', array( 'status' => 400 ) );
	}
	$from = (string) $from;
	if ( '' === $from || '/' !== $from[0] || 0 === strpos( $from, '//' ) ) {
		return new WP_Error( 'thants_bad_from', 'from must be a path on this site', array( 'status' => 400 ) );
	}
	// Relative to the site: on example.com/blog both "/old" and "/blog/old" name one page.
	$from_key = thants_normalize_path( thants_site_path( $from ) );
	if ( '/' === $from_key ) {
		return new WP_Error( 'thants_bad_from', 'the homepage cannot be redirected', array( 'status' => 400 ) );
	}
	$to = (string) $to;
	if ( 410 !== $code ) {
		$target = ( '' !== $to && '/' === $to[0] && 0 !== strpos( $to, '//' ) ) ? home_url( $to ) : $to;
		$home   = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( wp_parse_url( $target, PHP_URL_HOST ) !== $home ) {
			return new WP_Error( 'thants_bad_to', 'to must be on this site', array( 'status' => 400 ) );
		}
		if ( thants_normalize_path( thants_site_path( $target ) ) === $from_key ) {
			return new WP_Error( 'thants_loop', 'a redirect to itself', array( 'status' => 400 ) );
		}
		$to = esc_url_raw( $target );
	} else {
		$to = '';
	}
	$all = thants_get_redirects();
	foreach ( $all as $row ) {
		if ( thants_normalize_path( $row['from'] ) === $from_key ) {
			return new WP_Error( 'thants_exists', 'a redirect for this path exists', array( 'status' => 409, 'id' => $row['id'] ) );
		}
	}
	if ( count( $all ) >= THANTS_MAX_REDIRECTS ) {
		return new WP_Error( 'thants_full', 'too many redirects', array( 'status' => 409 ) );
	}
	$id         = wp_generate_uuid4();
	$row        = array(
		'id'      => $id,
		'from'    => $from_key,
		'to'      => $to,
		'code'    => $code,
		'created' => gmdate( 'c' ),
	);
	$all[ $id ] = $row;
	update_option( THANTS_REDIRECTS_OPTION, $all, false );
	return $row;
}

function thants_delete_redirect( $id ) {
	$all = thants_get_redirects();
	if ( ! isset( $all[ $id ] ) ) {
		return false;
	}
	$row = $all[ $id ];
	unset( $all[ $id ] );
	update_option( THANTS_REDIRECTS_OPTION, $all, false );
	return $row;
}

/** The redirect for this request's path, or null. */
function thants_match_redirect( $request_path ) {
	$key = thants_normalize_path( $request_path );
	foreach ( thants_get_redirects() as $row ) {
		if ( $row['from'] === $key ) {
			return $row;
		}
	}
	return null;
}

function thants_maybe_redirect() {
	if ( ! is_404() ) {
		return;
	}
	// Raw on purpose: sanitize_text_field would strip the percent-encoding
	// ("/caf%C3%A9" → "/caf") before the path is decoded. The value is only
	// compared with stored paths, never printed or stored.
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$row = thants_match_redirect( thants_site_path( $uri ) );
	if ( null === $row ) {
		return;
	}
	if ( 410 === (int) $row['code'] ) {
		status_header( 410 );
		nocache_headers();
		exit;
	}
	wp_safe_redirect( $row['to'], (int) $row['code'], '1000ants Connector' );
	exit;
}
