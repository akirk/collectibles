<?php
/**
 * Explicit public projections of private collections.
 *
 * @package Collectibles
 */

namespace Collectibles;

/**
 * Sharing settings, public URLs and anonymous public routes.
 */
class Sharing {
	public const META_KEY = '_coll_sharing';

	/** Register anonymous routes without changing the private app's access. */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_show_directory' ), 0 );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'share_new_item' ), 10, 3 );
		add_action( 'wp_ajax_coll_share_collection', array( __CLASS__, 'ajax_share_collection' ) );
		add_action( 'wp_ajax_coll_share_item', array( __CLASS__, 'ajax_share_item' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_show_public_page' ), 0 );
	}

	/**
	 * Match public slugs while reserving the private app's routes.
	 *
	 * @param string $path Request URL path.
	 */
	public static function get_public_route( string $path ): ?array {
		$base = (string) wp_parse_url( App::get_url(), PHP_URL_PATH );
		if ( 0 !== strpos( $path, trailingslashit( $base ) ) ) {
			return null;
		}
		$parts = explode( '/', trim( substr( $path, strlen( trailingslashit( $base ) ) ), '/' ) );
		if ( count( $parts ) > 2 || ! self::is_public_slug( $parts[0] ) ) {
			return null;
		}
		return array(
			'slug'      => $parts[0],
			'item_slug' => $parts[1] ?? '',
		);
	}

	/**
	 * Whether a slug can coexist with private management routes.
	 *
	 * @param string $slug Collection slug.
	 */
	private static function is_public_slug( string $slug ): bool {
		return '' !== $slug && ! ctype_digit( $slug ) && ! in_array( $slug, array( 'new', 'search', 'origins', 'settings', 'collection' ), true );
	}

	/** Serve public projections before the private app checks authentication. */
	public static function maybe_show_public_page(): void {
		$path  = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH );
		$route = self::get_public_route( (string) $path );
		if ( null === $route ) {
			return;
		}
		$coll_public_slug = $route['slug'];
		$coll_public_item = $route['item_slug'];
		require dirname( __DIR__ ) . '/templates/public-collection.php';
		exit;
	}

	/** Whether the site administrator has enabled the public directory. */
	public static function directory_enabled(): bool {
		return (bool) get_option( 'coll_public_directory', false );
	}

	/** Serve only the public root, leaving private routing and REST access intact. */
	public static function maybe_show_directory(): void {
		if ( ! self::directory_enabled() ) {
			return;
		}
		$path = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH );
		if ( untrailingslashit( (string) $path ) !== untrailingslashit( (string) wp_parse_url( App::get_url(), PHP_URL_PATH ) ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only directory selection.
		$shared = isset( $_GET['view'] ) && 'shared' === $_GET['view'];
		if ( current_user_can( 'edit_posts' ) && ! $shared ) {
			return;
		}
		self::send_headers();
		status_header( 200 );
		require dirname( __DIR__ ) . '/templates/public-directory.php';
		exit;
	}

	/** Return summaries containing only public collection information. */
	public static function get_directory(): array {
		$entries = array();
		foreach ( get_posts(
			array(
				'post_type'    => Collection::POST_TYPE,
				'post_status'  => 'publish',
				'has_password' => false,
				'numberposts'  => -1,
				'orderby'      => 'title',
				'order'        => 'ASC',
			)
		) as $collection ) {
			$settings = self::get_settings( $collection->ID );
			if ( 'selected' !== $settings['mode'] ) {
				continue;
			}
			$owner     = get_userdata( (int) $collection->post_author );
			$entries[] = array(
				'title' => $collection->post_title,
				'owner' => $owner ? $owner->display_name : '',
				'url'   => self::get_url( $collection, $settings['slug'] ?? '' ),
				'icon'  => Schema::get_kind_icon( Collection::get_kind( $collection->ID ) ),
			);
		}
		return $entries;
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
			'mode'        => in_array( $stored['mode'] ?? '', array( 'selected', 'unlisted' ), true ) ? $stored['mode'] : 'private',
			'description' => (string) ( $stored['description'] ?? '' ),
			'new_items'   => 'shared' === ( $stored['new_items'] ?? '' ) ? 'shared' : 'private',
			'item_ids'    => array_map( 'absint', (array) ( $stored['item_ids'] ?? array() ) ),
		);
	}

	/**
	 * Apply the collection's sharing default only when a published item is created.
	 *
	 * @param int      $id Item ID.
	 * @param \WP_Post $post Created post.
	 * @param bool     $update Whether an existing post was updated.
	 */
	public static function share_new_item( int $id, \WP_Post $post, bool $update ): void {
		if ( $update || Item::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		$settings = self::get_settings( (int) $post->post_parent );
		if ( 'private' !== $settings['mode'] && 'shared' === $settings['new_items'] ) {
			self::set_item_shared( (int) $post->post_parent, $id, true );
		}
	}

	/**
	 * The stable public URL; private pages still return 404 at this address.
	 *
	 * @param \WP_Post $collection Collection post.
	 * @param string   $slug Optional proposed collection slug.
	 */
	public static function get_url( \WP_Post $collection, string $slug = '' ): string {
		$slug = $slug ? $slug : $collection->post_name;
		return App::get_url( $slug );
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
		if ( ! in_array( $mode, array( 'private', 'selected', 'unlisted' ), true ) ) {
			return new \WP_Error( 'coll_sharing_mode', __( 'Choose a sharing mode.', 'collectibles' ) );
		}
		if ( 'private' !== $mode && ( 'publish' !== $collection->post_status || '' !== $collection->post_password ) ) {
			return new \WP_Error( 'coll_sharing_status', __( 'Publish the collection before sharing it.', 'collectibles' ) );
		}
		$slug = sanitize_title( (string) ( $input['slug'] ?? $collection->post_name ) );
		if ( ! self::is_public_slug( $slug ) ) {
			return new \WP_Error( 'coll_sharing_slug', __( 'Enter a public URL name that is not a number or a reserved app route.', 'collectibles' ) );
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
			'new_items'   => 'shared' === ( $input['new_items'] ?? self::get_settings( $id )['new_items'] ) ? 'shared' : 'private',
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
		$public     = $eligible && $selected && 'private' !== $settings['mode'];
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

	/**
	 * Change sharing for all current items under the same lock as individual changes.
	 *
	 * @param int  $collection_id Collection ID.
	 * @param bool $shared Whether to share all eligible items.
	 * @return array|\WP_Error Saved item states or error.
	 */
	public static function set_all_shared( int $collection_id, bool $shared ) {
		if ( ! current_user_can( 'edit_posts' ) || ! Collection::get( $collection_id ) || ! current_user_can( 'edit_post', $collection_id ) ) {
			return new \WP_Error( 'coll_sharing_forbidden', __( 'You cannot change sharing for this collection.', 'collectibles' ) );
		}
		$lock = 'coll_sharing_lock_' . $collection_id;
		if ( ! add_option( $lock, time(), '', false ) ) {
			return new \WP_Error( 'coll_sharing_busy', __( 'Sharing settings are being changed. Try again.', 'collectibles' ) );
		}
		try {
			$settings = self::get_settings( $collection_id );
			if ( 'private' === $settings['mode'] ) {
				return new \WP_Error( 'coll_sharing_disabled', __( 'Enable the public page first.', 'collectibles' ) );
			}
			$items = get_posts(
				array(
					'post_type'   => Item::POST_TYPE,
					'post_parent' => $collection_id,
					'post_status' => 'any',
					'numberposts' => -1,
				)
			);
			$ids   = array();
			foreach ( $items as $item ) {
				if ( ! current_user_can( 'edit_post', $item->ID ) ) {
					return new \WP_Error( 'coll_sharing_forbidden', __( 'You cannot change sharing for every item.', 'collectibles' ) );
				}
				if ( $shared && self::get_item_state( $collection_id, $item->ID )['eligible'] ) {
					$ids[] = $item->ID;
				}
			}
			$settings['item_ids'] = $ids;
			if ( ! update_post_meta( $collection_id, self::META_KEY, wp_slash( $settings ) ) && get_post_meta( $collection_id, self::META_KEY, true ) !== $settings ) {
				return new \WP_Error( 'coll_sharing_save', __( 'Sharing settings could not be saved. Try again.', 'collectibles' ) );
			}
			$states = array();
			foreach ( $items as $item ) {
				$states[ $item->ID ] = self::get_item_state( $collection_id, $item->ID );
			}
			return $states;
		} finally {
			delete_option( $lock );
		}
	}

	/** Authenticated bulk sharing endpoint, scoped to one collection. */
	public static function ajax_share_collection(): void {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			wp_send_json_error( __( 'Use POST to change sharing.', 'collectibles' ), 405 );
		}
		$id = absint( $_POST['collection_id'] ?? 0 );
		if ( ! check_ajax_referer( 'coll_share_collection_' . $id, 'nonce', false ) ) {
			wp_send_json_error( __( 'Your sharing session expired. Reload and try again.', 'collectibles' ), 403 );
		}
		$shared = sanitize_text_field( wp_unslash( $_POST['shared'] ?? '' ) );
		if ( ! in_array( $shared, array( '0', '1' ), true ) ) {
			wp_send_json_error( __( 'Choose whether to share the items.', 'collectibles' ), 422 );
		}
		$result = self::set_all_shared( $id, '1' === $shared );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 'coll_sharing_forbidden' === $result->get_error_code() ? 403 : 409 );
		}
		wp_send_json_success( $result );
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
	 * @param bool     $include_unshared Include all items for an authorized private comparison.
	 * @return array|null Public projection or null for an unavailable item.
	 */
	private static function build_page( \WP_Post $collection, array $settings, string $item_slug = '', bool $include_unshared = false ): ?array {
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
		if ( $include_unshared ) {
			$items = Item::query( array( 'collection' => $collection->ID ) );
		}
		if ( 'private' !== $settings['mode'] && $settings['item_ids'] ) {
			$args['post__in'] = $settings['item_ids'];
			$items            = get_posts( $args );
		}
		if ( '' !== $item_slug && ! $items ) {
			return null;
		}
		$url = self::get_url( $collection, $settings['slug'] ?? '' );
		if ( $include_unshared ) {
			$url = App::get_url( $collection->ID );
		}
		$owner = get_userdata( (int) $collection->post_author );
		$data  = array(
			'owner'       => $owner ? $owner->display_name : '',
			'version'     => 1,
			'kind'        => $kind,
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
						'key'   => $field['key'],
						'raw'   => (string) ( $values[ $field['key'] ] ?? '' ),
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
						'side'      => Item::get_side_photo_id( $item->ID, Item::FRONT_META_KEY ) === $photo_id ? 'front' : ( Item::get_side_photo_id( $item->ID, Item::BACK_META_KEY ) === $photo_id ? 'back' : '' ),
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
				'url'    => $include_unshared ? App::get_url( $collection->ID . '/item/' . $item->ID ) : $url . $item->post_name . '/',
				'fields' => $fields,
				'photos' => $photos,
			);
		}
		return $data;
	}

	/**
	 * Comparison data for an editor, including unshared items but only public fields.
	 *
	 * @param int $id Collection ID.
	 */
	public static function get_comparison_data( int $id ): ?array {
		$collection = Collection::get( $id );
		if ( ! $collection || ! current_user_can( 'edit_posts' ) || ! current_user_can( 'edit_post', $id ) ) {
			return null;
		}
		return self::build_page(
			$collection,
			array(
				'mode'        => 'private',
				'description' => '',
			),
			'',
			true
		);
	}

	/** Discourage indexing and prevent stale pages surviving unpublishing. */
	public static function send_headers(): void {
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Robots-Tag: noindex, nofollow' );
	}
}
