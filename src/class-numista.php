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
	 * Signed source hashes carried from a lookup to a new item's save.
	 *
	 * @param array  $mapped Mapped catalogue entry.
	 * @param string $kind Collection kind.
	 * @param int    $collection_id Collection ID.
	 */
	public static function source_token( array $mapped, string $kind, int $collection_id ): string {
		$hashes = array( 'title' => hash( 'sha256', sanitize_text_field( $mapped['title'] ) ) );
		foreach ( Item::get_fields_for_kind( $kind ) as $field ) {
			$value = Item::sanitize_field_value( $field, $mapped['values'][ $field['key'] ] ?? '' );
			if ( '' !== $value && empty( $field['numista_exempt'] ) ) {
				$hashes[ $field['key'] ] = hash( 'sha256', $value );
			}
		}
		// Hex survives WordPress text sanitization of submitted hidden fields.
		$payload = bin2hex( wp_json_encode( array( get_current_user_id(), $collection_id, $hashes ) ) );
		return $payload . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	/**
	 * Verify a form's source record without trusting hidden input values.
	 *
	 * @param string $token Signed source record.
	 * @param int    $collection_id Collection ID.
	 * @return array|null Source hashes, or null for an invalid token.
	 */
	public static function read_source_token( string $token, int $collection_id ): ?array {
		$parts = explode( '.', $token );
		if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) ), $parts[1] ) ) {
			return null;
		}
		$data = json_decode( hex2bin( $parts[0] ), true );
		if ( ! is_array( $data ) || 3 !== count( $data ) || get_current_user_id() !== $data[0] || $collection_id !== $data[1] || ! is_array( $data[2] ) ) {
			return null;
		}
		return $data[2];
	}

	/**
	 * Freeze a conservative baseline for older linked items before editing.
	 *
	 * Older imports have no per-field history. Their nonempty fields and title
	 * remain private until changed; this also covers CSV and ability additions.
	 * Numista identifiers are not catalogue descriptions and need no restriction.
	 *
	 * @param int $item_id Item ID.
	 */
	public static function snapshot_sources( int $item_id ): void {
		if ( metadata_exists( 'post', $item_id, '_coll_numista_sources' ) || ! get_post_meta( $item_id, 'numista_id', true ) ) {
			return;
		}
		$item = Item::get( $item_id );
		if ( ! $item ) {
			return;
		}
		$hashes = array( 'title' => hash( 'sha256', $item->post_title ) );
		foreach ( Item::get_fields_for_kind( Collection::get_kind( (int) $item->post_parent ) ) as $field ) {
			$value = (string) get_post_meta( $item_id, $field['key'], true );
			if ( ! Item::is_lot_field( $field ) && empty( $field['numista_exempt'] ) && '' !== $value ) {
				$hashes[ $field['key'] ] = hash( 'sha256', $value );
			}
		}
		update_post_meta( $item_id, '_coll_numista_sources', $hashes );
	}

	/**
	 * Keep fixed catalogue facts out of the source restriction and its history.
	 *
	 * @param array  $hashes Stored source hashes.
	 * @param string $kind Collection kind.
	 */
	public static function restricted_source_hashes( array $hashes, string $kind ): array {
		foreach ( Item::get_fields_for_kind( $kind ) as $field ) {
			if ( ! empty( $field['numista_exempt'] ) ) {
				unset( $hashes[ $field['key'] ] );
			}
		}
		return $hashes;
	}

	/**
	 * Return keys whose current values still come from Numista.
	 *
	 * Actual changes clear the source marker. An unchanged save does not.
	 * Reads compare again so REST/meta changes also respect the same boundary.
	 *
	 * @param int $item_id Item ID.
	 * @return string[] Imported keys, including title when applicable.
	 */
	public static function get_imported_keys( int $item_id ): array {
		self::snapshot_sources( $item_id );
		$stored   = get_post_meta( $item_id, '_coll_numista_sources', true );
		$item     = Item::get( $item_id );
		$kind     = $item ? Collection::get_kind( (int) $item->post_parent ) : Schema::KIND_OTHER;
		$hashes   = self::restricted_source_hashes( is_array( $stored ) ? $stored : array(), $kind );
		$previous = get_post_meta( $item_id, '_coll_numista_history', true );
		$history  = self::restricted_source_hashes( array_merge( is_array( $previous ) ? $previous : array(), $hashes ), $kind );
		if ( $history !== $previous && ( $history || is_array( $previous ) ) ) {
			update_post_meta( $item_id, '_coll_numista_history', $history );
		}
		// Preserve an opaque URL even after the imported name is replaced.
		$item = Item::get( $item_id );
		if ( $item ) {
			self::public_item_slug( $item );
		}
		foreach ( $hashes as $key => $hash ) {
			$value = 'title' === $key ? (string) get_post_field( 'post_title', $item_id ) : (string) get_post_meta( $item_id, $key, true );
			if ( ! hash_equals( $hash, hash( 'sha256', $value ) ) ) {
				unset( $hashes[ $key ] );
			}
		}
		if ( $hashes !== $stored && is_array( $stored ) ) {
			update_post_meta( $item_id, '_coll_numista_sources', $hashes );
		}
		return array_keys( $hashes );
	}

	/**
	 * Read all source hashes, including fields previously released by their owner.
	 *
	 * @param int $item_id Item ID.
	 */
	public static function get_source_hashes( int $item_id ): array {
		$history = get_post_meta( $item_id, '_coll_numista_history', true );
		$current = get_post_meta( $item_id, '_coll_numista_sources', true );
		$item    = Item::get( $item_id );
		$kind    = $item ? Collection::get_kind( (int) $item->post_parent ) : Schema::KIND_OTHER;
		return self::restricted_source_hashes( array_merge( is_array( $history ) ? $history : array(), is_array( $current ) ? $current : array() ), $kind );
	}

	/**
	 * Source fields already released on an earlier save remain toggleable.
	 *
	 * @param int $item_id Item ID.
	 */
	public static function get_released_keys( int $item_id ): array {
		$current = get_post_meta( $item_id, '_coll_numista_sources', true );
		return array_values( array_diff( array_keys( self::get_source_hashes( $item_id ) ), array_keys( is_array( $current ) ? $current : array() ) ) );
	}

	/**
	 * Sanitize the field keys a user explicitly confirms as their own entries.
	 *
	 * @param mixed $keys Submitted confirmation keys.
	 * @return string[] Field keys.
	 */
	public static function sanitize_confirmations( $keys ): array {
		$clean = array();
		foreach ( is_array( $keys ) ? $keys : array() as $key ) {
			if ( is_string( $key ) ) {
				$clean[] = sanitize_key( $key );
			}
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * Apply explicit per-field source confirmations only when the item is saved.
	 *
	 * @param int   $item_id Item ID.
	 * @param mixed $keys Submitted confirmation keys.
	 */
	public static function confirm_sources( int $item_id, $keys ): void {
		$hashes = get_post_meta( $item_id, '_coll_numista_sources', true );
		if ( ! is_array( $hashes ) ) {
			return;
		}
		foreach ( self::sanitize_confirmations( $keys ) as $key ) {
			unset( $hashes[ $key ] );
		}
		update_post_meta( $item_id, '_coll_numista_sources', $hashes );
	}


	/**
	 * Keep explicitly toggled-back values private using their current baseline.
	 *
	 * @param int   $item_id Item ID.
	 * @param mixed $keys Submitted private field keys.
	 */
	public static function keep_sources_private( int $item_id, $keys ): void {
		$keys = self::sanitize_confirmations( $keys );
		if ( ! $keys ) {
			return;
		}
		$item = Item::get( $item_id );
		if ( ! $item ) {
			return;
		}
		$fields = array_column( Item::get_fields_for_kind( Collection::get_kind( (int) $item->post_parent ) ), null, 'key' );
		$stored = get_post_meta( $item_id, '_coll_numista_sources', true );
		$hashes = is_array( $stored ) ? $stored : array();
		foreach ( $keys as $key ) {
			if ( 'title' === $key ) {
				$value = $item->post_title;
			} elseif ( isset( $fields[ $key ] ) && ! Item::is_lot_field( $fields[ $key ] ) && empty( $fields[ $key ]['numista_exempt'] ) ) {
				$value = (string) get_post_meta( $item_id, $key, true );
			} else {
				continue;
			}
			if ( '' !== $value ) {
				$hashes[ $key ] = hash( 'sha256', $value );
			}
		}
		update_post_meta( $item_id, '_coll_numista_sources', $hashes );
	}

	/**
	 * Compare proposed values with their imported source, without saving edits.
	 *
	 * @param string $kind Collection kind.
	 * @param array  $hashes Source hashes from storage or a verified token.
	 * @param array  $source Unslashed proposed form values.
	 * @param array  $released Field keys already released on an earlier save.
	 * @return array Field key => status and translated explanation.
	 */
	public static function source_statuses( string $kind, array $hashes, array $source, array $released = array() ): array {
		$fields    = array_column( Item::get_fields_for_kind( $kind ), null, 'key' );
		$states    = array();
		$confirmed = self::sanitize_confirmations( $source['coll_numista_own'] ?? array() );
		$private   = self::sanitize_confirmations( $source['coll_numista_private'] ?? array() );
		foreach ( self::restricted_source_hashes( $hashes, $kind ) as $key => $hash ) {
			if ( 'title' === $key ) {
				$value = sanitize_text_field( is_scalar( $source['coll_name'] ?? '' ) ? (string) ( $source['coll_name'] ?? '' ) : '' );
			} elseif ( isset( $fields[ $key ] ) ) {
				$value = Item::sanitize_field_value( $fields[ $key ], $source[ 'coll_field_' . $key ] ?? '' );
			} else {
				continue;
			}
			$state = '' === $value ? 'cleared' : ( hash_equals( $hash, hash( 'sha256', $value ) ) ? 'private' : 'updated' );
			if ( '' !== $value && ( in_array( $key, $confirmed, true ) || in_array( $key, $released, true ) ) ) {
				$state = 'confirmed';
			}
			if ( '' !== $value && in_array( $key, $private, true ) ) {
				$state = 'private';
			}
			$labels         = array(
				'private'   => __( 'Private until changed', 'collectibles' ),
				'updated'   => __( 'I’ve verified this', 'collectibles' ),
				'cleared'   => '',
				'confirmed' => __( 'I’ve verified this', 'collectibles' ),
			);
			$states[ $key ] = array(
				'state' => $state,
				'label' => $labels[ $state ],
			);
		}
		return $states;
	}

	/**
	 * Opaque public slugs prevent an imported title leaking through its URL.
	 *
	 * @param \WP_Post $item Item post.
	 */
	public static function public_item_slug( \WP_Post $item ): string {
		self::snapshot_sources( $item->ID );
		$slug   = get_post_meta( $item->ID, '_coll_numista_public_slug', true );
		$source = get_post_meta( $item->ID, '_coll_numista_sources', true );
		if ( ! $slug && is_array( $source ) && isset( $source['title'] ) ) {
			$slug = 'item-' . substr( hash_hmac( 'sha256', 'numista-item-' . $item->ID, wp_salt( 'auth' ) ), 0, 20 );
			update_post_meta( $item->ID, '_coll_numista_public_slug', $slug );
		}
		return $slug ? $slug : $item->post_name;
	}


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
		return 'https://numista.com/api/activate_api_key.php?plan=free';
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
	 * Place one issuer from what it calls itself.
	 *
	 * The slug is tried first: it is the French name of the place and, unlike
	 * the display name, it does not change with the language of the request.
	 *
	 * @param array $issuer One issuer, or the inline issuer of a type.
	 * @return string ISO territory code, or '' when it names no territory.
	 */
	private static function place_issuer( array $issuer ): string {
		$slug = (string) ( $issuer['code'] ?? '' );

		if ( '' !== $slug ) {
			// Numista groups a country's issuers under "<country>_section".
			$slug      = (string) preg_replace( '/_(section|notgeld|colonies?)$/', '', $slug );
			$territory = Geography::find_by_name( str_replace( array( '_', '-' ), ' ', $slug ), 'fr' );

			if ( '' !== $territory ) {
				return $territory;
			}
		}

		$name = (string) ( $issuer['name'] ?? '' );

		if ( '' === $name ) {
			return '';
		}

		// The name can also land on a historic issuer, which is how
		// Czechoslovakia and the Ottoman Empire keep a row of their own.
		$territory = Geography::find_by_name( $name, self::get_language() );

		if ( '' !== $territory ) {
			return $territory;
		}

		// Names written back to front: "Bahamas, The".
		$comma = strpos( $name, ',' );

		if ( false !== $comma ) {
			return Geography::find_by_name( trim( substr( $name, 0, $comma ) ) );
		}

		return '';
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
	public static function get_language(): string {
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

		// Numista's "issuer" is the country the piece is catalogued under, and
		// its "issuing entity" is the bank or authority behind it.
		$values[ Item::COUNTRY_META_KEY ] = self::map_country( $type );

		$values[ Item::ISSUER_META_KEY ] = self::first_scalar(
			$type,
			array( 'issuing_entity.name', 'secondary_issuing_entity.name' )
		);
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
	 * Work out which territory a type belongs to.
	 *
	 * Numista identifies its issuers by a slug that turns out to be the French
	 * name of the place — "estonie", "autriche", "etats-unis" — and, unlike the
	 * display name, it reads the same whatever language the catalogue answered
	 * in. So the slug is tried first and the name after it. Neither is an ISO
	 * code, which is why both go through the same name matching.
	 *
	 * @param array $type Decoded type.
	 */
	private static function map_country( array $type ): string {
		$issuer = isset( $type['issuer'] ) && is_array( $type['issuer'] ) ? $type['issuer'] : array();

		return self::place_issuer( $issuer );
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
