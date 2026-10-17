<?php
/**
 * Offline totals checks: wp --url=alex.kirk.at --user=<editor> eval-file tests/field-totals.php
 *
 * Temporary posts are removed in finally; no HTTP is required.
 *
 * @package Collectibles
 */

use Collectibles\Collection;
use Collectibles\Item;
use Collectibles\Schema;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'edit_posts' ) ) {
	throw new RuntimeException( 'Run through WP-CLI as an editor.' );
}
$coll_posts  = array();
$coll_checks = 0;
$coll_assert = static function ( $condition, $message ) use ( &$coll_checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$coll_checks;
};
$coll_render = static function ( $id ) {
	global $wp_app_route;
	$previous     = $wp_app_route;
	$wp_app_route = array( 'params' => array( 'id' => $id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WpApp routing global.
	ob_start();
	try {
		require dirname( __DIR__ ) . '/templates/collection.php';
		return ob_get_contents();
	} finally {
		ob_end_clean();
		$wp_app_route = $previous; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restore WpApp routing global.
	}
};
try {
	$coll_collection = wp_insert_post(
		array(
			'post_type'   => Collection::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Temporary brick total checks',
		)
	);
	$coll_posts[]    = $coll_collection;
	update_post_meta( $coll_collection, 'kind', Schema::KIND_BRICKS );
	$coll_fixtures = array(
		array(
			'parts'  => '100',
			'status' => Schema::STATUS_OWNED,
		),
		array(
			'parts'  => '50',
			'status' => Schema::STATUS_OWNED,
			'lots'   => array(
				array(
					'quantity'        => '1',
					'condition_grade' => 'sealed',
				),
				array(
					'quantity'        => '2',
					'condition_grade' => 'good',
				),
			),
		),
		array(
			'parts'  => '500',
			'status' => Schema::STATUS_OWNED,
			'lots'   => array( array( 'quantity' => '0' ) ),
		),
		array(
			'parts'  => '999',
			'status' => Schema::STATUS_WANTED,
		),
		array(
			'parts'  => '999',
			'status' => Schema::STATUS_SOLD,
		),
		array(
			'parts'  => '999',
			'status' => Schema::STATUS_ORDERED,
		),
		array(
			'parts'  => '20',
			'status' => Schema::STATUS_DUPLICATE,
			'lots'   => array( array( 'quantity' => '2' ) ),
		),
		array(
			'parts'  => '30',
			'status' => Schema::STATUS_FOR_SALE,
		),
		array(
			'parts'  => '',
			'status' => Schema::STATUS_OWNED,
		),
	);
	$coll_items    = array();
	foreach ( $coll_fixtures as $coll_fixture ) {
		$coll_id      = wp_insert_post(
			array(
				'post_type'   => Item::POST_TYPE,
				'post_status' => 'publish',
				'post_parent' => $coll_collection,
				'post_title'  => 'Temporary set',
			)
		);
		$coll_posts[] = $coll_id;
		update_post_meta( $coll_id, 'part_count', $coll_fixture['parts'] );
		update_post_meta( $coll_id, Item::STATUS_META_KEY, $coll_fixture['status'] );
		if ( isset( $coll_fixture['lots'] ) ) {
			Item::save_lots( $coll_id, Schema::KIND_BRICKS, array( 'coll_lot' => $coll_fixture['lots'] ) );
		}
		$coll_items[] = get_post( $coll_id );
	}
	$coll_assert( 100 === Item::sum_field( array( $coll_items[0] ), 'part_count' ), 'An unset quantity must count one set.' );
	$coll_assert( 150 === Item::sum_field( array( $coll_items[1] ), 'part_count' ), 'Every lot must contribute its copies.' );
	$coll_assert( 0 === Item::sum_field( array( $coll_items[2] ), 'part_count' ), 'Zero copies must contribute no bricks.' );
	$coll_assert( 0 === Item::sum_field( array_slice( $coll_items, 3, 3 ), 'part_count' ), 'Wishlist, sold and ordered sets must be excluded.' );
	$coll_assert( 70 === Item::sum_field( array_slice( $coll_items, 6, 2 ), 'part_count' ), 'Duplicates and sets for sale are still held.' );
	$coll_assert( 0 === Item::sum_field( array( $coll_items[8] ), 'part_count' ), 'Missing part counts must not invent bricks.' );
	$coll_assert( 320 === Item::sum_field( $coll_items, 'part_count' ), 'The collection must total parts per set times owned copies.' );
	$coll_assert( 0 === Item::sum_field( array(), 'part_count' ), 'Empty collections must total zero.' );
	$coll_html = $coll_render( $coll_collection );
	$coll_assert( false !== strpos( $coll_html, '>320</span>' ) && false !== strpos( $coll_html, 'bricks in total' ), 'The collection totals strip must display its brick count.' );
	update_post_meta( $coll_collection, 'kind', Schema::KIND_COINS );
	$coll_assert( false === strpos( $coll_render( $coll_collection ), 'bricks in total' ), 'Other collection kinds must not display a brick total.' );
	WP_CLI::success( $coll_checks . ' field total checks passed.' );
} finally {
	foreach ( array_reverse( $coll_posts ) as $coll_id ) {
		wp_delete_post( $coll_id, true );
	}
}
