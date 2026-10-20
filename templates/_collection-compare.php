<?php
/**
 * Public browser-only comparison controls. Expects $coll_local public data.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="coll-public-comparison">
<form id="coll-compare-form">
	<label class="screen-reader-text" for="coll-compare-url"><?php echo esc_html__( 'Shared collection URL', 'collectibles' ); ?></label>
	<input id="coll-compare-url" type="url" required placeholder="<?php echo esc_attr__( 'Paste your shared collection URL', 'collectibles' ); ?>">
	<button class="button button-primary" type="submit"><?php echo esc_html__( 'Compare', 'collectibles' ); ?></button>
</form>
<p id="coll-compare-message" role="status" aria-live="polite"></p>
<script type="application/json" id="coll-compare-data"><?php echo wp_json_encode( $coll_local, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
<script type="application/json" id="coll-compare-labels">
<?php
echo wp_json_encode(
	array(
		'contents' => __( 'Comparison sections', 'collectibles' ),
		'both'     => __( 'In both collections', 'collectibles' ),
		'local'    => __( 'Only in this shared collection', 'collectibles' ),
		'remote'   => __( 'Only in your shared collection', 'collectibles' ),
		'loading'  => __( 'Loading shared collection…', 'collectibles' ),
		'error'    => __( 'Could not load this collection. Check the URL and that the site supports public JSON sharing.', 'collectibles' ),
		'kind'     => __( 'Choose a collection of the same kind.', 'collectibles' ),
		'done'     => __( 'Comparison complete. Only publicly shared items from both collections are included.', 'collectibles' ),
	),
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
</script>
</section>
