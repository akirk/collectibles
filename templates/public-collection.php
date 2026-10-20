<?php
/**
 * Anonymous public collection and item routes. Never use private templates.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wp_app_route;
$coll_public_slug    = (string) ( $wp_app_route['params']['slug'] ?? '' );
$coll_public_item    = (string) ( $wp_app_route['params']['item_slug'] ?? '' );
$coll_public_data    = Sharing::get_page( $coll_public_slug, $coll_public_item );
$coll_public_preview = false;
Sharing::send_headers();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only representation.
$coll_public_json = isset( $_GET['format'] ) && 'json' === $_GET['format'];
if ( $coll_public_json ) {
	header( 'Access-Control-Allow-Origin: *' );
	header( 'X-Content-Type-Options: nosniff' );
	wp_send_json( $coll_public_data ? $coll_public_data : array( 'error' => 'Collection not available' ), $coll_public_data ? 200 : 404 );
}
status_header( $coll_public_data ? 200 : 404 );
require __DIR__ . '/_public-page.php';
