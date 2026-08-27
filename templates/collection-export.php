<?php
/**
 * CSV download of one collection.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_app_route;

$coll_route_params  = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : array();
$coll_collection_id = isset( $coll_route_params['id'] ) ? absint( $coll_route_params['id'] ) : absint( get_query_var( 'id' ) );
$coll_collection    = Collection::get( $coll_collection_id );

if ( ! $coll_collection ) {
	status_header( 404 );
	wp_die( esc_html__( 'The requested collection is not available.', 'collectibles' ), '', array( 'response' => 404 ) );
}

if ( ! current_user_can( 'edit_post', $coll_collection_id ) ) {
	status_header( 403 );
	wp_die( esc_html__( 'You do not have permission to export this collection.', 'collectibles' ), '', array( 'response' => 403 ) );
}

$coll_csv      = Csv::export_collection( $coll_collection_id );
$coll_filename = Csv::get_filename( $coll_collection );

nocache_headers();
header( 'Content-Type: text/csv; charset=utf-8' );
header( 'Content-Disposition: attachment; filename="' . $coll_filename . '"' );
header( 'Content-Length: ' . strlen( $coll_csv ) );

echo $coll_csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV document, quoted and escaped by Csv::to_string().
exit;
