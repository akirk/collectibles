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
			$header[] = Item::get_field_label( $field, $currency );
		}

		$header[] = __( 'Tags', 'collectibles' );
		$header[] = __( 'Notes', 'collectibles' );
		$header[] = 'Item ID';

		$rows = array( $header );

		foreach ( $items as $item ) {
			$tags  = implode( ', ', wp_list_pluck( Item::get_tags( $item->ID ), 'name' ) );
			$notes = wp_strip_all_tags( $item->post_content );

			// An item holding pieces in several conditions is several lines,
			// alike but for the lot columns — the shape a spreadsheet can sum.
			foreach ( Item::get_lots( $item->ID ) as $lot ) {
				$row = array( $item->post_title );

				foreach ( $fields as $field ) {
					$value = Item::is_lot_field( $field )
						? (string) $lot[ $field['key'] ]
						: (string) get_post_meta( $item->ID, $field['key'], true );

					if ( 'select' === $field['type'] && '' !== $value ) {
						$value = $field['options'][ $value ] ?? $value;
					}

					$row[] = $value;
				}

				$row[] = $tags;
				$row[] = $notes;
				$row[] = (string) $item->ID;

				$rows[] = $row;
			}
		}

		return self::to_string( $rows );
	}


	/**
	 * Validate an exported document before creating any items.
	 *
	 * Older exports have no item ID, so identical item columns group their lots.
	 * IDs in newer exports only group rows; existing items are never updated.
	 *
	 * @param string $document CSV contents.
	 * @param int    $collection_id Destination collection.
	 * @return array|\WP_Error Prepared items or a validation error.
	 */
	public static function parse_import( string $document, int $collection_id ) {
		$fields = Item::get_fields_for_kind( Collection::get_kind( $collection_id ) );
		$header = array( __( 'Name', 'collectibles' ) );
		foreach ( $fields as $field ) {
			$header[] = Item::get_field_label( $field, Collection::get_currency( $collection_id ) );
		}
		$header[] = __( 'Tags', 'collectibles' );
		$header[] = __( 'Notes', 'collectibles' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory CSV stream.
		$stream = fopen( 'php://temp', 'r+' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- In-memory CSV stream.
		fwrite( $stream, preg_replace( '/^\xEF\xBB\xBF/', '', $document ) );
		rewind( $stream );
		try {
			$actual = fgetcsv( $stream, 0, ',', '"', '' );
			$has_id = array_merge( $header, array( 'Item ID' ) ) === $actual;
			if ( ! $has_id && $header !== $actual ) {
				return new \WP_Error( 'csv_header', __( 'Choose a CSV exported from a collection of the same kind, currency and language.', 'collectibles' ) );
			}
			$items = array();
			// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Read one CSV record at a time.
			while ( false !== ( $row = fgetcsv( $stream, 0, ',', '"', '' ) ) ) {
				if ( array( null ) === $row ) {
					continue;
				}
				if ( count( $row ) !== count( $actual ) || '' === trim( $row[0] ) ) {
					return new \WP_Error( 'csv_row', __( 'Every CSV row must have a name and the same number of columns as the header.', 'collectibles' ) );
				}
				// Undo the formula protection added by our exporter.
				foreach ( $row as &$cell ) {
					if ( preg_match( "/^'[=+@-]/", $cell ) ) {
						$cell = substr( $cell, 1 );
					}
				}
				unset( $cell );
				$values = array();
				$lot    = array();
				foreach ( $fields as $index => $field ) {
					$value = $row[ $index + 1 ];
					if ( 'select' === $field['type'] && '' !== $value ) {
						$key   = array_search( $value, $field['options'], true );
						$value = false === $key ? $value : (string) $key;
					}
					if ( Item::is_lot_field( $field ) ) {
						$lot[ $field['key'] ] = $value;
					} else {
						$values[ 'coll_field_' . $field['key'] ] = $value;
					}
				}
				$item = array(
					'name'   => $row[0],
					'tags'   => $row[ count( $fields ) + 1 ],
					'notes'  => $row[ count( $fields ) + 2 ],
					'values' => $values,
				);
				if ( $has_id && ! ctype_digit( (string) end( $row ) ) ) {
					return new \WP_Error( 'csv_id', __( 'Every row must have an Item ID.', 'collectibles' ) );
				}
				$key = $has_id ? 'id:' . end( $row ) : wp_json_encode( $item );
				if ( isset( $items[ $key ] ) ) {
					$previous = $items[ $key ];
					unset( $previous['lots'] );
					if ( $previous !== $item ) {
						return new \WP_Error( 'csv_conflict', __( 'Rows for the same item must agree on all fields outside its lots.', 'collectibles' ) );
					}
				} else {
					$items[ $key ] = $item + array( 'lots' => array() );
				}
				$items[ $key ]['lots'][] = $lot;
			}
			if ( ! $items ) {
				return new \WP_Error( 'csv_empty', __( 'The CSV contains no items.', 'collectibles' ) );
			}
			return array_values( $items );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory CSV stream.
			fclose( $stream );
		}
	}

	/**
	 * Import new private items, removing this import's posts on failure.
	 *
	 * @param string $document CSV contents.
	 * @param int    $collection_id Destination collection.
	 * @return int|\WP_Error Number created or an error.
	 */
	public static function import_collection( string $document, int $collection_id ) {
		if ( ! Collection::get( $collection_id ) || ! current_user_can( 'edit_post', $collection_id ) || ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'csv_permission', __( 'You do not have permission to import into this collection.', 'collectibles' ) );
		}
		$items = self::parse_import( $document, $collection_id );
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		$created = array();
		foreach ( $items as $item ) {
			$id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => Item::POST_TYPE,
						'post_status'  => 'publish',
						'post_parent'  => $collection_id,
						'post_author'  => get_current_user_id(),
						'post_title'   => sanitize_text_field( $item['name'] ),
						'post_content' => sanitize_textarea_field( $item['notes'] ),
					)
				),
				true
			);
			if ( ! is_wp_error( $id ) ) {
				$created[] = $id;
				$result    = Sharing::set_item_shared( $collection_id, $id, false );
				if ( is_wp_error( $result ) ) {
					foreach ( $created as $created_id ) {
						wp_delete_post( $created_id, true );
					}
					return $result;
				}
				Item::save_values( $id, Collection::get_kind( $collection_id ), $item['values'] + array( 'coll_lot' => $item['lots'] ) );
				$result = wp_set_object_terms( $id, array_filter( array_map( 'trim', explode( ',', $item['tags'] ) ) ), Item::TAXONOMY );
			} else {
				$result = $id;
			}
			if ( is_wp_error( $result ) ) {
				foreach ( $created as $created_id ) {
					wp_delete_post( $created_id, true );
				}
				return $result;
			}
		}
		return count( $created );
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
