<?php
/**
 * Rebrickable lookup panel for a new item.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( '' !== Rebrickable::get_api_key() ) : ?>
	<form class="panel panel-lookup" method="post" action="<?php echo esc_url( $coll_form_url ); ?>">
		<input type="hidden" name="coll_action" value="rebrickable_lookup">
		<?php wp_nonce_field( 'coll_rebrickable_lookup_' . $coll_collection_id, 'coll_nonce' ); ?>
		<div class="field">
			<label for="coll_rebrickable_ref"><?php echo esc_html__( 'Fill in from Rebrickable', 'collectibles' ); ?></label>
			<div class="lookup-row">
				<input id="coll_rebrickable_ref" name="coll_rebrickable_ref" type="text" value="<?php echo esc_attr( $coll_lookup_ref ); ?>" placeholder="<?php echo esc_attr__( 'Set number or Rebrickable set link', 'collectibles' ); ?>" required>
				<button class="button" type="submit"><?php echo esc_html__( 'Fetch', 'collectibles' ); ?></button>
			</div>
			<p class="field-hint"><?php echo esc_html__( 'LEGO sets only. A number without a variant uses -1. Cached lookups make no API calls. Review the details before saving.', 'collectibles' ); ?></p>
			<?php if ( ! empty( $coll_prefill['values']['rebrickable_id'] ) ) : ?>
				<p class="field-hint">
					<?php echo esc_html__( 'Open the set page to find an image, then upload it below.', 'collectibles' ); ?>
					<?php foreach ( Rebrickable::get_set_links( $coll_prefill['values']['rebrickable_id'] ) as $coll_link_label => $coll_link_url ) : ?>
						<a href="<?php echo esc_url( $coll_link_url ); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html( $coll_link_label ); ?></a>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
		</div>
	</form>
<?php else : ?>
	<div class="notice">
		<?php echo esc_html__( 'Add your Rebrickable API key to fill LEGO sets in from the catalogue. You can enter sets of any brand manually.', 'collectibles' ); ?>
		<a href="<?php echo esc_url( App::get_url( 'settings' ) ); ?>"><?php echo esc_html__( 'Settings', 'collectibles' ); ?></a>
	</div>
<?php endif; ?>
