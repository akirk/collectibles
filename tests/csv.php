<?php
/**
 * CSV round trips: wp --url=alex.kirk.at --user=<editor> eval-file tests/csv.php
 *
 * @package Collectibles
 */

use Collectibles\Collection;
use Collectibles\Csv;
use Collectibles\Item;
use Collectibles\Schema;
use Collectibles\Sharing;

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
try {
	foreach ( Schema::get_kinds() as $coll_kind => $coll_definition ) {
		$coll_source  = wp_insert_post(
			array(
				'post_type'   => Collection::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'CSV checks',
			)
		);
		$coll_posts[] = $coll_source;
		update_post_meta( $coll_source, Collection::KIND_META_KEY, $coll_kind );
		update_post_meta( $coll_source, Collection::CURRENCY_META_KEY, 'EUR' );
		$coll_values = array();
		foreach ( Item::get_fields_for_kind( $coll_kind ) as $coll_field ) {
			if ( Item::is_lot_field( $coll_field ) ) {
				continue;
			}
			$coll_value = '';
			if ( 'select' === $coll_field['type'] && $coll_field['options'] ) {
				$coll_value = (string) array_key_first( $coll_field['options'] );
			} elseif ( 'text' === $coll_field['type'] ) {
				$coll_value = '@Text, "quoted"';
			}
			$coll_values[ 'coll_field_' . $coll_field['key'] ] = $coll_value;
		}
		$coll_grades             = Schema::get_grades( $coll_kind );
		$coll_values['coll_lot'] = array(
			array(
				'quantity'        => '0',
				'purchase_price'  => '2.5',
				'estimated_value' => '3',
				'condition_grade' => array_key_first( $coll_grades ),
			),
			array(
				'quantity'        => '2',
				'purchase_price'  => '4',
				'estimated_value' => '5',
			),
		);
		for ( $coll_i = 0; $coll_i < 2; ++$coll_i ) {
			$coll_id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => Item::POST_TYPE,
						'post_status'  => 'publish',
						'post_parent'  => $coll_source,
						'post_title'   => '=Same, "name"',
						'post_content' => "Notes\nsecond line",
					)
				)
			);
			Item::save_values( $coll_id, $coll_kind, $coll_values );
			wp_set_object_terms( $coll_id, array( 'CSV checks' ), Item::TAXONOMY );
		}
		$coll_document = Csv::export_collection( $coll_source );
		$coll_parsed   = Csv::parse_import( $coll_document, $coll_source );
		$coll_assert( is_array( $coll_parsed ) && 2 === count( $coll_parsed ) && 2 === count( $coll_parsed[0]['lots'] ), 'IDs must preserve distinct identical items and their lots.' );
		$coll_assert( '=Same, "name"' === $coll_parsed[0]['name'] && "Notes\nsecond line" === $coll_parsed[0]['notes'], 'Quoted and multiline text must round trip.' );
		$coll_legacy       = preg_replace( '/,"(?:Item ID|[0-9]+)"(?=\r?$)/m', '', $coll_document );
		$coll_legacy_items = Csv::parse_import( $coll_legacy, $coll_source );
		$coll_assert( is_array( $coll_legacy_items ) && 1 === count( $coll_legacy_items ) && 4 === count( $coll_legacy_items[0]['lots'] ), 'Legacy exports must combine matching item rows as lots.' );
		update_post_meta(
			$coll_source,
			Sharing::META_KEY,
			array(
				'mode'      => 'selected',
				'slug'      => 'csv-check-' . $coll_source,
				'item_ids'  => array(),
				'new_items' => 'shared',
			)
		);
		$coll_result = Csv::import_collection( $coll_document, $coll_source );
		$coll_assert( 2 === $coll_result, 'Import must add two items.' );
		$coll_items = Item::query( array( 'collection' => $coll_source ) );
		$coll_assert( 4 === count( $coll_items ), 'Existing items must stay intact.' );
		foreach ( $coll_items as $coll_item ) {
			$coll_assert( 2 === count( Item::get_lots( $coll_item->ID ) ) && 2 === Item::get_totals( $coll_item->ID )['pieces'], 'Lots and zero quantities must survive.' );
		}
		$coll_assert( array() === Sharing::get_settings( $coll_source )['item_ids'], 'Imports must stay private even when new items default to shared.' );
		$coll_before = count( $coll_items );
		$coll_assert( is_wp_error( Csv::import_collection( $coll_document . '"incomplete"', $coll_source ) ), 'Malformed rows must be rejected.' );
		$coll_assert( count( Item::query( array( 'collection' => $coll_source ) ) ) === $coll_before, 'Validation must precede all writes.' );
		$coll_assert( is_wp_error( Csv::parse_import( "Wrong,header\n", $coll_source ) ), 'Wrong schema must be rejected.' );
	}
	WP_CLI::success( $coll_checks . ' CSV checks passed.' );
} finally {
	foreach ( $coll_posts as $coll_id ) {
		wp_delete_post( $coll_id, true );
	}
}
