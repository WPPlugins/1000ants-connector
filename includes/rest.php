<?php
/**
 * The plugin's own routes, under /wp-json/1000ants/v1.
 *
 *   GET    /status            what this site lets the editor do (edit_posts)
 *   GET    /redirects         the stored redirects                (manage_options)
 *   POST   /redirects         {from, to, code}                    (manage_options)
 *   DELETE /redirects/<id>    the stored row, for the undo         (manage_options)
 *   GET    /robots            the appended lines                   (manage_options)
 *   PUT    /robots            {lines}; answers the previous lines  (manage_options)
 *   GET    /schema/<id>       the page's schema type and state     (edit_post)
 *   POST   /schema/<id>       {type, if_fingerprint}               (edit_post)
 *   POST   /schema/<id>/restore {if_fingerprint} — the undo of the last set (edit_post)
 *   GET    /schema-default/<type>   a post type's default schema       (manage_options)
 *   POST   /schema-default/<type>   {type, if_fingerprint}             (manage_options)
 *   POST   /schema-default/<type>/restore {values, if_fingerprint}     (manage_options)
 *
 * The SEO fields need no route of their own: they are the post's `meta` in
 * /wp/v2/pages and /wp/v2/posts once fields.php registered them.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function thants_can_edit() {
	return current_user_can( 'edit_posts' );
}

function thants_can_manage() {
	return current_user_can( 'manage_options' );
}

function thants_register_routes() {
	register_rest_route(
		THANTS_REST_NAMESPACE,
		'/status',
		array(
			'methods'             => 'GET',
			'callback'            => 'thants_rest_status',
			'permission_callback' => 'thants_can_edit',
		)
	);
	register_rest_route(
		THANTS_REST_NAMESPACE,
		'/redirects',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'thants_rest_list_redirects',
				'permission_callback' => 'thants_can_manage',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'thants_rest_add_redirect',
				'permission_callback' => 'thants_can_manage',
				'args'                => array(
					'from' => array( 'type' => 'string', 'required' => true ),
					'to'   => array( 'type' => 'string', 'default' => '' ),
					'code' => array( 'type' => 'integer', 'default' => 301 ),
				),
			),
		)
	);
	register_rest_route(
		THANTS_REST_NAMESPACE,
		'/redirects/(?P<id>[a-f0-9-]{36})',
		array(
			'methods'             => 'DELETE',
			'callback'            => 'thants_rest_delete_redirect',
			'permission_callback' => 'thants_can_manage',
		)
	);
	register_rest_route(
		THANTS_REST_NAMESPACE,
		'/robots',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'thants_rest_get_robots',
				'permission_callback' => 'thants_can_manage',
			),
			array(
				'methods'             => 'PUT',
				'callback'            => 'thants_rest_set_robots',
				'permission_callback' => 'thants_can_manage',
				'args'                => array(
					'lines' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
						'required' => true,
					),
				),
			),
		)
	);
}

/** The right to edit THIS post — the check WordPress applies to its content. */
function thants_can_edit_schema( WP_REST_Request $request ) {
	$id = (int) $request['id'];
	return $id > 0 && get_post( $id ) && current_user_can( 'edit_post', $id );
}

function thants_register_schema_routes() {
	register_rest_route(
		THANTS_REST_NAMESPACE,
		'/schema/(?P<id>\\d+)',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'thants_rest_get_schema',
				'permission_callback' => 'thants_can_edit_schema',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'thants_rest_set_schema',
				'permission_callback' => 'thants_can_edit_schema',
				'args'                => array(
					'type'           => array(
						'type'     => 'string',
						'enum'     => array_merge( THANTS_ARTICLE_TYPES, array( 'none' ) ),
						'required' => true,
					),
					'if_fingerprint' => array( 'type' => 'string', 'required' => true ),
				),
			),
		)
	);
	register_rest_route(
		THANTS_REST_NAMESPACE,
		'/schema/(?P<id>\\d+)/restore',
		array(
			'methods'             => 'POST',
			'callback'            => 'thants_rest_restore_schema',
			'permission_callback' => 'thants_can_edit_schema',
			'args'                => array(
				'if_fingerprint' => array( 'type' => 'string', 'required' => true ),
			),
		)
	);
}

function thants_register_schema_default_routes() {
	register_rest_route(
		THANTS_REST_NAMESPACE,
		'/schema-default/(?P<post_type>[a-z0-9_-]+)',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'thants_rest_get_schema_default',
				'permission_callback' => 'thants_can_manage',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'thants_rest_set_schema_default',
				'permission_callback' => 'thants_can_manage',
				'args'                => array(
					'type'           => array(
						'type'     => 'string',
						'enum'     => array_merge( THANTS_ARTICLE_TYPES, array( 'none' ) ),
						'required' => true,
					),
					'if_fingerprint' => array( 'type' => 'string', 'required' => true ),
				),
			),
		)
	);
	register_rest_route(
		THANTS_REST_NAMESPACE,
		'/schema-default/(?P<post_type>[a-z0-9_-]+)/restore',
		array(
			'methods'             => 'POST',
			'callback'            => 'thants_rest_restore_schema_default',
			'permission_callback' => 'thants_can_manage',
			'args'                => array(
				'values'         => array( 'type' => 'object', 'required' => true ),
				'if_fingerprint' => array( 'type' => 'string', 'required' => true ),
			),
		)
	);
}

function thants_rest_get_schema_default( WP_REST_Request $request ) {
	if ( ! thants_schema_supported() ) {
		return thants_schema_unsupported();
	}
	if ( ! thants_schema_post_type_ok( (string) $request['post_type'] ) ) {
		return new WP_Error( 'thants_bad_post_type', 'no such post type', array( 'status' => 404 ) );
	}
	return rest_ensure_response( thants_schema_default_view( (string) $request['post_type'] ) );
}

function thants_rest_set_schema_default( WP_REST_Request $request ) {
	if ( ! thants_schema_supported() ) {
		return thants_schema_unsupported();
	}
	$view = thants_set_schema_default( (string) $request['post_type'], (string) $request['type'], (string) $request['if_fingerprint'] );
	return is_wp_error( $view ) ? $view : rest_ensure_response( $view );
}

function thants_rest_restore_schema_default( WP_REST_Request $request ) {
	if ( ! thants_schema_supported() ) {
		return thants_schema_unsupported();
	}
	$view = thants_restore_schema_default( (string) $request['post_type'], (array) $request['values'], (string) $request['if_fingerprint'] );
	return is_wp_error( $view ) ? $view : rest_ensure_response( $view );
}

function thants_schema_unsupported() {
	return new WP_Error( 'thants_unsupported', 'the schema type is written for Rank Math only', array( 'status' => 409 ) );
}

function thants_rest_get_schema( WP_REST_Request $request ) {
	if ( ! thants_schema_supported() ) {
		return thants_schema_unsupported();
	}
	return rest_ensure_response( thants_schema_view( (int) $request['id'] ) );
}

function thants_rest_set_schema( WP_REST_Request $request ) {
	if ( ! thants_schema_supported() ) {
		return thants_schema_unsupported();
	}
	$view = thants_set_schema( (int) $request['id'], (string) $request['type'], (string) $request['if_fingerprint'] );
	return is_wp_error( $view ) ? $view : rest_ensure_response( $view );
}

function thants_rest_restore_schema( WP_REST_Request $request ) {
	if ( ! thants_schema_supported() ) {
		return thants_schema_unsupported();
	}
	$view = thants_restore_schema( (int) $request['id'], (string) $request['if_fingerprint'] );
	return is_wp_error( $view ) ? $view : rest_ensure_response( $view );
}

function thants_rest_status() {
	$plugin = thants_seo_plugin();
	$fields = array();
	foreach ( thants_field_map( $plugin ) as $role => $field ) {
		$fields[ $role ] = $field[0];
	}
	return rest_ensure_response(
		array(
			'connector'  => THANTS_CONNECTOR_VERSION,
			'wordpress'  => get_bloginfo( 'version' ),
			'seo_plugin' => $plugin,
			'fields'     => (object) $fields,
			'can_manage' => thants_can_manage(),
			'schema'     => thants_schema_supported(),
			'schema_default' => thants_schema_supported(),
			'redirects'  => count( thants_get_redirects() ),
			'robots'     => array(
				'physical_file' => thants_robots_physical_file(),
				'site_public'   => (bool) get_option( 'blog_public' ),
			),
		)
	);
}

function thants_rest_list_redirects() {
	return rest_ensure_response( array_values( thants_get_redirects() ) );
}

function thants_rest_add_redirect( WP_REST_Request $request ) {
	$row = thants_add_redirect( $request['from'], $request['to'], $request['code'] );
	if ( is_wp_error( $row ) ) {
		return $row;
	}
	return new WP_REST_Response( $row, 201 );
}

function thants_rest_delete_redirect( WP_REST_Request $request ) {
	$row = thants_delete_redirect( (string) $request['id'] );
	if ( false === $row ) {
		return new WP_Error( 'thants_not_found', 'no such redirect', array( 'status' => 404 ) );
	}
	return rest_ensure_response( array( 'deleted' => true, 'previous' => $row ) );
}

function thants_rest_get_robots() {
	return rest_ensure_response(
		array(
			'lines'         => thants_get_robots_lines(),
			'physical_file' => thants_robots_physical_file(),
		)
	);
}

function thants_rest_set_robots( WP_REST_Request $request ) {
	$previous = thants_set_robots_lines( $request['lines'] );
	if ( is_wp_error( $previous ) ) {
		return $previous;
	}
	return rest_ensure_response(
		array(
			'lines'         => thants_get_robots_lines(),
			'previous'      => $previous,
			'physical_file' => thants_robots_physical_file(),
		)
	);
}
