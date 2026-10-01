<?php
/**
 * Offline integration checks: wp --url=alex.kirk.at --user=<editor> eval-file tests/abilities.php
 *
 * Creates temporary posts and removes them in finally. All HTTP is blocked.
 *
 * @package Collectibles
 */

use Collectibles\Abilities;
use Collectibles\Collection;
use Collectibles\Item;
use Collectibles\Numista;
use Collectibles\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_get_ability' ) ) {
	throw new RuntimeException( 'These checks require the WordPress Abilities API.' );
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'edit_posts' ) ) {
	throw new RuntimeException( 'Run through WP-CLI as a user with edit_posts.' );
}

$coll_original_user = get_current_user_id();
$coll_posts         = array();
$coll_filters       = array();
$coll_checks        = 0;
$coll_http_calls    = 0;
$coll_assert        = static function ( $condition, $message ) use ( &$coll_checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$coll_checks;
};
$coll_http_block    = static function () use ( &$coll_http_calls ) {
	++$coll_http_calls;
	return new WP_Error( 'coll_test_http_blocked', 'HTTP is disabled in ability tests.' );
};
add_filter( 'pre_http_request', $coll_http_block );

try {
	$coll_lookup = wp_get_ability( 'collectibles/lookup-catalog-entry' );
	$coll_add    = wp_get_ability( 'collectibles/add-item' );
	$coll_assert( $coll_lookup && $coll_add, 'New abilities must be registered.' );
	foreach ( array( 'list-collections', 'search-items', 'get-item', 'lookup-catalog-entry', 'add-item' ) as $coll_name ) {
		$coll_assert( wp_get_ability( 'collectibles/' . $coll_name )->get_meta()['show_in_rest'], 'Ability must be REST visible.' );
	}

	foreach ( array( Schema::KIND_BRICKS, Schema::KIND_COINS, Schema::KIND_OTHER ) as $coll_kind ) {
		$coll_id                        = wp_insert_post(
			array(
				'post_type'   => Collection::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Ability test ' . wp_generate_uuid4(),
				'post_author' => $coll_original_user,
			)
		);
		$coll_posts[]                   = $coll_id;
		$coll_collections[ $coll_kind ] = $coll_id;
		update_post_meta( $coll_id, 'kind', $coll_kind );
	}
	$coll_bricks       = $coll_collections[ Schema::KIND_BRICKS ];
	$coll_coins        = $coll_collections[ Schema::KIND_COINS ];
	$coll_fixture_data = array(
		'coll_rebrickable_' . md5( 'sets/10497-1' ) => array(
			'set_num'   => '10497-1',
			'name'      => 'Galaxy Explorer',
			'year'      => 2022,
			'num_parts' => 1254,
			'theme_id'  => 497,
		),
		'coll_rebrickable_' . md5( 'themes/497' )   => array( 'name' => 'Space' ),
		'coll_numista_' . Numista::get_language() . '_999999991' => array(
			'id'    => 999999991,
			'title' => 'Offline coin',
		),
		'coll_numista_issues_' . Numista::get_language() . '_999999991' => array(
			array(
				'id'   => 11,
				'year' => 1990,
			),
			array(
				'id'   => 12,
				'year' => 1991,
			),
		),
	);
	foreach ( $coll_fixture_data as $coll_cache_key => $coll_fixture ) {
		$coll_filter = static function () use ( $coll_fixture ) {
			return $coll_fixture;
		};
		$coll_hook   = 'pre_transient_' . $coll_cache_key;
		add_filter( $coll_hook, $coll_filter );
		$coll_filters[ $coll_hook ] = $coll_filter;
	}

	$coll_metadata = $coll_lookup->execute(
		array(
			'collection' => $coll_bricks,
			'reference'  => '10497',
		)
	);
	$coll_assert( ! is_wp_error( $coll_metadata ), 'Rebrickable lookup failed.' );
	$coll_assert( 'Galaxy Explorer' === $coll_metadata['title'] && '1254' === $coll_metadata['values']['part_count'] && 'Space' === $coll_metadata['values']['theme'], 'Mapped set metadata differs.' );
	$coll_assert( 0 === count( Item::query( array( 'collection' => $coll_bricks ) ) ), 'Lookup must not create items.' );
	$coll_input   = array_merge(
		$coll_metadata,
		array(
			'collection' => $coll_bricks,
			'request_id' => wp_generate_uuid4(),
		)
	);
	$coll_created = $coll_add->execute( $coll_input );
	$coll_assert( ! is_wp_error( $coll_created ) && $coll_created['created'], 'Item creation failed.' );
	$coll_posts[] = $coll_created['id'];
	$coll_assert( 1 === Item::get_quantity( $coll_created['id'] ) && 'owned' === Item::get_status( $coll_created['id'] ), 'Default must be one owned set.' );
	$coll_assert( '10497-1' === get_post_meta( $coll_created['id'], 'rebrickable_id', true ), 'Rebrickable ID was not saved.' );
	$coll_assert( '' === get_post_meta( $coll_created['id'], 'condition_grade', true ), 'Condition must remain unspecified.' );
	$coll_retry = $coll_add->execute( $coll_input );
	$coll_assert( ! is_wp_error( $coll_retry ) && ! $coll_retry['created'] && $coll_retry['id'] === $coll_created['id'], 'Retry created a duplicate.' );
	$coll_input['title'] = 'Different set';
	$coll_assert( is_wp_error( $coll_add->execute( $coll_input ) ), 'Request ID reuse with changed payload must fail.' );
	$coll_input['request_id'] = wp_generate_uuid4();
	$coll_input['lots']       = array(
		array(
			'quantity'        => '1',
			'condition_grade' => 'sealed',
			'purchase_price'  => '100',
			'estimated_value' => '120',
		),
		array(
			'quantity'        => '2',
			'condition_grade' => 'good',
			'purchase_price'  => '50',
			'estimated_value' => '60',
		),
	);
	$coll_lots                = $coll_add->execute( $coll_input );
	$coll_assert( ! is_wp_error( $coll_lots ), 'Multiple lots could not be saved.' );
	$coll_posts[] = $coll_lots['id'];
	$coll_totals  = Item::get_totals( $coll_lots['id'] );
	$coll_assert( 3 === Item::get_quantity( $coll_lots['id'] ) && 200.0 === (float) $coll_totals['paid'] && 240.0 === (float) $coll_totals['value'], 'Lot totals are incorrect.' );
	$coll_assert( ! metadata_exists( 'post', $coll_lots['id'], 'quantity' ), 'Multiple lots must delete scalar lot meta.' );
	$coll_input['request_id'] = wp_generate_uuid4();
	$coll_input['lots']       = array( array( 'quantity' => '0' ) );
	$coll_zero                = $coll_add->execute( $coll_input );
	$coll_assert( ! is_wp_error( $coll_zero ), 'Zero quantity could not be saved.' );
	$coll_posts[] = $coll_zero['id'];
	$coll_assert( 0 === Item::get_quantity( $coll_zero['id'] ), 'Zero quantity must stay zero.' );

	$coll_invalid                           = $coll_input;
	$coll_invalid['request_id']             = wp_generate_uuid4();
	$coll_invalid['values']['denomination'] = '10';
	$coll_assert( is_wp_error( $coll_add->execute( $coll_invalid ) ), 'Wrong-kind field must be rejected.' );
	$coll_invalid         = $coll_input;
	$coll_invalid['lots'] = array( array( 'condition_grade' => 'uncirculated' ) );
	$coll_assert( is_wp_error( $coll_add->execute( $coll_invalid ) ), 'Wrong-kind grade must be rejected.' );
	$coll_invalid = $coll_input;
	unset( $coll_invalid['request_id'] );
	$coll_assert( is_wp_error( $coll_add->execute( $coll_invalid ) ), 'Missing request ID must be rejected by schema.' );
	$coll_assert(
		is_wp_error(
			$coll_lookup->execute(
				array(
					'collection' => $coll_collections[ Schema::KIND_OTHER ],
					'reference'  => '10497',
				)
			)
		),
		'Unsupported provider must be rejected.'
	);

	$coll_numista = $coll_lookup->execute(
		array(
			'collection' => $coll_coins,
			'reference'  => '999999991',
		)
	);
	$coll_assert( ! is_wp_error( $coll_numista ) && 0 === $coll_numista['issue_id'] && 2 === count( $coll_numista['issues'] ), 'Numista must expose issue choices.' );
	$coll_numista = $coll_lookup->execute(
		array(
			'collection' => $coll_coins,
			'reference'  => '999999991',
			'issue_id'   => 12,
		)
	);
	$coll_assert( ! is_wp_error( $coll_numista ) && '1991' === $coll_numista['values']['year'], 'Selected issue must supply the year.' );
	$coll_assert(
		is_wp_error(
			$coll_lookup->execute(
				array(
					'collection' => $coll_coins,
					'reference'  => '999999991',
					'issue_id'   => 99,
				)
			)
		),
		'Unrelated Numista issue must be rejected.'
	);

	$coll_deny = static function ( $caps, $cap, $user_id, $args ) use ( $coll_bricks ) {
		return 'edit_post' === $cap && ( $args[0] ?? 0 ) === $coll_bricks ? array( 'do_not_allow' ) : $caps;
	};
	add_filter( 'map_meta_cap', $coll_deny, 10, 4 );
	$coll_assert( is_wp_error( $coll_add->execute( $coll_input ) ), 'Unauthorized collection creation must fail.' );
	$coll_assert(
		is_wp_error(
			$coll_lookup->execute(
				array(
					'collection' => $coll_bricks,
					'reference'  => '10497',
				)
			)
		),
		'Unauthorized catalog lookup must fail.'
	);
	$coll_assert( is_wp_error( wp_get_ability( 'collectibles/search-items' )->execute( array( 'collection' => $coll_bricks ) ) ), 'Unauthorized explicit search must fail.' );
	remove_filter( 'map_meta_cap', $coll_deny );
	wp_set_current_user( 0 );
	$coll_assert( is_wp_error( $coll_add->execute( $coll_input ) ), 'Anonymous creation must fail.' );
	$coll_assert(
		is_wp_error(
			$coll_lookup->execute(
				array(
					'collection' => $coll_bricks,
					'reference'  => '10497',
				)
			)
		),
		'Anonymous lookup must fail.'
	);
	$coll_assert( 0 === $coll_http_calls, 'Checks attempted a catalog HTTP call.' );
	WP_CLI::line( 'Passed ' . $coll_checks . ' offline ability checks.' );
} finally {
	wp_set_current_user( $coll_original_user );
	if ( isset( $coll_deny ) ) {
		remove_filter( 'map_meta_cap', $coll_deny );
	}
	foreach ( array_reverse( $coll_posts ) as $coll_post_id ) {
		wp_delete_post( $coll_post_id, true );
	}
	foreach ( $coll_filters as $coll_hook => $coll_filter ) {
		remove_filter( $coll_hook, $coll_filter );
	}
	remove_filter( 'pre_http_request', $coll_http_block );
}
