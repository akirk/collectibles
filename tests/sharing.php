<?php
/**
 * Public sharing checks: wp --url=alex.kirk.at --user=<editor> eval-file tests/sharing.php
 *
 * Uses temporary posts, no HTTP, and removes fixtures in finally.
 *
 * @package Collectibles
 */

use Collectibles\Collection;
use Collectibles\Item;
use Collectibles\Schema;
use Collectibles\Sharing;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'edit_posts' ) ) {
	throw new RuntimeException( 'Run through WP-CLI as an editor.' );
}
$coll_user   = get_current_user_id();
$coll_posts  = array();
$coll_checks = 0;
$coll_assert = static function ( $condition, $message ) use ( &$coll_checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$coll_checks;
};
$coll_render = static function ( $data ) {
	$coll_public_data    = $data;
	$coll_public_preview = false;
	ob_start();
	try {
		require dirname( __DIR__ ) . '/templates/_public-page.php';
		return ob_get_contents();
	} finally {
		ob_end_clean();
	}
};
try {
	$coll_base = (string) wp_parse_url( Collectibles\App::get_url(), PHP_URL_PATH );
	foreach ( array( 'lego', 'lego/brick' ) as $coll_path ) {
		$coll_route = Sharing::get_public_route( trailingslashit( $coll_base ) . $coll_path . '/' );
		$coll_assert( 'lego' === $coll_route['slug'], 'Public collection and item paths must match without login.' );
	}
	foreach ( array( '', 'settings', 'search', 'origins', 'new', '123', '123/edit', 'collection/new', 'lego/brick/edit' ) as $coll_path ) {
		$coll_assert( null === Sharing::get_public_route( trailingslashit( $coll_base ) . $coll_path ), 'Private management paths must stay outside public routing.' );
	}
	$coll_assert( null === Sharing::get_public_route( '/collections/lego/' ), 'The old public prefix must not match.' );
	foreach ( array( Schema::KIND_BANKNOTES, Schema::KIND_BRICKS ) as $coll_kind ) {
		$coll_id      = wp_insert_post(
			array(
				'post_type'    => Collection::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Sharing checks ' . wp_generate_uuid4(),
				'post_content' => 'SECRET_COLLECTION_NOTES',
			)
		);
		$coll_posts[] = $coll_id;
		update_post_meta( $coll_id, 'kind', $coll_kind );
	}
	$coll_collection = $coll_posts[0];
	$coll_other      = $coll_posts[1];
	foreach ( array( 'publish', 'publish', 'draft', 'private' ) as $coll_status ) {
		$coll_id      = wp_insert_post(
			array(
				'post_type'    => Item::POST_TYPE,
				'post_status'  => $coll_status,
				'post_parent'  => $coll_collection,
				'post_title'   => 'Shared piece ' . wp_generate_uuid4(),
				'post_content' => 'SECRET_ITEM_NOTES',
			)
		);
		$coll_posts[] = $coll_id;
	}
	$coll_item       = $coll_posts[2];
	$coll_unselected = $coll_posts[3];
	$coll_foreign    = wp_insert_post(
		array(
			'post_type'   => Item::POST_TYPE,
			'post_status' => 'publish',
			'post_parent' => $coll_other,
			'post_title'  => 'SECRET_FOREIGN_ITEM',
		)
	);
	$coll_posts[]    = $coll_foreign;
	foreach ( array(
		'serial_number'    => 'SECRET_SERIAL',
		'storage_location' => 'SECRET_STORAGE',
		'acquired_from'    => 'SECRET_ACQUISITION',
		'purchase_price'   => '98765.43',
		'estimated_value'  => '98766.43',
		'denomination'     => '10 Example Dollars',
		'private_extra'    => 'SECRET_EXTRA',
	) as $coll_key => $coll_value ) {
		update_post_meta( $coll_item, $coll_key, $coll_value );
	}
	$coll_term    = wp_insert_term( 'SECRET_TAG_' . wp_generate_uuid4(), Item::TAXONOMY );
	$coll_term_id = $coll_term['term_id'];
	wp_set_object_terms( $coll_item, array( $coll_term_id ), Item::TAXONOMY );
	$coll_photo   = wp_insert_attachment(
		array(
			'post_title'     => 'SECRET_PHOTO_TITLE',
			'post_excerpt'   => 'SECRET_CAPTION',
			'post_parent'    => $coll_item,
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
			'guid'           => 'https://example.org/shared-test-photo.jpg',
		)
	);
	$coll_posts[] = $coll_photo;
	update_attached_file( $coll_photo, sys_get_temp_dir() . '/' . wp_generate_uuid4() . '-shared-test-photo.jpg' );
	update_post_meta( $coll_photo, '_wp_attachment_image_alt', 'SECRET_ALT' );
	$coll_foreign_photo = wp_insert_attachment(
		array(
			'post_title'     => 'SECRET_FOREIGN_PHOTO',
			'post_parent'    => $coll_foreign,
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
			'guid'           => 'https://example.org/foreign-test-photo.jpg',
		)
	);
	$coll_posts[]       = $coll_foreign_photo;
	update_post_meta( $coll_item, Item::BACK_META_KEY, $coll_foreign_photo );
	$coll_slug = get_post( $coll_collection )->post_name;
	$coll_assert( 'private' === Sharing::get_settings( $coll_collection )['mode'] && null === Sharing::get_page( $coll_slug ), 'Collections must default to private.' );
	$coll_settings = array(
		'mode'        => 'selected',
		'slug'        => 'sharing-test-' . wp_generate_uuid4(),
		'description' => 'A public overview',
		'item_ids'    => array( $coll_item ),
	);
	Sharing::set_item_shared( $coll_collection, $coll_item, true );
	$coll_preview = Sharing::preview( $coll_collection, $coll_settings );
	$coll_assert( ! is_wp_error( $coll_preview ) && 1 === count( $coll_preview['items'] ), 'An owner must be able to preview selected items.' );
	$coll_assert( 'private' === Sharing::get_settings( $coll_collection )['mode'] && get_post( $coll_collection )->post_name === $coll_slug, 'Preview must not publish or change the slug.' );
	// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- Snapshot and restore superglobals for a nonce-verified form fixture.
	$coll_preview_form = static function ( $id, $settings ) {
		global $wp_app_route;
		$previous_route            = $wp_app_route;
		$previous_post             = $_POST;
		$previous_request          = $_REQUEST;
		$previous_method           = $_SERVER['REQUEST_METHOD'] ?? '';
		$wp_app_route              = array( 'params' => array( 'id' => $id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WpApp routing global.
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			'_wpnonce'         => wp_create_nonce( 'coll_sharing_' . $id ),
			'coll_action'      => 'preview',
			'coll_mode'        => $settings['mode'],
			'coll_slug'        => $settings['slug'],
			'coll_description' => $settings['description'],
			'coll_item_ids'    => $settings['item_ids'],
		);
		$_REQUEST                  = $_POST;
		ob_start();
		try {
			require dirname( __DIR__ ) . '/templates/collection-sharing.php';
			return ob_get_contents();
		} finally {
			ob_end_clean();
			$wp_app_route              = $previous_route; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restore WpApp routing global.
			$_POST                     = $previous_post;
			$_REQUEST                  = $previous_request;
			$_SERVER['REQUEST_METHOD'] = $previous_method;
		}
	};
	// phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	$coll_preview_html = $coll_preview_form( $coll_collection, $coll_settings );
	$coll_assert( false !== strpos( $coll_preview_html, 'value="save"' ) && false !== strpos( $coll_preview_html, 'name="_wpnonce"' ), 'Preview must provide an authorized apply-settings form.' );
	$coll_assert( false === strpos( $coll_preview_html, 'SECRET_' ) && 'private' === Sharing::get_settings( $coll_collection )['mode'], 'The preview form must use the public projection without publishing.' );
	$coll_result = Sharing::save( $coll_collection, $coll_settings );
	$coll_assert( ! is_wp_error( $coll_result ), 'Owner must be able to save sharing settings.' );
	$coll_slug = $coll_result['slug'];
	$coll_assert( false !== strpos( Sharing::get_url( get_post( $coll_collection ) ), '/collectibles/' . $coll_slug . '/' ), 'The public URL must use the chosen slug.' );
	$coll_item_slug  = get_post( $coll_item )->post_name;
	$coll_other_slug = get_post( $coll_unselected )->post_name;
	wp_set_current_user( 0 );
	$coll_page = Sharing::get_page( $coll_slug );
	$coll_assert( get_userdata( $coll_user )->display_name === $coll_page['owner'], 'Public pages must show the owner display name.' );
	$coll_directory = Sharing::get_directory();
	$coll_urls      = array_column( $coll_directory, 'url' );
	$coll_assert( in_array( $coll_page['url'], $coll_urls, true ), 'Shared collections must appear in the directory.' );
	$coll_assert( ! in_array( Sharing::get_url( get_post( $coll_other ) ), $coll_urls, true ), 'Private collections must stay out of the directory.' );
	foreach ( $coll_directory as $coll_entry ) {
		$coll_assert( array( 'title', 'owner', 'url', 'icon' ) === array_keys( $coll_entry ), 'Directory summaries must contain only public fields.' );
	}

	$coll_assert( 1 === count( $coll_page['items'] ) && 'A public overview' === $coll_page['description'], 'Guests must see only the selected items and public description.' );
	$coll_assert( null !== Sharing::get_page( $coll_slug, $coll_item_slug ), 'A selected item must have a public detail URL.' );
	$coll_assert( null === Sharing::get_page( $coll_slug, $coll_other_slug ), 'An unselected item detail URL must return no data.' );
	$coll_assert( null === Sharing::get_page( $coll_slug, get_post( $coll_foreign )->post_name ), 'Cross-collection item URLs must return no data.' );
	$coll_assert( null === Sharing::get_page( '%' ) && null === Sharing::get_page( $coll_slug, '%' ), 'Malformed slugs must not become unrestricted queries.' );
	$coll_assert( is_wp_error( Sharing::save( $coll_collection, $coll_settings ) ) && is_wp_error( Sharing::preview( $coll_collection, $coll_settings ) ), 'Guests must not save or preview private sharing choices.' );
	$coll_assert( 1 === $coll_page['version'] && Schema::KIND_BANKNOTES === $coll_page['kind'], 'Public JSON must identify its schema version and collection kind.' );
	$coll_public_fields = array_column( $coll_page['items'][0]['fields'], 'raw', 'key' );
	$coll_assert( '10 Example Dollars' === $coll_public_fields['denomination'] && ! isset( $coll_public_fields['serial_number'] ), 'Machine-readable fields must use the same public allowlist.' );
	$coll_json = wp_json_encode( $coll_page );
	$coll_html = $coll_render( $coll_page );
	$coll_assert( false !== strpos( $coll_html, 'id="coll-compare-form"' ) && false !== strpos( $coll_html, 'id="coll-compare-data"' ), 'Guests must be able to compare directly on the public collection page.' );
	$coll_assert( false === strpos( $coll_preview_html, 'id="coll-compare-form"' ), 'Sharing previews must omit comparison controls.' );
	$coll_detail_html = $coll_render( Sharing::get_page( $coll_slug, $coll_item_slug ) );
	$coll_assert( false === strpos( $coll_detail_html, 'id="coll-compare-form"' ), 'Individual item pages must omit collection comparison controls.' );
	foreach ( array( 'SECRET_', '98765.43', '98766.43', 'foreign-test-photo.jpg', 'purchase_price', 'estimated_value', 'storage_location', 'serial_number' ) as $coll_secret ) {
		$coll_assert( false === strpos( $coll_json . $coll_html, $coll_secret ), 'Private data escaped the public projection: ' . $coll_secret );
	}
	$coll_assert( false !== strpos( $coll_html, '10 Example Dollars' ) && false !== strpos( $coll_html, 'shared-test-photo.jpg' ), 'Descriptive fields and own photos must remain visible.' );
	$coll_assert( false === strpos( $coll_html, 'wp-app-masterbar' ) && false === strpos( $coll_html, '/collectibles/collection/' ), 'Public pages must omit private app navigation.' );
	$coll_assert( false !== strpos( $coll_html, 'noindex,nofollow' ), 'Shared pages must discourage indexing.' );
	$coll_assert( false !== strpos( $coll_html, 'id="coll-public-gallery"' ) && false !== strpos( $coll_html, 'id="coll-public-expanded"' ), 'Public collections offer gallery and detailed views.' );
	$coll_assert( false === strpos( $coll_html, '<details' ) && false === strpos( $coll_html, '<summary' ), 'Public items must not use per-item Details controls.' );

	$coll_assert( false === strpos( $coll_html, 'data-coll-share-item' ) && false === strpos( $coll_html, 'data-coll-flip' ), 'Public cards omit private controls and do not flip to unrelated attachments.' );
	$coll_back    = wp_insert_attachment(
		array(
			'post_title'     => 'SECRET_BACK_TITLE',
			'post_parent'    => $coll_item,
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
			'guid'           => 'https://example.org/shared-back.jpg',
		)
	);
	$coll_posts[] = $coll_back;
	update_attached_file( $coll_back, sys_get_temp_dir() . '/' . wp_generate_uuid4() . '-shared-back.jpg' );
	update_post_meta( $coll_item, Item::FRONT_META_KEY, $coll_photo );
	update_post_meta( $coll_item, Item::BACK_META_KEY, $coll_back );
	$coll_html = $coll_render( Sharing::get_page( $coll_slug ) );
	$coll_assert( false !== strpos( $coll_html, 'data-coll-flip' ) && false !== strpos( $coll_html, 'shared-back.jpg' ) && false === strpos( $coll_html, 'SECRET_BACK_TITLE' ), 'Public cards can flip between their own projected front and back photos without leaking attachment metadata.' );
	wp_set_current_user( $coll_user );
	$coll_card_item          = get_post( $coll_item );
	$coll_card_collection_id = $coll_collection;
	$coll_card_kind          = Schema::KIND_BANKNOTES;
	$coll_card_currency      = 'EUR';
	ob_start();
	require dirname( __DIR__ ) . '/templates/_item-card.php';
	$coll_internal_html = ob_get_clean();
	$coll_assert( false !== strpos( $coll_internal_html, 'data-coll-flip' ) && false !== strpos( $coll_internal_html, 'data-coll-share-item' ), 'Internal cards use the shared photo layout while retaining their owner controls.' );
	wp_set_current_user( 0 );
	$coll_response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/coll_item/' . $coll_item ) );
	$coll_assert( 200 !== $coll_response->get_status(), 'Sharing must not open the private item REST endpoint.' );
	wp_set_current_user( $coll_user );
	$coll_bad             = $coll_settings;
	$coll_bad['item_ids'] = array( $coll_foreign );
	$coll_assert( is_wp_error( Sharing::save( $coll_collection, $coll_bad ) ), 'Owners must not select items from another collection.' );
	$coll_bad['item_ids'] = array();
	$coll_bad['slug']     = '   ';
	$coll_assert( is_wp_error( Sharing::save( $coll_collection, $coll_bad ) ), 'An empty slug must be rejected.' );
	$coll_collision = Sharing::save(
		$coll_other,
		array(
			'mode'     => 'selected',
			'slug'     => $coll_slug,
			'item_ids' => array(),
		)
	);
	$coll_assert( ! is_wp_error( $coll_collision ) && $coll_slug !== $coll_collision['slug'], 'Slug collisions must resolve to different public URLs.' );
	$coll_empty = Sharing::get_page( $coll_collision['slug'] );
	$coll_assert( array() === $coll_empty['items'], 'An empty selection must never mean all items.' );
	$coll_legacy         = Sharing::get_settings( $coll_collection );
	$coll_legacy['mode'] = 'all';
	update_post_meta( $coll_collection, Sharing::META_KEY, $coll_legacy );
	$coll_assert( 2 === count( Sharing::get_page( $coll_slug )['items'] ) && 'selected' === Sharing::get_settings( $coll_collection )['mode'], 'Legacy all-items sharing must freeze to its current published items.' );
	$coll_future  = wp_insert_post(
		array(
			'post_type'   => Item::POST_TYPE,
			'post_status' => 'publish',
			'post_parent' => $coll_collection,
			'post_title'  => 'Future shared item',
		)
	);
	$coll_posts[] = $coll_future;
	$coll_assert( 2 === count( Sharing::get_page( $coll_slug )['items'] ), 'Future additions must start private after legacy sharing is converted.' );
	$coll_settings['mode']     = 'selected';
	$coll_settings['item_ids'] = array();
	Sharing::save( $coll_collection, $coll_settings );
	$coll_assert( array() === Sharing::get_page( $coll_slug )['items'] && null === Sharing::get_page( $coll_slug, $coll_item_slug ), 'Deselecting an item must immediately revoke its detail URL.' );
	$coll_settings['mode']  = 'selected';
	$coll_settings['slug'] .= '-renamed';
	Sharing::save( $coll_collection, $coll_settings );
	$coll_assert( null === Sharing::get_page( $coll_slug ) && null !== Sharing::get_page( $coll_settings['slug'] ), 'Slug changes must retire the old public URL.' );
	$coll_settings['mode'] = 'private';
	Sharing::save( $coll_collection, $coll_settings );
	$coll_assert( null === Sharing::get_page( $coll_settings['slug'] ) && null === Sharing::get_page( $coll_settings['slug'], $coll_item_slug ), 'Unpublishing must revoke collection and item URLs immediately.' );
	wp_update_post(
		array(
			'ID'          => $coll_collection,
			'post_status' => 'draft',
		)
	);
	$coll_settings['mode'] = 'selected';
	$coll_assert( is_wp_error( Sharing::save( $coll_collection, $coll_settings ) ), 'Draft collections cannot become public through sharing settings.' );
	wp_update_post(
		array(
			'ID'          => $coll_collection,
			'post_status' => 'publish',
		)
	);
	$coll_assert( 'SECRET_ITEM_NOTES' === get_post( $coll_item )->post_content && 'SECRET_SERIAL' === get_post_meta( $coll_item, 'serial_number', true ), 'Sharing must not alter private item data.' );
	WP_CLI::success( $coll_checks . ' public sharing checks passed.' );
} finally {
	wp_set_current_user( $coll_user );
	if ( isset( $coll_term_id ) ) {
		wp_delete_term( $coll_term_id, Item::TAXONOMY );
	}
	foreach ( array_reverse( $coll_posts ) as $coll_id ) {
		wp_delete_post( $coll_id, true );
	}
}
