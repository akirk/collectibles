<?php
/**
 * Item helpers.
 *
 * @package Collectibles
 */

namespace Collectibles;

/**
 * A single collectible: one coin, one stamp, one record. Items are children of
 * a collection via post_parent and take their field set from the collection's
 * kind.
 */
class Item {
	public const POST_TYPE = 'coll_item';
	public const TAXONOMY  = 'coll_tag';

	public const STATUS_META_KEY    = 'status';
	public const QUANTITY_META_KEY  = 'quantity';
	public const YEAR_META_KEY      = 'year';
	public const ORIGIN_META_KEY    = 'origin';
	public const CONDITION_META_KEY = 'condition_grade';
	public const CATALOG_META_KEY   = 'catalog_number';
	public const LOCATION_META_KEY  = 'storage_location';
	public const ACQUIRED_META_KEY  = 'acquired_date';
	public const SOURCE_META_KEY    = 'acquired_from';
	public const PAID_META_KEY      = 'purchase_price';
	public const VALUE_META_KEY     = 'estimated_value';

	/**
	 * Common fields, shared by every kind of item.
	 *
	 * The condition field is listed here but its options depend on the
	 * collection kind, so it carries a 'grades' marker instead of 'options'.
	 *
	 * @return array<int, array>
	 */
	public static function get_common_fields(): array {
		return array(
			array(
				'key'     => self::STATUS_META_KEY,
				'label'   => __( 'Status', 'collectibles' ),
				'type'    => 'select',
				'options' => Schema::get_statuses(),
			),
			array(
				'key'     => self::CONDITION_META_KEY,
				'label'   => __( 'Condition', 'collectibles' ),
				'type'    => 'select',
				'grades'  => true,
				'options' => array(),
			),
			array(
				'key'   => self::QUANTITY_META_KEY,
				'label' => __( 'Quantity', 'collectibles' ),
				'type'  => 'number',
				'step'  => '1',
				'min'   => '0',
			),
			array(
				'key'         => self::YEAR_META_KEY,
				'label'       => __( 'Year', 'collectibles' ),
				'type'        => 'number',
				'step'        => '1',
				'placeholder' => __( 'e.g. 1934', 'collectibles' ),
			),
			array(
				'key'         => self::ORIGIN_META_KEY,
				'label'       => __( 'Origin', 'collectibles' ),
				'type'        => 'text',
				'placeholder' => __( 'Country or region', 'collectibles' ),
			),
			array(
				'key'         => self::CATALOG_META_KEY,
				'label'       => __( 'Catalog number', 'collectibles' ),
				'type'        => 'text',
				'placeholder' => __( 'Reference in a catalogue', 'collectibles' ),
			),
			array(
				'key'         => self::LOCATION_META_KEY,
				'label'       => __( 'Stored in', 'collectibles' ),
				'type'        => 'text',
				'placeholder' => __( 'Album, box, shelf, …', 'collectibles' ),
			),
			array(
				'key'   => self::ACQUIRED_META_KEY,
				'label' => __( 'Acquired on', 'collectibles' ),
				'type'  => 'date',
			),
			array(
				'key'         => self::SOURCE_META_KEY,
				'label'       => __( 'Acquired from', 'collectibles' ),
				'type'        => 'text',
				'placeholder' => __( 'Dealer, fair, inheritance, …', 'collectibles' ),
			),
			array(
				'key'   => self::PAID_META_KEY,
				'label' => __( 'Price paid', 'collectibles' ),
				'type'  => 'number',
				'step'  => '0.01',
				'min'   => '0',
				'money' => true,
			),
			array(
				'key'   => self::VALUE_META_KEY,
				'label' => __( 'Estimated value', 'collectibles' ),
				'type'  => 'number',
				'step'  => '0.01',
				'min'   => '0',
				'money' => true,
			),
		);
	}

	/**
	 * The full field set for an item of the given kind: common fields first,
	 * then the fields specific to that kind. The condition options and the
	 * catalog number wording come from the kind as well.
	 *
	 * @param string $kind Collection kind slug.
	 * @return array<int, array>
	 */
	public static function get_fields_for_kind( string $kind ): array {
		$fields  = self::get_common_fields();
		$catalog = Schema::get_catalog_field( $kind );

		foreach ( $fields as $index => $field ) {
			if ( ! empty( $field['grades'] ) ) {
				$fields[ $index ]['options'] = Schema::get_grades( $kind );
			}

			if ( $catalog && self::CATALOG_META_KEY === $field['key'] ) {
				$fields[ $index ] = array_merge( $field, $catalog );
			}
		}

		return array_merge( $fields, Schema::get_fields( $kind ) );
	}

	/**
	 * Register the item post type.
	 */
	public static function register_post_types(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Items', 'collectibles' ),
					'singular_name' => __( 'Item', 'collectibles' ),
					'add_new_item'  => __( 'Add New Item', 'collectibles' ),
					'edit_item'     => __( 'Edit Item', 'collectibles' ),
					'new_item'      => __( 'New Item', 'collectibles' ),
					'view_item'     => __( 'View Item', 'collectibles' ),
					'search_items'  => __( 'Search Items', 'collectibles' ),
				),
				'description'  => __( 'Individual collectibles.', 'collectibles' ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=' . Collection::POST_TYPE,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'author', 'thumbnail', 'custom-fields' ),
				'map_meta_cap' => true,
			)
		);
	}

	/**
	 * Register the free-form tag taxonomy shared by all items.
	 */
	public static function register_taxonomies(): void {
		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Tags', 'collectibles' ),
					'singular_name' => __( 'Tag', 'collectibles' ),
					'search_items'  => __( 'Search Tags', 'collectibles' ),
					'add_new_item'  => __( 'Add New Tag', 'collectibles' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'hierarchical'      => false,
			)
		);
	}

	/**
	 * The auth callback used for every meta field in the plugin: editing an
	 * item's meta requires the capability to edit that post.
	 */
	public static function get_meta_auth_callback(): callable {
		return function ( ...$args ) {
			$post_id = isset( $args[2] ) ? absint( $args[2] ) : 0;
			$user_id = isset( $args[3] ) ? absint( $args[3] ) : get_current_user_id();

			if ( $post_id ) {
				return user_can( $user_id, 'edit_post', $post_id );
			}

			return user_can( $user_id, 'edit_posts' );
		};
	}

	/**
	 * Register post meta for every common and kind-specific item field.
	 */
	public static function register_meta(): void {
		$auth_callback = self::get_meta_auth_callback();
		$fields        = array_merge( self::get_common_fields(), array_values( Schema::get_all_fields() ) );

		foreach ( $fields as $field ) {
			register_post_meta(
				self::POST_TYPE,
				$field['key'],
				array(
					'type'              => 'number' === $field['type'] ? 'number' : 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'number' === $field['type']
						? array( __CLASS__, 'sanitize_number_meta' )
						: array( __CLASS__, 'sanitize_text_meta' ),
					'auth_callback'     => $auth_callback,
				)
			);
		}
	}

	/**
	 * Sanitize a meta value into a non-negative float.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_number_meta( $value ): float {
		if ( ! is_numeric( $value ) ) {
			return 0.0;
		}

		return (float) $value;
	}

	/**
	 * Sanitize a meta value into plain text.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_text_meta( $value ): string {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitize one submitted field value according to its definition.
	 *
	 * Returns an empty string for "not set", which the caller stores by
	 * deleting the meta row.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Raw submitted value.
	 * @return string
	 */
	public static function sanitize_field_value( array $field, $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		switch ( $field['type'] ) {
			case 'number':
				if ( ! is_numeric( $value ) ) {
					return '';
				}

				$number = (float) $value;

				if ( isset( $field['min'] ) && $number < (float) $field['min'] ) {
					$number = (float) $field['min'];
				}

				return self::format_number( $number );

			case 'select':
				$slug = sanitize_key( $value );

				return isset( $field['options'][ $slug ] ) ? $slug : '';

			case 'date':
				$time = strtotime( $value );

				return $time ? gmdate( 'Y-m-d', $time ) : '';

			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Read every field of an item into a key => stored value map.
	 *
	 * @param int    $item_id Item post ID.
	 * @param string $kind    Collection kind slug.
	 * @return array<string, string>
	 */
	public static function get_values( int $item_id, string $kind ): array {
		$values = array();

		foreach ( self::get_fields_for_kind( $kind ) as $field ) {
			$values[ $field['key'] ] = (string) get_post_meta( $item_id, $field['key'], true );
		}

		return $values;
	}

	/**
	 * Store the submitted field values for an item.
	 *
	 * Empty values delete the meta row so that "unset" and "zero" stay
	 * distinguishable.
	 *
	 * @param int    $item_id Item post ID.
	 * @param string $kind    Collection kind slug.
	 * @param array  $source  Unslashed request data, usually $_POST.
	 */
	public static function save_values( int $item_id, string $kind, array $source ): void {
		foreach ( self::get_fields_for_kind( $kind ) as $field ) {
			$input = 'coll_field_' . $field['key'];
			$value = self::sanitize_field_value( $field, $source[ $input ] ?? '' );

			if ( '' === $value ) {
				delete_post_meta( $item_id, $field['key'] );
				continue;
			}

			update_post_meta( $item_id, $field['key'], $value );
		}
	}

	/**
	 * Format a float for storage, trimming trailing zeros (12.50 → "12.5").
	 *
	 * @param float $value Value to format.
	 */
	public static function format_number( float $value ): string {
		$formatted = rtrim( rtrim( number_format( $value, 4, '.', '' ), '0' ), '.' );

		return '' === $formatted ? '0' : $formatted;
	}

	/**
	 * Format an amount for display in the collection's currency.
	 *
	 * @param float  $amount   The amount.
	 * @param string $currency Three-letter currency code.
	 */
	public static function format_money( float $amount, string $currency ): string {
		$symbols = array(
			'EUR' => '€',
			'USD' => '$',
			'GBP' => '£',
			'CHF' => 'CHF',
			'JPY' => '¥',
		);

		$formatted = number_format_i18n( $amount, 2 );

		if ( isset( $symbols[ $currency ] ) ) {
			return $symbols[ $currency ] . ' ' . $formatted;
		}

		return $formatted . ' ' . $currency;
	}

	/**
	 * Display value of a single field, resolved through its options where
	 * applicable and suffixed with its unit.
	 *
	 * @param array  $field    Field definition.
	 * @param string $value    Stored value.
	 * @param string $currency Currency code for money fields.
	 */
	public static function format_field_value( array $field, string $value, string $currency = '' ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( 'select' === $field['type'] ) {
			return $field['options'][ $value ] ?? $value;
		}

		if ( 'date' === $field['type'] ) {
			$time = strtotime( $value );

			return $time ? date_i18n( (string) get_option( 'date_format' ), $time ) : $value;
		}

		if ( 'number' === $field['type'] ) {
			if ( ! empty( $field['money'] ) ) {
				return self::format_money( (float) $value, $currency );
			}

			$number = self::format_number( (float) $value );

			if ( self::YEAR_META_KEY === $field['key'] ) {
				return $number;
			}

			return isset( $field['unit'] ) ? $number . ' ' . $field['unit'] : $number;
		}

		return $value;
	}

	/**
	 * Load an item post, or null when the ID is not an item.
	 *
	 * @param int $item_id Item post ID.
	 */
	public static function get( int $item_id ): ?\WP_Post {
		if ( ! $item_id ) {
			return null;
		}

		$post = get_post( $item_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Fetch items, optionally narrowed to one collection.
	 *
	 * Filtering and sorting happen in PHP: a personal catalog is small enough
	 * that one query plus an in-memory pass is simpler and more capable than
	 * stacking meta queries — the free-text search covers titles, notes and
	 * every field value at once.
	 *
	 * @param array $args Query arguments: 'collection' (post ID, 0 for every
	 *                    collection of the current user), 'search' (free text),
	 *                    'status' (status slug, '' for any), 'tag' (term slug,
	 *                    '' for any) and 'orderby' (one of recent, title,
	 *                    year_desc, year_asc, value_desc).
	 * @return \WP_Post[]
	 */
	public static function query( array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'collection' => 0,
				'search'     => '',
				'status'     => '',
				'tag'        => '',
				'orderby'    => 'recent',
			)
		);

		$query_args = array(
			'post_type'        => self::POST_TYPE,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
			'numberposts'      => -1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		);

		if ( $args['collection'] ) {
			$query_args['post_parent'] = absint( $args['collection'] );
		} else {
			// Ownership is a property of the collection, so scoping to the
			// user's collections is enough — no author filter on the items.
			$query_args['post_parent__in'] = self::get_collection_ids_for_current_user();

			if ( empty( $query_args['post_parent__in'] ) ) {
				return array();
			}
		}

		if ( '' !== $args['tag'] ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Personal-scale catalog, one taxonomy term.
				array(
					'taxonomy' => self::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $args['tag'],
				),
			);
		}

		$items = get_posts( $query_args );

		if ( '' !== $args['status'] ) {
			$items = array_values(
				array_filter(
					$items,
					function ( $item ) use ( $args ) {
						return self::get_status( $item->ID ) === $args['status'];
					}
				)
			);
		}

		if ( '' !== $args['search'] ) {
			$items = self::filter_by_search( $items, $args['search'] );
		}

		return self::sort( $items, (string) $args['orderby'] );
	}

	/**
	 * The IDs of every collection owned by the current user.
	 *
	 * @return int[]
	 */
	public static function get_collection_ids_for_current_user(): array {
		return array_map(
			'absint',
			get_posts(
				array(
					'post_type'        => Collection::POST_TYPE,
					'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
					'author'           => get_current_user_id(),
					'numberposts'      => -1,
					'fields'           => 'ids',
					'suppress_filters' => false,
				)
			)
		);
	}

	/**
	 * Keep the items whose title, notes, tags or any field value contain the
	 * search term.
	 *
	 * @param \WP_Post[] $items  Items to filter.
	 * @param string     $search Search term.
	 * @return \WP_Post[]
	 */
	private static function filter_by_search( array $items, string $search ): array {
		$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search ) : strtolower( $search );

		// Only the plugin's own fields — searching raw post meta would match
		// attachment IDs and whatever else WordPress keeps there.
		$keys = array_merge(
			wp_list_pluck( self::get_common_fields(), 'key' ),
			array_keys( Schema::get_all_fields() )
		);

		return array_values(
			array_filter(
				$items,
				function ( $item ) use ( $needle, $keys ) {
					$haystack = array(
						$item->post_title,
						$item->post_content,
					);

					foreach ( $keys as $key ) {
						$value = get_post_meta( $item->ID, $key, true );

						if ( is_scalar( $value ) ) {
							$haystack[] = (string) $value;
						}
					}

					foreach ( self::get_tags( $item->ID ) as $tag ) {
						$haystack[] = $tag->name;
					}

					$haystack = implode( ' ', $haystack );
					$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );

					return false !== strpos( $haystack, $needle );
				}
			)
		);
	}

	/**
	 * Sort items by one of the supported orders.
	 *
	 * @param \WP_Post[] $items   Items to sort.
	 * @param string     $orderby Order key.
	 * @return \WP_Post[]
	 */
	public static function sort( array $items, string $orderby ): array {
		switch ( $orderby ) {
			case 'title':
				usort(
					$items,
					function ( $a, $b ) {
						return strnatcasecmp( $a->post_title, $b->post_title );
					}
				);
				break;

			case 'year_asc':
			case 'year_desc':
				$direction = 'year_asc' === $orderby ? 1 : -1;

				usort(
					$items,
					function ( $a, $b ) use ( $direction ) {
						$year_a = (int) get_post_meta( $a->ID, self::YEAR_META_KEY, true );
						$year_b = (int) get_post_meta( $b->ID, self::YEAR_META_KEY, true );

						// Items without a year sort last, in either direction.
						if ( 0 === $year_a && 0 !== $year_b ) {
							return 1;
						}

						if ( 0 !== $year_a && 0 === $year_b ) {
							return -1;
						}

						if ( $year_a === $year_b ) {
							return strnatcasecmp( $a->post_title, $b->post_title );
						}

						return ( $year_a < $year_b ? -1 : 1 ) * $direction;
					}
				);
				break;

			case 'value_desc':
				usort(
					$items,
					function ( $a, $b ) {
						$value_a = (float) get_post_meta( $a->ID, self::VALUE_META_KEY, true );
						$value_b = (float) get_post_meta( $b->ID, self::VALUE_META_KEY, true );

						if ( $value_a === $value_b ) {
							return strnatcasecmp( $a->post_title, $b->post_title );
						}

						return $value_a < $value_b ? 1 : -1;
					}
				);
				break;
		}

		return $items;
	}

	/**
	 * The sort orders offered in the toolbar, as key => label.
	 *
	 * @return array<string, string>
	 */
	public static function get_sort_options(): array {
		return array(
			'recent'     => __( 'Recently added', 'collectibles' ),
			'title'      => __( 'Name', 'collectibles' ),
			'year_desc'  => __( 'Year, newest first', 'collectibles' ),
			'year_asc'   => __( 'Year, oldest first', 'collectibles' ),
			'value_desc' => __( 'Most valuable', 'collectibles' ),
		);
	}

	/**
	 * The status of an item, defaulting to owned.
	 *
	 * @param int $item_id Item post ID.
	 */
	public static function get_status( int $item_id ): string {
		$status = (string) get_post_meta( $item_id, self::STATUS_META_KEY, true );

		return '' === $status ? Schema::STATUS_OWNED : $status;
	}

	/**
	 * The quantity of an item, defaulting to one.
	 *
	 * @param int $item_id Item post ID.
	 */
	public static function get_quantity( int $item_id ): int {
		$quantity = get_post_meta( $item_id, self::QUANTITY_META_KEY, true );

		if ( '' === $quantity || ! is_numeric( $quantity ) ) {
			return 1;
		}

		return max( 0, (int) $quantity );
	}

	/**
	 * The tags of an item.
	 *
	 * @param int $item_id Item post ID.
	 * @return \WP_Term[]
	 */
	public static function get_tags( int $item_id ): array {
		$terms = get_the_terms( $item_id, self::TAXONOMY );

		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Totals across a set of items: how many, how many pieces, what they cost
	 * and what they are worth. Only physically held items count towards the
	 * money and piece totals.
	 *
	 * @param \WP_Post[] $items Items to summarize.
	 * @return array{items:int,pieces:int,paid:float,value:float,wanted:int}
	 */
	public static function summarize( array $items ): array {
		$summary = array(
			'items'  => count( $items ),
			'pieces' => 0,
			'paid'   => 0.0,
			'value'  => 0.0,
			'wanted' => 0,
		);

		$owned_statuses = Schema::get_owned_statuses();

		foreach ( $items as $item ) {
			$status = self::get_status( $item->ID );

			if ( Schema::STATUS_WANTED === $status ) {
				++$summary['wanted'];
			}

			if ( ! in_array( $status, $owned_statuses, true ) ) {
				continue;
			}

			$quantity = self::get_quantity( $item->ID );
			$paid     = (float) get_post_meta( $item->ID, self::PAID_META_KEY, true );
			$value    = (float) get_post_meta( $item->ID, self::VALUE_META_KEY, true );

			$summary['pieces'] += $quantity;
			$summary['paid']   += $paid * $quantity;
			$summary['value']  += $value * $quantity;
		}

		return $summary;
	}

	/**
	 * Every photo attached to an item, primary image first.
	 *
	 * @param int $item_id Item post ID.
	 * @return int[]
	 */
	public static function get_photo_ids( int $item_id ): array {
		$attachments = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'post_parent'      => $item_id,
				'post_mime_type'   => 'image',
				'numberposts'      => -1,
				'orderby'          => 'menu_order ID',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);

		$attachments = array_map( 'absint', $attachments );
		$primary     = (int) get_post_thumbnail_id( $item_id );

		if ( $primary && in_array( $primary, $attachments, true ) ) {
			$attachments = array_values( array_diff( $attachments, array( $primary ) ) );
			array_unshift( $attachments, $primary );
		}

		return $attachments;
	}

	/**
	 * Handle photo uploads submitted with an item form.
	 *
	 * The first successfully uploaded image becomes the item's featured image
	 * when it does not have one yet.
	 *
	 * @param int    $item_id    Item post ID.
	 * @param string $input_name Name of the file input.
	 * @return string Error message, or '' when everything uploaded.
	 */
	public static function handle_photo_uploads( int $item_id, string $input_name = 'coll_photos' ): string {
		if ( empty( $_FILES[ $input_name ]['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers verify the nonce before saving.
			return '';
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return __( 'You do not have permission to upload photos.', 'collectibles' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$files = self::normalize_files_array( $input_name );
		$error = '';

		foreach ( $files as $file ) {
			if ( empty( $file['name'] ) ) {
				continue;
			}

			// media_handle_upload() reads straight from $_FILES, so hand it a
			// single-file slot it can find.
			$_FILES[ $input_name . '_single' ] = $file; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers verify the nonce before saving.

			$attachment_id = media_handle_upload( $input_name . '_single', $item_id );

			unset( $_FILES[ $input_name . '_single' ] );

			if ( is_wp_error( $attachment_id ) ) {
				$error = $attachment_id->get_error_message();
				continue;
			}

			if ( ! get_post_thumbnail_id( $item_id ) ) {
				set_post_thumbnail( $item_id, $attachment_id );
			}
		}

		return $error;
	}

	/**
	 * Turn PHP's per-property multi-file $_FILES structure into a list of
	 * single-file arrays.
	 *
	 * @param string $input_name Name of the file input.
	 * @return array<int, array>
	 */
	private static function normalize_files_array( string $input_name ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Callers verify the nonce before saving.
		$upload = isset( $_FILES[ $input_name ] ) ? $_FILES[ $input_name ] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Values are passed to media_handle_upload(), which validates them.
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! isset( $upload['name'] ) ) {
			return array();
		}

		if ( ! is_array( $upload['name'] ) ) {
			return array( $upload );
		}

		$files = array();

		foreach ( array_keys( $upload['name'] ) as $index ) {
			$files[] = array(
				'name'     => $upload['name'][ $index ],
				'type'     => $upload['type'][ $index ] ?? '',
				'tmp_name' => $upload['tmp_name'][ $index ] ?? '',
				'error'    => $upload['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $upload['size'][ $index ] ?? 0,
			);
		}

		return $files;
	}

	/**
	 * Permanently delete every photo attached to an item.
	 *
	 * @param int $item_id Item post ID.
	 */
	public static function delete_photos( int $item_id ): void {
		foreach ( self::get_photo_ids( $item_id ) as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}
}
