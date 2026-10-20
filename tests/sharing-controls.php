<?php
/**
 * Sharing control checks: wp --url=alex.kirk.at --user=<editor> eval-file tests/sharing-controls.php
 *
 * Temporary posts are removed in finally. AJAX is dispatched in-process.
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
if ( ! defined( 'DOING_AJAX' ) ) {
	define( 'DOING_AJAX', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress AJAX test context.
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
$coll_die    = static function () {
	return static function () {
		throw new RuntimeException( 'coll_ajax_done' );
	};
};
add_filter( 'wp_die_ajax_handler', $coll_die );
// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- Snapshot and restore the AJAX request fixture; the actual handler verifies its nonce.
$coll_ajax = static function ( $input, $method = 'POST' ) {
	$post                      = $_POST;
	$request                   = $_REQUEST;
	$previous_method           = $_SERVER['REQUEST_METHOD'] ?? '';
	$_POST                     = $input;
	$_REQUEST                  = $input;
	$_SERVER['REQUEST_METHOD'] = $method;
	$status                    = 200;
	$status_filter             = static function ( $header, $code ) use ( &$status ) {
		$status = $code;
		return $header;
	};
	add_filter( 'status_header', $status_filter, 10, 2 );
	ob_start();
	try {
		try {
			do_action( 'coll_share_collection' === ( $input['action'] ?? '' ) ? 'wp_ajax_coll_share_collection' : 'wp_ajax_coll_share_item' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Dispatch the registered WordPress AJAX action.
		} catch ( RuntimeException $exception ) {
			if ( 'coll_ajax_done' !== $exception->getMessage() ) {
				throw $exception;
			}
		}
		return array(
			'body'   => json_decode( ob_get_contents(), true ),
			'status' => $status,
		);
	} finally {
		ob_end_clean();
		remove_filter( 'status_header', $status_filter, 10 );
		$_POST                     = $post;
		$_REQUEST                  = $request;
		$_SERVER['REQUEST_METHOD'] = $previous_method;
	}
};
// phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
$coll_render = static function ( $file, $id ) {
	global $wp_app_route;
	$previous     = $wp_app_route;
	$wp_app_route = array( 'params' => array( 'id' => $id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WpApp routing global.
	ob_start();
	try {
		require dirname( __DIR__ ) . '/templates/' . $file;
		return ob_get_contents();
	} finally {
		ob_end_clean();
		$wp_app_route = $previous; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restore routing global.
	}
};
try {
	foreach ( array( Schema::KIND_COINS, Schema::KIND_BRICKS ) as $coll_kind ) {
		$coll_id      = wp_insert_post(
			array(
				'post_type'   => Collection::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Sharing controls ' . wp_generate_uuid4(),
			)
		);
		$coll_posts[] = $coll_id;
		update_post_meta( $coll_id, 'kind', $coll_kind );
	}
	$coll_collection = $coll_posts[0];
	$coll_other      = $coll_posts[1];
	foreach ( array( 'publish', 'publish', 'draft' ) as $coll_status ) {
		$coll_id      = wp_insert_post(
			array(
				'post_type'   => Item::POST_TYPE,
				'post_status' => $coll_status,
				'post_parent' => $coll_collection,
				'post_title'  => 'Switch test item',
			)
		);
		$coll_posts[] = $coll_id;
	}
	$coll_item         = $coll_posts[2];
	$coll_second       = $coll_posts[3];
	$coll_draft        = $coll_posts[4];
	$coll_input        = array(
		'item_id'       => $coll_item,
		'collection_id' => $coll_collection,
		'shared'        => '1',
		'nonce'         => wp_create_nonce( 'coll_share_item_' . $coll_item ),
	);
	$coll_bad          = $coll_input;
	$coll_bad['nonce'] = 'invalid';
	$coll_result       = $coll_ajax( $coll_bad );
	$coll_assert( 403 === $coll_result['status'] && ! $coll_result['body']['success'] && ! Sharing::get_item_state( $coll_collection, $coll_item )['selected'], 'Invalid nonces must not change sharing.' );
	$coll_result = $coll_ajax( $coll_input, 'GET' );
	$coll_assert( 405 === $coll_result['status'] && ! $coll_result['body']['success'], 'GET requests must not change sharing.' );
	$coll_bad           = $coll_input;
	$coll_bad['shared'] = 'yes';
	$coll_assert( 422 === $coll_ajax( $coll_bad )['status'], 'Invalid selection values must be rejected.' );
	$coll_bad                  = $coll_input;
	$coll_bad['collection_id'] = $coll_other;
	$coll_assert( 403 === $coll_ajax( $coll_bad )['status'], 'Cross-collection toggles must be rejected.' );
	$coll_result = $coll_ajax( $coll_input );
	$coll_assert( $coll_result['body']['success'] && $coll_result['body']['data']['selected'] && 'Selected' === $coll_result['body']['data']['label'], 'A private collection must allow selecting an item without claiming it is public.' );
	$coll_assert( 'private' === Sharing::get_settings( $coll_collection )['mode'] && null === Sharing::get_page( get_post( $coll_collection )->post_name ), 'A card switch must not implicitly publish the collection.' );
	$coll_slug = get_post( $coll_collection )->post_name;
	Sharing::save(
		$coll_collection,
		array(
			'mode'        => 'selected',
			'slug'        => $coll_slug,
			'description' => 'Public switch test',
		)
	);
	$coll_assert( 1 === count( Sharing::get_page( $coll_slug )['items'] ), 'Enabling the public page must preserve the card selection.' );
	$coll_result = $coll_ajax( $coll_input );
	$coll_assert( 'Shared' === $coll_result['body']['data']['label'], 'An enabled selected item must be labelled Shared.' );
	$coll_assert( 1 === count( Sharing::get_settings( $coll_collection )['item_ids'] ), 'Repeating a switch-on request must not duplicate selections.' );
	Sharing::set_item_shared( $coll_collection, $coll_second, true );
	$coll_input['shared'] = '0';
	$coll_result          = $coll_ajax( $coll_input );
	$coll_assert( $coll_result['body']['success'] && ! $coll_result['body']['data']['selected'] && 'Private' === $coll_result['body']['data']['label'], 'AJAX switch-off must report the saved private state.' );
	$coll_assert( null === Sharing::get_page( $coll_slug, get_post( $coll_item )->post_name ) && Sharing::get_item_state( $coll_collection, $coll_second )['selected'], 'Switch-off must revoke its detail page while preserving other selections.' );
	Sharing::save(
		$coll_collection,
		array(
			'mode'        => 'selected',
			'slug'        => $coll_slug,
			'description' => 'Changed description',
		)
	);
	$coll_assert( Sharing::get_item_state( $coll_collection, $coll_second )['selected'], 'Saving public page settings must not overwrite selections.' );
	$coll_lock = 'coll_sharing_lock_' . $coll_collection;
	add_option( $coll_lock, time(), '', false );
	try {
		$coll_assert(
			is_wp_error( Sharing::set_item_shared( $coll_collection, $coll_item, true ) ) && is_wp_error(
				Sharing::save(
					$coll_collection,
					array(
						'mode' => 'private',
						'slug' => $coll_slug,
					)
				)
			),
			'Concurrent card and settings updates must use the same lock.'
		);
	} finally {
		delete_option( $coll_lock );
	}
	$coll_assert( is_wp_error( Sharing::set_item_shared( $coll_collection, $coll_draft, true ) ), 'Draft items cannot be selected for sharing.' );
	$coll_html = $coll_render( 'collection.php', $coll_collection );
	$coll_assert( false !== strpos( $coll_html, 'data-coll-share-item="' . $coll_item . '"' ), 'Overview cards must expose sharing switches.' );
	preg_match( '~<header class="topbar">(.*?)</header>~s', $coll_html, $coll_matches );
	$coll_assert( 3 === substr_count( $coll_matches[1], 'class="button' ) && false !== strpos( $coll_matches[1], 'coll-actions-menu' ) && false !== strpos( $coll_matches[1], '/export' ), 'The header must keep Add, Sharing and More visible with secondary actions inside dropdowns.' );
	preg_match_all( '~<details class="coll-actions-menu">(.*?)</details>~s', $coll_html, $coll_menus );
	$coll_assert( 2 === count( $coll_menus[1] ) && false !== strpos( $coll_menus[1][0], '/sharing' ) && false !== strpos( $coll_menus[1][0], 'data-coll-share-all' ) && false === strpos( $coll_menus[1][1], '/sharing' ) && false === strpos( $coll_menus[1][1], 'data-coll-share-all' ), 'Sharing actions belong in their own dropdown, separate from More.' );
	$coll_html = $coll_render( 'collection-sharing.php', $coll_collection );
	$coll_assert( false === strpos( $coll_html, 'value="all"' ) && false === strpos( $coll_html, 'name="coll_item_ids[]"' ), 'Sharing settings must omit all-items mode and the separate item picker.' );

	$coll_assert( false !== strpos( $coll_html, 'name="coll_new_items"' ), 'Sharing settings offer a default for new items.' );
	Sharing::save(
		$coll_collection,
		array(
			'mode'      => 'selected',
			'new_items' => 'shared',
		)
	);
	$coll_new     = wp_insert_post(
		array(
			'post_type'   => Item::POST_TYPE,
			'post_status' => 'publish',
			'post_parent' => $coll_collection,
			'post_title'  => 'Sharing default fixture',
		)
	);
	$coll_posts[] = $coll_new;
	$coll_assert( Sharing::get_item_state( $coll_collection, $coll_new )['selected'], 'New items inherit the shared default.' );
	Sharing::set_item_shared( $coll_collection, $coll_new, false );
	wp_update_post(
		array(
			'ID'         => $coll_new,
			'post_title' => 'Edited fixture',
		)
	);
	$coll_assert( ! Sharing::get_item_state( $coll_collection, $coll_new )['selected'], 'Editing an item preserves its individual choice.' );
	Sharing::save(
		$coll_collection,
		array(
			'mode'      => 'private',
			'new_items' => 'shared',
		)
	);
	$coll_html = $coll_render( 'collection.php', $coll_collection );
	$coll_assert( false === strpos( $coll_html, 'data-coll-share-item=' ), 'Disabled sharing hides all item switches.' );
	$coll_assert( false === strpos( $coll_html, 'coll-public-status' ), 'Private collections must not show a public-page link.' );
	$coll_private = wp_insert_post(
		array(
			'post_type'   => Item::POST_TYPE,
			'post_status' => 'publish',
			'post_parent' => $coll_collection,
			'post_title'  => 'Private default fixture',
		)
	);
	$coll_posts[] = $coll_private;
	$coll_assert( ! Sharing::get_item_state( $coll_collection, $coll_private )['selected'], 'Items created while sharing is disabled stay private.' );
	Sharing::save(
		$coll_collection,
		array(
			'mode'      => 'selected',
			'new_items' => 'private',
		)
	);
	$coll_html = $coll_render( 'collection.php', $coll_collection );
	$coll_assert( (bool) preg_match( '~class="item-card-heading">.*?data-coll-share-item=.*?</div>~s', $coll_html ), 'Enabled sharing places switches beside the card title.' );
	$coll_assert( false !== strpos( $coll_html, 'coll-public-status' ) && false !== strpos( $coll_html, esc_url( Sharing::get_url( get_post( $coll_collection ) ) ) ), 'Public collections show their public-page link on the internal overview.' );

	$coll_bulk = array(
		'action'        => 'coll_share_collection',
		'collection_id' => $coll_collection,
		'nonce'         => 'invalid',
		'shared'        => '1',
	);
	$coll_assert( 403 === $coll_ajax( $coll_bulk )['status'], 'Bulk sharing requires its collection nonce.' );
	$coll_bulk['nonce'] = wp_create_nonce( 'coll_share_collection_' . $coll_collection );
	$coll_assert( 405 === $coll_ajax( $coll_bulk, 'GET' )['status'], 'Bulk sharing rejects GET.' );
	$coll_result = $coll_ajax( $coll_bulk );
	$coll_assert( 200 === $coll_result['status'] && $coll_result['body']['success'], 'Share all succeeds via AJAX.' );
	$coll_assert( Sharing::get_item_state( $coll_collection, $coll_item )['selected'] && Sharing::get_item_state( $coll_collection, $coll_new )['selected'] && ! Sharing::get_item_state( $coll_collection, $coll_draft )['selected'], 'Share all includes published items and excludes drafts.' );
	$coll_assert( 'private' === Sharing::get_settings( $coll_collection )['new_items'], 'Bulk sharing preserves the new-item default.' );
	$coll_bulk['shared'] = '0';
	$coll_result         = $coll_ajax( $coll_bulk );
	$coll_assert( 200 === $coll_result['status'] && array() === Sharing::get_settings( $coll_collection )['item_ids'] && 'selected' === Sharing::get_settings( $coll_collection )['mode'], 'Make all private clears selections without disabling the public page.' );
	$coll_assert( ! has_action( 'wp_ajax_nopriv_coll_share_collection' ), 'Bulk sharing has no anonymous endpoint.' );
	wp_set_current_user( 0 );
	$coll_input['nonce'] = wp_create_nonce( 'coll_share_item_' . $coll_item );
	$coll_assert( 403 === $coll_ajax( $coll_input )['status'] && ! has_action( 'wp_ajax_nopriv_coll_share_item' ), 'Anonymous users must have no sharing mutation endpoint.' );
	WP_CLI::success( $coll_checks . ' sharing control checks passed.' );
} finally {
	wp_set_current_user( $coll_user );
	remove_filter( 'wp_die_ajax_handler', $coll_die );
	foreach ( array_reverse( $coll_posts ) as $coll_id ) {
		wp_delete_post( $coll_id, true );
	}
}
