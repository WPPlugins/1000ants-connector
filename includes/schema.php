<?php
/**
 * The page's schema type, as the SEO plugin keeps it (Rank Math for now).
 *
 * Rank Math keeps a page's schema as post meta: one `rank_math_schema_<Type>`
 * row per schema (a key may repeat), the primary one marked
 * `metadata.isPrimary`. Without any, `rank_math_rich_snippet` decides ('off'
 * = none); without that either, the post type's default from Rank Math's
 * settings applies.
 *
 * Two writes, both guarded by the fingerprint of the state they were
 * prepared against, so a change made in the editor in between is never
 * overwritten:
 *   - set:     the primary schema becomes Article / BlogPosting / NewsArticle,
 *              or none (the page keeps WebPage only); other schemas stay.
 *   - restore: the exact state read before — the undo. That state is kept
 *              HERE, on the post (`_thants_schema_undo`, protected meta),
 *              when the set is made; the undo request carries only the
 *              fingerprint, so nothing a caller sends is ever stored as
 *              schema (Rank Math prints it into the page as JSON-LD).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'THANTS_SCHEMA_PREFIX', 'rank_math_schema_' );
define( 'THANTS_ARTICLE_TYPES', array( 'Article', 'BlogPosting', 'NewsArticle' ) );
define( 'THANTS_SCHEMA_UNDO', '_thants_schema_undo' );

function thants_schema_supported() {
	return 'rank_math' === thants_seo_plugin();
}

/** Every schema row of the post, in order: list of [meta_key, value]. */
function thants_schema_rows( $post_id ) {
	$rows = array();
	foreach ( (array) get_post_meta( $post_id ) as $key => $values ) {
		if ( 0 !== strpos( (string) $key, THANTS_SCHEMA_PREFIX ) ) {
			continue;
		}
		foreach ( (array) $values as $value ) {
			$rows[] = array( (string) $key, maybe_unserialize( $value ) );
		}
	}
	return $rows;
}

/** What the post holds: the schema rows and the rich-snippet switch. */
function thants_schema_state( $post_id ) {
	return array(
		'rows'         => thants_schema_rows( $post_id ),
		'rich_snippet' => metadata_exists( 'post', $post_id, 'rank_math_rich_snippet' )
			? (string) get_post_meta( $post_id, 'rank_math_rich_snippet', true ) : null,
	);
}

function thants_schema_fingerprint( $state ) {
	return md5( (string) wp_json_encode( $state ) );
}

/**
 * The page's primary schema type: a schema.org type, or 'none'. Schemas
 * that are not primary (an FAQ added beside the article) are extras and do
 * not make a page an article.
 */
function thants_schema_type( $post_id, $state ) {
	foreach ( $state['rows'] as $row ) {
		$schema = is_array( $row[1] ) ? $row[1] : array();
		if ( ! empty( $schema['metadata']['isPrimary'] ) && ! empty( $schema['@type'] ) ) {
			return (string) $schema['@type'];
		}
	}
	if ( ! empty( $state['rows'] ) ) {
		return 'none';  // Rank Math prints the rows as they are; none is primary.
	}
	if ( null !== $state['rich_snippet'] ) {
		return thants_schema_from_setting( $state['rich_snippet'], $post_id );
	}
	// The post type's default from Rank Math's settings.
	if ( class_exists( '\RankMath\Helper' ) && method_exists( '\RankMath\Helper', 'get_default_schema_type' ) ) {
		$default = \RankMath\Helper::get_default_schema_type( $post_id );
		return $default ? ucfirst( (string) $default ) : 'none';
	}
	return 'none';
}

function thants_schema_from_setting( $value, $post_id ) {
	$value = (string) $value;
	if ( '' === $value || 'off' === $value ) {
		return 'none';
	}
	if ( 'article' === $value ) {
		$type   = get_post_type( $post_id );
		$titles = (array) get_option( 'rank-math-options-titles', array() );
		$key    = "pt_{$type}_default_article_type";
		return isset( $titles[ $key ] ) && $titles[ $key ] ? (string) $titles[ $key ] : 'Article';
	}
	return ucfirst( $value );
}

function thants_schema_view( $post_id ) {
	$state = thants_schema_state( $post_id );
	return array(
		'seo_plugin'  => thants_seo_plugin(),
		'type'        => thants_schema_type( $post_id, $state ),
		'is_default'  => empty( $state['rows'] ) && null === $state['rich_snippet'],
		'state'       => $state,
		'fingerprint' => thants_schema_fingerprint( $state ),
	);
}

/** The template Rank Math's own editor saves for an article. */
function thants_article_schema( $type ) {
	return array(
		'@type'         => $type,
		'metadata'      => array(
			'title'     => $type,
			'type'      => 'template',
			'shortcode' => uniqid( 's-' ),
			'isPrimary' => true,
		),
		'headline'      => '%seo_title%',
		'description'   => '%seo_description%',
		'keywords'      => '%keywords%',
		'articleSection' => '%primary_taxonomy_terms%',
		'datePublished' => '%date(Y-m-dTH:i:sP)%',
		'dateModified'  => '%modified(Y-m-dTH:i:sP)%',
		'author'        => array(
			'@type' => 'Person',
			'name'  => '%name%',
		),
	);
}

/** Put the post's schema rows back exactly as listed. */
function thants_schema_write_rows( $post_id, $rows ) {
	foreach ( array_keys( (array) get_post_meta( $post_id ) ) as $key ) {
		if ( 0 === strpos( (string) $key, THANTS_SCHEMA_PREFIX ) ) {
			delete_post_meta( $post_id, $key );
		}
	}
	foreach ( $rows as $row ) {
		add_post_meta( $post_id, $row[0], wp_slash( $row[1] ) );
	}
}

function thants_schema_write_rich_snippet( $post_id, $value ) {
	if ( null === $value ) {
		delete_post_meta( $post_id, 'rank_math_rich_snippet' );
	} else {
		update_post_meta( $post_id, 'rank_math_rich_snippet', wp_slash( (string) $value ) );
	}
}

/**
 * The new primary schema: an article type, or 'none'. Other schemas (an FAQ
 * added by hand) stay as they are.
 */
function thants_set_schema( $post_id, $type, $if_fingerprint ) {
	$state = thants_schema_state( $post_id );
	if ( thants_schema_fingerprint( $state ) !== $if_fingerprint ) {
		return new WP_Error( 'thants_changed', 'the schema changed since it was read', array( 'status' => 409 ) );
	}
	$keep = array();
	foreach ( $state['rows'] as $row ) {
		if ( empty( $row[1]['metadata']['isPrimary'] ) ) {
			$keep[] = $row;
		}
	}
	if ( 'none' === $type ) {
		thants_schema_write_rows( $post_id, $keep );
		thants_schema_write_rich_snippet( $post_id, 'off' );
	} else {
		array_unshift( $keep, array( THANTS_SCHEMA_PREFIX . $type, thants_article_schema( $type ) ) );
		thants_schema_write_rows( $post_id, $keep );
		thants_schema_write_rich_snippet( $post_id, null );
	}
	$view = thants_schema_view( $post_id );
	// What the undo puts back, kept with the fingerprint it may be undone from.
	update_post_meta( $post_id, THANTS_SCHEMA_UNDO, wp_slash( array( 'after' => $view['fingerprint'], 'state' => $state ) ) );
	return $view;
}

/**
 * The undo: the state kept when the last change was made, row for row —
 * only while the post still holds exactly what that change left.
 */
function thants_restore_schema( $post_id, $if_fingerprint ) {
	$undo = get_post_meta( $post_id, THANTS_SCHEMA_UNDO, true );
	if ( ! is_array( $undo ) || empty( $undo['after'] ) || ! isset( $undo['state'] ) || ! is_array( $undo['state'] ) ) {
		return new WP_Error( 'thants_no_undo', 'nothing to undo', array( 'status' => 409 ) );
	}
	$now = thants_schema_fingerprint( thants_schema_state( $post_id ) );
	if ( $now !== $if_fingerprint || $now !== $undo['after'] ) {
		return new WP_Error( 'thants_changed', 'the schema changed since it was written', array( 'status' => 409 ) );
	}
	$state = $undo['state'];
	thants_schema_write_rows( $post_id, isset( $state['rows'] ) ? (array) $state['rows'] : array() );
	thants_schema_write_rich_snippet( $post_id, isset( $state['rich_snippet'] ) ? $state['rich_snippet'] : null );
	delete_post_meta( $post_id, THANTS_SCHEMA_UNDO );
	return thants_schema_view( $post_id );
}

/*
 * The post type's default, in Rank Math's settings (Titles & Meta → <type>
 * → Schema Type): one setting behind every page that has no schema of its
 * own. Written as one change instead of one per page, with the same guard
 * and undo; a site-wide setting, so it needs manage_options.
 */

define( 'THANTS_RANK_MATH_TITLES', 'rank-math-options-titles' );

function thants_schema_default_values( $post_type ) {
	$titles = (array) get_option( THANTS_RANK_MATH_TITLES, array() );
	$snip   = "pt_{$post_type}_default_rich_snippet";
	$art    = "pt_{$post_type}_default_article_type";
	return array(
		'rich_snippet' => isset( $titles[ $snip ] ) ? (string) $titles[ $snip ] : null,
		'article_type' => isset( $titles[ $art ] ) ? (string) $titles[ $art ] : null,
	);
}

function thants_schema_default_view( $post_type ) {
	$values = thants_schema_default_values( $post_type );
	$type   = 'none';
	if ( null !== $values['rich_snippet'] && '' !== $values['rich_snippet'] && 'off' !== $values['rich_snippet'] ) {
		$type = 'article' === $values['rich_snippet']
			? ( $values['article_type'] ? $values['article_type'] : 'Article' )
			: ucfirst( $values['rich_snippet'] );
	}
	return array(
		'post_type'   => $post_type,
		'type'        => $type,
		'values'      => $values,
		'fingerprint' => md5( (string) wp_json_encode( $values ) ),
	);
}

function thants_schema_default_write( $post_type, $values ) {
	$titles = (array) get_option( THANTS_RANK_MATH_TITLES, array() );
	foreach ( array( 'rich_snippet' => "pt_{$post_type}_default_rich_snippet",
		'article_type' => "pt_{$post_type}_default_article_type" ) as $k => $key ) {
		if ( null === $values[ $k ] ) {
			unset( $titles[ $key ] );
		} else {
			$titles[ $key ] = $values[ $k ];
		}
	}
	update_option( THANTS_RANK_MATH_TITLES, $titles );
}

function thants_schema_post_type_ok( $post_type ) {
	return is_string( $post_type ) && preg_match( '/^[a-z0-9_\-]{1,40}$/', $post_type ) && post_type_exists( $post_type );
}

function thants_set_schema_default( $post_type, $type, $if_fingerprint ) {
	if ( ! thants_schema_post_type_ok( $post_type ) ) {
		return new WP_Error( 'thants_bad_post_type', 'no such post type', array( 'status' => 400 ) );
	}
	$view = thants_schema_default_view( $post_type );
	if ( $view['fingerprint'] !== $if_fingerprint ) {
		return new WP_Error( 'thants_changed', 'the setting changed since it was read', array( 'status' => 409 ) );
	}
	$values = $view['values'];
	if ( 'none' === $type ) {
		$values['rich_snippet'] = 'off';
	} else {
		$values['rich_snippet'] = 'article';
		$values['article_type'] = $type;
	}
	thants_schema_default_write( $post_type, $values );
	return thants_schema_default_view( $post_type );
}

function thants_restore_schema_default( $post_type, $values, $if_fingerprint ) {
	if ( ! thants_schema_post_type_ok( $post_type ) ) {
		return new WP_Error( 'thants_bad_post_type', 'no such post type', array( 'status' => 400 ) );
	}
	if ( thants_schema_default_view( $post_type )['fingerprint'] !== $if_fingerprint ) {
		return new WP_Error( 'thants_changed', 'the setting changed since it was written', array( 'status' => 409 ) );
	}
	$clean = array();
	foreach ( array( 'rich_snippet', 'article_type' ) as $k ) {
		$v = isset( $values[ $k ] ) ? $values[ $k ] : null;
		if ( null !== $v && ( ! is_string( $v ) || ! preg_match( '/^[A-Za-z_]{0,40}$/', $v ) ) ) {
			return new WP_Error( 'thants_bad_state', 'not a schema setting', array( 'status' => 400 ) );
		}
		$clean[ $k ] = $v;
	}
	thants_schema_default_write( $post_type, $clean );
	return thants_schema_default_view( $post_type );
}
