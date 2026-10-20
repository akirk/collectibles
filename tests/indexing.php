<?php
/**
 * Indexing policy: wp --url=alex.kirk.at eval-file tests/indexing.php
 *
 * @package Collectibles
 */

use Collectibles\Sharing;

$coll_checks = 0;
foreach ( array( false, true ) as $coll_site ) {
	foreach ( array( false, true ) as $coll_directory ) {
		foreach ( array( false, true ) as $coll_collections ) {
			$coll_filters   = array(
				'blog_public'            => $coll_site,
				'coll_index_directory'   => $coll_directory,
				'coll_index_collections' => $coll_collections,
			);
			$coll_callbacks = array();
			foreach ( $coll_filters as $coll_option => $coll_value ) {
				$coll_callbacks[ $coll_option ] = static function () use ( $coll_value ) {
					return $coll_value ? '1' : '0';
				};
				add_filter( 'pre_option_' . $coll_option, $coll_callbacks[ $coll_option ] );
			}
			try {
				if ( Sharing::indexing_enabled( 'directory' ) !== ( $coll_site && $coll_directory ) || Sharing::indexing_enabled( 'collections' ) !== ( $coll_site && $coll_collections ) || Sharing::indexing_enabled( 'unknown' ) ) {
					throw new RuntimeException( 'Indexing must require the site setting and the matching area opt-in.' );
				}
				++$coll_checks;
			} finally {
				foreach ( $coll_callbacks as $coll_option => $coll_callback ) {
					remove_filter( 'pre_option_' . $coll_option, $coll_callback );
				}
			}
		}
	}
}
WP_CLI::success( $coll_checks . ' indexing policy combinations passed.' );
