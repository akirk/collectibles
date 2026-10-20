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
$coll_card_url             = App::get_url( absint( $coll_card_collection_id ) . '/item/' . absint( $coll_card_item->ID ) );
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

$coll_card_front   = Item::get_side_photo_id( $coll_card_item->ID, Item::FRONT_META_KEY );
$coll_card_back    = Item::get_side_photo_id( $coll_card_item->ID, Item::BACK_META_KEY );
$coll_card_sharing = array();
if ( 'selected' === Sharing::get_settings( (int) $coll_card_collection_id )['mode'] && current_user_can( 'edit_post', $coll_card_collection_id ) && current_user_can( 'edit_post', $coll_card_item->ID ) ) {
	$coll_card_sharing                  = Sharing::get_item_state( (int) $coll_card_collection_id, (int) $coll_card_item->ID );
	$coll_card_sharing['item_id']       = $coll_card_item->ID;
	$coll_card_sharing['collection_id'] = $coll_card_collection_id;
	$coll_card_sharing['nonce']         = wp_create_nonce( 'coll_share_item_' . $coll_card_item->ID );
}
$coll_card_display = array(
	'title'          => $coll_card_title,
	'url'            => $coll_card_url,
	'ratio'          => Schema::get_thumb_ratio( $coll_card_kind ),
	'icon'           => Schema::get_kind_icon( $coll_card_kind ),
	'image'          => $coll_card_front ? wp_get_attachment_image_url( $coll_card_front, 'medium' ) : wp_get_attachment_image_url( $coll_card_photo, 'medium' ),
	'back'           => $coll_card_front && $coll_card_back && $coll_card_front !== $coll_card_back ? wp_get_attachment_image_url( $coll_card_back, 'medium' ) : '',
	'quantity'       => $coll_card_quantity,
	'facts'          => $coll_card_facts,
	'collection'     => $coll_card_show_collection ? get_the_title( $coll_card_collection_id ) : '',
	'collection_url' => App::get_url( absint( $coll_card_collection_id ) ),
	'status'         => Schema::STATUS_OWNED !== $coll_card_status ? Schema::get_status_label( $coll_card_status ) : '',
	'status_class'   => $coll_card_status,
	'value'          => $coll_card_value > 0 ? Item::format_money( $coll_card_value, $coll_card_currency ) : '',
	'sharing'        => $coll_card_sharing,
	'fields'         => array(),
	'photos'         => array(),
);
require __DIR__ . '/_item-card-display.php';
