<?php
/**
 * Owner-only sharing choices and a preview using the public projection.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wp_app_route;
$coll_share_id   = absint( $wp_app_route['params']['id'] ?? get_query_var( 'id' ) );
$coll_collection = Collection::get( $coll_share_id );
if ( ! $coll_collection || ! current_user_can( 'edit_posts' ) || ! current_user_can( 'edit_post', $coll_share_id ) ) {
	wp_die( esc_html__( 'You cannot change sharing for this collection.', 'collectibles' ), '', array( 'response' => 403 ) );
}
Sharing::send_headers();
$coll_share_values         = Sharing::get_settings( $coll_share_id );
$coll_share_values['slug'] = $coll_collection->post_name;
$coll_share_error          = '';
$coll_share_saved          = false;
if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
	check_admin_referer( 'coll_sharing_' . $coll_share_id );
	$coll_share_values = array(
		'mode'        => sanitize_key( wp_unslash( $_POST['coll_mode'] ?? 'private' ) ),
		'slug'        => sanitize_title( wp_unslash( $_POST['coll_slug'] ?? '' ) ),
		'description' => sanitize_textarea_field( wp_unslash( $_POST['coll_description'] ?? '' ) ),
		'item_ids'    => array_map( 'absint', (array) wp_unslash( $_POST['coll_item_ids'] ?? array() ) ),
	);
	$coll_share_action = sanitize_key( wp_unslash( $_POST['coll_action'] ?? '' ) );
	$coll_share_result = 'preview' === $coll_share_action
		? Sharing::preview( $coll_share_id, $coll_share_values )
		: ( 'save' === $coll_share_action ? Sharing::save( $coll_share_id, $coll_share_values ) : new \WP_Error( 'coll_sharing_action', __( 'Choose a sharing action.', 'collectibles' ) ) );
	if ( is_wp_error( $coll_share_result ) ) {
		$coll_share_error = $coll_share_result->get_error_message();
	} elseif ( 'preview' === $coll_share_action ) {
		$coll_public_data    = $coll_share_result;
		$coll_public_preview = true;
		require __DIR__ . '/_public-page.php';
		return;
	} else {
		$coll_share_values = $coll_share_result;
		$coll_share_saved  = true;
		$coll_collection   = Collection::get( $coll_share_id );
	}
}
$coll_share_items = Item::query( array( 'collection' => $coll_share_id ) );
$coll_page_title  = __( 'Sharing', 'collectibles' );
$coll_shell_class = 'shell shell-mid';
require __DIR__ . '/_head.php';
?>
<header class="topbar">
	<div>
		<a class="crumb" href="<?php echo esc_url( App::get_url( 'collection/' . $coll_share_id ) ); ?>"><?php echo esc_html( $coll_collection->post_title ); ?></a>
		<h1><?php echo esc_html( $coll_page_title ); ?></h1>
		<p><?php echo esc_html__( 'Share your collection, or just the items you choose.', 'collectibles' ); ?></p>
	</div>
</header>
<?php if ( $coll_share_error ) : ?>
	<p class="notice notice-error"><?php echo esc_html( $coll_share_error ); ?></p>
<?php endif; ?>
<?php if ( $coll_share_saved ) : ?>
	<p class="notice"><?php echo esc_html__( 'Sharing settings saved.', 'collectibles' ); ?></p>
<?php endif; ?>
<?php if ( 'private' !== Sharing::get_settings( $coll_share_id )['mode'] ) : ?>
	<p class="notice"><a href="<?php echo esc_url( Sharing::get_url( $coll_collection ) ); ?>"><?php echo esc_html__( 'View public collection', 'collectibles' ); ?></a></p>
<?php endif; ?>
<form class="panel" method="post" action="<?php echo esc_url( App::get_url( 'collection/' . $coll_share_id . '/sharing' ) ); ?>">
	<?php wp_nonce_field( 'coll_sharing_' . $coll_share_id ); ?>
	<fieldset class="field coll-share-options">
		<legend><?php echo esc_html__( 'Visibility', 'collectibles' ); ?></legend>
		<?php
		foreach ( array(
			'private'  => __( 'Private', 'collectibles' ),
			'all'      => __( 'All items, including wishlist entries and future additions', 'collectibles' ),
			'selected' => __( 'Only selected items', 'collectibles' ),
		) as $coll_mode => $coll_mode_label ) :
			?>
			<label><input type="radio" name="coll_mode" value="<?php echo esc_attr( $coll_mode ); ?>" <?php checked( $coll_share_values['mode'], $coll_mode ); ?>> <?php echo esc_html( $coll_mode_label ); ?></label>
		<?php endforeach; ?>
	</fieldset>
	<div class="field">
		<label for="coll_slug"><?php echo esc_html__( 'Public URL name', 'collectibles' ); ?></label>
		<input id="coll_slug" name="coll_slug" type="text" value="<?php echo esc_attr( $coll_share_values['slug'] ); ?>" required>
		<p class="field-hint"><?php echo esc_html( home_url( '/collections/' ) ); ?><strong><?php echo esc_html( $coll_share_values['slug'] ); ?></strong>/</p>
		<p class="field-hint"><?php echo esc_html__( 'A number is appended if that name is already in use. Changing the name changes the shared link.', 'collectibles' ); ?></p>
	</div>
	<div class="field">
		<label for="coll_description"><?php echo esc_html__( 'Public description', 'collectibles' ); ?></label>
		<textarea id="coll_description" name="coll_description" rows="4"><?php echo esc_textarea( $coll_share_values['description'] ); ?></textarea>
	</div>
	<fieldset class="field">
		<legend><?php echo esc_html__( 'Selected items', 'collectibles' ); ?></legend>
		<p class="field-hint"><?php echo esc_html__( 'These choices apply when visibility is set to Only selected items. Draft, private and password-protected items never appear publicly.', 'collectibles' ); ?></p>
		<div class="coll-share-selection">
		<?php foreach ( $coll_share_items as $coll_share_item ) : ?>
			<?php if ( 'publish' !== $coll_share_item->post_status || '' !== $coll_share_item->post_password ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<label><input type="checkbox" name="coll_item_ids[]" value="<?php echo esc_attr( $coll_share_item->ID ); ?>" <?php checked( in_array( (int) $coll_share_item->ID, $coll_share_values['item_ids'], true ) ); ?>>
				<?php echo esc_html( $coll_share_item->post_title ); ?>
				<span class="field-hint"><?php echo esc_html( Schema::get_status_label( Item::get_status( $coll_share_item->ID ) ) ); ?></span>
				<?php $coll_share_number = get_post_meta( $coll_share_item->ID, Item::CATALOG_META_KEY, true ); ?>
				<?php if ( $coll_share_number ) : ?>
					<span class="field-hint"><?php echo esc_html( $coll_share_number ); ?></span>
				<?php endif; ?>
			</label>
		<?php endforeach; ?>
		</div>
	</fieldset>
	<p class="field-hint"><?php echo esc_html__( 'Public pages show titles, photos and descriptive catalogue fields. Prices, storage and acquisition details, serial numbers, personal notes and tags stay private.', 'collectibles' ); ?></p>
	<p class="field-hint"><?php echo esc_html__( 'Anyone can view a shared page without logging in. Search engines are asked not to index it. Photo files already have their own WordPress upload URLs.', 'collectibles' ); ?></p>
	<div class="actions">
		<button class="button" name="coll_action" value="preview"><?php echo esc_html__( 'Preview public page', 'collectibles' ); ?></button>
		<button class="button button-primary" name="coll_action" value="save"><?php echo esc_html__( 'Save visibility', 'collectibles' ); ?></button>
	</div>
</form>
<?php require __DIR__ . '/_foot.php'; ?>
