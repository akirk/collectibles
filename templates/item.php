<?php
/**
 * One item: its photos, every recorded field, tags and notes.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_app_route;

$coll_route_params  = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : array();
$coll_collection_id = isset( $coll_route_params['collection_id'] ) ? absint( $coll_route_params['collection_id'] ) : absint( get_query_var( 'collection_id' ) );
$coll_item_id       = isset( $coll_route_params['id'] ) ? absint( $coll_route_params['id'] ) : absint( get_query_var( 'id' ) );
$coll_collection    = Collection::get( $coll_collection_id );
$coll_item          = Item::get( $coll_item_id );

$coll_not_found = ! $coll_collection || ! $coll_item || absint( $coll_item->post_parent ) !== $coll_collection_id;
$coll_forbidden = ! $coll_not_found && ! current_user_can( 'edit_post', $coll_item_id );

if ( $coll_not_found ) {
	status_header( 404 );
} elseif ( $coll_forbidden ) {
	status_header( 403 );
}

$coll_kind       = $coll_not_found ? Schema::KIND_OTHER : Collection::get_kind( $coll_collection_id );
$coll_currency   = $coll_not_found ? 'EUR' : Collection::get_currency( $coll_collection_id );
$coll_values     = array();
$coll_rows       = array();
$coll_photo_ids  = array();
$coll_tags       = array();
$coll_lots       = array();
$coll_lot_fields = array();
$coll_paid       = 0.0;
$coll_value      = 0.0;

if ( ! $coll_not_found && ! $coll_forbidden ) {
	$coll_values     = Item::get_values( $coll_item_id, $coll_kind );
	$coll_photo_ids  = Item::get_photo_ids( $coll_item_id );
	$coll_tags       = Item::get_tags( $coll_item_id );
	$coll_lots       = Item::get_lots( $coll_item_id );
	$coll_lot_fields = Item::get_lot_fields( $coll_kind );
	$coll_totals     = Item::get_totals( $coll_item_id );
	$coll_paid       = $coll_totals['paid'];
	$coll_value      = $coll_totals['value'];

	foreach ( Item::get_fields_for_kind( $coll_kind ) as $coll_field ) {
		// With one lot the four lot values are simply fields of the item; with
		// several they get a table of their own below.
		if ( Item::is_lot_field( $coll_field ) && count( $coll_lots ) > 1 ) {
			continue;
		}

		$coll_raw     = $coll_values[ $coll_field['key'] ] ?? '';
		$coll_display = Item::format_field_value( $coll_field, $coll_raw, $coll_currency );

		if ( '' === $coll_display ) {
			continue;
		}

		$coll_rows[] = array(
			'label' => $coll_field['label'],
			'value' => $coll_display,
			// A catalogue number that has a canonical page links to it.
			'url'   => isset( $coll_field['link'] ) ? sprintf( $coll_field['link'], rawurlencode( $coll_raw ) ) : '',
		);
	}
}

$coll_page_title  = $coll_item ? get_the_title( $coll_item ) : __( 'Item', 'collectibles' );
$coll_shell_class = 'shell shell-mid';

require __DIR__ . '/_head.php';
?>

		<?php if ( $coll_not_found ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Item not found', 'collectibles' ); ?></h1>
				<p><?php echo esc_html__( 'The requested item is not available.', 'collectibles' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to collections', 'collectibles' ); ?></a></p>
			</section>
		<?php elseif ( $coll_forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access denied', 'collectibles' ); ?></h1>
				<p><?php echo esc_html__( 'You do not have permission to view this item.', 'collectibles' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to collections', 'collectibles' ); ?></a></p>
			</section>
		<?php else : ?>
			<header class="topbar">
				<div>
					<a class="crumb" href="<?php echo esc_url( App::get_url( 'collection/' . $coll_collection_id ) ); ?>">
						<?php echo esc_html( get_the_title( $coll_collection ) ); ?>
					</a>
					<h1><?php echo esc_html( get_the_title( $coll_item ) ); ?></h1>
					<p class="eyebrow">
						<?php echo esc_html( Schema::get_status_label( Item::get_status( $coll_item_id ) ) ); ?>
						<?php if ( Item::get_quantity( $coll_item_id ) > 1 ) : ?>
							·
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: number of pieces */
									__( '%s pieces', 'collectibles' ),
									number_format_i18n( Item::get_quantity( $coll_item_id ) )
								)
							);
							?>
						<?php endif; ?>
					</p>
				</div>
				<div class="actions">
					<?php if ( current_user_can( 'edit_post', $coll_item_id ) ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( App::get_url( 'collection/' . $coll_collection_id . '/item/' . $coll_item_id . '/edit' ) ); ?>">
							<?php echo esc_html__( 'Edit', 'collectibles' ); ?>
						</a>
					<?php endif; ?>
					<a class="button" href="<?php echo esc_url( App::get_url( 'collection/' . $coll_collection_id ) ); ?>">
						<?php echo esc_html__( 'Back to collection', 'collectibles' ); ?>
					</a>
				</div>
			</header>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Post-redirect query flag used only for a notice. ?>
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Item saved.', 'collectibles' ); ?></div>
			<?php endif; ?>

			<div class="item-detail">
				<div class="item-detail-media">
					<?php if ( empty( $coll_photo_ids ) ) : ?>
						<div class="item-photo item-photo-empty">
							<span class="kind-icon" aria-hidden="true"><?php echo esc_html( Schema::get_kind_icon( $coll_kind ) ); ?></span>
						</div>
					<?php else : ?>
						<?php foreach ( $coll_photo_ids as $coll_photo_index => $coll_photo_id ) : ?>
							<figure class="item-photo">
								<a href="<?php echo esc_url( (string) wp_get_attachment_image_url( $coll_photo_id, 'full' ) ); ?>">
									<?php
									echo wp_kses_post(
										wp_get_attachment_image(
											$coll_photo_id,
											0 === $coll_photo_index ? 'large' : 'medium',
											false,
											array( 'alt' => get_the_title( $coll_item ) )
										)
									);
									?>
								</a>
								<?php $coll_photo_side = Item::get_photo_side_label( $coll_item_id, $coll_photo_id ); ?>
								<?php if ( '' !== $coll_photo_side ) : ?>
									<figcaption><?php echo esc_html( $coll_photo_side ); ?></figcaption>
								<?php endif; ?>
							</figure>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<div class="item-detail-body">
					<?php if ( ! empty( $coll_rows ) ) : ?>
						<dl class="spec-list">
							<?php foreach ( $coll_rows as $coll_row ) : ?>
								<div class="spec">
									<dt><?php echo esc_html( $coll_row['label'] ); ?></dt>
									<dd>
										<?php if ( '' !== $coll_row['url'] ) : ?>
											<a href="<?php echo esc_url( $coll_row['url'] ); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html( $coll_row['value'] ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $coll_row['value'] ); ?>
										<?php endif; ?>
									</dd>
								</div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>

					<?php if ( count( $coll_lots ) > 1 ) : ?>
						<div class="lot-table-scroll">
						<table class="lot-table">
							<thead>
								<tr>
									<?php foreach ( $coll_lot_fields as $coll_lot_field ) : ?>
										<th scope="col"><?php echo esc_html( Item::get_field_label( $coll_lot_field, $coll_currency ) ); ?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $coll_lots as $coll_lot ) : ?>
									<tr>
										<?php foreach ( $coll_lot_fields as $coll_lot_field ) : ?>
											<?php
											$coll_lot_display = Item::format_field_value(
												$coll_lot_field,
												(string) $coll_lot[ $coll_lot_field['key'] ],
												$coll_currency
											);
											?>
											<td data-label="<?php echo esc_attr( Item::get_field_label( $coll_lot_field, $coll_currency ) ); ?>">
												<?php echo '' === $coll_lot_display ? '&mdash;' : esc_html( $coll_lot_display ); ?>
											</td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
							<tfoot>
								<tr>
									<th scope="row"><?php echo esc_html__( 'Total', 'collectibles' ); ?></th>
									<td data-label="<?php echo esc_attr( Item::get_field_label( $coll_lot_fields[1], $coll_currency ) ); ?>">
										<?php echo esc_html( number_format_i18n( $coll_totals['pieces'] ) ); ?>
									</td>
									<td data-label="<?php echo esc_attr( Item::get_field_label( $coll_lot_fields[2], $coll_currency ) ); ?>">
										<?php echo esc_html( $coll_paid > 0 ? Item::format_money( $coll_paid, $coll_currency ) : '' ); ?>
									</td>
									<td data-label="<?php echo esc_attr( Item::get_field_label( $coll_lot_fields[3], $coll_currency ) ); ?>">
										<?php echo esc_html( $coll_value > 0 ? Item::format_money( $coll_value, $coll_currency ) : '' ); ?>
									</td>
								</tr>
							</tfoot>
						</table>
						</div>
					<?php endif; ?>

					<?php if ( $coll_paid > 0 && $coll_value > 0 ) : ?>
						<?php $coll_delta = $coll_value - $coll_paid; ?>
						<p class="value-delta <?php echo $coll_delta < 0 ? 'is-down' : 'is-up'; ?>">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: formatted amount, including sign */
									$coll_delta < 0 ? __( '%s below what you paid', 'collectibles' ) : __( '%s above what you paid', 'collectibles' ),
									Item::format_money( abs( $coll_delta ), $coll_currency )
								)
							);
							?>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $coll_tags ) ) : ?>
						<p class="tag-list">
							<?php foreach ( $coll_tags as $coll_tag_term ) : ?>
								<a class="tag" href="<?php echo esc_url( add_query_arg( 'tag', $coll_tag_term->slug, App::get_url( 'collection/' . $coll_collection_id ) ) ); ?>">
									<?php echo esc_html( $coll_tag_term->name ); ?>
								</a>
							<?php endforeach; ?>
						</p>
					<?php endif; ?>

					<?php if ( '' !== trim( (string) $coll_item->post_content ) ) : ?>
						<section class="panel">
							<h2><?php echo esc_html__( 'Notes', 'collectibles' ); ?></h2>
							<?php echo wp_kses_post( wpautop( $coll_item->post_content ) ); ?>
						</section>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

<?php
require __DIR__ . '/_foot.php';
