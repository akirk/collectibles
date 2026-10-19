<?php
/**
 * Explicit public projections of private collections.
 *
 * @package Collectibles
 */

namespace Collectibles;

use WpApp\WpApp;

/**
 * Sharing settings, public URLs and an isolated anonymous router.
 */
class Sharing {
	public const META_KEY = '_coll_sharing';

	/** Register anonymous routes without changing the private app's access. */
	public static function init(): void {
		add_action( 'wp_ajax_coll_share_item', array( __CLASS__, 'ajax_share_item' ) );
		$app = new WpApp(
			dirname( __DIR__ ) . '/templates',
			'collections',
			array(
				'require_login' => false,
				'launcher'      => false,
				'app_name'      => 'Collections',
			)
		);
		$app->route( '', 'public-collection.php' );
		$app->route( '{slug}', 'public-collection.php' );
		$app->route( '{slug}/{item_slug}', 'public-collection.php' );
		$app->init();
		$app->enable_masterbar( false );
	}

	/**
	 * Read settings, defaulting existing and new collections to private.
	 *
	 * @param int $id Collection ID.
	 */
	public static function get_settings( int $id ): array {
		$stored = get_post_meta( $id, self::META_KEY, true );
		$stored = is_array( $stored ) ? $stored : array();
		// Freeze the old all-items mode to its current published items once.
		// A compare-and-swap prevents overwriting another request's selection.
		if ( 'all' === ( $stored['mode'] ?? '' ) ) {
			$migrated             = $stored;
			$migrated['mode']     = 'selected';
			$migrated['item_ids'] = array_map(
				'absint',
				get_posts(
					array(
						'post_type'    => Item::POST_TYPE,
						'post_parent'  => $id,
						'post_status'  => 'publish',
						'has_password' => false,
						'numberposts'  => -1,
						'fields'       => 'ids',
					)
				)
			);
			update_post_meta( $id, self::META_KEY, wp_slash( $migrated ), $stored );
			$stored = get_post_meta( $id, self::META_KEY, true );
		}

		return array(
			'mode'        => 'selected' === ( $stored['mode'] ?? '' ) ? 'selected' : 'private',
			'description' => (string) ( $stored['description'] ?? '' ),
			'item_ids'    => array_map( 'absint', (array) ( $stored['item_ids'] ?? array() ) ),
		);
	}

	/**
	 * The stable public URL; private pages still return 404 at this address.
	 *
	 * @param \WP_Post $collection Collection post.
	 * @param string   $slug Optional proposed collection slug.
	 */
	public static function get_url( \WP_Post $collection, string $slug = '' ): string {
		$slug = $slug ? $slug : $collection->post_name;
		return home_url( '/collections/' . $slug . '/' );
	}

	/**
	 * Validate an owner's proposed settings without publishing anything.
	 *
	 * @param int   $id Collection ID.
	 * @param array $input Unslashed sharing form values.
	 * @return array|\WP_Error Validated settings and slug, or error.
	 */
	public static function prepare( int $id, array $input ) {
		$collection = Collection::get( $id );
		if ( ! $collection || ! current_user_can( 'edit_posts' ) || ! current_user_can( 'edit_post', $id ) ) {
			return new \WP_Error( 'coll_sharing_forbidden', __( 'You cannot change sharing for this collection.', 'collectibles' ) );
		}
		$mode = $input['mode'] ?? 'private';
		if ( ! in_array( $mode, array( 'private', 'selected' ), true ) ) {
			return new \WP_Error( 'coll_sharing_mode', __( 'Choose a sharing mode.', 'collectibles' ) );
		}
		if ( 'private' !== $mode && ( 'publish' !== $collection->post_status || '' !== $collection->post_password ) ) {
			return new \WP_Error( 'coll_sharing_status', __( 'Publish the collection before sharing it.', 'collectibles' ) );
		}
		$slug = sanitize_title( (string) ( $input['slug'] ?? $collection->post_name ) );
		if ( '' === $slug ) {
			return new \WP_Error( 'coll_sharing_slug', __( 'Enter a public URL name.', 'collectibles' ) );
		}
		$slug = wp_unique_post_slug( $slug, $id, 'publish', Collection::POST_TYPE, 0 );
		$ids  = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $input['item_ids'] ?? self::get_settings( $id )['item_ids'] ) ) ) ) );
		foreach ( $ids as $item_id ) {
			$item = Item::get( $item_id );
			if ( ! $item || $id !== (int) $item->post_parent || ! current_user_can( 'edit_post', $item_id ) ) {
				return new \WP_Error( 'coll_sharing_item', __( 'Only items from this collection can be selected.', 'collectibles' ) );
			}
		}
		return array(
			'mode'        => $mode,
			'description' => sanitize_textarea_field( (string) ( $input['description'] ?? '' ) ),
			'item_ids'    => $ids,
			'slug'        => $slug,
		);
	}

	/**
	 * Save a validated sharing choice, including an optional new slug.
	 *
	 * @param int   $id Collection ID.
	 * @param array $input Unslashed sharing form values.
	 * @return array|\WP_Error Saved settings or error.
	 */
	public static function save( int $id, array $input ) {
		if ( ! Collection::get( $id ) || ! current_user_can( 'edit_posts' ) || ! current_user_can( 'edit_post', $id ) ) {
			return new \WP_Error( 'coll_sharing_forbidden', __( 'You cannot change sharing for this collection.', 'collectibles' ) );
		}
		$lock = 'coll_sharing_lock_' . $id;
		if ( ! add_option( $lock, time(), '', false ) ) {
			return new \WP_Error( 'coll_sharing_busy', __( 'Sharing settings are being changed. Try again.', 'collectibles' ) );
		}
		try {
			$settings = self::prepare( $id, $input );
			if ( is_wp_error( $settings ) ) {
				return $settings;
			}
			$result = wp_update_post(
				wp_slash(
					array(
						'ID'        => $id,
						'post_name' => $settings['slug'],
					)
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$settings['slug'] = get_post( $id )->post_name;
			$stored           = $settings;
			unset( $stored['slug'] );
			if ( ! update_post_meta( $id, self::META_KEY, wp_slash( $stored ) ) && get_post_meta( $id, self::META_KEY, true ) !== $stored ) {
				return new \WP_Error( 'coll_sharing_save', __( 'Sharing settings could not be saved. Try again.', 'collectibles' ) );
			}
			return $settings;
		} finally {
			delete_option( $lock );
		}
	}

	/**
	 * Describe selection separately from whether the collection is public.
	 *
	 * @param int $collection_id Collection ID.
	 * @param int $item_id Item ID.
	 */
	public static function get_item_state( int $collection_id, int $item_id ): array {
		$settings   = self::get_settings( $collection_id );
		$collection = Collection::get( $collection_id );
		$item       = Item::get( $item_id );
		$eligible   = $collection && $item && $collection_id === (int) $item->post_parent && 'publish' === $collection->post_status && '' === $collection->post_password && 'publish' === $item->post_status && '' === $item->post_password;
		$selected   = in_array( $item_id, $settings['item_ids'], true );
		$public     = $eligible && $selected && 'selected' === $settings['mode'];
		return array(
			'selected' => $selected,
			'eligible' => (bool) $eligible,
			'label'    => $public ? __( 'Shared', 'collectibles' ) : ( $selected ? __( 'Selected', 'collectibles' ) : __( 'Private', 'collectibles' ) ),
			'action'   => $selected ? __( 'Remove from the public page', 'collectibles' ) : __( 'Include on the public page', 'collectibles' ),
			'message'  => $selected && 'private' === $settings['mode'] ? __( 'Selected for sharing. The collection’s public page is disabled.', 'collectibles' ) : ( $public ? __( 'This item is now shared.', 'collectibles' ) : __( 'This item is private.', 'collectibles' ) ),
		);
	}

	/**
	 * Set one selection without overwriting simultaneous changes to other items.
	 *
	 * @param int  $collection_id Collection ID.
	 * @param int  $item_id Item ID.
	 * @param bool $shared Whether the item should be selected.
	 * @return array|\WP_Error Saved state or error.
	 */
	public static function set_item_shared( int $collection_id, int $item_id, bool $shared ) {
		$item = Item::get( $item_id );
		if ( ! current_user_can( 'edit_posts' ) || ! Collection::get( $collection_id ) || ! current_user_can( 'edit_post', $collection_id ) || ! $item || $collection_id !== (int) $item->post_parent || ! current_user_can( 'edit_post', $item_id ) ) {
			return new \WP_Error( 'coll_sharing_forbidden', __( 'You cannot change sharing for this item.', 'collectibles' ) );
		}
		$lock = 'coll_sharing_lock_' . $collection_id;
		if ( ! add_option( $lock, time(), '', false ) ) {
			return new \WP_Error( 'coll_sharing_busy', __( 'Sharing settings are being changed. Try again.', 'collectibles' ) );
		}
		try {
			$state = self::get_item_state( $collection_id, $item_id );
			if ( $shared && ! $state['eligible'] ) {
				return new \WP_Error( 'coll_sharing_item', __( 'Only published items without a password can be shared.', 'collectibles' ) );
			}
			$settings = self::get_settings( $collection_id );
			$ids      = array_values( array_diff( $settings['item_ids'], array( $item_id ) ) );
			if ( $shared ) {
				$ids[] = $item_id;
			}
			$settings['item_ids'] = $ids;
			if ( ! update_post_meta( $collection_id, self::META_KEY, wp_slash( $settings ) ) && get_post_meta( $collection_id, self::META_KEY, true ) !== $settings ) {
				return new \WP_Error( 'coll_sharing_save', __( 'Sharing settings could not be saved. Try again.', 'collectibles' ) );
			}
			return self::get_item_state( $collection_id, $item_id );
		} finally {
			delete_option( $lock );
		}
	}

	/** Authenticated, nonce-protected AJAX endpoint for item card switches. */
	public static function ajax_share_item(): void {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			wp_send_json_error( __( 'Use POST to change sharing.', 'collectibles' ), 405 );
		}
		$item_id = absint( $_POST['item_id'] ?? 0 );
		if ( ! check_ajax_referer( 'coll_share_item_' . $item_id, 'nonce', false ) ) {
			wp_send_json_error( __( 'Your sharing session expired. Reload and try again.', 'collectibles' ), 403 );
		}
		$shared = sanitize_text_field( wp_unslash( $_POST['shared'] ?? '' ) );
		if ( ! in_array( $shared, array( '0', '1' ), true ) ) {
			wp_send_json_error( __( 'Choose whether to share this item.', 'collectibles' ), 422 );
		}
		$result = self::set_item_shared( absint( $_POST['collection_id'] ?? 0 ), $item_id, '1' === $shared );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 'coll_sharing_forbidden' === $result->get_error_code() ? 403 : 409 );
		}
		wp_send_json_success( $result );
	}


	/**
	 * Render proposed content for an authorized editor without saving it.
	 *
	 * @param int   $id Collection ID.
	 * @param array $input Unslashed sharing form values.
	 * @return array|\WP_Error Public projection or error.
	 */
	public static function preview( int $id, array $input ) {
		$settings = self::prepare( $id, $input );
		return is_wp_error( $settings ) ? $settings : self::build_page( Collection::get( $id ), $settings );
	}

	/**
	 * Look up only an explicitly shared collection and optionally one item.
	 *
	 * @param string $slug Collection slug from the public route.
	 * @param string $item_slug Optional item slug.
	 * @return array|null Public projection, or null for every unavailable URL.
	 */
	public static function get_page( string $slug, string $item_slug = '' ): ?array {
		if ( '' === sanitize_title( $slug ) ) {
			return null;
		}
		$collections = get_posts(
			array(
				'post_type'    => Collection::POST_TYPE,
				'post_status'  => 'publish',
				'has_password' => false,
				'name'         => sanitize_title( $slug ),
				'numberposts'  => 1,
			)
		);
		if ( ! $collections ) {
			return null;
		}
		$collection = $collections[0];
		$settings   = self::get_settings( $collection->ID );
		if ( 'private' === $settings['mode'] ) {
			return null;
		}
		return self::build_page( $collection, $settings, $item_slug );
	}

	/**
	 * Build a page from an allowlist, never forwarding raw posts or metadata.
	 *
	 * @param \WP_Post $collection Collection post.
	 * @param array    $settings Validated sharing settings.
	 * @param string   $item_slug Optional individual item slug.
	 * @return array|null Public projection or null for an unavailable item.
	 */
	private static function build_page( \WP_Post $collection, array $settings, string $item_slug = '' ): ?array {
		$kind = Collection::get_kind( $collection->ID );
		$args = array(
			'post_type'    => Item::POST_TYPE,
			'has_password' => false,
			'post_status'  => 'publish',
			'post_parent'  => $collection->ID,
			'numberposts'  => -1,
			'orderby'      => 'title',
			'order'        => 'ASC',
		);
		if ( '' !== $item_slug && '' === sanitize_title( $item_slug ) ) {
			return null;
		}
		if ( '' !== $item_slug ) {
			$args['name'] = sanitize_title( $item_slug );
		}
		$items = array();
		if ( 'selected' === $settings['mode'] && $settings['item_ids'] ) {
			$args['post__in'] = $settings['item_ids'];
			$items            = get_posts( $args );
		}
		if ( '' !== $item_slug && ! $items ) {
			return null;
		}
		$url  = self::get_url( $collection, $settings['slug'] ?? '' );
		$data = array(
			'title'       => $collection->post_title,
			'description' => $settings['description'],
			'url'         => $url,
			'icon'        => Schema::get_kind_icon( $kind ),
			'ratio'       => Schema::get_thumb_ratio( $kind ),
			'detail'      => '' !== $item_slug,
			'items'       => array(),
		);
		foreach ( $items as $item ) {
			$values = Item::get_values( $item->ID, $kind );
			$fields = array();
			foreach ( Item::get_fields_for_kind( $kind ) as $field ) {
				if ( empty( $field['public'] ) || Item::is_lot_field( $field ) ) {
					continue;
				}
				$value = Item::format_field_value( $field, $values[ $field['key'] ] ?? '', '' );
				if ( '' !== $value ) {
					$fields[] = array(
						'label' => $field['label'],
						'value' => $value,
					);
				}
			}
			$photos = array();
			foreach ( Item::get_photo_ids( $item->ID ) as $photo_id ) {
				$photo = get_post( $photo_id );
				if ( ! $photo || (int) $item->ID !== (int) $photo->post_parent || ! wp_attachment_is_image( $photo_id ) ) {
					continue;
				}
				$full  = wp_get_attachment_image_url( $photo_id, 'full' );
				$thumb = wp_get_attachment_image_url( $photo_id, 'large' );
				if ( $full && $thumb ) {
					$medium   = wp_get_attachment_image_url( $photo_id, 'medium' );
					$source   = get_post_meta( $photo_id, '_coll_catalog_source', true );
					$source   = is_string( $source ) && preg_match( '~^https://rebrickable\.com/sets/[a-z0-9-]+/$~i', $source ) ? $source : '';
					$photos[] = array(
						'url'       => $full,
						'image'     => $thumb,
						'source'    => $source,
						'thumbnail' => $medium ? $medium : $thumb,
					);
				}
			}
			$title = $item->post_title;
			if ( ! empty( Schema::get_kind( $kind )['catalog']['show_on_card'] ) && ! empty( $values['catalog_number'] ) ) {
				$title = $values['catalog_number'] . ' ' . $title;
			}
			$data['items'][] = array(
				'title'  => $title,
				'slug'   => $item->post_name,
				'url'    => $url . $item->post_name . '/',
				'fields' => $fields,
				'photos' => $photos,
			);
		}
		return $data;
	}

	/** Discourage indexing and prevent stale pages surviving unpublishing. */
	public static function send_headers(): void {
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Robots-Tag: noindex, nofollow' );
	}
}
