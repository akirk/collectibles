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
		return array(
			'mode'        => in_array( $stored['mode'] ?? '', array( 'all', 'selected' ), true ) ? $stored['mode'] : 'private',
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
		if ( ! in_array( $mode, array( 'private', 'all', 'selected' ), true ) ) {
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
		$ids  = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $input['item_ids'] ?? array() ) ) ) ) );
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
		if ( 'all' === $settings['mode'] || ( 'selected' === $settings['mode'] && $settings['item_ids'] ) ) {
			if ( 'selected' === $settings['mode'] ) {
				$args['post__in'] = $settings['item_ids'];
			}
			$items = get_posts( $args );
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
