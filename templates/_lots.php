<?php
/**
 * The lots of an item: one row per condition, each with its own piece count
 * and its own money.
 *
 * Expects $coll_lot_fields (from Item::get_lot_fields()), $coll_lot_rows (from
 * Item::get_lots()) and $coll_currency. A blank row is always appended so a
 * further condition can be added without JavaScript.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $coll_lot_fields ) || ! is_array( $coll_lot_fields ) || empty( $coll_lot_fields ) ) {
	return;
}

$coll_lot_rows   = isset( $coll_lot_rows ) && is_array( $coll_lot_rows ) ? array_values( $coll_lot_rows ) : array();
$coll_currency   = isset( $coll_currency ) ? (string) $coll_currency : '';
$coll_lot_rows[] = array();
?>
<fieldset class="field lots">
	<legend><?php echo esc_html__( 'Condition and pieces', 'collectibles' ); ?></legend>

	<div class="lot-head" aria-hidden="true">
		<?php foreach ( $coll_lot_fields as $coll_lot_field ) : ?>
			<span><?php echo esc_html( Item::get_field_label( $coll_lot_field, $coll_currency ) ); ?></span>
		<?php endforeach; ?>
	</div>

	<div class="lot-rows">
		<?php foreach ( $coll_lot_rows as $coll_lot_index => $coll_lot_row ) : ?>
			<div class="lot-row">
				<?php foreach ( $coll_lot_fields as $coll_lot_field ) : ?>
					<?php
					$coll_lot_key   = $coll_lot_field['key'];
					$coll_lot_name  = 'coll_lot[' . absint( $coll_lot_index ) . '][' . $coll_lot_key . ']';
					$coll_lot_value = isset( $coll_lot_row[ $coll_lot_key ] ) ? (string) $coll_lot_row[ $coll_lot_key ] : '';
					$coll_lot_label = Item::get_field_label( $coll_lot_field, $coll_currency );
					?>
					<div class="lot-cell">
						<span class="lot-cell-label" aria-hidden="true"><?php echo esc_html( $coll_lot_label ); ?></span>

						<?php if ( 'select' === $coll_lot_field['type'] ) : ?>
							<select name="<?php echo esc_attr( $coll_lot_name ); ?>" aria-label="<?php echo esc_attr( $coll_lot_label ); ?>">
								<option value="">&mdash;</option>
								<?php foreach ( $coll_lot_field['options'] as $coll_lot_slug => $coll_lot_option ) : ?>
									<option value="<?php echo esc_attr( $coll_lot_slug ); ?>" <?php selected( $coll_lot_value, $coll_lot_slug ); ?>>
										<?php echo esc_html( $coll_lot_option ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php else : ?>
							<input
								name="<?php echo esc_attr( $coll_lot_name ); ?>"
								type="number"
								inputmode="decimal"
								step="<?php echo esc_attr( $coll_lot_field['step'] ?? 'any' ); ?>"
								<?php if ( isset( $coll_lot_field['min'] ) ) : ?>
									min="<?php echo esc_attr( $coll_lot_field['min'] ); ?>"
								<?php endif; ?>
								value="<?php echo esc_attr( $coll_lot_value ); ?>"
								aria-label="<?php echo esc_attr( $coll_lot_label ); ?>"
							>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<p class="field-hint">
		<button class="button button-quiet" type="button" data-coll-add-lot hidden>
			<?php echo esc_html__( 'Add another condition', 'collectibles' ); ?>
		</button>
		<?php echo esc_html__( 'Prices are per piece. Empty a row to drop it.', 'collectibles' ); ?>
	</p>

	<script>
		( function () {
			var root = document.currentScript.parentNode;
			var button = root.querySelector( '[data-coll-add-lot]' );
			var rows = root.querySelector( '.lot-rows' );

			if ( ! button || ! rows ) {
				return;
			}

			button.hidden = false;
			button.addEventListener( 'click', function () {
				var all = rows.querySelectorAll( '.lot-row' );
				var copy = all[ all.length - 1 ].cloneNode( true );
				var index = all.length;

				copy.querySelectorAll( 'input, select' ).forEach( function ( control ) {
					control.name = control.name.replace( /\[\d+\]/, '[' + index + ']' );
					control.value = '';
				} );

				rows.appendChild( copy );
				copy.querySelector( 'input, select' ).focus();
			} );
		}() );
	</script>
</fieldset>
