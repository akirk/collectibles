<?php
/**
 * Source status for an imported field, also refreshed by the private form JS.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! isset( $coll_numista_states[ $coll_source_key ] ) ) {
	return;
}
$coll_source_state = $coll_numista_states[ $coll_source_key ];
?>
<div class="coll-numista-controls" <?php echo 'cleared' === $coll_source_state['state'] ? 'hidden' : ''; ?>>
<button type="button" class="coll-numista-status"
	data-coll-numista-key="<?php echo esc_attr( $coll_source_key ); ?>"
	data-coll-source-input="<?php echo esc_attr( $coll_source_input ); ?>"
	data-state="<?php echo esc_attr( $coll_source_state['state'] ); ?>"
	data-coll-source-hash="<?php echo esc_attr( $coll_numista_hashes[ $coll_source_key ] ); ?>"
	data-coll-source-released="<?php echo in_array( $coll_source_key, $coll_numista_released ?? array(), true ) ? 'true' : 'false'; ?>"
	aria-pressed="<?php echo in_array( $coll_source_state['state'], array( 'updated', 'confirmed' ), true ) ? 'true' : 'false'; ?>"
	><?php echo esc_html( $coll_source_state['label'] ); ?></button>
<button type="button" class="coll-numista-clear" data-coll-clear-source="<?php echo esc_attr( $coll_source_key ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: field label. */ __( 'Clear %s', 'collectibles' ), 'title' === $coll_source_key ? __( 'Name', 'collectibles' ) : $coll_field_label ) ); ?>">&times;</button>
</div>
<input type="checkbox" hidden name="coll_numista_own[]" value="<?php echo esc_attr( $coll_source_key ); ?>" data-coll-numista-own="<?php echo esc_attr( $coll_source_key ); ?>" <?php checked( in_array( $coll_source_key, $coll_numista_own ?? array(), true ) ); ?>>
<input type="hidden" name="coll_numista_private[]" value="<?php echo esc_attr( $coll_source_key ); ?>" data-coll-numista-private="<?php echo esc_attr( $coll_source_key ); ?>" <?php disabled( ! in_array( $coll_source_key, $coll_numista_private ?? array(), true ) ); ?>>
