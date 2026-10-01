<?php
/**
 * WordPress Abilities exposed by the plugin.
 *
 * Assistants can inspect the catalog, look up external catalog entries and
 * create items through the same sanitizers and lot storage as the app forms.
 *
 * @package Collectibles
 */

namespace Collectibles;

/**
 * Registers the Collectibles abilities.
 */
class Abilities {
	/**
	 * Register the ability category.
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'collectibles',
			array(
				'label'       => __( 'Collectibles', 'collectibles' ),
				'description' => __( 'Look up and add items in the collectibles catalog.', 'collectibles' ),
			)
		);
	}

	/**
	 * Register the abilities themselves.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$permission_callback = function () {
			return current_user_can( 'edit_posts' );
		};

		wp_register_ability(
			'collectibles/list-collections',
			array(
				'label'               => __( 'List Collections', 'collectibles' ),
				'description'         => 'Returns the current user\'s collections with IDs, kind, item counts and totals.',
				'category'            => 'collectibles',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'collections' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'       => array(
										'type'        => 'integer',
										'description' => 'Use with collectibles/search-items.',
									),
									'name'     => array( 'type' => 'string' ),
									'kind'     => array( 'type' => 'string' ),
									'items'    => array( 'type' => 'integer' ),
									'currency' => array( 'type' => 'string' ),
									'value'    => array( 'type' => 'number' ),
								),
							),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'list_collections' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'instructions' => 'Use the returned collection IDs to narrow collectibles/search-items.',
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);

		wp_register_ability(
			'collectibles/search-items',
			array(
				'label'               => __( 'Search Collectible Items', 'collectibles' ),
				'description'         => 'Searches items across the catalog by free text, status and collection.',
				'category'            => 'collectibles',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search'     => array(
							'type'        => 'string',
							'description' => 'Free-text term matched against titles, notes and every field value.',
						),
						'collection' => array(
							'type'        => 'integer',
							'description' => 'Optional collection ID from collectibles/list-collections.',
						),
						'status'     => array(
							'type'        => 'string',
							'description' => 'Optional status slug: owned, wanted, ordered, duplicate, for_sale, sold.',
						),
						'limit'      => array(
							'type'        => 'integer',
							'description' => 'Maximum number of items to return, default 25.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'items' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'         => array(
										'type'        => 'integer',
										'description' => 'Use with collectibles/get-item.',
									),
									'title'      => array( 'type' => 'string' ),
									'collection' => array( 'type' => 'string' ),
									'year'       => array( 'type' => 'string' ),
									'status'     => array( 'type' => 'string' ),
								),
							),
						),
						'total' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'search_items' ),
				'permission_callback' => static function ( $input ) {
					return current_user_can( 'edit_posts' ) && ( empty( $input['collection'] ) || self::can_access_collection( $input ) );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'instructions' => 'Use the returned item IDs with collectibles/get-item for full details.',
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);

		wp_register_ability(
			'collectibles/get-item',
			array(
				'label'               => __( 'Get Collectible Item', 'collectibles' ),
				'description'         => 'Returns every recorded field of one item, including its kind-specific fields.',
				'category'            => 'collectibles',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Item ID from collectibles/search-items.',
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => array( 'type' => 'integer' ),
						'title'      => array( 'type' => 'string' ),
						'collection' => array( 'type' => 'string' ),
						'kind'       => array( 'type' => 'string' ),
						'pieces'     => array( 'type' => 'integer' ),
						'notes'      => array( 'type' => 'string' ),
						'tags'       => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'fields'     => array( 'type' => 'object' ),
						'lots'       => array(
							'type'        => 'array',
							'description' => 'Present when the item holds pieces in more than one condition.',
							'items'       => array( 'type' => 'object' ),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_item' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'instructions' => 'Present the fields as a compact list; empty fields are omitted.',
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);

		$value_properties = array();
		$lot_properties   = array();
		foreach ( array_merge( Item::get_common_fields(), Schema::get_all_fields() ) as $field ) {
			$definition = array(
				'type'        => 'string',
				'description' => $field['label'],
			);
			if ( ! empty( $field['grades'] ) ) {
				$options = array();
				foreach ( Schema::get_kinds() as $kind ) {
					$options = array_merge( $options, array_keys( $kind['grades'] ) );
				}
				$definition['enum'] = array_values( array_unique( array_merge( array( '' ), $options ) ) );
			} elseif ( ! empty( $field['options'] ) && count( $field['options'] ) <= 30 ) {
				$definition['enum'] = array_merge( array( '' ), array_keys( $field['options'] ) );
			}
			if ( Item::is_lot_field( $field ) ) {
				$lot_properties[ $field['key'] ] = $definition;
			} else {
				$value_properties[ $field['key'] ] = $definition;
			}
		}

		wp_register_ability(
			'collectibles/lookup-catalog-entry',
			array(
				'label'               => __( 'Look Up Catalog Entry', 'collectibles' ),
				'description'         => 'Look up a Rebrickable set number/link for brick sets or a Numista type number/link for coins and banknotes. Returns title, values, notes and comma-separated tags for collectibles/add-item. Does not save an item. Multiple Numista issues require choosing an issue_id and repeating the lookup.',
				'category'            => 'collectibles',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'collection' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'reference'  => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'issue_id'   => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'required'             => array( 'collection', 'reference' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'title'    => array( 'type' => 'string' ),
						'values'   => array( 'type' => 'object' ),
						'notes'    => array( 'type' => 'string' ),
						'tags'     => array( 'type' => 'string' ),
						'issue_id' => array( 'type' => 'integer' ),
						'issues'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'lookup_catalog_entry' ),
				'permission_callback' => array( __CLASS__, 'can_access_collection' ),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'collectibles/add-item',
			array(
				'label'               => __( 'Add Collectible Item', 'collectibles' ),
				'description'         => 'Create an item in a collection. Pass title, values, notes and tags from the catalog lookup, plus personal details when supplied. Field and lot values are strings. Defaults to owned with one set/copy; part_count is parts per set, quantity is number of sets/copies. Put condition_grade, quantity, purchase_price and estimated_value in lots. Prices are per piece in the collection currency. Do not guess condition, completeness or prices. Search for existing items before adding. Use a unique request_id per intended addition and reuse the same ID and payload on retries.',
				'category'            => 'collectibles',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'collection' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'request_id' => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 128,
						),
						'title'      => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'notes'      => array( 'type' => 'string' ),
						'tags'       => array(
							'type'        => 'string',
							'description' => 'Comma-separated tag names.',
						),
						'values'     => array(
							'type'                 => 'object',
							'properties'           => $value_properties,
							'additionalProperties' => false,
						),
						'lots'       => array(
							'type'     => 'array',
							'minItems' => 1,
							'items'    => array(
								'type'                 => 'object',
								'properties'           => $lot_properties,
								'additionalProperties' => false,
							),
						),
					),
					'required'             => array( 'collection', 'request_id', 'title' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer' ),
						'url'     => array( 'type' => 'string' ),
						'created' => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'add_item' ),
				'permission_callback' => array( __CLASS__, 'can_access_collection' ),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}

	/**
	 * Authorize collection-scoped lookups and creation.
	 *
	 * @param mixed $input Ability input.
	 */
	public static function can_access_collection( $input ): bool {
		$id = is_array( $input ) ? absint( $input['collection'] ?? 0 ) : 0;
		return current_user_can( 'edit_posts' ) && Collection::get( $id ) && current_user_can( 'edit_post', $id );
	}

	/**
	 * Fetch catalog metadata without saving an item.
	 *
	 * @param array $input Collection, reference and optional Numista issue ID.
	 * @return array|\WP_Error
	 */
	public static function lookup_catalog_entry( array $input ) {
		if ( ! self::can_access_collection( $input ) ) {
			return new \WP_Error( 'collectibles_collection_forbidden', __( 'You do not have access to this collection.', 'collectibles' ) );
		}
		$kind      = Collection::get_kind( absint( $input['collection'] ) );
		$reference = sanitize_text_field( $input['reference'] ?? '' );
		if ( Rebrickable::supports_kind( $kind ) ) {
			$set = Rebrickable::fetch_set( $reference );
			return is_wp_error( $set ) ? $set : Rebrickable::map_set( $set );
		}
		if ( ! Numista::supports_kind( $kind ) ) {
			return new \WP_Error( 'collectibles_catalog_unsupported', __( 'This collection has no catalog lookup provider.', 'collectibles' ) );
		}
		$id   = Numista::parse_id( $reference );
		$type = Numista::fetch_type( $id );
		if ( is_wp_error( $type ) ) {
			return $type;
		}
		$issues = Numista::fetch_issues( $id );
		if ( is_wp_error( $issues ) ) {
			return $issues;
		}
		$issue    = array();
		$issue_id = absint( $input['issue_id'] ?? 0 );
		foreach ( $issues as $candidate ) {
			if ( $issue_id && absint( $candidate['id'] ?? 0 ) === $issue_id ) {
				$issue = $candidate;
			}
		}
		if ( $issue_id && empty( $issue ) ) {
			return new \WP_Error( 'collectibles_issue_not_found', __( 'That issue does not belong to this catalog entry.', 'collectibles' ) );
		}
		if ( ! $issue_id && 1 === count( $issues ) ) {
			$issue = reset( $issues );
		}
		$result             = Numista::map_type( $type, $kind, $issue );
		$result['issue_id'] = absint( $issue['id'] ?? 0 );
		$result['issues']   = array();
		foreach ( $issues as $candidate ) {
			$result['issues'][] = array(
				'id'    => absint( $candidate['id'] ?? 0 ),
				'label' => Numista::describe_issue( $candidate ),
			);
		}
		return $result;
	}

	/**
	 * Create an item using the form sanitizers, with retry protection.
	 *
	 * @param array $input Complete new item and unique request ID.
	 * @return array|\WP_Error
	 */
	public static function add_item( array $input ) {
		if ( ! self::can_access_collection( $input ) ) {
			return new \WP_Error( 'collectibles_collection_forbidden', __( 'You do not have access to this collection.', 'collectibles' ) );
		}
		$collection_id = absint( $input['collection'] );
		$kind          = Collection::get_kind( $collection_id );
		$title         = sanitize_text_field( $input['title'] ?? '' );
		$request_id    = trim( $input['request_id'] ?? '' );
		if ( '' === $title || '' === $request_id || strlen( $request_id ) > 128 ) {
			return new \WP_Error( 'collectibles_item_input', __( 'An item name and a request ID of at most 128 characters are required.', 'collectibles' ) );
		}
		$fields = array();
		foreach ( Item::get_fields_for_kind( $kind ) as $field ) {
			$fields[ $field['key'] ] = $field;
		}
		$source = array();
		foreach ( $input['values'] ?? array() as $key => $value ) {
			if ( ! isset( $fields[ $key ] ) || Item::is_lot_field( $fields[ $key ] ) || ! is_string( $value ) ) {
				return new \WP_Error( 'collectibles_field_invalid', __( 'Use fields belonging to this collection kind, with lot fields in lots.', 'collectibles' ) );
			}
			if ( '' !== trim( $value ) && '' === Item::sanitize_field_value( $fields[ $key ], $value ) ) {
				return new \WP_Error( 'collectibles_field_value', __( 'A field value is invalid for this collection kind.', 'collectibles' ) );
			}
			$source[ 'coll_field_' . $key ] = $value;
		}
		foreach ( $input['lots'] ?? array() as $lot ) {
			foreach ( $lot as $key => $value ) {
				if ( ! in_array( $key, Item::get_lot_field_keys(), true ) || ! is_string( $value ) ) {
					return new \WP_Error( 'collectibles_lot_invalid', __( 'Lots must contain only condition, quantity and prices as strings.', 'collectibles' ) );
				}
				if ( '' !== trim( $value ) && '' === Item::sanitize_field_value( $fields[ $key ], $value ) ) {
					return new \WP_Error( 'collectibles_lot_value', __( 'A lot value is invalid for this collection kind.', 'collectibles' ) );
				}
			}
		}
		$source['coll_lot'] = $input['lots'] ?? array();

		// An atomic option lock serializes calls sharing a user/request ID.
		$request_key = hash( 'sha256', get_current_user_id() . ':' . $request_id );
		$lock        = 'coll_add_' . $request_key;
		if ( ! add_option( $lock, time(), '', false ) ) {
			return new \WP_Error( 'collectibles_request_busy', __( 'This addition is already in progress. Retry later with the same request ID.', 'collectibles' ) );
		}
		try {
			$existing = get_posts(
				array(
					'post_type'   => Item::POST_TYPE,
					'post_status' => 'any',
					'numberposts' => 1,
					'meta_key'    => '_coll_ability_request', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One retry marker in a personal catalog.
					'meta_value'  => $request_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Exact retry marker.
				)
			);
			ksort( $source );
			foreach ( $source['coll_lot'] as &$lot ) {
				ksort( $lot );
			}
			unset( $lot );
			$fingerprint = hash( 'sha256', wp_json_encode( array( $collection_id, $title, $source, $input['notes'] ?? '', $input['tags'] ?? '' ) ) );
			if ( $existing ) {
				$item_id = (int) $existing[0]->ID;
				if ( ! current_user_can( 'edit_post', $item_id ) || get_post_meta( $item_id, '_coll_ability_payload', true ) !== $fingerprint ) {
					return new \WP_Error( 'collectibles_request_conflict', __( 'This request ID was already used for a different addition.', 'collectibles' ) );
				}
				if ( ! get_post_meta( $item_id, '_coll_ability_complete', true ) ) {
					return new \WP_Error( 'collectibles_request_incomplete', __( 'This addition was interrupted. Check the item before adding it again.', 'collectibles' ) );
				}
				return self::added_item_result( $item_id, $collection_id, false );
			}
			$item_id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => Item::POST_TYPE,
						'post_status'  => 'publish',
						'post_parent'  => $collection_id,
						'post_author'  => get_current_user_id(),
						'post_title'   => $title,
						'post_content' => sanitize_textarea_field( $input['notes'] ?? '' ),
						'meta_input'   => array(
							'_coll_ability_request' => $request_key,
							'_coll_ability_payload' => $fingerprint,
						),
					)
				),
				true
			);
			if ( is_wp_error( $item_id ) ) {
				return $item_id;
			}
			Item::save_values( $item_id, $kind, $source );
			$tags   = array_values( array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $input['tags'] ?? '' ) ) ) ) );
			$result = wp_set_object_terms( $item_id, $tags, Item::TAXONOMY, false );
			if ( is_wp_error( $result ) ) {
				wp_delete_post( $item_id, true );
				return $result;
			}
			update_post_meta( $item_id, '_coll_ability_complete', '1' );
			return self::added_item_result( $item_id, $collection_id, true );
		} finally {
			delete_option( $lock );
		}
	}

	/**
	 * Return the created or previously created item's location.
	 *
	 * @param int  $item_id       Item ID.
	 * @param int  $collection_id Collection ID.
	 * @param bool $created       Whether this call created the item.
	 */
	private static function added_item_result( int $item_id, int $collection_id, bool $created ): array {
		return array(
			'id'      => $item_id,
			'url'     => App::get_url( 'collection/' . $collection_id . '/item/' . $item_id ),
			'created' => $created,
		);
	}

	/**
	 * Execute callback for collectibles/list-collections.
	 *
	 * @param mixed $input Ability input.
	 */
	public static function list_collections( $input = array() ): array {
		unset( $input );

		$collections = array();

		foreach ( Collection::get_for_current_user() as $collection ) {
			$items    = Item::query( array( 'collection' => $collection->ID ) );
			$summary  = Item::summarize( $items );
			$currency = Collection::get_currency( $collection->ID );

			$collections[] = array(
				'id'       => (int) $collection->ID,
				'name'     => get_the_title( $collection ),
				'kind'     => Collection::get_kind( $collection->ID ),
				'items'    => (int) $summary['items'],
				'currency' => $currency,
				'value'    => (float) $summary['value'],
			);
		}

		return array( 'collections' => $collections );
	}

	/**
	 * Execute callback for collectibles/search-items.
	 *
	 * @param mixed $input Ability input.
	 */
	public static function search_items( $input = array() ): array {
		$input = is_array( $input ) ? $input : array();
		$limit = isset( $input['limit'] ) ? max( 1, min( 200, absint( $input['limit'] ) ) ) : 25;

		$items = Item::query(
			array(
				'collection' => isset( $input['collection'] ) ? absint( $input['collection'] ) : 0,
				'search'     => isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '',
				'status'     => isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : '',
			)
		);

		$results = array();

		foreach ( array_slice( $items, 0, $limit ) as $item ) {
			$results[] = array(
				'id'         => (int) $item->ID,
				'title'      => get_the_title( $item ),
				'collection' => get_the_title( $item->post_parent ),
				'year'       => (string) get_post_meta( $item->ID, Item::YEAR_META_KEY, true ),
				'status'     => Item::get_status( $item->ID ),
			);
		}

		return array(
			'items' => $results,
			'total' => count( $items ),
		);
	}

	/**
	 * Execute callback for collectibles/get-item.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function get_item( $input = array() ) {
		$input   = is_array( $input ) ? $input : array();
		$item_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		$item    = Item::get( $item_id );

		if ( ! $item ) {
			return new \WP_Error( 'collectibles_item_not_found', __( 'No such item.', 'collectibles' ) );
		}

		if ( ! current_user_can( 'edit_post', $item_id ) ) {
			return new \WP_Error( 'collectibles_item_forbidden', __( 'You do not have access to this item.', 'collectibles' ) );
		}

		$collection_id = absint( $item->post_parent );
		$kind          = Collection::get_kind( $collection_id );
		$currency      = Collection::get_currency( $collection_id );
		$values        = Item::get_values( $item_id, $kind );
		$fields        = array();

		$lots = Item::get_lots( $item_id );

		foreach ( Item::get_fields_for_kind( $kind ) as $field ) {
			// Several lots have no single condition or price to report; they
			// are listed separately below.
			if ( Item::is_lot_field( $field ) && count( $lots ) > 1 ) {
				continue;
			}

			$value = Item::format_field_value( $field, $values[ $field['key'] ] ?? '', $currency );

			if ( '' !== $value ) {
				$fields[ $field['key'] ] = $value;
			}
		}

		$result = array(
			'id'         => $item_id,
			'title'      => get_the_title( $item ),
			'collection' => get_the_title( $collection_id ),
			'kind'       => $kind,
			'pieces'     => Item::get_quantity( $item_id ),
			'notes'      => wp_strip_all_tags( $item->post_content ),
			'tags'       => wp_list_pluck( Item::get_tags( $item_id ), 'name' ),
			'fields'     => $fields,
		);

		if ( count( $lots ) > 1 ) {
			$result['lots'] = array();

			foreach ( $lots as $lot ) {
				$row = array();

				foreach ( Item::get_lot_fields( $kind ) as $field ) {
					$row[ $field['key'] ] = Item::format_field_value( $field, (string) $lot[ $field['key'] ], $currency );
				}

				$result['lots'][] = $row;
			}
		}

		return $result;
	}
}
