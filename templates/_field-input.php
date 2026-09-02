<?php
/**
 * One form control for an item field definition.
 *
 * Expects $coll_field (a field definition from Item::get_fields_for_kind())
 * and $coll_field_value (the stored value). $coll_field_currency labels money
 * fields.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $coll_field ) || ! is_array( $coll_field ) ) {
	return;
}

$coll_field_value    = isset( $coll_field_value ) ? (string) $coll_field_value : '';
$coll_field_currency = isset( $coll_field_currency ) ? (string) $coll_field_currency : '';
$coll_field_id       = 'coll_field_' . $coll_field['key'];
$coll_field_label    = Item::get_field_label( $coll_field, $coll_field_currency );
?>
<div class="field field-<?php echo esc_attr( $coll_field['type'] ); ?>">
	<label for="<?php echo esc_attr( $coll_field_id ); ?>"><?php echo esc_html( $coll_field_label ); ?></label>

	<?php if ( 'select' === $coll_field['type'] ) : ?>
		<select id="<?php echo esc_attr( $coll_field_id ); ?>" name="<?php echo esc_attr( $coll_field_id ); ?>">
			<option value="">&mdash;</option>
			<?php foreach ( $coll_field['options'] as $coll_option_slug => $coll_option_label ) : ?>
				<option value="<?php echo esc_attr( $coll_option_slug ); ?>" <?php selected( $coll_field_value, $coll_option_slug ); ?>>
					<?php echo esc_html( $coll_option_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

	<?php elseif ( 'number' === $coll_field['type'] ) : ?>
		<input
			id="<?php echo esc_attr( $coll_field_id ); ?>"
			name="<?php echo esc_attr( $coll_field_id ); ?>"
			type="number"
			inputmode="decimal"
			step="<?php echo esc_attr( $coll_field['step'] ?? 'any' ); ?>"
			<?php if ( isset( $coll_field['min'] ) ) : ?>
				min="<?php echo esc_attr( $coll_field['min'] ); ?>"
			<?php endif; ?>
			<?php if ( isset( $coll_field['placeholder'] ) ) : ?>
				placeholder="<?php echo esc_attr( $coll_field['placeholder'] ); ?>"
			<?php endif; ?>
			value="<?php echo esc_attr( $coll_field_value ); ?>"
		>

	<?php elseif ( 'date' === $coll_field['type'] ) : ?>
		<input
			id="<?php echo esc_attr( $coll_field_id ); ?>"
			name="<?php echo esc_attr( $coll_field_id ); ?>"
			type="date"
			value="<?php echo esc_attr( $coll_field_value ); ?>"
		>

	<?php else : ?>
		<input
			id="<?php echo esc_attr( $coll_field_id ); ?>"
			name="<?php echo esc_attr( $coll_field_id ); ?>"
			type="text"
			<?php if ( isset( $coll_field['placeholder'] ) ) : ?>
				placeholder="<?php echo esc_attr( $coll_field['placeholder'] ); ?>"
			<?php endif; ?>
			value="<?php echo esc_attr( $coll_field_value ); ?>"
		>
	<?php endif; ?>
</div>
