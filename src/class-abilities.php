<?php
/**
 * WordPress Abilities exposed by the plugin.
 *
 * These are read-only lookups so an assistant can answer questions about the
 * catalog ("do I already have this one?") without guessing at plugin
 * internals. Writing is left to the app's own forms.
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
				'description' => __( 'Look things up in the collectibles catalog.', 'collectibles' ),
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
					'annotations' => array(
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
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'annotations' => array(
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
						'notes'      => array( 'type' => 'string' ),
						'tags'       => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'fields'     => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_item' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'annotations' => array(
						'instructions' => 'Present the fields as a compact list; empty fields are omitted.',
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
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

		foreach ( Item::get_fields_for_kind( $kind ) as $field ) {
			$value = Item::format_field_value( $field, $values[ $field['key'] ] ?? '', $currency );

			if ( '' !== $value ) {
				$fields[ $field['key'] ] = $value;
			}
		}

		return array(
			'id'         => $item_id,
			'title'      => get_the_title( $item ),
			'collection' => get_the_title( $collection_id ),
			'kind'       => $kind,
			'notes'      => wp_strip_all_tags( $item->post_content ),
			'tags'       => wp_list_pluck( Item::get_tags( $item_id ), 'name' ),
			'fields'     => $fields,
		);
	}
}
