<?php
/**
 * Collection helpers.
 *
 * @package Collectibles
 */

namespace Collectibles;

/**
 * A collection groups items of one kind — a coin collection, a stamp album, a
 * shelf of records.
 */
class Collection {
	public const POST_TYPE = 'coll_collection';

	public const KIND_META_KEY     = 'kind';
	public const CURRENCY_META_KEY = 'currency';

	/**
	 * Register the collection post type.
	 */
	public static function register_post_types(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Collections', 'collectibles' ),
					'singular_name' => __( 'Collection', 'collectibles' ),
					'add_new_item'  => __( 'Add New Collection', 'collectibles' ),
					'edit_item'     => __( 'Edit Collection', 'collectibles' ),
					'new_item'      => __( 'New Collection', 'collectibles' ),
					'view_item'     => __( 'View Collection', 'collectibles' ),
					'search_items'  => __( 'Search Collections', 'collectibles' ),
				),
				'description'  => __( 'Collections cataloged with Collectibles.', 'collectibles' ),
				'public'       => false,
				'show_ui'      => true,
				'menu_icon'    => 'dashicons-archive',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'author', 'custom-fields' ),
				'map_meta_cap' => true,
			)
		);
	}

	/**
	 * Register the collection post meta.
	 */
	public static function register_meta(): void {
		$auth_callback = Item::get_meta_auth_callback();

		register_post_meta(
			self::POST_TYPE,
			self::KIND_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => Schema::KIND_OTHER,
				'sanitize_callback' => array( Schema::class, 'sanitize_kind' ),
				'auth_callback'     => $auth_callback,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::CURRENCY_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_currency' ),
				'auth_callback'     => $auth_callback,
			)
		);
	}

	/**
	 * Sanitize a currency code into three uppercase letters.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_currency( $value ): string {
		$value = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) );

		return substr( $value, 0, 3 );
	}

	/**
	 * The kind of a collection.
	 *
	 * @param int $collection_id Collection post ID.
	 */
	public static function get_kind( int $collection_id ): string {
		return Schema::sanitize_kind( get_post_meta( $collection_id, self::KIND_META_KEY, true ) );
	}

	/**
	 * The currency a collection's prices are recorded in.
	 *
	 * @param int $collection_id Collection post ID.
	 */
	public static function get_currency( int $collection_id ): string {
		$currency = self::sanitize_currency( get_post_meta( $collection_id, self::CURRENCY_META_KEY, true ) );

		return '' === $currency ? 'EUR' : $currency;
	}

	/**
	 * All collections belonging to the current user, newest first.
	 *
	 * @return \WP_Post[]
	 */
	public static function get_for_current_user(): array {
		return get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'author'           => get_current_user_id(),
				'numberposts'      => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
	}

	/**
	 * Load a collection post, or null when the ID is not a readable collection.
	 *
	 * @param int $collection_id Collection post ID.
	 */
	public static function get( int $collection_id ): ?\WP_Post {
		if ( ! $collection_id ) {
			return null;
		}

		$post = get_post( $collection_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}
}
