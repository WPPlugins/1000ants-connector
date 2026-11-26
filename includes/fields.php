<?php
/**
 * The SEO plugin's own fields, registered for the REST API.
 *
 * The values stay where the SEO plugin keeps them — this registers access,
 * it never copies, renames or wraps a field. Writing one needs the right to
 * edit that post (`edit_post`), checked per post by WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which SEO plugin runs on this site: 'rank_math', 'yoast' or ''.
 * Rank Math wins when both are active (it is the one that prints the head).
 */
function thants_seo_plugin() {
	if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
		return 'rank_math';
	}
	if ( defined( 'WPSEO_VERSION' ) ) {
		return 'yoast';
	}
	return '';
}

/**
 * The fields per SEO plugin: role => [meta key, type].
 * `noindex` is the one robots switch the editor sets; for Rank Math it is a
 * value inside the `rank_math_robots` list, for Yoast its own field ('1' =
 * noindex, '2' = index, '0' = the post type's default).
 */
function thants_field_map( $plugin ) {
	if ( 'rank_math' === $plugin ) {
		return array(
			'title'       => array( 'rank_math_title', 'string' ),
			'description' => array( 'rank_math_description', 'string' ),
			'canonical'   => array( 'rank_math_canonical_url', 'string' ),
			'robots'      => array( 'rank_math_robots', 'array' ),
		);
	}
	if ( 'yoast' === $plugin ) {
		return array(
			'title'       => array( '_yoast_wpseo_title', 'string' ),
			'description' => array( '_yoast_wpseo_metadesc', 'string' ),
			'canonical'   => array( '_yoast_wpseo_canonical', 'string' ),
			'noindex'     => array( '_yoast_wpseo_meta-robots-noindex', 'string' ),
		);
	}
	return array();
}

/** The post types the editor works on: every public one but attachments. */
function thants_post_types() {
	$types = get_post_types( array( 'public' => true ), 'names' );
	unset( $types['attachment'] );
	return array_values( $types );
}

/**
 * Runs on EVERY save of these keys — the SEO plugin's own screens and
 * importers included, not only ours (WordPress sanitizes registered meta in
 * update_metadata). So it changes nothing the SEO plugin would keep: no
 * sanitize_text_field, which strips "%da" out of Rank Math's %date% and
 * Yoast's %%date%%. Control characters go (tab and newline stay), and tags
 * go for a user without `unfiltered_html` — on the SEO plugin's own saves
 * too, which is the point: they print the value into the head.
 */
function thants_sanitize_field( $value, $meta_key ) {
	if ( is_array( $value ) ) {
		return array_values( array_map( 'sanitize_key', $value ) );
	}
	$value = (string) $value;
	if ( false !== strpos( $meta_key, 'canonical' ) ) {
		return '' === $value ? '' : esc_url_raw( $value );
	}
	$value = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
	// No markup from someone WordPress does not trust with HTML (an author,
	// a contributor): the SEO plugin prints these values into the page head.
	// Only real tags go — "Save <50% today" stays whole, which strip_tags
	// would cut after "<" — and %-variables are left alone.
	return current_user_can( 'unfiltered_html' ) ? $value : thants_strip_markup( $value );
}

function thants_strip_markup( $value ) {
	$value = (string) preg_replace( '@<(script|style)\b[^>]*>.*?</\1\s*>@is', '', (string) $value );
	$value = (string) preg_replace( '@<!--.*?-->@s', '', $value );
	return (string) preg_replace( '@</?[a-z][a-z0-9-]*(\s[^<>]*)?/?>@i', '', $value );
}

function thants_register_seo_fields() {
	$plugin = thants_seo_plugin();
	if ( '' === $plugin ) {
		return;
	}
	foreach ( thants_post_types() as $post_type ) {
		foreach ( thants_field_map( $plugin ) as $field ) {
			list( $key, $type ) = $field;
			$args = array(
				'type'              => $type,
				'single'            => true,
				'sanitize_callback' => 'thants_sanitize_field',
				'auth_callback'     => 'thants_can_edit_field',
				'show_in_rest'      => true,
			);
			if ( 'array' === $type ) {
				$args['show_in_rest'] = array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				);
			}
			register_post_meta( $post_type, $key, $args );
		}
	}
}

/**
 * The same right WordPress asks for the post's content.
 *
 * @param bool   $allowed  Whether the user can add the meta (ignored).
 * @param string $meta_key The key.
 * @param int    $post_id  The post.
 */
function thants_can_edit_field( $allowed, $meta_key, $post_id ) {
	return current_user_can( 'edit_post', (int) $post_id );
}
