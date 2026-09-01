<?php
/**
 * Where the pieces come from.
 *
 * The territory table below is generated from ICU's territory containment data
 * (`ResourceBundle::create( 'supplementalData', 'ICUDATA' )`), which is where
 * the continent of every ISO 3166-1 country comes from. The English names are
 * a fallback for installs without the intl extension; with intl, names are
 * localized for the reader.
 *
 * @package Collectibles
 */

namespace Collectibles;

/**
 * Territories, continents, and the historic issuers that no longer have either.
 */
class Geography {
	public const CONTINENTS = array( '150', '019', '002', '142', '009' );

	/**
	 * Languages a territory name is looked for in, beyond the reader's own.
	 */
	public const SEARCH_LOCALES = array( 'en', 'de', 'fr', 'es', 'it' );

	/**
	 * Every ISO 3166-1 territory, as code => array( continent, English name ).
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function get_territories(): array {
		return array(
			'AD' => array( '150', 'Andorra' ),
			'AE' => array( '142', 'United Arab Emirates' ),
			'AF' => array( '142', 'Afghanistan' ),
			'AG' => array( '019', 'Antigua & Barbuda' ),
			'AI' => array( '019', 'Anguilla' ),
			'AL' => array( '150', 'Albania' ),
			'AM' => array( '142', 'Armenia' ),
			'AO' => array( '002', 'Angola' ),
			'AR' => array( '019', 'Argentina' ),
			'AS' => array( '009', 'American Samoa' ),
			'AT' => array( '150', 'Austria' ),
			'AU' => array( '009', 'Australia' ),
			'AW' => array( '019', 'Aruba' ),
			'AX' => array( '150', 'Åland Islands' ),
			'AZ' => array( '142', 'Azerbaijan' ),
			'BA' => array( '150', 'Bosnia & Herzegovina' ),
			'BB' => array( '019', 'Barbados' ),
			'BD' => array( '142', 'Bangladesh' ),
			'BE' => array( '150', 'Belgium' ),
			'BF' => array( '002', 'Burkina Faso' ),
			'BG' => array( '150', 'Bulgaria' ),
			'BH' => array( '142', 'Bahrain' ),
			'BI' => array( '002', 'Burundi' ),
			'BJ' => array( '002', 'Benin' ),
			'BL' => array( '019', 'St. Barthélemy' ),
			'BM' => array( '019', 'Bermuda' ),
			'BN' => array( '142', 'Brunei' ),
			'BO' => array( '019', 'Bolivia' ),
			'BQ' => array( '019', 'Caribbean Netherlands' ),
			'BR' => array( '019', 'Brazil' ),
			'BS' => array( '019', 'Bahamas' ),
			'BT' => array( '142', 'Bhutan' ),
			'BV' => array( '019', 'Bouvet Island' ),
			'BW' => array( '002', 'Botswana' ),
			'BY' => array( '150', 'Belarus' ),
			'BZ' => array( '019', 'Belize' ),
			'CA' => array( '019', 'Canada' ),
			'CC' => array( '009', 'Cocos (Keeling) Islands' ),
			'CD' => array( '002', 'Congo - Kinshasa' ),
			'CF' => array( '002', 'Central African Republic' ),
			'CG' => array( '002', 'Congo - Brazzaville' ),
			'CH' => array( '150', 'Switzerland' ),
			'CI' => array( '002', 'Côte d’Ivoire' ),
			'CK' => array( '009', 'Cook Islands' ),
			'CL' => array( '019', 'Chile' ),
			'CM' => array( '002', 'Cameroon' ),
			'CN' => array( '142', 'China' ),
			'CO' => array( '019', 'Colombia' ),
			'CR' => array( '019', 'Costa Rica' ),
			'CU' => array( '019', 'Cuba' ),
			'CV' => array( '002', 'Cape Verde' ),
			'CW' => array( '019', 'Curaçao' ),
			'CX' => array( '009', 'Christmas Island' ),
			'CY' => array( '142', 'Cyprus' ),
			'CZ' => array( '150', 'Czechia' ),
			'DE' => array( '150', 'Germany' ),
			'DJ' => array( '002', 'Djibouti' ),
			'DK' => array( '150', 'Denmark' ),
			'DM' => array( '019', 'Dominica' ),
			'DO' => array( '019', 'Dominican Republic' ),
			'DZ' => array( '002', 'Algeria' ),
			'EA' => array( '002', 'Ceuta & Melilla' ),
			'EC' => array( '019', 'Ecuador' ),
			'EE' => array( '150', 'Estonia' ),
			'EG' => array( '002', 'Egypt' ),
			'EH' => array( '002', 'Western Sahara' ),
			'ER' => array( '002', 'Eritrea' ),
			'ES' => array( '150', 'Spain' ),
			'ET' => array( '002', 'Ethiopia' ),
			'FI' => array( '150', 'Finland' ),
			'FJ' => array( '009', 'Fiji' ),
			'FK' => array( '019', 'Falkland Islands' ),
			'FM' => array( '009', 'Micronesia' ),
			'FO' => array( '150', 'Faroe Islands' ),
			'FR' => array( '150', 'France' ),
			'GA' => array( '002', 'Gabon' ),
			'GB' => array( '150', 'United Kingdom' ),
			'GD' => array( '019', 'Grenada' ),
			'GE' => array( '142', 'Georgia' ),
			'GF' => array( '019', 'French Guiana' ),
			'GG' => array( '150', 'Guernsey' ),
			'GH' => array( '002', 'Ghana' ),
			'GI' => array( '150', 'Gibraltar' ),
			'GL' => array( '019', 'Greenland' ),
			'GM' => array( '002', 'Gambia' ),
			'GN' => array( '002', 'Guinea' ),
			'GP' => array( '019', 'Guadeloupe' ),
			'GQ' => array( '002', 'Equatorial Guinea' ),
			'GR' => array( '150', 'Greece' ),
			'GS' => array( '019', 'South Georgia & South Sandwich Islands' ),
			'GT' => array( '019', 'Guatemala' ),
			'GU' => array( '009', 'Guam' ),
			'GW' => array( '002', 'Guinea-Bissau' ),
			'GY' => array( '019', 'Guyana' ),
			'HK' => array( '142', 'Hong Kong SAR China' ),
			'HM' => array( '009', 'Heard & McDonald Islands' ),
			'HN' => array( '019', 'Honduras' ),
			'HR' => array( '150', 'Croatia' ),
			'HT' => array( '019', 'Haiti' ),
			'HU' => array( '150', 'Hungary' ),
			'IC' => array( '002', 'Canary Islands' ),
			'ID' => array( '142', 'Indonesia' ),
			'IE' => array( '150', 'Ireland' ),
			'IL' => array( '142', 'Israel' ),
			'IM' => array( '150', 'Isle of Man' ),
			'IN' => array( '142', 'India' ),
			'IO' => array( '002', 'British Indian Ocean Territory' ),
			'IQ' => array( '142', 'Iraq' ),
			'IR' => array( '142', 'Iran' ),
			'IS' => array( '150', 'Iceland' ),
			'IT' => array( '150', 'Italy' ),
			'JE' => array( '150', 'Jersey' ),
			'JM' => array( '019', 'Jamaica' ),
			'JO' => array( '142', 'Jordan' ),
			'JP' => array( '142', 'Japan' ),
			'KE' => array( '002', 'Kenya' ),
			'KG' => array( '142', 'Kyrgyzstan' ),
			'KH' => array( '142', 'Cambodia' ),
			'KI' => array( '009', 'Kiribati' ),
			'KM' => array( '002', 'Comoros' ),
			'KN' => array( '019', 'St. Kitts & Nevis' ),
			'KP' => array( '142', 'North Korea' ),
			'KR' => array( '142', 'South Korea' ),
			'KW' => array( '142', 'Kuwait' ),
			'KY' => array( '019', 'Cayman Islands' ),
			'KZ' => array( '142', 'Kazakhstan' ),
			'LA' => array( '142', 'Laos' ),
			'LB' => array( '142', 'Lebanon' ),
			'LC' => array( '019', 'St. Lucia' ),
			'LI' => array( '150', 'Liechtenstein' ),
			'LK' => array( '142', 'Sri Lanka' ),
			'LR' => array( '002', 'Liberia' ),
			'LS' => array( '002', 'Lesotho' ),
			'LT' => array( '150', 'Lithuania' ),
			'LU' => array( '150', 'Luxembourg' ),
			'LV' => array( '150', 'Latvia' ),
			'LY' => array( '002', 'Libya' ),
			'MA' => array( '002', 'Morocco' ),
			'MC' => array( '150', 'Monaco' ),
			'MD' => array( '150', 'Moldova' ),
			'ME' => array( '150', 'Montenegro' ),
			'MF' => array( '019', 'St. Martin' ),
			'MG' => array( '002', 'Madagascar' ),
			'MH' => array( '009', 'Marshall Islands' ),
			'MK' => array( '150', 'North Macedonia' ),
			'ML' => array( '002', 'Mali' ),
			'MM' => array( '142', 'Myanmar (Burma)' ),
			'MN' => array( '142', 'Mongolia' ),
			'MO' => array( '142', 'Macao SAR China' ),
			'MP' => array( '009', 'Northern Mariana Islands' ),
			'MQ' => array( '019', 'Martinique' ),
			'MR' => array( '002', 'Mauritania' ),
			'MS' => array( '019', 'Montserrat' ),
			'MT' => array( '150', 'Malta' ),
			'MU' => array( '002', 'Mauritius' ),
			'MV' => array( '142', 'Maldives' ),
			'MW' => array( '002', 'Malawi' ),
			'MX' => array( '019', 'Mexico' ),
			'MY' => array( '142', 'Malaysia' ),
			'MZ' => array( '002', 'Mozambique' ),
			'NA' => array( '002', 'Namibia' ),
			'NC' => array( '009', 'New Caledonia' ),
			'NE' => array( '002', 'Niger' ),
			'NF' => array( '009', 'Norfolk Island' ),
			'NG' => array( '002', 'Nigeria' ),
			'NI' => array( '019', 'Nicaragua' ),
			'NL' => array( '150', 'Netherlands' ),
			'NO' => array( '150', 'Norway' ),
			'NP' => array( '142', 'Nepal' ),
			'NR' => array( '009', 'Nauru' ),
			'NU' => array( '009', 'Niue' ),
			'NZ' => array( '009', 'New Zealand' ),
			'OM' => array( '142', 'Oman' ),
			'PA' => array( '019', 'Panama' ),
			'PE' => array( '019', 'Peru' ),
			'PF' => array( '009', 'French Polynesia' ),
			'PG' => array( '009', 'Papua New Guinea' ),
			'PH' => array( '142', 'Philippines' ),
			'PK' => array( '142', 'Pakistan' ),
			'PL' => array( '150', 'Poland' ),
			'PM' => array( '019', 'St. Pierre & Miquelon' ),
			'PN' => array( '009', 'Pitcairn Islands' ),
			'PR' => array( '019', 'Puerto Rico' ),
			'PS' => array( '142', 'Palestinian Territories' ),
			'PT' => array( '150', 'Portugal' ),
			'PW' => array( '009', 'Palau' ),
			'PY' => array( '019', 'Paraguay' ),
			'QA' => array( '142', 'Qatar' ),
			'QO' => array( '009', 'Outlying Oceania' ),
			'RE' => array( '002', 'Réunion' ),
			'RO' => array( '150', 'Romania' ),
			'RS' => array( '150', 'Serbia' ),
			'RU' => array( '150', 'Russia' ),
			'RW' => array( '002', 'Rwanda' ),
			'SA' => array( '142', 'Saudi Arabia' ),
			'SB' => array( '009', 'Solomon Islands' ),
			'SC' => array( '002', 'Seychelles' ),
			'SD' => array( '002', 'Sudan' ),
			'SE' => array( '150', 'Sweden' ),
			'SG' => array( '142', 'Singapore' ),
			'SH' => array( '002', 'St. Helena' ),
			'SI' => array( '150', 'Slovenia' ),
			'SJ' => array( '150', 'Svalbard & Jan Mayen' ),
			'SK' => array( '150', 'Slovakia' ),
			'SL' => array( '002', 'Sierra Leone' ),
			'SM' => array( '150', 'San Marino' ),
			'SN' => array( '002', 'Senegal' ),
			'SO' => array( '002', 'Somalia' ),
			'SR' => array( '019', 'Suriname' ),
			'SS' => array( '002', 'South Sudan' ),
			'ST' => array( '002', 'São Tomé & Príncipe' ),
			'SV' => array( '019', 'El Salvador' ),
			'SX' => array( '019', 'Sint Maarten' ),
			'SY' => array( '142', 'Syria' ),
			'SZ' => array( '002', 'Eswatini' ),
			'TC' => array( '019', 'Turks & Caicos Islands' ),
			'TD' => array( '002', 'Chad' ),
			'TF' => array( '002', 'French Southern Territories' ),
			'TG' => array( '002', 'Togo' ),
			'TH' => array( '142', 'Thailand' ),
			'TJ' => array( '142', 'Tajikistan' ),
			'TK' => array( '009', 'Tokelau' ),
			'TL' => array( '142', 'Timor-Leste' ),
			'TM' => array( '142', 'Turkmenistan' ),
			'TN' => array( '002', 'Tunisia' ),
			'TO' => array( '009', 'Tonga' ),
			'TR' => array( '142', 'Turkey' ),
			'TT' => array( '019', 'Trinidad & Tobago' ),
			'TV' => array( '009', 'Tuvalu' ),
			'TW' => array( '142', 'Taiwan' ),
			'TZ' => array( '002', 'Tanzania' ),
			'UA' => array( '150', 'Ukraine' ),
			'UG' => array( '002', 'Uganda' ),
			'UM' => array( '009', 'U.S. Outlying Islands' ),
			'US' => array( '019', 'United States' ),
			'UY' => array( '019', 'Uruguay' ),
			'UZ' => array( '142', 'Uzbekistan' ),
			'VA' => array( '150', 'Vatican City' ),
			'VC' => array( '019', 'St. Vincent & Grenadines' ),
			'VE' => array( '019', 'Venezuela' ),
			'VG' => array( '019', 'British Virgin Islands' ),
			'VI' => array( '019', 'U.S. Virgin Islands' ),
			'VN' => array( '142', 'Vietnam' ),
			'VU' => array( '009', 'Vanuatu' ),
			'WF' => array( '009', 'Wallis & Futuna' ),
			'WS' => array( '009', 'Samoa' ),
			'XK' => array( '150', 'Kosovo' ),
			'YE' => array( '142', 'Yemen' ),
			'YT' => array( '002', 'Mayotte' ),
			'ZA' => array( '002', 'South Africa' ),
			'ZM' => array( '002', 'Zambia' ),
			'ZW' => array( '002', 'Zimbabwe' ),
		);
	}

	/**
	 * Issuers that no longer exist, as code => array( continent, name, territory ).
	 *
	 * A collection is mostly made of states that are gone. They keep a row of
	 * their own under the name they are catalogued by, and carry the present-day
	 * territory they occupied so they can still be placed on a map. Codes are
	 * ISO 3166-3 where there is one, and prefixed with "x" where there is not.
	 *
	 * This list is meant to grow: add the issuer, the continent, its name and
	 * the modern code its territory falls in.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function get_historic_issuers(): array {
		return array(
			'xathu' => array( '150', __( 'Austria-Hungary', 'collectibles' ), 'AT' ),
			'xdeim' => array( '150', __( 'German Empire', 'collectibles' ), 'DE' ),
			'xprus' => array( '150', __( 'Prussia', 'collectibles' ), 'DE' ),
			'ddde'  => array( '150', __( 'East Germany', 'collectibles' ), 'DE' ),
			'cshh'  => array( '150', __( 'Czechoslovakia', 'collectibles' ), 'CZ' ),
			'yucs'  => array( '150', __( 'Yugoslavia', 'collectibles' ), 'RS' ),
			'csxx'  => array( '150', __( 'Serbia and Montenegro', 'collectibles' ), 'RS' ),
			'suhh'  => array( '150', __( 'Soviet Union', 'collectibles' ), 'RU' ),
			'xotto' => array( '142', __( 'Ottoman Empire', 'collectibles' ), 'TR' ),
			'xbrin' => array( '142', __( 'British India', 'collectibles' ), 'IN' ),
			'bumm'  => array( '142', __( 'Burma', 'collectibles' ), 'MM' ),
			'vdvn'  => array( '142', __( 'North Vietnam', 'collectibles' ), 'VN' ),
			'ydye'  => array( '142', __( 'South Yemen', 'collectibles' ), 'YE' ),
			'zrcd'  => array( '002', __( 'Zaire', 'collectibles' ), 'CD' ),
			'rhzw'  => array( '002', __( 'Rhodesia', 'collectibles' ), 'ZW' ),
			'dybj'  => array( '002', __( 'Dahomey', 'collectibles' ), 'BJ' ),
			'anhh'  => array( '019', __( 'Netherlands Antilles', 'collectibles' ), 'CW' ),
		);
	}

	/**
	 * Every option for the origin field, as code => name, sorted by name with
	 * the historic issuers last.
	 *
	 * @return array<string, string>
	 */
	public static function get_options(): array {
		$options = array();

		foreach ( array_keys( self::get_territories() ) as $code ) {
			$options[ self::to_stored_code( $code ) ] = self::get_name( $code );
		}

		asort( $options );

		$historic = array();

		foreach ( self::get_historic_issuers() as $code => $entry ) {
			$historic[ $code ] = $entry[1];
		}

		asort( $historic );

		return $options + $historic;
	}

	/**
	 * The code as it is stored: sanitize_key() lowercases select values, so the
	 * stored form of "AT" is "at".
	 *
	 * @param string $code Territory code in any case.
	 */
	public static function to_stored_code( string $code ): string {
		return strtolower( trim( $code ) );
	}

	/**
	 * The ISO form of a stored code, or '' when it is not one.
	 *
	 * @param string $code Stored code.
	 */
	public static function to_iso_code( string $code ): string {
		$code = strtoupper( trim( $code ) );

		return isset( self::get_territories()[ $code ] ) ? $code : '';
	}

	/**
	 * Whether a stored code is one this plugin knows.
	 *
	 * @param string $code Stored code.
	 */
	public static function is_known( string $code ): bool {
		$code = self::to_stored_code( $code );

		return '' !== self::to_iso_code( $code ) || isset( self::get_historic_issuers()[ $code ] );
	}

	/**
	 * The name of a territory or historic issuer, localized when intl is around.
	 *
	 * @param string $code Territory code, stored or ISO.
	 */
	public static function get_name( string $code ): string {
		$stored   = self::to_stored_code( $code );
		$historic = self::get_historic_issuers();

		if ( isset( $historic[ $stored ] ) ) {
			return $historic[ $stored ][1];
		}

		$iso = self::to_iso_code( $code );

		if ( '' === $iso ) {
			return $code;
		}

		if ( class_exists( '\\Locale' ) ) {
			$name = \Locale::getDisplayRegion( '-' . $iso, self::get_display_locale() );

			if ( '' !== $name && $name !== $iso ) {
				return $name;
			}
		}

		return self::get_territories()[ $iso ][1];
	}

	/**
	 * The present-day territory a code sits in: itself for a country, the
	 * successor territory for a historic issuer.
	 *
	 * @param string $code Stored code.
	 */
	public static function get_map_territory( string $code ): string {
		$stored   = self::to_stored_code( $code );
		$historic = self::get_historic_issuers();

		if ( isset( $historic[ $stored ] ) ) {
			return $historic[ $stored ][2];
		}

		return self::to_iso_code( $code );
	}

	/**
	 * The continent a code belongs to.
	 *
	 * @param string $code Stored code.
	 */
	public static function get_continent( string $code ): string {
		$stored   = self::to_stored_code( $code );
		$historic = self::get_historic_issuers();

		if ( isset( $historic[ $stored ] ) ) {
			return $historic[ $stored ][0];
		}

		$iso = self::to_iso_code( $code );

		return '' === $iso ? '' : self::get_territories()[ $iso ][0];
	}

	/**
	 * The continent names, in reading order, as code => name.
	 *
	 * @return array<string, string>
	 */
	public static function get_continent_names(): array {
		$fallbacks = array(
			'150' => __( 'Europe', 'collectibles' ),
			'019' => __( 'Americas', 'collectibles' ),
			'002' => __( 'Africa', 'collectibles' ),
			'142' => __( 'Asia', 'collectibles' ),
			'009' => __( 'Oceania', 'collectibles' ),
		);

		$names = array();

		foreach ( self::CONTINENTS as $code ) {
			$name = '';

			if ( class_exists( '\\Locale' ) ) {
				$name = \Locale::getDisplayRegion( '-' . $code, self::get_display_locale() );
			}

			$names[ $code ] = ( '' !== $name && $name !== $code ) ? $name : $fallbacks[ $code ];
		}

		return $names;
	}

	/**
	 * The flag of a territory, built from the two regional indicator symbols
	 * its letters stand for. Historic issuers show their successor's flag as
	 * the nearest thing to a picture of where they were.
	 *
	 * @param string $code Stored code.
	 */
	public static function get_flag( string $code ): string {
		$iso = self::get_map_territory( $code );

		if ( 2 !== strlen( $iso ) ) {
			return '';
		}

		$flag = '';

		foreach ( str_split( strtoupper( $iso ) ) as $letter ) {
			$flag .= mb_chr( 0x1F1E6 + ord( $letter ) - ord( 'A' ), 'UTF-8' );
		}

		return $flag;
	}

	/**
	 * Find a territory by the name a catalogue gives it. This is how a Numista
	 * issuer ("Estland") becomes a code without a lookup table of its own.
	 *
	 * The name arrives in whichever language the catalogue was asked in, which
	 * is not necessarily the reader's, so both are tried, and English last.
	 *
	 * @param string $name   Territory name.
	 * @param string $locale Language the name is in, when it is known.
	 */
	public static function find_by_name( string $name, string $locale = '' ): string {
		$name = self::normalize_name( $name );

		if ( '' === $name ) {
			return '';
		}

		// The reader's language first, then the languages a catalogue is likely
		// to have answered in. A record fetched in German may well be read in
		// an English context later.
		$locales = array_unique(
			array_filter(
				array_merge(
					array( $locale, self::get_display_locale() ),
					self::SEARCH_LOCALES
				)
			)
		);

		foreach ( $locales as $candidate ) {
			$index = self::get_name_index( $candidate );

			if ( isset( $index[ $name ] ) ) {
				return $index[ $name ];
			}
		}

		foreach ( self::get_historic_issuers() as $code => $entry ) {
			if ( self::normalize_name( $entry[1] ) === $name ) {
				return $code;
			}
		}

		return '';
	}

	/**
	 * Every territory name in one language, folded for comparison and mapped
	 * back to its stored code.
	 *
	 * Naming 252 territories is a few hundred ICU calls, and a lookup asks for
	 * several languages, so the answer is kept for the rest of the request.
	 *
	 * @param string $locale Locale to name territories in.
	 * @return array<string, string>
	 */
	private static function get_name_index( string $locale ): array {
		static $indexes = array();

		if ( isset( $indexes[ $locale ] ) ) {
			return $indexes[ $locale ];
		}

		$index = array();

		foreach ( array_keys( self::get_territories() ) as $iso ) {
			$index[ self::normalize_name( self::get_name_in( $iso, $locale ) ) ] = self::to_stored_code( $iso );
		}

		$indexes[ $locale ] = $index;

		return $index;
	}

	/**
	 * The name of a territory in one specific language.
	 *
	 * @param string $iso    ISO 3166-1 code.
	 * @param string $locale Locale to name it in.
	 */
	private static function get_name_in( string $iso, string $locale ): string {
		if ( class_exists( '\\Locale' ) ) {
			$name = \Locale::getDisplayRegion( '-' . $iso, $locale );

			if ( '' !== $name && $name !== $iso ) {
				return $name;
			}
		}

		return self::get_territories()[ $iso ][1] ?? $iso;
	}

	/**
	 * Fold a name to something comparable across languages and punctuation.
	 *
	 * @param string $name Raw name.
	 */
	private static function normalize_name( string $name ): string {
		$name = trim( (string) $name );

		if ( '' === $name ) {
			return '';
		}

		if ( function_exists( 'mb_strtolower' ) ) {
			$name = mb_strtolower( $name, 'UTF-8' );
		} else {
			$name = strtolower( $name );
		}

		// Fold the accents too: people type "Osterreich" for "Österreich", and
		// a catalogue may spell a name either way.
		$name = self::fold_accents( $name );

		return preg_replace( '/[^\p{L}\p{N}]+/u', '', $name );
	}

	/**
	 * Strip diacritics, leaving the base letters.
	 *
	 * @param string $name Lowercased name.
	 */
	private static function fold_accents( string $name ): string {
		// Built once: a lookup folds hundreds of names, and constructing a
		// transliterator per name is what makes that expensive.
		static $transliterator = false;

		if ( false === $transliterator ) {
			$transliterator = class_exists( '\\Transliterator' )
				? \Transliterator::create( 'NFD; [:Nonspacing Mark:] Remove; NFC' )
				: null;
		}

		if ( $transliterator ) {
			$folded = $transliterator->transliterate( $name );

			if ( is_string( $folded ) ) {
				return $folded;
			}
		}

		if ( function_exists( 'iconv' ) ) {
			$folded = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $name ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Falls back to the unfolded name.

			if ( is_string( $folded ) && '' !== $folded ) {
				return $folded;
			}
		}

		return $name;
	}

	/**
	 * The locale to name territories in: the reader's, not the site's.
	 */
	private static function get_display_locale(): string {
		return function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
	}

	/**
	 * The world map, as an inline SVG with one path per ISO code.
	 *
	 * Generated from Natural Earth's public domain 110m country boundaries
	 * (via the ISC-licensed world-atlas package) projected with d3-geo.
	 */
	public static function get_map_svg(): string {
		$path = dirname( __DIR__ ) . '/assets/world.svg';

		if ( ! file_exists( $path ) ) {
			return '';
		}

		return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a file that ships with the plugin.
	}
}
