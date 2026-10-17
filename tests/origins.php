<?php
/**
 * Collection-scoped origins checks: wp --url=alex.kirk.at --user=<editor> eval-file tests/origins.php
 *
 * Creates temporary posts and removes them in finally; no HTTP is required.
 *
 * @package Collectibles
 */

use Collectibles\App;
use Collectibles\Collection;
use Collectibles\Item;
use Collectibles\Schema;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'edit_posts' ) ) {
	throw new RuntimeException( 'Run through WP-CLI as an editor.' );
}
$coll_user       = get_current_user_id();
$coll_posts      = array();
$coll_checks     = 0;
$coll_assert     = static function ( $condition, $message ) use ( &$coll_checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$coll_checks;
};
$coll_render     = static function ( $id ) {
	global $wp_app_route;
	$previous     = $wp_app_route;
	$wp_app_route = array( 'params' => array( 'id' => $id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WpApp routing global.
	ob_start();
	try {
		require dirname( __DIR__ ) . '/templates/origins.php';
		return ob_get_contents();
	} finally {
		ob_end_clean();
		$wp_app_route = $previous; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restore WpApp global.
	}
};
$coll_die_filter = static function () {
	return static function ( $message, $title, $args ) {
		throw new RuntimeException( 'response:' . absint( $args['response'] ) );
	};
};
$coll_menu_app   = new class() extends App {
	/** Set up a menu sink without registering a second WpApp. */
	public function __construct() {
		$this->app = new class() {
			/**
			 * Menu entries captured for assertions.
			 *
			 * @var array
			 */
			public $items = array();
			/**
			 * Capture one menu entry.
			 *
			 * @param string $id Menu ID.
			 * @param string $title Menu title.
			 * @param string $url Menu URL.
			 */
			public function add_menu_item( $id, $title, $url ) {
				$this->items[ $id ] = array(
					'title' => $title,
					'url'   => $url,
				);
			}
		};
	}
	/** Return a fresh menu for the current reader. */
	public function menu(): array {
		$this->app->items = array();
		$this->setup_menu();
		return $this->app->items;
	}
};
try {
	foreach ( array( Schema::KIND_COINS, Schema::KIND_COINS, Schema::KIND_BRICKS ) as $coll_kind ) {
		$coll_id      = wp_insert_post(
			array(
				'post_type'   => Collection::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Origin checks ' . wp_generate_uuid4(),
			)
		);
		$coll_posts[] = $coll_id;
		update_post_meta( $coll_id, 'kind', $coll_kind );
	}
	foreach ( array( 'de', 'at', 'dk' ) as $coll_index => $coll_code ) {
		$coll_id      = wp_insert_post(
			array(
				'post_type'   => Item::POST_TYPE,
				'post_status' => 'publish',
				'post_parent' => $coll_posts[ $coll_index ],
				'post_title'  => 'Temporary origin item',
			)
		);
		$coll_posts[] = $coll_id;
		update_post_meta( $coll_id, Item::COUNTRY_META_KEY, $coll_code );
	}
	$coll_html = $coll_render( $coll_posts[0] );
	$coll_url  = add_query_arg(
		array(
			'collection' => $coll_posts[0],
			'origin'     => 'de',
		),
		App::get_url( 'search' )
	);
	$coll_assert( false !== strpos( $coll_html, esc_url( $coll_url ) ), 'Country links must retain the collection scope.' );
	$coll_assert( false === strpos( $coll_html, 'origin=at' ) && false === strpos( $coll_html, 'origin=dk' ), 'Other collections must not contribute origins.' );
	$coll_assert( false !== strpos( $coll_html, esc_html( get_the_title( $coll_posts[0] ) ) ), 'The collection name must identify the map.' );
	$coll_fields = wp_list_pluck( Item::get_fields_for_kind( Schema::KIND_BRICKS ), 'key' );
	$coll_assert( ! in_array( Item::COUNTRY_META_KEY, $coll_fields, true ), 'Brick set forms and details must omit origin.' );
	$coll_assert( in_array( Item::COUNTRY_META_KEY, wp_list_pluck( Item::get_fields_for_kind( Schema::KIND_COINS ), 'key' ), true ), 'Coin origins must remain available.' );
	Item::save_values( $coll_posts[5], Schema::KIND_BRICKS, array() );
	$coll_assert( 'dk' === get_post_meta( $coll_posts[5], Item::COUNTRY_META_KEY, true ), 'Hidden origin values must be preserved.' );
	$coll_menu = $coll_menu_app->menu();
	$coll_assert( isset( $coll_menu[ 'collection-' . $coll_posts[0] ], $coll_menu[ 'collection-' . $coll_posts[2] ] ), 'The menu must link to coin and brick collections.' );
	$coll_assert( ! isset( $coll_menu['origins'] ), 'The global origins menu entry must be removed.' );
	add_filter( 'wp_die_handler', $coll_die_filter );
	try {
		$coll_render( $coll_posts[2] );
		$coll_assert( false, 'A brick origins route must not render a map.' );
	} catch ( RuntimeException $coll_exception ) {
		$coll_assert( 'response:404' === $coll_exception->getMessage(), 'Brick origins must return 404.' );
	}
	wp_set_current_user( 0 );
	$coll_menu = $coll_menu_app->menu();
	$coll_assert( ! isset( $coll_menu[ 'collection-' . $coll_posts[0] ] ), 'Anonymous menus must not reveal collection names.' );
	try {
		$coll_render( $coll_posts[0] );
		$coll_assert( false, 'An unauthorized reader must not see origins.' );
	} catch ( RuntimeException $coll_exception ) {
		$coll_assert( 'response:403' === $coll_exception->getMessage(), 'Unauthorized origins must return 403.' );
	}
	WP_CLI::success( $coll_checks . ' collection origins checks passed.' );
} finally {
	remove_filter( 'wp_die_handler', $coll_die_filter );
	wp_set_current_user( $coll_user );
	foreach ( array_reverse( $coll_posts ) as $coll_id ) {
		wp_delete_post( $coll_id, true );
	}
}
