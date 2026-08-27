<?php
/**
 * Declarative field schema for the different kinds of collections.
 *
 * Everything the app knows about coins, stamps, banknotes and friends lives
 * here: which extra fields a kind has, and which condition scale it is graded
 * on. Templates render forms and detail views straight from these arrays, so
 * supporting a new kind of collectible means adding one entry below.
 *
 * @package Collectibles
 */

namespace Collectibles;

/**
 * Field and grading definitions per collection kind.
 */
class Schema {
	public const KIND_COINS     = 'coins';
	public const KIND_STAMPS    = 'stamps';
	public const KIND_BANKNOTES = 'banknotes';
	public const KIND_CARDS     = 'cards';
	public const KIND_RECORDS   = 'records';
	public const KIND_BOOKS     = 'books';
	public const KIND_OTHER     = 'other';

	public const STATUS_OWNED     = 'owned';
	public const STATUS_WANTED    = 'wanted';
	public const STATUS_ORDERED   = 'ordered';
	public const STATUS_DUPLICATE = 'duplicate';
	public const STATUS_FOR_SALE  = 'for_sale';
	public const STATUS_SOLD      = 'sold';

	/**
	 * All collection kinds, keyed by slug.
	 *
	 * Each kind provides a label, an icon, the extra fields its items carry,
	 * and the condition scale those items are graded on.
	 *
	 * @return array<string, array>
	 */
	public static function get_kinds(): array {
		return array(
			self::KIND_COINS     => array(
				'label'  => __( 'Coins', 'collectibles' ),
				'noun'   => __( 'Coin', 'collectibles' ),
				'icon'   => '🪙',
				'fields' => array(
					array(
						'key'         => 'denomination',
						'label'       => __( 'Denomination', 'collectibles' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. 5 Schilling', 'collectibles' ),
					),
					array(
						'key'         => 'mint',
						'label'       => __( 'Mint', 'collectibles' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. Vienna', 'collectibles' ),
					),
					array(
						'key'   => 'mint_mark',
						'label' => __( 'Mint mark', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'         => 'composition',
						'label'       => __( 'Composition', 'collectibles' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. .900 silver', 'collectibles' ),
					),
					array(
						'key'   => 'weight_g',
						'label' => __( 'Weight', 'collectibles' ),
						'type'  => 'number',
						'step'  => '0.01',
						'unit'  => __( 'g', 'collectibles' ),
					),
					array(
						'key'   => 'diameter_mm',
						'label' => __( 'Diameter', 'collectibles' ),
						'type'  => 'number',
						'step'  => '0.1',
						'unit'  => __( 'mm', 'collectibles' ),
					),
				),
				'grades' => array(
					'ms' => __( 'Mint State (MS)', 'collectibles' ),
					'au' => __( 'About Uncirculated (AU)', 'collectibles' ),
					'xf' => __( 'Extremely Fine (XF)', 'collectibles' ),
					'vf' => __( 'Very Fine (VF)', 'collectibles' ),
					'f'  => __( 'Fine (F)', 'collectibles' ),
					'vg' => __( 'Very Good (VG)', 'collectibles' ),
					'g'  => __( 'Good (G)', 'collectibles' ),
					'ag' => __( 'About Good (AG)', 'collectibles' ),
					'p'  => __( 'Poor (P)', 'collectibles' ),
				),
			),
			self::KIND_STAMPS    => array(
				'label'  => __( 'Stamps', 'collectibles' ),
				'noun'   => __( 'Stamp', 'collectibles' ),
				'icon'   => '✉️',
				'fields' => array(
					array(
						'key'         => 'denomination',
						'label'       => __( 'Face value', 'collectibles' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. 50 Groschen', 'collectibles' ),
					),
					array(
						'key'   => 'color',
						'label' => __( 'Colour', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'         => 'perforation',
						'label'       => __( 'Perforation', 'collectibles' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. 12½ × 13', 'collectibles' ),
					),
					array(
						'key'   => 'watermark',
						'label' => __( 'Watermark', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'     => 'gum',
						'label'   => __( 'Gum', 'collectibles' ),
						'type'    => 'select',
						'options' => array(
							'never_hinged' => __( 'Never hinged', 'collectibles' ),
							'hinged'       => __( 'Hinged', 'collectibles' ),
							'no_gum'       => __( 'No gum', 'collectibles' ),
							'regummed'     => __( 'Regummed', 'collectibles' ),
						),
					),
					array(
						'key'         => 'cancellation',
						'label'       => __( 'Cancellation', 'collectibles' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. first day, Vienna 1934', 'collectibles' ),
					),
				),
				'grades' => array(
					'superb'    => __( 'Superb', 'collectibles' ),
					'xf'        => __( 'Extremely Fine', 'collectibles' ),
					'vf'        => __( 'Very Fine', 'collectibles' ),
					'f'         => __( 'Fine', 'collectibles' ),
					'average'   => __( 'Average', 'collectibles' ),
					'defective' => __( 'Defective', 'collectibles' ),
				),
			),
			self::KIND_BANKNOTES => array(
				'label'  => __( 'Banknotes', 'collectibles' ),
				'noun'   => __( 'Banknote', 'collectibles' ),
				'icon'   => '💵',
				'fields' => array(
					array(
						'key'   => 'denomination',
						'label' => __( 'Denomination', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'serial_number',
						'label' => __( 'Serial number', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'printer',
						'label' => __( 'Printer', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'signature',
						'label' => __( 'Signatures', 'collectibles' ),
						'type'  => 'text',
					),
				),
				'grades' => array(
					'unc' => __( 'Uncirculated (UNC)', 'collectibles' ),
					'au'  => __( 'About Uncirculated (AU)', 'collectibles' ),
					'xf'  => __( 'Extremely Fine (XF)', 'collectibles' ),
					'vf'  => __( 'Very Fine (VF)', 'collectibles' ),
					'f'   => __( 'Fine (F)', 'collectibles' ),
					'vg'  => __( 'Very Good (VG)', 'collectibles' ),
					'g'   => __( 'Good (G)', 'collectibles' ),
					'p'   => __( 'Poor (P)', 'collectibles' ),
				),
			),
			self::KIND_CARDS     => array(
				'label'  => __( 'Trading cards', 'collectibles' ),
				'noun'   => __( 'Card', 'collectibles' ),
				'icon'   => '🃏',
				'fields' => array(
					array(
						'key'   => 'set_name',
						'label' => __( 'Set', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'card_number',
						'label' => __( 'Card number', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'         => 'subject',
						'label'       => __( 'Subject', 'collectibles' ),
						'type'        => 'text',
						'placeholder' => __( 'Player, character, …', 'collectibles' ),
					),
					array(
						'key'   => 'manufacturer',
						'label' => __( 'Manufacturer', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'         => 'variant',
						'label'       => __( 'Variant', 'collectibles' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. holo, refractor', 'collectibles' ),
					),
					array(
						'key'         => 'grader',
						'label'       => __( 'Graded by', 'collectibles' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. PSA, BGS', 'collectibles' ),
					),
				),
				'grades' => array(
					'gem_mint'  => __( 'Gem Mint (10)', 'collectibles' ),
					'mint'      => __( 'Mint (9)', 'collectibles' ),
					'nm_mt'     => __( 'Near Mint–Mint (8)', 'collectibles' ),
					'nm'        => __( 'Near Mint (7)', 'collectibles' ),
					'ex_mt'     => __( 'Excellent–Mint (6)', 'collectibles' ),
					'ex'        => __( 'Excellent (5)', 'collectibles' ),
					'vg_ex'     => __( 'Very Good–Excellent (4)', 'collectibles' ),
					'vg'        => __( 'Very Good (3)', 'collectibles' ),
					'good'      => __( 'Good (2)', 'collectibles' ),
					'poor_fair' => __( 'Poor–Fair (1)', 'collectibles' ),
				),
			),
			self::KIND_RECORDS   => array(
				'label'  => __( 'Records', 'collectibles' ),
				'noun'   => __( 'Record', 'collectibles' ),
				'icon'   => '💿',
				'fields' => array(
					array(
						'key'   => 'artist',
						'label' => __( 'Artist', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'label_name',
						'label' => __( 'Label', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'     => 'format',
						'label'   => __( 'Format', 'collectibles' ),
						'type'    => 'select',
						'options' => array(
							'lp'      => __( '12" LP', 'collectibles' ),
							'ep'      => __( '10" EP', 'collectibles' ),
							'single'  => __( '7" single', 'collectibles' ),
							'shellac' => __( '78 rpm shellac', 'collectibles' ),
							'boxset'  => __( 'Box set', 'collectibles' ),
						),
					),
					array(
						'key'     => 'speed',
						'label'   => __( 'Speed', 'collectibles' ),
						'type'    => 'select',
						'options' => array(
							'33' => __( '33⅓ rpm', 'collectibles' ),
							'45' => __( '45 rpm', 'collectibles' ),
							'78' => __( '78 rpm', 'collectibles' ),
						),
					),
					array(
						'key'         => 'pressing',
						'label'       => __( 'Pressing', 'collectibles' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. first pressing, reissue', 'collectibles' ),
					),
					array(
						'key'     => 'sleeve_condition',
						'label'   => __( 'Sleeve condition', 'collectibles' ),
						'type'    => 'select',
						'options' => array(
							'm'       => __( 'Mint (M)', 'collectibles' ),
							'nm'      => __( 'Near Mint (NM)', 'collectibles' ),
							'vg_plus' => __( 'Very Good Plus (VG+)', 'collectibles' ),
							'vg'      => __( 'Very Good (VG)', 'collectibles' ),
							'g'       => __( 'Good (G)', 'collectibles' ),
							'f'       => __( 'Fair (F)', 'collectibles' ),
							'p'       => __( 'Poor (P)', 'collectibles' ),
						),
					),
				),
				'grades' => array(
					'm'       => __( 'Mint (M)', 'collectibles' ),
					'nm'      => __( 'Near Mint (NM)', 'collectibles' ),
					'vg_plus' => __( 'Very Good Plus (VG+)', 'collectibles' ),
					'vg'      => __( 'Very Good (VG)', 'collectibles' ),
					'g'       => __( 'Good (G)', 'collectibles' ),
					'f'       => __( 'Fair (F)', 'collectibles' ),
					'p'       => __( 'Poor (P)', 'collectibles' ),
				),
			),
			self::KIND_BOOKS     => array(
				'label'  => __( 'Books', 'collectibles' ),
				'noun'   => __( 'Book', 'collectibles' ),
				'icon'   => '📚',
				'fields' => array(
					array(
						'key'   => 'author',
						'label' => __( 'Author', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'publisher',
						'label' => __( 'Publisher', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'         => 'edition',
						'label'       => __( 'Edition', 'collectibles' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. first edition', 'collectibles' ),
					),
					array(
						'key'   => 'isbn',
						'label' => __( 'ISBN', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'     => 'binding',
						'label'   => __( 'Binding', 'collectibles' ),
						'type'    => 'select',
						'options' => array(
							'hardcover' => __( 'Hardcover', 'collectibles' ),
							'paperback' => __( 'Paperback', 'collectibles' ),
							'leather'   => __( 'Leather', 'collectibles' ),
							'spiral'    => __( 'Spiral', 'collectibles' ),
						),
					),
					array(
						'key'     => 'dust_jacket',
						'label'   => __( 'Dust jacket', 'collectibles' ),
						'type'    => 'select',
						'options' => array(
							'present'   => __( 'Present', 'collectibles' ),
							'absent'    => __( 'Absent', 'collectibles' ),
							'facsimile' => __( 'Facsimile', 'collectibles' ),
						),
					),
				),
				'grades' => array(
					'as_new' => __( 'As New', 'collectibles' ),
					'fine'   => __( 'Fine', 'collectibles' ),
					'vg'     => __( 'Very Good', 'collectibles' ),
					'good'   => __( 'Good', 'collectibles' ),
					'fair'   => __( 'Fair', 'collectibles' ),
					'poor'   => __( 'Poor', 'collectibles' ),
				),
			),
			self::KIND_OTHER     => array(
				'label'  => __( 'Anything else', 'collectibles' ),
				'noun'   => __( 'Item', 'collectibles' ),
				'icon'   => '📦',
				'fields' => array(
					array(
						'key'         => 'maker',
						'label'       => __( 'Maker', 'collectibles' ),
						'type'        => 'text',
						'placeholder' => __( 'Manufacturer, artist, …', 'collectibles' ),
					),
					array(
						'key'   => 'material',
						'label' => __( 'Material', 'collectibles' ),
						'type'  => 'text',
					),
					array(
						'key'         => 'dimensions',
						'label'       => __( 'Dimensions', 'collectibles' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. 12 × 8 × 3 cm', 'collectibles' ),
					),
				),
				'grades' => array(
					'mint'      => __( 'Mint', 'collectibles' ),
					'excellent' => __( 'Excellent', 'collectibles' ),
					'good'      => __( 'Good', 'collectibles' ),
					'fair'      => __( 'Fair', 'collectibles' ),
					'poor'      => __( 'Poor', 'collectibles' ),
				),
			),
		);
	}

	/**
	 * Whether the given slug is a known collection kind.
	 *
	 * @param string $kind Kind slug.
	 */
	public static function is_kind( string $kind ): bool {
		return array_key_exists( $kind, self::get_kinds() );
	}

	/**
	 * Normalize an arbitrary value into a known kind slug.
	 *
	 * @param mixed $kind Raw value.
	 */
	public static function sanitize_kind( $kind ): string {
		$kind = sanitize_key( (string) $kind );

		return self::is_kind( $kind ) ? $kind : self::KIND_OTHER;
	}

	/**
	 * The definition of a single kind.
	 *
	 * @param string $kind Kind slug.
	 */
	public static function get_kind( string $kind ): array {
		$kinds = self::get_kinds();
		$kind  = self::sanitize_kind( $kind );

		return $kinds[ $kind ];
	}

	/**
	 * Human-readable label for a kind.
	 *
	 * @param string $kind Kind slug.
	 */
	public static function get_kind_label( string $kind ): string {
		return self::get_kind( $kind )['label'];
	}

	/**
	 * Singular noun used for the items of a kind ("Coin", "Stamp", …).
	 *
	 * @param string $kind Kind slug.
	 */
	public static function get_item_noun( string $kind ): string {
		return self::get_kind( $kind )['noun'];
	}

	/**
	 * Emoji icon for a kind.
	 *
	 * @param string $kind Kind slug.
	 */
	public static function get_kind_icon( string $kind ): string {
		return self::get_kind( $kind )['icon'];
	}

	/**
	 * The kind-specific field definitions for a kind.
	 *
	 * @param string $kind Kind slug.
	 * @return array<int, array>
	 */
	public static function get_fields( string $kind ): array {
		return self::get_kind( $kind )['fields'];
	}

	/**
	 * The condition scale for a kind, as slug => label.
	 *
	 * @param string $kind Kind slug.
	 * @return array<string, string>
	 */
	public static function get_grades( string $kind ): array {
		return self::get_kind( $kind )['grades'];
	}

	/**
	 * Label for a stored condition slug, falling back to the raw value when the
	 * collection kind changed after the item was graded.
	 *
	 * @param string $kind  Kind slug.
	 * @param string $grade Stored grade slug.
	 */
	public static function get_grade_label( string $kind, string $grade ): string {
		if ( '' === $grade ) {
			return '';
		}

		$grades = self::get_grades( $kind );

		return $grades[ $grade ] ?? $grade;
	}

	/**
	 * Every kind-specific field key used by any kind, mapped to its definition.
	 *
	 * Items of all kinds share one post type, so meta registration works on the
	 * union of all fields. Where two kinds use the same key (coins and banknotes
	 * both have a denomination) the select options are merged.
	 *
	 * @return array<string, array>
	 */
	public static function get_all_fields(): array {
		$all = array();

		foreach ( self::get_kinds() as $kind ) {
			foreach ( $kind['fields'] as $field ) {
				$key = $field['key'];

				if ( ! isset( $all[ $key ] ) ) {
					$all[ $key ] = $field;
					continue;
				}

				if ( isset( $field['options'] ) ) {
					$all[ $key ]['options'] = array_merge(
						$all[ $key ]['options'] ?? array(),
						$field['options']
					);
				}
			}
		}

		return $all;
	}

	/**
	 * The item statuses, as slug => label.
	 *
	 * @return array<string, string>
	 */
	public static function get_statuses(): array {
		return array(
			self::STATUS_OWNED     => __( 'In the collection', 'collectibles' ),
			self::STATUS_WANTED    => __( 'On the wishlist', 'collectibles' ),
			self::STATUS_ORDERED   => __( 'Ordered', 'collectibles' ),
			self::STATUS_DUPLICATE => __( 'Duplicate', 'collectibles' ),
			self::STATUS_FOR_SALE  => __( 'For sale', 'collectibles' ),
			self::STATUS_SOLD      => __( 'Sold', 'collectibles' ),
		);
	}

	/**
	 * Label for a status slug.
	 *
	 * @param string $status Status slug.
	 */
	public static function get_status_label( string $status ): string {
		$statuses = self::get_statuses();

		return $statuses[ $status ] ?? $statuses[ self::STATUS_OWNED ];
	}

	/**
	 * Statuses that count as physically held, and therefore towards the totals.
	 *
	 * @return string[]
	 */
	public static function get_owned_statuses(): array {
		return array(
			self::STATUS_OWNED,
			self::STATUS_DUPLICATE,
			self::STATUS_FOR_SALE,
		);
	}
}
