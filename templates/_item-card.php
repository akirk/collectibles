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
$coll_card_origin          = Geography::get_name( (string) get_post_meta( $coll_card_item->ID, Item::COUNTRY_META_KEY, true ) );
$coll_card_condition       = Schema::get_grade_label( $coll_card_kind, (string) get_post_meta( $coll_card_item->ID, Item::CONDITION_META_KEY, true ) );
$coll_card_value           = (string) get_post_meta( $coll_card_item->ID, Item::VALUE_META_KEY, true );
$coll_card_facts           = array_values( array_filter( array( $coll_card_year, $coll_card_origin, $coll_card_condition ) ) );
?>
<article class="item-card">
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
		<h3><a href="<?php echo esc_url( $coll_card_url ); ?>"><?php echo esc_html( get_the_title( $coll_card_item ) ); ?></a></h3>

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
			<?php if ( Schema::STATUS_OWNED !== $coll_card_status ) : ?>
				<span class="pill pill-<?php echo esc_attr( $coll_card_status ); ?>"><?php echo esc_html( Schema::get_status_label( $coll_card_status ) ); ?></span>
			<?php endif; ?>

			<?php if ( '' !== $coll_card_value ) : ?>
				<span class="item-card-value"><?php echo esc_html( Item::format_money( (float) $coll_card_value, $coll_card_currency ) ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</article>
