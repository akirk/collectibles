<?php
/**
 * Offline Numista provenance and form checks.
 *
 * Run: wp --url=alex.kirk.at --user=<editor> eval-file tests/numista-sources.php
 * Creates temporary posts, intercepts HTTP and removes its fixtures.
 *
 * @package Collectibles
 */

use Collectibles\Abilities;
use Collectibles\Collection;
use Collectibles\Item;
use Collectibles\Numista;
use Collectibles\Schema;
use Collectibles\Sharing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'edit_posts' ) ) {
	throw new RuntimeException( 'Run through WP-CLI as an editor.' );
}

if ( ! defined( 'DOING_AJAX' ) ) {
	define( 'DOING_AJAX', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Test the registered WordPress AJAX handler.
}

$coll_posts          = array();
$coll_checks         = 0;
$coll_original_post  = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserve request globals for an offline form fixture.
$coll_original_route = $GLOBALS['wp_app_route'] ?? null;
$coll_assert         = static function ( $condition, $message ) use ( &$coll_checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$coll_checks;
};
$coll_http_calls     = 0;
$coll_block_http     = static function () use ( &$coll_http_calls ) {
	++$coll_http_calls;
	return new WP_Error( 'coll_offline', 'No HTTP in source checks.' );
};
add_filter( 'pre_http_request', $coll_block_http );
$coll_fake_key = static function ( $value, $user_id, $key ) {
	return Numista::KEY_META === $key ? 'offline-source-check' : $value;
};
add_filter( 'get_user_metadata', $coll_fake_key, 10, 3 );

$coll_fixture_id   = 999999983;
$coll_cache        = 'coll_numista_' . Numista::get_language() . '_' . $coll_fixture_id;
$coll_issues_cache = 'coll_numista_issues_' . Numista::get_language() . '_' . $coll_fixture_id;



try {
	$coll_collection = wp_insert_post(
		array(
			'post_type'   => Collection::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Source checks ' . wp_generate_uuid4(),
		)
	);
	$coll_posts[]    = $coll_collection;
	update_post_meta( $coll_collection, 'kind', Schema::KIND_COINS );
	$coll_mapped = Numista::map_type(
		array(
			'id'          => $coll_fixture_id,
			'title'       => 'IMPORTED_TITLE',
			'min_year'    => 1981,
			'value'       => array( 'text' => 'EXEMPT_DENOMINATION' ),
			'composition' => array( 'text' => 'IMPORTED_COMPOSITION' ),
		),
		Schema::KIND_COINS
	);
	$coll_token  = Numista::source_token( $coll_mapped, Schema::KIND_COINS, $coll_collection );
	$coll_assert( null !== Numista::read_source_token( $coll_token, $coll_collection ), 'Lookup source record must verify.' );
	$coll_assert( null === Numista::read_source_token( $coll_token . 'x', $coll_collection ), 'Tampering must invalidate source records.' );
	$coll_assert( null === Numista::read_source_token( $coll_token, $coll_collection + 1 ), 'Source records must be scoped to a collection.' );
	$coll_user = get_current_user_id();
	wp_set_current_user( 0 );
	$coll_assert( null === Numista::read_source_token( $coll_token, $coll_collection ), 'Source records must be scoped to a user.' );
	wp_set_current_user( $coll_user );

	$coll_added = Abilities::add_item(
		array(
			'collection'     => $coll_collection,
			'title'          => $coll_mapped['title'],
			'values'         => $coll_mapped['values'] + array( 'mint' => 'USER_MINT' ),
			'numista_source' => $coll_token,
			'request_id'     => wp_generate_uuid4(),
		)
	);
	$coll_assert( ! is_wp_error( $coll_added ), 'Ability creation must accept signed Numista source records.' );
	$coll_item    = $coll_added['id'];
	$coll_posts[] = $coll_item;
	$coll_keys    = Numista::get_imported_keys( $coll_item );
	$coll_assert( in_array( 'title', $coll_keys, true ) && in_array( 'year', $coll_keys, true ) && ! in_array( 'mint', $coll_keys, true ), 'Only actually imported fields and name must be restricted.' );
	$coll_assert( ! in_array( 'numista_id', $coll_keys, true ), 'The catalogue identifier is not a restricted descriptive field.' );

	$coll_assert( ! in_array( 'denomination', $coll_keys, true ), 'Denomination must be exempt from source restrictions.' );
	$coll_assert( ! isset( Numista::read_source_token( $coll_token, $coll_collection )['denomination'] ), 'New imports must not restrict exempt fields.' );
	$coll_old_sources = get_post_meta( $coll_item, '_coll_numista_sources', true );
	foreach ( array(
		'catalog_number' => 'P#123',
		'origin_code'    => 'at',
		'denomination'   => 'EXEMPT_DENOMINATION',
	) as $coll_exempt_key => $coll_exempt_value ) {
		update_post_meta( $coll_item, $coll_exempt_key, $coll_exempt_value );
		$coll_old_sources[ $coll_exempt_key ] = hash( 'sha256', $coll_exempt_value );
	}
	update_post_meta( $coll_item, '_coll_numista_sources', $coll_old_sources );
	$coll_keys = Numista::get_imported_keys( $coll_item );
	$coll_assert( ! array_intersect( array( 'catalog_number', 'origin_code', 'denomination' ), $coll_keys ), 'Existing imported catalogue number, origin and denomination markers must be removed.' );
	$coll_assert( ! array_intersect( array( 'catalog_number', 'origin_code', 'denomination' ), array_keys( Numista::get_source_hashes( $coll_item ) ) ), 'Exempt fields must not retain confirmation controls through history.' );
	$coll_source = array( 'coll_numista_source' => $coll_token );
	foreach ( $coll_mapped['values'] + array(
		'mint'           => 'USER_MINT',
		'catalog_number' => 'P#123',
		'origin_code'    => 'at',
	) as $coll_key => $coll_value ) {
		$coll_source[ 'coll_field_' . $coll_key ] = $coll_value;
	}
	Item::save_values( $coll_item, Schema::KIND_COINS, $coll_source );
	$coll_assert( Numista::get_imported_keys( $coll_item ) === $coll_keys, 'An unchanged save must retain all source restrictions.' );

	$coll_preview_source = $coll_source + array( 'coll_name' => $coll_mapped['title'] );
	$coll_hashes         = Numista::read_source_token( $coll_token, $coll_collection );
	$coll_states         = Numista::source_statuses( Schema::KIND_COINS, $coll_hashes, $coll_preview_source );
	$coll_assert( 'private' === $coll_states['year']['state'] && 'private' === $coll_states['title']['state'], 'Live checks must recognize unchanged imports.' );
	$coll_preview_source['coll_field_year'] = '1981.0000';
	$coll_preview_source['coll_name']      .= '  ';
	$coll_states                            = Numista::source_statuses( Schema::KIND_COINS, $coll_hashes, $coll_preview_source );
	$coll_assert( 'private' === $coll_states['year']['state'] && 'private' === $coll_states['title']['state'], 'Formatting and trailing whitespace must not count as changes.' );
	$coll_preview_source['coll_field_year'] = '1983';
	$coll_states                            = Numista::source_statuses( Schema::KIND_COINS, $coll_hashes, $coll_preview_source );
	$coll_assert( 'updated' === $coll_states['year']['state'] && 'private' === $coll_states['composition']['state'], 'A live change must affect only its own field.' );
	$coll_preview_source['coll_field_year']  = '';
	$coll_preview_source['coll_numista_own'] = array( 'year', 'composition' );
	$coll_states                             = Numista::source_statuses( Schema::KIND_COINS, $coll_hashes, $coll_preview_source );
	$coll_assert( 'cleared' === $coll_states['year']['state'] && 'confirmed' === $coll_states['composition']['state'], 'Independent confirmation must preserve exact facts, while cleared fields stay empty.' );
	$coll_assert( Numista::get_imported_keys( $coll_item ) === $coll_keys, 'Live source previews must not alter stored provenance.' );

	$coll_photo   = wp_insert_attachment(
		array(
			'post_title'     => 'OWN_PHOTO',
			'post_parent'    => $coll_item,
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
			'guid'           => 'https://example.org/own-source-photo.jpg',
		)
	);
	$coll_posts[] = $coll_photo;
	update_attached_file( $coll_photo, sys_get_temp_dir() . '/' . wp_generate_uuid4() . '-own-source-photo.jpg' );
	$coll_settings = array(
		'mode'        => 'unlisted',
		'slug'        => get_post_field( 'post_name', $coll_collection ),
		'description' => '',
		'new_items'   => 'private',
	);
	Sharing::save( $coll_collection, $coll_settings );
	Sharing::set_item_shared( $coll_collection, $coll_item, true );
	$coll_page = Sharing::get_page( $coll_settings['slug'] );
	$coll_json = wp_json_encode( $coll_page );
	$coll_assert( false === strpos( $coll_json, 'IMPORTED_' ) && false === strpos( $coll_json, '1981' ), 'Public and unlisted projections must exclude imported values, name and original slug.' );
	$coll_assert( false !== strpos( $coll_json, 'USER_MINT' ), 'User-supplied fields must remain visible.' );
	$coll_assert( 1 === count( $coll_page['items'][0]['photos'] ), 'Own photos must remain shareable while imported fields are private.' );

	$coll_assert( 'Title is private' === Sharing::get_item_state( $coll_collection, $coll_item )['warning'], 'Sharing state must warn while the title is still imported.' );
	$coll_render_card = static function ( $item, $collection ) {
		$coll_card_item          = Item::get( $item );
		$coll_card_collection_id = $collection;
		$coll_card_kind          = Schema::KIND_COINS;
		$coll_card_currency      = 'EUR';
		ob_start();
		try {
			require dirname( __DIR__ ) . '/templates/_item-card.php';
			return ob_get_contents();
		} finally {
			ob_end_clean();
		}
	};
	$coll_card_html   = $coll_render_card( $coll_item, $coll_collection );
	$coll_assert( false !== strpos( $coll_card_html, 'title="Title is private"' ) && 1 === preg_match( '/<span[^>]*data-coll-share-warning[^>]*>⚠<\/span>/', $coll_card_html, $coll_warning_icon ) && false === strpos( $coll_warning_icon[0], 'hidden>' ), 'The overview sharing toggle must show the private-title warning and short tooltip.' );
	$coll_public_slug = $coll_page['items'][0]['slug'];
	$coll_assert( null !== Sharing::get_page( $coll_settings['slug'], $coll_public_slug ), 'Opaque public item URLs must resolve.' );
	$coll_assert( null === Sharing::get_page( $coll_settings['slug'], get_post_field( 'post_name', $coll_item ) ), 'Original imported-title URLs must not resolve.' );
	$coll_assert( false === strpos( wp_json_encode( Sharing::preview( $coll_collection, $coll_settings ) ), 'IMPORTED_' ), 'Previews must use the same restricted projection.' );
	$coll_assert( false === strpos( wp_json_encode( Sharing::get_comparison_data( $coll_collection ) ), 'IMPORTED_' ), 'Comparison projections must exclude imported fields too.' );

	$coll_result = Abilities::update_item(
		array(
			'id'         => $coll_item,
			'collection' => $coll_collection,
			'title'      => 'USER_TITLE',
			'values'     => array( 'year' => '1982' ),
		)
	);
	$coll_assert( ! is_wp_error( $coll_result ), 'User changes must save through abilities.' );
	$coll_assert( '' === Sharing::get_item_state( $coll_collection, $coll_item )['warning'], 'Editing the title must clear its sharing warning.' );
	$coll_keys = Numista::get_imported_keys( $coll_item );
	$coll_assert( ! in_array( 'title', $coll_keys, true ) && ! in_array( 'year', $coll_keys, true ) && in_array( 'composition', $coll_keys, true ), 'Changing one field must release only that field.' );
	$coll_page = Sharing::get_page( $coll_settings['slug'], $coll_public_slug );
	$coll_json = wp_json_encode( $coll_page );
	$coll_assert( false !== strpos( $coll_json, 'USER_TITLE' ) && false !== strpos( $coll_json, '1982' ) && false === strpos( $coll_json, 'IMPORTED_COMPOSITION' ), 'User changes must be visible while untouched imported data stays private.' );
	$coll_assert( $coll_public_slug === $coll_page['items'][0]['slug'], 'Public URLs must stay stable after editing.' );
	Abilities::update_item(
		array(
			'id'         => $coll_item,
			'collection' => $coll_collection,
			'values'     => array( 'composition' => '' ),
		)
	);
	$coll_assert( ! in_array( 'composition', Numista::get_imported_keys( $coll_item ), true ), 'Clearing an imported field must clear its source marker.' );


	$coll_saved_source = array();
	foreach ( Item::get_values( $coll_item, Schema::KIND_COINS ) as $coll_saved_key => $coll_saved_value ) {
		$coll_saved_source[ 'coll_field_' . $coll_saved_key ] = $coll_saved_value;
	}
	$coll_saved_source['coll_numista_private'] = array( 'year' );
	Item::save_values( $coll_item, Schema::KIND_COINS, $coll_saved_source );
	$coll_assert( in_array( 'year', Numista::get_imported_keys( $coll_item ), true ), 'A changed value toggled back to private must stay private after saving.' );
	$coll_assert( '1982' === get_post_meta( $coll_item, 'year', true ), 'Making a field private must not change its contents.' );
	unset( $coll_saved_source['coll_numista_private'] );
	$coll_saved_source['coll_numista_own'] = array( 'year' );
	Item::save_values( $coll_item, Schema::KIND_COINS, $coll_saved_source );
	$coll_assert( ! in_array( 'year', Numista::get_imported_keys( $coll_item ), true ) && in_array( 'year', Numista::get_released_keys( $coll_item ), true ), 'A later confirmation must release the field while retaining its toggle history.' );
	$coll_states = Numista::source_statuses( Schema::KIND_COINS, Numista::get_source_hashes( $coll_item ), $coll_saved_source, Numista::get_released_keys( $coll_item ) );
	$coll_assert( 'confirmed' === $coll_states['year']['state'], 'A saved confirmation must remain visible after reloading.' );
	$coll_legacy  = wp_insert_post(
		array(
			'post_type'   => Item::POST_TYPE,
			'post_status' => 'publish',
			'post_parent' => $coll_collection,
			'post_title'  => 'LEGACY_TITLE',
		)
	);
	$coll_posts[] = $coll_legacy;
	update_post_meta( $coll_legacy, 'numista_id', (string) $coll_fixture_id );
	update_post_meta( $coll_legacy, 'year', '1901' );
	wp_update_post(
		array(
			'ID'         => $coll_legacy,
			'post_title' => 'OWN_LEGACY_TITLE',
		)
	);
	$coll_keys = Numista::get_imported_keys( $coll_legacy );
	$coll_assert( ! in_array( 'title', $coll_keys, true ) && in_array( 'year', $coll_keys, true ), 'Legacy values must be captured before the first title edit.' );
	Abilities::update_item(
		array(
			'id'         => $coll_legacy,
			'collection' => $coll_collection,
			'values'     => array( 'year' => '1902' ),
		)
	);
	$coll_assert( ! in_array( 'year', Numista::get_imported_keys( $coll_legacy ), true ), 'Legacy values must become eligible after a real edit.' );

	// Exercise real form rendering using cached responses: no API credentials needed.
	set_transient(
		$coll_cache,
		array(
			'id'       => $coll_fixture_id,
			'title'    => 'REFERENCE_TITLE',
			'min_year' => 1977,
		),
		HOUR_IN_SECONDS
	);
	set_transient(
		$coll_issues_cache,
		array(
			array(
				'id'   => 42,
				'year' => 1978,
			),
			array(
				'id'   => 43,
				'year' => 1979,
			),
		),
		HOUR_IN_SECONDS
	);
	$coll_render_form = static function ( $collection, $post ) {
		$GLOBALS['wp_app_route'] = array( 'params' => array( 'collection_id' => $collection ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WpApp route fixture.
		$_POST                   = $post;
		ob_start();
		try {
			require dirname( __DIR__ ) . '/templates/item-form.php';
			return ob_get_contents();
		} finally {
			ob_end_clean();
		}
	};
	$coll_post        = array(
		'coll_action'      => 'numista_lookup',
		'coll_nonce'       => wp_create_nonce( 'coll_numista_lookup_' . $coll_collection ),
		'coll_numista_ref' => (string) $coll_fixture_id,
	);
	$coll_html        = $coll_render_form( $coll_collection, $coll_post );
	$coll_assert( false !== strpos( $coll_html, 'value="REFERENCE_TITLE"' ) && false !== strpos( $coll_html, 'value="1977"' ), 'A normal lookup must fill the item name and fields.' );
	$coll_assert( false === strpos( $coll_html, 'coll_numista_mode' ) && false === strpos( $coll_html, 'coll-numista-guide' ), 'The mode picker and reference panel must be removed.' );
	$coll_assert( 1 === preg_match( '/name="coll_numista_source" value="([^"]+)"/', $coll_html, $coll_match ) && null !== Numista::read_source_token( html_entity_decode( $coll_match[1] ), $coll_collection ), 'Default lookups must carry signed provenance into saving.' );
	$coll_post['coll_name']       = 'TYPED_TITLE';
	$coll_post['coll_field_year'] = '2001';
	$coll_html                    = $coll_render_form( $coll_collection, $coll_post );
	$coll_assert( false !== strpos( $coll_html, 'value="TYPED_TITLE"' ) && false !== strpos( $coll_html, 'value="1977"' ), 'Lookups must preserve an entered name while filling catalogue facts.' );
	unset( $coll_post['coll_name'], $coll_post['coll_field_year'] );
	$coll_post['coll_numista_issue'] = '42';
	$coll_post['coll_numista_own']   = array( 'year' );
	$coll_html                       = $coll_render_form( $coll_collection, $coll_post );
	$coll_assert( false !== strpos( $coll_html, 'value="REFERENCE_TITLE"' ) && false !== strpos( $coll_html, 'value="1978"' ), 'Lookup must apply the selected issue.' );
	$coll_assert( 1 === preg_match( '/name="coll_numista_source" value="([^"]+)"/', $coll_html, $coll_match ) && null !== Numista::read_source_token( html_entity_decode( $coll_match[1] ), $coll_collection ), 'Fill mode must carry signed source tracking into the save form.' );
	$coll_assert( 1 === preg_match( '/<input[^>]*data-coll-numista-own="year"[^>]*>/', $coll_html, $coll_checkbox ) && false === strpos( $coll_checkbox[0], 'checked=' ), 'A fresh catalogue fill must reset earlier confirmations.' );
	$coll_assert( false !== strpos( $coll_html, 'Private until changed' ) && false !== strpos( $coll_html, 'private, personal collection' ) && false !== strpos( $coll_html, 'your own entries' ), 'The import form must explain personal use and updating imported values before sharing.' );

	// Run real creation requests, stopping at the redirect so the fixtures can be inspected.
	$coll_stop_redirect = static function () {
		throw new RuntimeException( 'coll_test_redirect' );
	};
	foreach ( array( 'manual', 'imported' ) as $coll_create_mode ) {
		$coll_name = 'Source form save ' . $coll_create_mode . ' ' . wp_generate_uuid4();
		$coll_save = array(
			'coll_action'           => 'create_item',
			'coll_nonce'            => wp_create_nonce( 'coll_create_item_' . $coll_collection ),
			'coll_name'             => $coll_name,
			'coll_field_numista_id' => (string) $coll_fixture_id,
			'coll_field_year'       => '1981',
		);
		if ( 'imported' === $coll_create_mode ) {
			$coll_save['coll_numista_source']    = $coll_token;
			$coll_save['coll_field_composition'] = $coll_mapped['values']['composition'];
			$coll_save['coll_numista_own']       = array( 'composition' );
		}
		add_filter( 'wp_redirect', $coll_stop_redirect, -999 );
		try {
			$coll_rendered_save = $coll_render_form( $coll_collection, $coll_save );
			preg_match( '/class="notice notice-error">(.*?)<\/div>/s', $coll_rendered_save, $coll_save_error );
			throw new RuntimeException( 'Creation did not reach its redirect: ' . ( $coll_save_error[1] ?? $coll_create_mode ) );
		} catch ( RuntimeException $coll_exception ) {
			if ( 'coll_test_redirect' !== $coll_exception->getMessage() ) {
				throw $coll_exception;
			}
		} finally {
			remove_filter( 'wp_redirect', $coll_stop_redirect, -999 );
		}
		$coll_created = get_posts(
			array(
				'post_type'   => Item::POST_TYPE,
				'post_parent' => $coll_collection,
				'title'       => $coll_name,
				'numberposts' => 1,
			)
		);
		$coll_assert( 1 === count( $coll_created ), 'The submitted item must have been created.' );
		$coll_new_id   = $coll_created[0]->ID;
		$coll_posts[]  = $coll_new_id;
		$coll_new_keys = Numista::get_imported_keys( $coll_new_id );
		$coll_assert( ! in_array( 'title', $coll_new_keys, true ), 'A name typed before the first save must remain user-supplied.' );
		$coll_assert( ( 'imported' === $coll_create_mode ) === in_array( 'year', $coll_new_keys, true ), 'Only the import request may mark its unchanged year private; a manually entered Numista number must not taint user input.' );
		if ( 'imported' === $coll_create_mode ) {
			$coll_assert( ! in_array( 'composition', $coll_new_keys, true ) && get_post_meta( $coll_new_id, 'composition', true ) === $coll_mapped['values']['composition'], 'Saving an independently confirmed fact must release only that field without changing its value.' );
		}
	}
	$coll_assert( 0 === $coll_http_calls, 'All source and form checks must stay offline.' );
	WP_CLI::success( $coll_checks . ' Numista source checks passed.' );
} finally {
	if ( isset( $coll_user ) ) {
		wp_set_current_user( $coll_user );
	}
	$_POST                   = $coll_original_post;
	$GLOBALS['wp_app_route'] = $coll_original_route; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restore WpApp route.
	remove_filter( 'pre_http_request', $coll_block_http );
	remove_filter( 'get_user_metadata', $coll_fake_key, 10 );
	delete_transient( $coll_cache );
	delete_transient( $coll_issues_cache );
	foreach ( array_reverse( $coll_posts ) as $coll_id ) {
		wp_delete_post( $coll_id, true );
	}
}
