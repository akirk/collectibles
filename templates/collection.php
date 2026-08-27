<?php
/**
 * One collection: its totals, a filter toolbar, and the items in it.
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
$coll_not_found     = ! $coll_collection;
$coll_forbidden     = ! $coll_not_found && ! current_user_can( 'edit_post', $coll_collection_id );

if ( $coll_not_found ) {
	status_header( 404 );
} elseif ( $coll_forbidden ) {
	status_header( 403 );
}

$coll_kind     = $coll_not_found ? Schema::KIND_OTHER : Collection::get_kind( $coll_collection_id );
$coll_currency = $coll_not_found ? 'EUR' : Collection::get_currency( $coll_collection_id );

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
$coll_search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$coll_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$coll_tag    = isset( $_GET['tag'] ) ? sanitize_title( wp_unslash( $_GET['tag'] ) ) : '';
$coll_sort   = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'recent';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( ! array_key_exists( $coll_status, Schema::get_statuses() ) ) {
	$coll_status = '';
}

if ( ! array_key_exists( $coll_sort, Item::get_sort_options() ) ) {
	$coll_sort = 'recent';
}

$coll_all_items = array();
$coll_items     = array();
$coll_summary   = Item::summarize( array() );
$coll_tags      = array();

if ( ! $coll_not_found && ! $coll_forbidden ) {
	$coll_all_items = Item::query( array( 'collection' => $coll_collection_id ) );
	$coll_summary   = Item::summarize( $coll_all_items );

	foreach ( $coll_all_items as $coll_item ) {
		foreach ( Item::get_tags( $coll_item->ID ) as $coll_term ) {
			$coll_tags[ $coll_term->slug ] = $coll_term->name;
		}
	}

	natcasesort( $coll_tags );

	$coll_is_filtered = '' !== $coll_search || '' !== $coll_status || '' !== $coll_tag;

	$coll_items = $coll_is_filtered || 'recent' !== $coll_sort
		? Item::query(
			array(
				'collection' => $coll_collection_id,
				'search'     => $coll_search,
				'status'     => $coll_status,
				'tag'        => $coll_tag,
				'orderby'    => $coll_sort,
			)
		)
		: $coll_all_items;
}

$coll_page_title = $coll_collection ? get_the_title( $coll_collection ) : __( 'Collection', 'collectibles' );

require __DIR__ . '/_head.php';
?>

		<?php if ( $coll_not_found ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Collection not found', 'collectibles' ); ?></h1>
				<p><?php echo esc_html__( 'The requested collection is not available.', 'collectibles' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to collections', 'collectibles' ); ?></a></p>
			</section>
		<?php elseif ( $coll_forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access denied', 'collectibles' ); ?></h1>
				<p><?php echo esc_html__( 'You do not have permission to view this collection.', 'collectibles' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to collections', 'collectibles' ); ?></a></p>
			</section>
		<?php else : ?>
			<header class="topbar">
				<div>
					<a class="crumb" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Collections', 'collectibles' ); ?></a>
					<h1>
						<span class="title-icon" aria-hidden="true"><?php echo esc_html( Schema::get_kind_icon( $coll_kind ) ); ?></span>
						<?php echo esc_html( get_the_title( $coll_collection ) ); ?>
					</h1>
					<p class="eyebrow"><?php echo esc_html( Schema::get_kind_label( $coll_kind ) ); ?></p>
				</div>
				<div class="actions">
					<a class="button button-primary" href="<?php echo esc_url( App::get_url( 'collection/' . $coll_collection_id . '/item/new' ) ); ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: singular item noun, e.g. "Coin" */
								__( 'Add %s', 'collectibles' ),
								Schema::get_item_noun( $coll_kind )
							)
						);
						?>
					</a>
					<a class="button" href="<?php echo esc_url( App::get_url( 'collection/' . $coll_collection_id . '/edit' ) ); ?>">
						<?php echo esc_html__( 'Edit', 'collectibles' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( App::get_url( 'collection/' . $coll_collection_id . '/export' ) ); ?>">
						<?php echo esc_html__( 'Export CSV', 'collectibles' ); ?>
					</a>
				</div>
			</header>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Post-redirect query flags used only for notices. ?>
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Collection saved.', 'collectibles' ); ?></div>
			<?php endif; ?>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Post-redirect query flags used only for notices. ?>
			<?php if ( isset( $_GET['item_deleted'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Item removed.', 'collectibles' ); ?></div>
			<?php endif; ?>

			<?php if ( '' !== trim( (string) $coll_collection->post_content ) ) : ?>
				<p class="collection-notes"><?php echo esc_html( $coll_collection->post_content ); ?></p>
			<?php endif; ?>

			<section class="stat-strip" aria-label="<?php echo esc_attr__( 'Collection totals', 'collectibles' ); ?>">
				<div class="stat">
					<span class="stat-value"><?php echo esc_html( number_format_i18n( $coll_summary['items'] ) ); ?></span>
					<span class="stat-label"><?php echo esc_html( _n( 'entry', 'entries', $coll_summary['items'], 'collectibles' ) ); ?></span>
				</div>
				<div class="stat">
					<span class="stat-value"><?php echo esc_html( number_format_i18n( $coll_summary['pieces'] ) ); ?></span>
					<span class="stat-label"><?php echo esc_html( _n( 'piece held', 'pieces held', $coll_summary['pieces'], 'collectibles' ) ); ?></span>
				</div>
				<?php if ( $coll_summary['paid'] > 0 ) : ?>
					<div class="stat">
						<span class="stat-value"><?php echo esc_html( Item::format_money( $coll_summary['paid'], $coll_currency ) ); ?></span>
						<span class="stat-label"><?php echo esc_html__( 'paid in total', 'collectibles' ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $coll_summary['value'] > 0 ) : ?>
					<div class="stat">
						<span class="stat-value"><?php echo esc_html( Item::format_money( $coll_summary['value'], $coll_currency ) ); ?></span>
						<span class="stat-label"><?php echo esc_html__( 'estimated value', 'collectibles' ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $coll_summary['wanted'] > 0 ) : ?>
					<div class="stat">
						<span class="stat-value"><?php echo esc_html( number_format_i18n( $coll_summary['wanted'] ) ); ?></span>
						<span class="stat-label"><?php echo esc_html__( 'on the wishlist', 'collectibles' ); ?></span>
					</div>
				<?php endif; ?>
			</section>

			<?php if ( ! empty( $coll_all_items ) ) : ?>
				<form class="toolbar" method="get" action="<?php echo esc_url( App::get_url( 'collection/' . $coll_collection_id ) ); ?>">
					<div class="toolbar-search">
						<label class="screen-reader-text" for="coll_q"><?php echo esc_html__( 'Search this collection', 'collectibles' ); ?></label>
						<input id="coll_q" name="q" type="search" value="<?php echo esc_attr( $coll_search ); ?>" placeholder="<?php echo esc_attr__( 'Search this collection…', 'collectibles' ); ?>">
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

					<?php if ( ! empty( $coll_tags ) ) : ?>
						<div class="toolbar-field">
							<label class="screen-reader-text" for="coll_tag"><?php echo esc_html__( 'Tag', 'collectibles' ); ?></label>
							<select id="coll_tag" name="tag">
								<option value=""><?php echo esc_html__( 'Any tag', 'collectibles' ); ?></option>
								<?php foreach ( $coll_tags as $coll_tag_slug => $coll_tag_name ) : ?>
									<option value="<?php echo esc_attr( $coll_tag_slug ); ?>" <?php selected( $coll_tag, $coll_tag_slug ); ?>>
										<?php echo esc_html( $coll_tag_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

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

					<button class="button" type="submit"><?php echo esc_html__( 'Apply', 'collectibles' ); ?></button>

					<?php if ( '' !== $coll_search || '' !== $coll_status || '' !== $coll_tag || 'recent' !== $coll_sort ) : ?>
						<a class="button button-quiet" href="<?php echo esc_url( App::get_url( 'collection/' . $coll_collection_id ) ); ?>">
							<?php echo esc_html__( 'Reset', 'collectibles' ); ?>
						</a>
					<?php endif; ?>
				</form>
			<?php endif; ?>

			<?php if ( empty( $coll_all_items ) ) : ?>
				<section class="empty-state">
					<p class="empty-icon" aria-hidden="true"><?php echo esc_html( Schema::get_kind_icon( $coll_kind ) ); ?></p>
					<h2><?php echo esc_html__( 'This collection is empty', 'collectibles' ); ?></h2>
					<p><?php echo esc_html__( 'Add the first piece and the totals will start filling in.', 'collectibles' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( App::get_url( 'collection/' . $coll_collection_id . '/item/new' ) ); ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: singular item noun, e.g. "Coin" */
								__( 'Add %s', 'collectibles' ),
								Schema::get_item_noun( $coll_kind )
							)
						);
						?>
					</a>
				</section>
			<?php elseif ( empty( $coll_items ) ) : ?>
				<section class="empty-state">
					<h2><?php echo esc_html__( 'Nothing matches those filters', 'collectibles' ); ?></h2>
					<a class="button" href="<?php echo esc_url( App::get_url( 'collection/' . $coll_collection_id ) ); ?>"><?php echo esc_html__( 'Reset filters', 'collectibles' ); ?></a>
				</section>
			<?php else : ?>
				<?php if ( count( $coll_items ) !== count( $coll_all_items ) ) : ?>
					<p class="result-count">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: number of matching entries, 2: total number of entries */
								__( 'Showing %1$s of %2$s entries.', 'collectibles' ),
								number_format_i18n( count( $coll_items ) ),
								number_format_i18n( count( $coll_all_items ) )
							)
						);
						?>
					</p>
				<?php endif; ?>

				<section class="item-grid" aria-label="<?php echo esc_attr__( 'Items', 'collectibles' ); ?>">
					<?php
					foreach ( $coll_items as $coll_card_item ) {
						$coll_card_collection_id = $coll_collection_id;
						$coll_card_kind          = $coll_kind;
						$coll_card_currency      = $coll_currency;

						require __DIR__ . '/_item-card.php';
					}
					?>
				</section>
			<?php endif; ?>
		<?php endif; ?>

<?php
require __DIR__ . '/_foot.php';
