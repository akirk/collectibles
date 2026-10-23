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
	<form class="panel panel-lookup" data-coll-rebrickable-lookup method="post" action="<?php echo esc_url( $coll_form_url ); ?>">
		<input type="hidden" name="coll_action" value="rebrickable_lookup">
		<?php wp_nonce_field( 'coll_rebrickable_lookup_' . $coll_collection_id, 'coll_nonce' ); ?>
		<div class="field" data-coll-bulk-controls hidden
			data-discard="<?php echo esc_attr__( 'Discard this entry', 'collectibles' ); ?>"
			data-loading="<?php echo esc_attr__( 'Queued for lookup…', 'collectibles' ); ?>"
			data-refine="<?php echo esc_attr__( 'Review and refine details', 'collectibles' ); ?>"
			data-save="<?php echo esc_attr__( 'Add to collection', 'collectibles' ); ?>"
			data-duplicate="<?php echo esc_attr__( 'Already entered in this session', 'collectibles' ); ?>"
			data-saving="<?php echo esc_attr__( 'Queued to save…', 'collectibles' ); ?>"
			data-saved="<?php echo esc_attr__( 'Saved — view set', 'collectibles' ); ?>"
			data-error="<?php echo esc_attr__( 'Request failed. Check your connection and reload before trying again; a save may already have completed.', 'collectibles' ); ?>"
			data-leave="<?php echo esc_attr__( 'There are unsaved sets or pending requests.', 'collectibles' ); ?>">
			<label><input type="checkbox" data-coll-bulk-toggle> <?php echo esc_html__( 'Bulk add mode', 'collectibles' ); ?></label>
			<p class="field-hint"><?php echo esc_html__( 'Enter a set number and press Enter, then type the next one. Check each result and add it below. Requests run one at a time. Keep this page open until your sets are saved. Open “Review and refine details” to adjust any fields or upload photos before saving.', 'collectibles' ); ?></p>
		</div>
		<div class="field">
			<label for="coll_rebrickable_ref"><?php echo esc_html__( 'Fill in from Rebrickable', 'collectibles' ); ?></label>
			<div class="lookup-row">
				<input id="coll_rebrickable_ref" name="coll_rebrickable_ref" type="text" value="<?php echo esc_attr( $coll_lookup_ref ); ?>" placeholder="<?php echo esc_attr__( 'Set number, LEGO or Rebrickable link', 'collectibles' ); ?>" required>
				<button class="button" type="submit"><?php echo esc_html__( 'Fetch', 'collectibles' ); ?></button>
			</div>
			<p class="field-hint"><?php echo esc_html__( 'LEGO sets only. A number without a variant uses -1. Cached lookups make no API calls. Review the details before saving.', 'collectibles' ); ?></p>
			<?php if ( ! empty( $coll_prefill['values']['rebrickable_id'] ) ) : ?>
				<p class="field-hint">
					<?php echo esc_html__( 'The catalogue image is imported when you save. You can also upload your own photo below.', 'collectibles' ); ?>
					<?php foreach ( Rebrickable::get_set_links( $coll_prefill['values']['rebrickable_id'] ) as $coll_link_label => $coll_link_url ) : ?>
						<a href="<?php echo esc_url( $coll_link_url ); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html( $coll_link_label ); ?></a>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
		</div>
	</form>
	<div data-coll-bulk-results aria-live="polite"></div>
<?php else : ?>
	<div class="notice">
		<?php echo esc_html__( 'Add your Rebrickable API key to fill LEGO sets in from the catalogue. You can enter sets of any brand manually.', 'collectibles' ); ?>
		<a href="<?php echo esc_url( App::get_url( 'settings' ) ); ?>"><?php echo esc_html__( 'Settings', 'collectibles' ); ?></a>
	</div>
<?php endif; ?>
