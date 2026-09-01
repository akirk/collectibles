<?php
/**
 * Numista catalogue lookups.
 *
 * Numista serves its catalogue pages behind a bot challenge, so the plugin
 * talks to the documented v3 API at api.numista.com instead of reading the
 * public HTML. That needs a personal API key, which every user brings for
 * their own account.
 *
 * The API is metered — a personal key is good for a couple of thousand calls a
 * month — so every answer is cached for a year and every call is counted
 * against a monthly budget that the app refuses to exceed.
 *
 * @package Collectibles
 */

namespace Collectibles;

/**
 * A thin client for the Numista v3 API, plus the mapping from one catalogue
 * entry onto this plugin's item fields.
 */
class Numista {
	public const API_BASE = 'https://api.numista.com/v3';

	public const KEY_META         = 'coll_numista_api_key';
	public const CLIENT_ID_META   = 'coll_numista_client_id';
	public const CLIENT_NAME_META = 'coll_numista_client_name';
	public const USAGE_META       = 'coll_numista_usage';

	/**
	 * Calls a personal key is allowed per calendar month. Numista grants 2000;
	 * the app stops short of the ceiling rather than at it.
	 */
	public const MONTHLY_BUDGET = 2000;

	/**
	 * The interface languages the API is asked for. The specification documents
	 * en, es and fr; de is served too. Anything else falls back to the API's own
	 * default rather than risking a rejected request.
	 */
	public const LANGUAGES = array( 'en', 'es', 'fr', 'de' );

	/**
	 * The kinds a Numista lookup can fill in. Numista catalogues coins,
	 * banknotes and exonumia; the other kinds have nothing to gain from it.
	 *
	 * @return string[]
	 */
	public static function get_supported_kinds(): array {
		return array( Schema::KIND_COINS, Schema::KIND_BANKNOTES );
	}

	/**
	 * Whether items of this kind can be looked up on Numista.
	 *
	 * @param string $kind Collection kind slug.
	 */
	public static function supports_kind( string $kind ): bool {
		return in_array( $kind, self::get_supported_kinds(), true );
	}

	/**
	 * The credentials Numista issues for an API client: the key that
	 * authenticates a request, and the client id and name the account is
	 * registered under.
	 *
	 * @return array{key: string, client_id: string, client_name: string}
	 */
	public static function get_credentials(): array {
		$user_id = get_current_user_id();

		$credentials = array(
			'key'         => (string) get_user_meta( $user_id, self::KEY_META, true ),
			'client_id'   => (string) get_user_meta( $user_id, self::CLIENT_ID_META, true ),
			'client_name' => (string) get_user_meta( $user_id, self::CLIENT_NAME_META, true ),
		);

		if ( self::is_api_key_fixed() ) {
			$credentials['key'] = (string) COLLECTIBLES_NUMISTA_API_KEY;
		}

		/**
		 * Filters the Numista credentials used for catalogue lookups.
		 *
		 * @param array $credentials Key, client id and client name.
		 */
		$credentials = (array) apply_filters( 'collectibles_numista_credentials', $credentials );

		return array(
			'key'         => trim( (string) ( $credentials['key'] ?? '' ) ),
			'client_id'   => trim( (string) ( $credentials['client_id'] ?? '' ) ),
			'client_name' => trim( (string) ( $credentials['client_name'] ?? '' ) ),
		);
	}

	/**
	 * The API key to use for the current user.
	 */
	public static function get_api_key(): string {
		return self::get_credentials()['key'];
	}

	/**
	 * Whether a key is configured for the current user.
	 */
	public static function has_api_key(): bool {
		return '' !== self::get_api_key();
	}

	/**
	 * Whether the key comes from wp-config.php and cannot be changed in the app.
	 */
	public static function is_api_key_fixed(): bool {
		return defined( 'COLLECTIBLES_NUMISTA_API_KEY' ) && COLLECTIBLES_NUMISTA_API_KEY;
	}

	/**
	 * Store (or clear) the current user's credentials.
	 *
	 * @param array $credentials Key, client id and client name as entered.
	 */
	public static function save_credentials( array $credentials ): void {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		$map = array(
			self::KEY_META         => 'key',
			self::CLIENT_ID_META   => 'client_id',
			self::CLIENT_NAME_META => 'client_name',
		);

		foreach ( $map as $meta_key => $field ) {
			$value = sanitize_text_field( (string) ( $credentials[ $field ] ?? '' ) );

			if ( '' === $value ) {
				delete_user_meta( $user_id, $meta_key );
				continue;
			}

			update_user_meta( $user_id, $meta_key, $value );
		}
	}

	/**
	 * Where a user registers a client and gets a key.
	 */
	public static function get_api_key_url(): string {
		return 'https://en.numista.com/api/doc/index.php';
	}

	/**
	 * The public catalogue URL for a type.
	 *
	 * @param int $id Numista type ID.
	 */
	public static function get_type_url( int $id ): string {
		return 'https://en.numista.com/catalogue/pieces' . $id . '.html';
	}

	/**
	 * Pull the Numista type ID out of whatever the user pasted: a catalogue
	 * URL, the "N# 202650" reference, or the bare number.
	 *
	 * @param string $input Pasted text.
	 */
	public static function parse_id( string $input ): int {
		$input = trim( $input );

		if ( '' === $input ) {
			return 0;
		}

		if ( preg_match( '/^\d+$/', $input ) ) {
			return (int) $input;
		}

		if ( preg_match( '/\bN#\s*(\d+)/i', $input, $matches ) ) {
			return (int) $matches[1];
		}

		$host = (string) wp_parse_url( $input, PHP_URL_HOST );

		if ( '' !== $host && preg_match( '/(^|\.)numista\.com$/i', $host ) ) {
			$path = (string) wp_parse_url( $input, PHP_URL_PATH );

			if ( preg_match( '/(\d+)(?:\.html)?$/', $path, $matches ) ) {
				return (int) $matches[1];
			}
		}

		return 0;
	}

	/**
	 * Fetch one catalogue type: what the piece is, as opposed to which issue of
	 * it you are holding.
	 *
	 * @param int $id Numista type ID.
	 * @return array|\WP_Error The decoded type, or an error to show the user.
	 */
	public static function fetch_type( int $id ) {
		if ( $id <= 0 ) {
			return new \WP_Error(
				'collectibles_numista_input',
				__( 'That does not look like a Numista link or number.', 'collectibles' )
			);
		}

		$body = self::request( 'types/' . $id, 'coll_numista_' . self::get_language() . '_' . $id );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		if ( ! is_array( $body ) || empty( $body['id'] ) ) {
			return new \WP_Error(
				'collectibles_numista_body',
				__( 'Numista answered with something unexpected.', 'collectibles' )
			);
		}

		return $body;
	}

	/**
	 * Make one API call, or answer it from the cache.
	 *
	 * Answers are kept for a year: a catalogue entry describes a piece that was
	 * minted decades ago, and a cache miss costs one of a small monthly ration
	 * of calls.
	 *
	 * @param string $path      Path below the API base, without a leading slash.
	 * @param string $cache_key Transient key to store the answer under.
	 * @return array|\WP_Error The decoded body, or an error to show the user.
	 */
	private static function request( string $path, string $cache_key ) {
		$language = self::get_language();
		$cached   = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( ! self::has_api_key() ) {
			return new \WP_Error(
				'collectibles_numista_key',
				__( 'No Numista API key is configured yet.', 'collectibles' )
			);
		}

		if ( ! self::has_budget_left() ) {
			return new \WP_Error(
				'collectibles_numista_budget',
				sprintf(
					/* translators: %d: number of lookups allowed per month */
					__( 'All %d Numista lookups for this month are used up. The catalogue entries you already fetched stay available.', 'collectibles' ),
					self::get_monthly_budget()
				)
			);
		}

		$url = self::API_BASE . '/' . $path;

		if ( '' !== $language ) {
			$url = add_query_arg( 'lang', $language, $url );
		}

		self::record_call();

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => self::get_user_agent(),
				'headers'    => array(
					'Numista-API-Key' => self::get_api_key(),
					'Accept'          => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// The request never got there, so Numista never counted it either.
			self::refund_call();

			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $status || 403 === $status ) {
			// A key Numista does not recognise spends nobody's allowance.
			self::refund_call();
		}

		if ( 200 !== $status ) {
			return new \WP_Error( 'collectibles_numista_http', self::get_http_message( $status, $body ) );
		}

		if ( ! is_array( $body ) ) {
			return new \WP_Error(
				'collectibles_numista_body',
				__( 'Numista answered with something unexpected.', 'collectibles' )
			);
		}

		// A year rather than forever: a non-expiring transient is autoloaded on
		// every request, and a catalogue this size would weigh on all of them.
		set_transient( $cache_key, $body, YEAR_IN_SECONDS );

		return $body;
	}

	/**
	 * Fetch the issues of a type: the individual years, mint letters and
	 * signature combinations a type was actually produced in.
	 *
	 * A type says what a piece is; an issue says which one you are holding.
	 * Signatures and mint letters live here rather than on the type, so filling
	 * a banknote in completely costs a second call.
	 *
	 * @param int $id Numista type ID.
	 * @return array|\WP_Error List of issues, or an error.
	 */
	public static function fetch_issues( int $id ) {
		$body = self::request( 'types/' . $id . '/issues', 'coll_numista_issues_' . self::get_language() . '_' . $id );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		return is_array( $body ) ? $body : array();
	}

	/**
	 * A one-line description of an issue, for the picker.
	 *
	 * @param array $issue One issue from the API.
	 */
	public static function describe_issue( array $issue ): string {
		$parts = array();
		$year  = self::first_scalar( $issue, array( 'gregorian_year', 'year', 'min_year' ) );

		if ( '' !== $year ) {
			$parts[] = $year;
		}

		$letter = self::first_scalar( $issue, array( 'mint_letter' ) );

		if ( '' !== $letter ) {
			/* translators: %s: mint letter on a coin */
			$parts[] = sprintf( __( 'mint letter %s', 'collectibles' ), $letter );
		}

		$signatures = self::map_signatures( $issue );

		if ( '' !== $signatures ) {
			$parts[] = $signatures;
		}

		$comment = self::first_scalar( $issue, array( 'comment' ) );

		if ( '' !== $comment ) {
			$parts[] = $comment;
		}

		if ( empty( $parts ) ) {
			/* translators: %d: Numista issue ID */
			return sprintf( __( 'Issue %d', 'collectibles' ), absint( $issue['id'] ?? 0 ) );
		}

		return implode( ' · ', $parts );
	}

	/**
	 * The signatures on an issue, as a readable list.
	 *
	 * @param array $issue One issue from the API.
	 */
	private static function map_signatures( array $issue ): string {
		$names = array();

		foreach ( (array) ( $issue['signatures'] ?? array() ) as $signature ) {
			if ( ! is_array( $signature ) ) {
				continue;
			}

			$name = self::first_scalar( $signature, array( 'signer_name' ) );

			if ( '' !== $name ) {
				$names[] = $name;
			}
		}

		return implode( ', ', array_unique( $names ) );
	}

	/**
	 * The User-Agent sent with every call, naming the registered client so that
	 * Numista can tell whose traffic it is.
	 */
	private static function get_user_agent(): string {
		$credentials = self::get_credentials();
		$agent       = 'Collectibles/1.0 (WordPress)';

		if ( '' !== $credentials['client_name'] ) {
			$agent = $credentials['client_name'] . ' ' . $agent;
		}

		if ( '' !== $credentials['client_id'] ) {
			$agent .= ' client/' . $credentials['client_id'];
		}

		return $agent;
	}

	/**
	 * How many calls a key may make per calendar month.
	 */
	public static function get_monthly_budget(): int {
		/**
		 * Filters the monthly Numista call budget.
		 *
		 * @param int $budget Calls allowed per calendar month.
		 */
		return max( 0, (int) apply_filters( 'collectibles_numista_monthly_budget', self::MONTHLY_BUDGET ) );
	}

	/**
	 * Calls made by the current user in the current calendar month.
	 */
	public static function get_usage(): int {
		$usage = get_user_meta( get_current_user_id(), self::USAGE_META, true );

		if ( ! is_array( $usage ) || ( $usage['month'] ?? '' ) !== self::get_current_month() ) {
			return 0;
		}

		return absint( $usage['count'] ?? 0 );
	}

	/**
	 * Calls left this month.
	 */
	public static function get_remaining(): int {
		return max( 0, self::get_monthly_budget() - self::get_usage() );
	}

	/**
	 * Whether another call is allowed this month.
	 */
	public static function has_budget_left(): bool {
		return self::get_remaining() > 0;
	}

	/**
	 * Count one call against this month's budget.
	 *
	 * Counted before the request goes out, so a call that times out is still
	 * paid for — which is what Numista sees too.
	 */
	private static function record_call(): void {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		update_user_meta(
			$user_id,
			self::USAGE_META,
			array(
				'month' => self::get_current_month(),
				'count' => self::get_usage() + 1,
			)
		);
	}

	/**
	 * Give a counted call back, for a request that never reached the quota.
	 */
	private static function refund_call(): void {
		$user_id = get_current_user_id();
		$used    = self::get_usage();

		if ( ! $user_id || $used < 1 ) {
			return;
		}

		update_user_meta(
			$user_id,
			self::USAGE_META,
			array(
				'month' => self::get_current_month(),
				'count' => $used - 1,
			)
		);
	}

	/**
	 * The current calendar month in the site's timezone, as "YYYY-MM".
	 */
	private static function get_current_month(): string {
		return wp_date( 'Y-m' );
	}

	/**
	 * A readable message for an API status code.
	 *
	 * @param int   $status HTTP status code.
	 * @param mixed $body   Decoded response body, if any.
	 */
	private static function get_http_message( int $status, $body ): string {
		switch ( $status ) {
			case 401:
			case 403:
				return __( 'Numista rejected the API key. Check it in the settings.', 'collectibles' );

			case 404:
				return __( 'Numista has no catalogue entry with that number.', 'collectibles' );

			case 429:
				return __( 'Numista is rate limiting the lookups. Try again in a while.', 'collectibles' );
		}

		if ( is_array( $body ) && ! empty( $body['error_message'] ) ) {
			return sanitize_text_field( (string) $body['error_message'] );
		}

		/* translators: %d: HTTP status code */
		return sprintf( __( 'Numista answered with status %d.', 'collectibles' ), $status );
	}

	/**
	 * The language to request, from the site locale, or an empty string to let
	 * the API pick its default.
	 */
	private static function get_language(): string {
		$locale   = strtolower( (string) get_locale() );
		$language = substr( $locale, 0, 2 );

		return in_array( $language, self::LANGUAGES, true ) ? $language : '';
	}

	/**
	 * Map a fetched catalogue type onto this plugin's fields.
	 *
	 * Numista describes coins and banknotes with one schema, so every key is
	 * read defensively: a record that does not carry a piece of information
	 * simply leaves that field alone rather than emptying it.
	 *
	 * @param array  $type  Decoded type from the API.
	 * @param string $kind  Collection kind slug the item is being added to.
	 * @param array  $issue The chosen issue, when one has been picked.
	 * @return array {
	 *     @type string $title  Suggested item name.
	 *     @type array  $values Field key => value.
	 *     @type string $tags   The catalogue's tags, comma separated.
	 *     @type string $notes  What the fields cannot hold: the descriptions of
	 *                          both sides, and the other catalogues' references.
	 *     @type string $url    The catalogue page for the entry.
	 * }
	 */
	public static function map_type( array $type, string $kind, array $issue = array() ): array {
		$values = array();
		$id     = isset( $type['id'] ) ? absint( $type['id'] ) : 0;

		$values['numista_id'] = $id ? (string) $id : '';

		$year = self::first_scalar( $type, array( 'min_year', 'year' ) );

		if ( '' !== $year ) {
			$values[ Item::YEAR_META_KEY ] = $year;
		}

		$values[ Item::ORIGIN_META_KEY ] = self::first_scalar( $type, array( 'issuer.name', 'issuer.code' ) );
		$values['denomination']          = self::first_scalar( $type, array( 'value.text', 'value.currency.name' ) );
		$values['series']                = self::first_scalar( $type, array( 'series' ) );
		$values['composition']           = self::first_scalar( $type, array( 'composition.text', 'composition.name' ) );
		$values['watermark']             = self::first_scalar( $type, array( 'watermark.description', 'watermark' ) );
		$values['signature']             = self::first_scalar( $type, array( 'signature.description', 'signature' ) );
		$values['printer']               = self::join_names( $type, 'printers' );
		$values['mint']                  = self::join_names( $type, 'mints' );
		$values['mint_mark']             = self::first_scalar( $type, array( 'mintmark', 'mint_mark' ) );

		$weight = self::first_scalar( $type, array( 'weight' ) );

		if ( is_numeric( $weight ) ) {
			$values['weight_g'] = $weight;
		}

		self::map_size( $type, $values );

		$references = self::map_references( $type, $kind );

		if ( '' !== $references['catalog_number'] ) {
			$values[ Item::CATALOG_META_KEY ] = $references['catalog_number'];
		}

		if ( ! empty( $issue ) ) {
			self::map_issue( $issue, $kind, $values );
		}

		// Only offer the fields this kind actually has.
		$known  = wp_list_pluck( Item::get_fields_for_kind( $kind ), 'key' );
		$values = array_intersect_key( $values, array_flip( $known ) );
		$values = array_filter(
			$values,
			static function ( $value ) {
				return '' !== $value;
			}
		);

		$url = self::first_scalar( $type, array( 'url' ) );

		return array(
			'title'  => self::first_scalar( $type, array( 'title' ) ),
			'values' => $values,
			'tags'   => self::map_tags( $type ),
			'notes'  => self::map_notes( $type, $references['notes'] ),
			'url'    => '' !== $url ? $url : ( $id ? self::get_type_url( $id ) : '' ),
		);
	}

	/**
	 * Overlay what the chosen issue knows: the exact year it was struck or
	 * printed, its mint letter, the signatures it carries, and the more precise
	 * catalogue number of that issue.
	 *
	 * @param array  $issue  One issue from the API.
	 * @param string $kind   Collection kind slug.
	 * @param array  $values Field values, modified in place.
	 */
	private static function map_issue( array $issue, string $kind, array &$values ): void {
		$year = self::first_scalar( $issue, array( 'gregorian_year', 'year', 'min_year' ) );

		if ( '' !== $year ) {
			$values[ Item::YEAR_META_KEY ] = $year;
		}

		$letter = self::first_scalar( $issue, array( 'mint_letter' ) );

		if ( '' !== $letter ) {
			$values['mint_mark'] = $letter;
		}

		$signatures = self::map_signatures( $issue );

		if ( '' !== $signatures ) {
			$values['signature'] = $signatures;
		}

		$references = self::map_references( $issue, $kind );

		if ( '' !== $references['catalog_number'] ) {
			$values[ Item::CATALOG_META_KEY ] = $references['catalog_number'];
		}
	}

	/**
	 * Map the physical size, which is a diameter in millimetres for coins and
	 * width by height for banknotes.
	 *
	 * @param array $type   Decoded type.
	 * @param array $values Field values, modified in place.
	 */
	private static function map_size( array $type, array &$values ): void {
		$size  = $type['size'] ?? '';
		$size2 = $type['size2'] ?? '';

		// A rectangular piece is measured twice: width in "size", height in
		// "size2". A round one only has the first, which is its diameter.
		if ( is_numeric( $size ) && is_numeric( $size2 ) ) {
			$values['dimensions'] = sprintf(
				/* translators: 1: width in millimetres, 2: height in millimetres */
				__( '%1$s × %2$s mm', 'collectibles' ),
				self::format_measure( $size ),
				self::format_measure( $size2 )
			);

			return;
		}

		if ( is_numeric( $size ) ) {
			$values['diameter_mm'] = self::format_measure( $size );

			/* translators: %s: diameter in millimetres */
			$values['dimensions'] = sprintf( __( '%s mm', 'collectibles' ), self::format_measure( $size ) );

			return;
		}

		if ( is_string( $size ) && '' !== $size ) {
			$values['dimensions'] = $size;
		}
	}

	/**
	 * Trim a measurement to a plain number: 140.0 reads as 140.
	 *
	 * @param mixed $value Numeric measurement.
	 */
	private static function format_measure( $value ): string {
		return Item::format_number( (float) $value );
	}

	/**
	 * The catalogue's own tags, ready for the item's tag field.
	 *
	 * @param array $type Decoded type.
	 */
	private static function map_tags( array $type ): string {
		$tags = array();

		foreach ( (array) ( $type['tags'] ?? array() ) as $tag ) {
			if ( is_string( $tag ) && '' !== trim( $tag ) ) {
				$tags[] = trim( $tag );
				continue;
			}

			if ( is_array( $tag ) ) {
				$name = self::first_scalar( $tag, array( 'name' ) );

				if ( '' !== $name ) {
					$tags[] = $name;
				}
			}
		}

		return implode( ', ', array_unique( $tags ) );
	}

	/**
	 * What the catalogue describes but the fields cannot hold: the two sides of
	 * the piece, and the catalogues other than the one it is filed under.
	 *
	 * @param array  $type   Decoded type.
	 * @param string $others Remaining catalogue references.
	 */
	private static function map_notes( array $type, string $others ): string {
		$lines   = array();
		$obverse = self::first_scalar( $type, array( 'obverse.description' ) );
		$reverse = self::first_scalar( $type, array( 'reverse.description' ) );

		if ( '' !== $obverse ) {
			/* translators: %s: description of the front of the piece */
			$lines[] = sprintf( __( 'Obverse: %s', 'collectibles' ), $obverse );
		}

		if ( '' !== $reverse ) {
			/* translators: %s: description of the back of the piece */
			$lines[] = sprintf( __( 'Reverse: %s', 'collectibles' ), $reverse );
		}

		if ( '' !== $others ) {
			/* translators: %s: comma separated catalogue references, e.g. "TBB# 246, KK# 250" */
			$lines[] = sprintf( __( 'Also catalogued as %s.', 'collectibles' ), $others );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Split the catalogue references into the one this kind files items under
	 * and the rest, which are kept as a note so nothing is silently dropped.
	 *
	 * @param array  $type Decoded type.
	 * @param string $kind Collection kind slug.
	 * @return array{catalog_number: string, notes: string}
	 */
	private static function map_references( array $type, string $kind ): array {
		$preferred = Schema::get_catalog_codes( $kind );
		$number    = '';
		$others    = array();

		foreach ( (array) ( $type['references'] ?? array() ) as $reference ) {
			if ( ! is_array( $reference ) ) {
				continue;
			}

			$code  = self::first_scalar( $reference, array( 'catalogue.code', 'catalogue.title', 'catalogue' ) );
			$value = self::first_scalar( $reference, array( 'number' ) );

			if ( '' === $value ) {
				continue;
			}

			if ( '' === $number && in_array( $code, $preferred, true ) ) {
				$number = $value;
				continue;
			}

			$others[] = '' === $code ? $value : $code . '# ' . $value;
		}

		return array(
			'catalog_number' => $number,
			'notes'          => implode( ', ', $others ),
		);
	}

	/**
	 * The first non-empty scalar among a list of dotted paths.
	 *
	 * @param array    $data  Source array.
	 * @param string[] $paths Dotted paths to try in order.
	 */
	private static function first_scalar( array $data, array $paths ): string {
		foreach ( $paths as $path ) {
			$value = $data;

			foreach ( explode( '.', $path ) as $segment ) {
				if ( ! is_array( $value ) || ! isset( $value[ $segment ] ) ) {
					$value = null;
					break;
				}

				$value = $value[ $segment ];
			}

			if ( is_scalar( $value ) && '' !== (string) $value ) {
				return trim( (string) $value );
			}
		}

		return '';
	}

	/**
	 * Join the names of a list of objects ("mints", "printers") into one value.
	 *
	 * @param array  $data Source array.
	 * @param string $key  List key.
	 */
	private static function join_names( array $data, string $key ): string {
		$names = array();

		foreach ( (array) ( $data[ $key ] ?? array() ) as $entry ) {
			if ( is_string( $entry ) && '' !== $entry ) {
				$names[] = trim( $entry );
				continue;
			}

			if ( is_array( $entry ) ) {
				$name = self::first_scalar( $entry, array( 'name', 'title' ) );

				if ( '' !== $name ) {
					$names[] = $name;
				}
			}
		}

		return implode( ', ', array_unique( $names ) );
	}
}
