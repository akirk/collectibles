<?php
/**
 * CSV export.
 *
 * @package Collectibles
 */

namespace Collectibles;

/**
 * Turns a collection into a spreadsheet — for insurance lists, for a dealer,
 * or just to have the catalog somewhere that is not a database.
 */
class Csv {
	/**
	 * Build the CSV document for one collection.
	 *
	 * Numbers stay unformatted so that a spreadsheet reads them as numbers;
	 * selects are exported with their human-readable label.
	 *
	 * @param int $collection_id Collection post ID.
	 */
	public static function export_collection( int $collection_id ): string {
		$kind     = Collection::get_kind( $collection_id );
		$currency = Collection::get_currency( $collection_id );
		$fields   = Item::get_fields_for_kind( $kind );
		$items    = Item::query(
			array(
				'collection' => $collection_id,
				'orderby'    => 'title',
			)
		);

		$header = array( __( 'Name', 'collectibles' ) );

		foreach ( $fields as $field ) {
			$label = $field['label'];

			if ( ! empty( $field['money'] ) ) {
				/* translators: 1: field label, 2: currency code or unit of measurement */
				$label = sprintf( __( '%1$s (%2$s)', 'collectibles' ), $label, $currency );
			} elseif ( isset( $field['unit'] ) ) {
				/* translators: 1: field label, 2: currency code or unit of measurement */
				$label = sprintf( __( '%1$s (%2$s)', 'collectibles' ), $label, $field['unit'] );
			}

			$header[] = $label;
		}

		$header[] = __( 'Tags', 'collectibles' );
		$header[] = __( 'Notes', 'collectibles' );

		$rows = array( $header );

		foreach ( $items as $item ) {
			$row = array( get_the_title( $item ) );

			foreach ( $fields as $field ) {
				$value = (string) get_post_meta( $item->ID, $field['key'], true );

				if ( 'select' === $field['type'] && '' !== $value ) {
					$value = $field['options'][ $value ] ?? $value;
				}

				$row[] = $value;
			}

			$row[] = implode( ', ', wp_list_pluck( Item::get_tags( $item->ID ), 'name' ) );
			$row[] = wp_strip_all_tags( $item->post_content );

			$rows[] = $row;
		}

		return self::to_string( $rows );
	}

	/**
	 * Render rows as an RFC 4180 CSV document, prefixed with a byte order mark
	 * so spreadsheets open it as UTF-8.
	 *
	 * @param array<int, array<int, string>> $rows Rows of cells.
	 */
	public static function to_string( array $rows ): string {
		$lines = array();

		foreach ( $rows as $row ) {
			$cells = array();

			foreach ( $row as $cell ) {
				$cells[] = self::escape_cell( (string) $cell );
			}

			$lines[] = implode( ',', $cells );
		}

		return "\xEF\xBB\xBF" . implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Quote a single cell.
	 *
	 * Cells starting with a formula character are prefixed with an apostrophe
	 * so that a spreadsheet treats them as text.
	 *
	 * @param string $value Cell value.
	 */
	private static function escape_cell( string $value ): string {
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );

		if ( '' !== $value && false !== strpos( '=+-@', $value[0] ) ) {
			$value = "'" . $value;
		}

		return '"' . str_replace( '"', '""', $value ) . '"';
	}

	/**
	 * A filesystem-safe download filename for a collection.
	 *
	 * @param \WP_Post $collection The collection.
	 */
	public static function get_filename( \WP_Post $collection ): string {
		$slug = sanitize_title( get_the_title( $collection ) );

		if ( '' === $slug ) {
			$slug = 'collection-' . absint( $collection->ID );
		}

		return $slug . '-' . gmdate( 'Y-m-d' ) . '.csv';
	}
}
