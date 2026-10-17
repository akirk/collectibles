<?php
/**
 * Offline photo and rate limit checks: wp --url=alex.kirk.at --user=<editor> eval-file tests/rebrickable-photos.php
 *
 * Uses temporary posts and /tmp uploads. HTTP is intercepted; no API calls occur.
 *
 * @package Collectibles
 */

use Collectibles\Collection;
use Collectibles\Item;
use Collectibles\Rebrickable;
use Collectibles\Schema;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'upload_files' ) ) {
	throw new RuntimeException( 'Run through WP-CLI as an editor with upload_files.' );
}

$coll_posts   = array();
$coll_checks  = 0;
$coll_calls   = array();
$coll_status  = 200;
$coll_id      = 'collphototest' . wp_rand( 100000, 999999 ) . '-1';
$coll_cache   = 'coll_rebrickable_' . md5( 'sets/' . $coll_id );
$coll_lock    = 'coll_offline_unused';
$coll_fixture = array(
	'set_num'     => $coll_id,
	'name'        => 'Photo test',
	'set_img_url' => 'https://cdn.rebrickable.com/media/sets/' . $coll_id . '/test.png',
);
$coll_dir     = sys_get_temp_dir() . '/collectibles-photos-' . wp_generate_uuid4();
$coll_assert  = static function ( $condition, $message ) use ( &$coll_checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$coll_checks;
};
$coll_uploads = static function ( $uploads ) use ( $coll_dir ) {
	$uploads['path']    = $coll_dir;
	$uploads['basedir'] = $coll_dir;
	$uploads['url']     = 'https://example.org/photo-tests';
	$uploads['baseurl'] = $uploads['url'];
	$uploads['subdir']  = '';
	$uploads['error']   = false;
	return $uploads;
};
$coll_http    = static function ( $pre, $args ) use ( &$coll_calls, &$coll_status, &$coll_fixture ) {
	$coll_calls[] = microtime( true );
	if ( ! empty( $args['stream'] ) ) {
		// A tiny PNG fixture is enough to exercise WordPress's media validation.
		file_put_contents( $args['filename'], base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3ioAAAAASUVORK5CYII=' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Temporary PNG fixture.
		return array(
			'headers'  => array(),
			'body'     => '',
			'response' => array( 'code' => 200 ),
		);
	}
	return array(
		'headers'  => array( 'retry-after' => '120' ),
		'body'     => wp_json_encode( $coll_fixture ),
		'response' => array( 'code' => $coll_status ),
	);
};
add_filter( 'upload_dir', $coll_uploads );
add_filter( 'pre_http_request', $coll_http, 10, 3 );
wp_mkdir_p( $coll_dir );

try {
	$coll_collection = wp_insert_post(
		array(
			'post_type'   => Collection::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Temporary photo checks',
		)
	);
	$coll_posts[]    = $coll_collection;
	update_post_meta( $coll_collection, 'kind', Schema::KIND_BRICKS );
	$coll_item    = wp_insert_post(
		array(
			'post_type'   => Item::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Temporary LEGO set',
			'post_parent' => $coll_collection,
		)
	);
	$coll_posts[] = $coll_item;
	update_post_meta( $coll_item, 'rebrickable_id', $coll_id );
	$coll_render = static function ( $collection_id ) {
		global $wp_app_route;
		$wp_app_route = array( 'params' => array( 'id' => $collection_id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WpApp owns this routing global.
		ob_start();
		require dirname( __DIR__ ) . '/templates/collection-photos.php';
		return ob_get_clean();
	};
	$coll_html   = $coll_render( $coll_collection );
	$coll_assert( false !== strpos( $coll_html, 'coll-import-all' ) && false !== strpos( $coll_html, 'value="preview"' ) && false !== strpos( $coll_html, 'name="coll_photo_front"' ), 'Missing photo mode must offer bulk import, preview and personal uploads.' );
	set_transient( $coll_cache, $coll_fixture, HOUR_IN_SECONDS );
	$coll_assert( Rebrickable::get_photo_url( $coll_item ) === $coll_fixture['set_img_url'], 'Cached set data must provide the photo without HTTP.' );
	$coll_assert( 0 === count( $coll_calls ), 'Cached lookup must not call the API.' );
	$coll_attachment = Rebrickable::import_photo( $coll_item );
	$coll_assert( is_int( $coll_attachment ) && $coll_attachment > 0, 'A catalogue image must become a real attachment.' );
	$coll_assert( (int) get_post_thumbnail_id( $coll_item ) === $coll_attachment, 'The import must become the featured image.' );
	$coll_assert( (int) get_post( $coll_attachment )->post_parent === $coll_item, 'The attachment must belong to the item.' );
	$coll_assert( (bool) get_post_meta( $coll_attachment, '_coll_catalog_source', true ), 'Catalogue provenance must be stored.' );
	$coll_before = count( $coll_calls );
	$coll_assert( 0 === Rebrickable::import_photo( $coll_item ), 'Retry must preserve the existing photo.' );
	$coll_assert( count( $coll_calls ) === $coll_before, 'Retry must make no HTTP requests.' );
	$coll_fixture['set_img_url'] = 'https://example.org/untrusted.jpg';
	set_transient( $coll_cache, $coll_fixture, HOUR_IN_SECONDS );
	$coll_assert( is_wp_error( Rebrickable::get_photo_url( $coll_item ) ), 'Untrusted image hosts must be rejected.' );
	wp_set_current_user( 0 );
	$coll_assert( is_wp_error( Rebrickable::import_photo( $coll_item ) ), 'Unauthorized users must be rejected.' );
	wp_set_current_user( (int) get_post( $coll_item )->post_author );

	if ( Rebrickable::is_api_key_fixed() ) {
		throw new RuntimeException( 'Rate checks require per-user credentials for isolation.' );
	}
	// Exercise the real request method with mocked responses and an isolated key.
	$coll_request = new ReflectionMethod( Rebrickable::class, 'request' );
	$coll_request->setAccessible( true );
	$coll_key_filter = static function ( $value, $user_id, $key ) {
		return Rebrickable::KEY_META === $key ? 'offline-photo-test-key' : $value;
	};
	add_filter( 'get_user_metadata', $coll_key_filter, 10, 3 );
	$coll_lock = 'coll_rebrickable_request_' . md5( Rebrickable::get_api_key() );
	$coll_assert( ! get_option( $coll_lock ) && ! get_transient( $coll_lock ), 'Rate checks require an idle test credential.' );
	delete_transient( $coll_cache );
	$coll_fixture['set_img_url'] = 'https://cdn.rebrickable.com/media/sets/test/test.png';
	$coll_before                 = microtime( true );
	$coll_request->invoke( null, 'sets/' . $coll_id );
	$coll_assert( microtime( true ) - $coll_before >= 1.1, 'A network call must reserve the spacing interval.' );
	$coll_before = count( $coll_calls );
	$coll_request->invoke( null, 'sets/' . $coll_id );
	$coll_assert( count( $coll_calls ) === $coll_before, 'Cached requests must bypass the rate limiter and network.' );
	delete_transient( $coll_cache );
	add_option( $coll_lock, time(), '', false );
	$coll_assert( is_wp_error( $coll_request->invoke( null, 'sets/' . $coll_id ) ), 'Concurrent requests must be blocked.' );
	$coll_assert( count( $coll_calls ) === $coll_before, 'A held lock must prevent network calls.' );
	delete_option( $coll_lock );
	$coll_expiry            = 0;
	$coll_expiration_filter = static function ( $expiration ) use ( &$coll_expiry ) {
		$coll_expiry = $expiration;
		return $expiration;
	};
	add_filter( 'expiration_of_transient_' . $coll_lock, $coll_expiration_filter );
	$coll_status = 429;
	$coll_assert( is_wp_error( $coll_request->invoke( null, 'sets/' . $coll_id ) ), 'Rate limited responses must report an error.' );
	$coll_assert( (bool) get_transient( $coll_lock ), 'A 429 must establish a shared cooldown.' );
	$coll_assert( $coll_expiry >= 120, 'The cooldown must honor Retry-After.' );
	$coll_before = count( $coll_calls );
	$coll_request->invoke( null, 'sets/' . $coll_id );
	$coll_assert( count( $coll_calls ) === $coll_before, 'Cooldown must prevent additional API calls.' );
	WP_CLI::success( $coll_checks . ' offline photo checks passed.' );
} finally {
	if ( isset( $coll_expiration_filter ) ) {
		remove_filter( 'expiration_of_transient_' . $coll_lock, $coll_expiration_filter );
	}
	if ( isset( $coll_key_filter ) ) {
		remove_filter( 'get_user_metadata', $coll_key_filter, 10 );
	}
	delete_option( $coll_lock );
	delete_transient( $coll_lock );
	delete_transient( $coll_cache );
	foreach ( array_reverse( $coll_posts ) as $coll_post ) {
		wp_delete_post( $coll_post, true );
	}
	remove_filter( 'pre_http_request', $coll_http, 10 );
	remove_filter( 'upload_dir', $coll_uploads );
	if ( is_dir( $coll_dir ) ) {
		rmdir( $coll_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Empty temporary fixture directory.
	}
}
