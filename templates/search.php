<?php
/**
 * Search across every collection.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only search form.
$coll_search    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$coll_status    = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$coll_scope     = isset( $_GET['collection'] ) ? absint( wp_unslash( $_GET['collection'] ) ) : 0;
$coll_sort      = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'recent';
$coll_has_query = isset( $_GET['q'] ) || isset( $_GET['status'] ) || isset( $_GET['collection'] );
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( ! array_key_exists( $coll_status, Schema::get_statuses() ) ) {
	$coll_status = '';
}

if ( ! array_key_exists( $coll_sort, Item::get_sort_options() ) ) {
	$coll_sort = 'recent';
}

$coll_collections = Collection::get_for_current_user();
$coll_known_ids   = wp_list_pluck( $coll_collections, 'ID' );

if ( $coll_scope && ! in_array( $coll_scope, array_map( 'absint', $coll_known_ids ), true ) ) {
	$coll_scope = 0;
}

$coll_items = $coll_has_query
	? Item::query(
		array(
			'collection' => $coll_scope,
			'search'     => $coll_search,
			'status'     => $coll_status,
			'orderby'    => $coll_sort,
		)
	)
	: array();

$coll_page_title = __( 'Search', 'collectibles' );

require __DIR__ . '/_head.php';
?>

		<header class="topbar">
			<div>
				<a class="crumb" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Collections', 'collectibles' ); ?></a>
				<h1><?php echo esc_html__( 'Search', 'collectibles' ); ?></h1>
				<p class="eyebrow"><?php echo esc_html__( 'Titles, notes, tags and every recorded field.', 'collectibles' ); ?></p>
			</div>
		</header>

		<?php if ( empty( $coll_collections ) ) : ?>
			<section class="empty-state">
				<h2><?php echo esc_html__( 'Nothing to search yet', 'collectibles' ); ?></h2>
				<a class="button button-primary" href="<?php echo esc_url( App::get_url( 'collection/new' ) ); ?>">
					<?php echo esc_html__( 'Start a collection', 'collectibles' ); ?>
				</a>
			</section>
		<?php else : ?>
			<form class="toolbar" method="get" action="<?php echo esc_url( App::get_url( 'search' ) ); ?>">
				<div class="toolbar-search">
					<label class="screen-reader-text" for="coll_q"><?php echo esc_html__( 'Search', 'collectibles' ); ?></label>
					<input id="coll_q" name="q" type="search" value="<?php echo esc_attr( $coll_search ); ?>" placeholder="<?php echo esc_attr__( 'Search everything…', 'collectibles' ); ?>" autofocus>
				</div>

				<div class="toolbar-field">
					<label class="screen-reader-text" for="coll_collection"><?php echo esc_html__( 'Collection', 'collectibles' ); ?></label>
					<select id="coll_collection" name="collection">
						<option value="0"><?php echo esc_html__( 'All collections', 'collectibles' ); ?></option>
						<?php foreach ( $coll_collections as $coll_option_collection ) : ?>
							<option value="<?php echo esc_attr( $coll_option_collection->ID ); ?>" <?php selected( $coll_scope, absint( $coll_option_collection->ID ) ); ?>>
								<?php echo esc_html( get_the_title( $coll_option_collection ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="toolbar-field">
					<label class="screen-reader-text" for="coll_status"><?php echo esc_html__( 'Status', 'collectibles' ); ?></label>
					<select id="coll_status" name="status">
						<option value=""><?php echo esc_html__( 'Any status', 'collectibles' ); ?></option>
						<?php foreach ( Schema::get_statuses() as $coll_status_slug => $coll_status_label ) : ?>
							<option value="<?php echo esc_attr( $coll_status_slug ); ?>" <?php selected( $coll_status, $coll_status_slug ); ?>>
								<?php echo esc_html( $coll_status_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="toolbar-field">
					<label class="screen-reader-text" for="coll_sort"><?php echo esc_html__( 'Sort by', 'collectibles' ); ?></label>
					<select id="coll_sort" name="sort">
						<?php foreach ( Item::get_sort_options() as $coll_sort_slug => $coll_sort_label ) : ?>
							<option value="<?php echo esc_attr( $coll_sort_slug ); ?>" <?php selected( $coll_sort, $coll_sort_slug ); ?>>
								<?php echo esc_html( $coll_sort_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<button class="button button-primary" type="submit"><?php echo esc_html__( 'Search', 'collectibles' ); ?></button>
			</form>

			<?php if ( $coll_has_query ) : ?>
				<?php if ( empty( $coll_items ) ) : ?>
					<section class="empty-state">
						<h2><?php echo esc_html__( 'No matches', 'collectibles' ); ?></h2>
						<p><?php echo esc_html__( 'Try a shorter term, or drop the filters.', 'collectibles' ); ?></p>
					</section>
				<?php else : ?>
					<p class="result-count">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of matching entries */
								_n( '%s match', '%s matches', count( $coll_items ), 'collectibles' ),
								number_format_i18n( count( $coll_items ) )
							)
						);
						?>
					</p>

					<section class="item-grid" aria-label="<?php echo esc_attr__( 'Search results', 'collectibles' ); ?>">
						<?php
						foreach ( $coll_items as $coll_card_item ) {
							$coll_card_collection_id   = absint( $coll_card_item->post_parent );
							$coll_card_kind            = Collection::get_kind( $coll_card_collection_id );
							$coll_card_currency        = Collection::get_currency( $coll_card_collection_id );
							$coll_card_show_collection = true;

							require __DIR__ . '/_item-card.php';
						}
						?>
					</section>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>

<?php
require __DIR__ . '/_foot.php';
