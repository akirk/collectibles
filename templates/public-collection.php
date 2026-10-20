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
status_header( $coll_public_data ? 200 : 404 );
require __DIR__ . '/_public-page.php';
