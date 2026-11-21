<?php
/**
 * robots.txt rules appended to the virtual robots.txt WordPress serves.
 *
 * A physical robots.txt in the web root is served by the web server and
 * never reaches WordPress; the status route says so, and nothing pretends.
 * Only the five directives a crawler reads are accepted, one per line.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function thants_robots_physical_file() {
	return file_exists( trailingslashit( ABSPATH ) . 'robots.txt' );
}

function thants_get_robots_lines() {
	$lines = get_option( THANTS_ROBOTS_OPTION, array() );
	return is_array( $lines ) ? array_values( $lines ) : array();
}

/**
 * Check every line; store them all or none. Returns the previous lines or a
 * WP_Error.
 *
 * @param array $lines The new lines.
 */
function thants_set_robots_lines( $lines ) {
	if ( ! is_array( $lines ) || count( $lines ) > THANTS_MAX_ROBOTS_LINES ) {
		return new WP_Error( 'thants_bad_lines', 'lines must be a list of at most 50', array( 'status' => 400 ) );
	}
	$clean = array();
	foreach ( $lines as $line ) {
		$line = trim( str_replace( array( "\r", "\n" ), '', (string) $line ) );
		if ( '' === $line ) {
			continue;
		}
		if ( strlen( $line ) > 300 || ! preg_match( '/^(User-agent|Disallow|Allow|Sitemap|Crawl-delay)\s*:\s*\S.*$/i', $line )
			&& ! preg_match( '/^Disallow\s*:\s*$/i', $line ) ) {
			return new WP_Error( 'thants_bad_line', 'not a robots.txt directive: ' . substr( $line, 0, 80 ), array( 'status' => 400 ) );
		}
		$clean[] = $line;
	}
	$previous = thants_get_robots_lines();
	update_option( THANTS_ROBOTS_OPTION, $clean, false );
	return $previous;
}

/**
 * @param string $output The robots.txt WordPress (and the SEO plugin) built.
 * @param bool   $public Whether the site asks to be indexed.
 */
function thants_robots_txt( $output, $public ) {
	$lines = thants_get_robots_lines();
	if ( ! $public || empty( $lines ) ) {
		return $output;
	}
	// Rules appended after another group would join THAT group (a
	// "User-agent: GPTBot" block, say). Ours open their own group unless
	// they start with one.
	$first = '';
	foreach ( $lines as $l ) {
		if ( ! preg_match( '/^Sitemap\s*:/i', $l ) ) {
			$first = $l;
			break;
		}
	}
	if ( '' !== $first && ! preg_match( '/^User-agent\s*:/i', $first ) ) {
		array_unshift( $lines, 'User-agent: *' );
	}
	return rtrim( (string) $output ) . "\n\n# 1000ants Connector\n" . implode( "\n", $lines ) . "\n";
}
