<?php
/**
 * One item, rendered as a card in a grid.
 *
 * Expects $coll_card_item plus $coll_card_collection_id, $coll_card_kind and
 * $coll_card_currency. Set $coll_card_show_collection to also name the
 * collection the item belongs to.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $coll_card_item ) || ! ( $coll_card_item instanceof \WP_Post ) ) {
	return;
}

$coll_card_show_collection = isset( $coll_card_show_collection ) ? (bool) $coll_card_show_collection : false;
$coll_card_url             = App::get_url( 'collection/' . absint( $coll_card_collection_id ) . '/item/' . absint( $coll_card_item->ID ) );
$coll_card_photo           = get_post_thumbnail_id( $coll_card_item->ID );
$coll_card_status          = Item::get_status( $coll_card_item->ID );
$coll_card_quantity        = Item::get_quantity( $coll_card_item->ID );
$coll_card_year            = (string) get_post_meta( $coll_card_item->ID, Item::YEAR_META_KEY, true );
$coll_card_origin          = Schema::supports_origins( $coll_card_kind ) ? Geography::get_name( (string) get_post_meta( $coll_card_item->ID, Item::COUNTRY_META_KEY, true ) ) : '';
$coll_card_condition       = Item::describe_conditions( $coll_card_item->ID, $coll_card_kind );
$coll_card_value           = Item::get_totals( $coll_card_item->ID )['value'];
$coll_card_facts           = array_values( array_filter( array( $coll_card_year, $coll_card_origin, $coll_card_condition ) ) );
$coll_card_catalog         = Schema::get_kind( $coll_card_kind )['catalog'] ?? array();
$coll_card_number          = (string) get_post_meta( $coll_card_item->ID, 'catalog_number', true );
$coll_card_title           = get_the_title( $coll_card_item );
if ( ! empty( $coll_card_catalog['show_on_card'] ) && '' !== $coll_card_number ) {
	$coll_card_title = $coll_card_number . ' ' . $coll_card_title;
}
?>
<article class="item-card" style="--coll-thumb-ratio: <?php echo esc_attr( Schema::get_thumb_ratio( $coll_card_kind ) ); ?>">
	<a class="item-card-thumb<?php echo $coll_card_photo ? '' : ' is-empty'; ?>" href="<?php echo esc_url( $coll_card_url ); ?>" aria-hidden="true" tabindex="-1">
		<?php if ( $coll_card_photo ) : ?>
			<?php echo wp_kses_post( wp_get_attachment_image( $coll_card_photo, 'medium', false, array( 'alt' => '' ) ) ); ?>
		<?php else : ?>
			<span class="kind-icon"><?php echo esc_html( Schema::get_kind_icon( $coll_card_kind ) ); ?></span>
		<?php endif; ?>
		<?php if ( $coll_card_quantity > 1 ) : ?>
			<span class="qty-badge">&times;<?php echo esc_html( number_format_i18n( $coll_card_quantity ) ); ?></span>
		<?php endif; ?>
	</a>

	<div class="item-card-body">
		<h3><a href="<?php echo esc_url( $coll_card_url ); ?>"><?php echo esc_html( $coll_card_title ); ?></a></h3>

		<?php if ( $coll_card_show_collection ) : ?>
			<p class="item-card-collection">
				<a href="<?php echo esc_url( App::get_url( 'collection/' . absint( $coll_card_collection_id ) ) ); ?>">
					<?php echo esc_html( get_the_title( $coll_card_collection_id ) ); ?>
				</a>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $coll_card_facts ) ) : ?>
			<p class="item-card-facts"><?php echo esc_html( implode( ' · ', $coll_card_facts ) ); ?></p>
		<?php endif; ?>

		<div class="item-card-foot">
			<?php if ( current_user_can( 'edit_post', $coll_card_collection_id ) && current_user_can( 'edit_post', $coll_card_item->ID ) ) : ?>
				<?php $coll_card_sharing = Sharing::get_item_state( (int) $coll_card_collection_id, (int) $coll_card_item->ID ); ?>
				<button type="button" class="coll-share-toggle" data-coll-share-item="<?php echo esc_attr( $coll_card_item->ID ); ?>" data-coll-share-collection="<?php echo esc_attr( $coll_card_collection_id ); ?>" data-coll-share-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-coll-share-nonce="<?php echo esc_attr( wp_create_nonce( 'coll_share_item_' . $coll_card_item->ID ) ); ?>" data-coll-share-saving="<?php echo esc_attr__( 'Saving…', 'collectibles' ); ?>" data-coll-share-unknown="<?php echo esc_attr__( 'Check sharing', 'collectibles' ); ?>" data-coll-share-error="<?php echo esc_attr__( 'Sharing could not be saved. Reload to check the current state and try again.', 'collectibles' ); ?>" data-coll-share-title="<?php echo esc_attr( $coll_card_title ); ?>" aria-pressed="<?php echo $coll_card_sharing['selected'] ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $coll_card_title . ': ' . $coll_card_sharing['action'] ); ?>" title="<?php echo esc_attr( $coll_card_sharing['selected'] ? $coll_card_sharing['message'] : $coll_card_sharing['action'] ); ?>" <?php disabled( ! $coll_card_sharing['eligible'] && ! $coll_card_sharing['selected'] ); ?> hidden>
					<span class="coll-share-switch" aria-hidden="true"></span><span data-coll-share-label><?php echo esc_html( $coll_card_sharing['label'] ); ?></span>
				</button>
			<?php endif; ?>

			<?php if ( Schema::STATUS_OWNED !== $coll_card_status ) : ?>
				<span class="pill pill-<?php echo esc_attr( $coll_card_status ); ?>"><?php echo esc_html( Schema::get_status_label( $coll_card_status ) ); ?></span>
			<?php endif; ?>

			<?php if ( $coll_card_value > 0 ) : ?>
				<span class="item-card-value"><?php echo esc_html( Item::format_money( $coll_card_value, $coll_card_currency ) ); ?></span>
			<?php endif; ?>
		</div>
		<p class="coll-share-message screen-reader-text" role="status" aria-live="polite"></p>
	</div>
</article>
