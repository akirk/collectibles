<?php
/**
 * Read-only LEGO set lookups from Rebrickable's v3 catalogue.
 *
 * @package Collectibles
 */

namespace Collectibles;

/**
 * Personal API credentials, cached requests and set field mapping.
 */
class Rebrickable {

	public const KEY_META         = 'coll_rebrickable_api_key';
	public const LEGO_LOCALE_META = 'coll_lego_locale';

	/**
	 * Normalize a LEGO language-region code; empty means automatic.
	 *
	 * @param string $locale Language-region code.
	 */
	public static function sanitize_lego_locale( string $locale ): string {
		$locale = strtolower( trim( $locale ) );
		if ( '' === $locale ) {
			return self::get_default_lego_locale();
		}
		return preg_match( '/^[a-z]{2}-[a-z]{2}$/', $locale ) ? $locale : '';
	}

	/**
	 * The current reader's preferred LEGO website language and region.
	 */
	public static function get_lego_locale(): string {
		$locale = self::sanitize_lego_locale( (string) get_user_meta( get_current_user_id(), self::LEGO_LOCALE_META, true ) );
		return '' !== $locale ? $locale : self::get_default_lego_locale();
	}

	/**
	 * Infer a LEGO URL locale from the reader's WordPress language and region.
	 * Availability on LEGO.com is not guaranteed; users can override this.
	 */
	public static function get_default_lego_locale(): string {
		if ( preg_match( '/^([a-z]{2})[_-]([a-z]{2})(?:[_-]|$)/i', get_user_locale(), $matches ) ) {
			return strtolower( $matches[1] . '-' . $matches[2] );
		}
		return 'en-us';
	}

	/**
	 * The stored override, or empty when following the user's language.
	 */
	public static function get_lego_locale_override(): string {
		return (string) get_user_meta( get_current_user_id(), self::LEGO_LOCALE_META, true );
	}

	/**
	 * Save the current user's preferred LEGO website locale.
	 *
	 * @param string $locale Language-region code.
	 */
	public static function save_lego_locale( string $locale ): void {
		if ( get_current_user_id() && '' === trim( $locale ) ) {
			delete_user_meta( get_current_user_id(), self::LEGO_LOCALE_META );
			return;
		}
		$locale = self::sanitize_lego_locale( $locale );
		if ( get_current_user_id() && '' !== $locale ) {
			update_user_meta( get_current_user_id(), self::LEGO_LOCALE_META, $locale );
		}
	}

	/**
	 * Whether this kind declares Rebrickable as its catalogue provider.
	 *
	 * @param string $kind Collection kind.
	 */
	public static function supports_kind( string $kind ): bool {
		return 'rebrickable' === ( Schema::get_kind( $kind )['catalog']['provider'] ?? '' );
	}

	/**
	 * Whether wp-config.php supplies the key.
	 */
	public static function is_api_key_fixed(): bool {
		return defined( 'COLLECTIBLES_REBRICKABLE_API_KEY' ) && COLLECTIBLES_REBRICKABLE_API_KEY;
	}

	/**
	 * The current user's API key.
	 */
	public static function get_api_key(): string {
		return trim( (string) ( self::is_api_key_fixed() ? COLLECTIBLES_REBRICKABLE_API_KEY : get_user_meta( get_current_user_id(), self::KEY_META, true ) ) );
	}

	/**
	 * Store or clear the current user's key.
	 *
	 * @param string $key Personal API key.
	 */
	public static function save_api_key( string $key ): void {
		if ( ! get_current_user_id() || self::is_api_key_fixed() ) {
			return;
		}
		$key = sanitize_text_field( $key );
		if ( '' === $key ) {
			delete_user_meta( get_current_user_id(), self::KEY_META );
		} else {
			update_user_meta( get_current_user_id(), self::KEY_META, $key );
		}
	}

	/**
	 * Normalize a set number or a Rebrickable set URL; bare numbers use variant 1.
	 *
	 * @param string $reference Submitted reference.
	 */
	public static function parse_id( string $reference ): string {
		$reference = trim( $reference );
		if ( preg_match( '~^https?://(?:www\.)?rebrickable\.com/sets/([^/]+)(?:/[^?#]*)?(?:[?#].*)?$~i', $reference, $matches ) ) {
			$reference = $matches[1];
		}
		if ( ! preg_match( '/^[a-z0-9]+(?:-\d+)?$/i', $reference ) ) {
			return '';
		}
		return false === strpos( $reference, '-' ) ? $reference . '-1' : $reference;
	}

	/**
	 * Public pages for a set, with LEGO's number excluding the catalogue variant.
	 *
	 * @param string $reference Set number or catalogue URL.
	 * @return array<string, string> Labels and public URLs.
	 */
	public static function get_set_links( string $reference ): array {
		$id = self::parse_id( $reference );
		if ( '' === $id ) {
			return array();
		}
		$links = array( 'Rebrickable' => 'https://rebrickable.com/sets/' . rawurlencode( $id ) . '/' );
		if ( preg_match( '/^(\d+)-\d+$/', $id, $matches ) ) {
			$links['LEGO.com'] = 'https://www.lego.com/' . self::get_lego_locale() . '/product/' . $matches[1];
		}
		return $links;
	}

	/**
	 * Link a LEGO set number to its product page, excluding other brands.
	 *
	 * @param string $reference Set number.
	 * @param array  $values    Item field values.
	 */
	public static function get_lego_url( string $reference, array $values ): string {
		if ( 'lego' !== strtolower( trim( $values['brand'] ?? '' ) ) ) {
			return '';
		}
		return self::get_set_links( $reference )['LEGO.com'] ?? '';
	}

	/**
	 * LEGO's instructions page for a numeric set number.
	 *
	 * @param string $reference Set number.
	 * @param array  $values    Item field values.
	 */
	public static function get_instructions_url( string $reference, array $values ): string {
		if ( '' === self::get_lego_url( $reference, $values ) ) {
			return '';
		}
		$id = self::parse_id( $reference );
		return 'https://www.lego.com/' . self::get_lego_locale() . '/service/building-instructions/' . explode( '-', $id )[0];
	}

	/**
	 * Fetch a set and, if available, its theme, without saving an item.
	 *
	 * @param string $reference Set number or catalogue URL.
	 * @return array|\WP_Error Set details or a friendly error.
	 */
	public static function fetch_set( string $reference ) {
		$id = self::parse_id( $reference );
		if ( '' === $id ) {
			return new \WP_Error( 'coll_rebrickable_reference', __( 'Enter a set number or a Rebrickable set link.', 'collectibles' ) );
		}
		$set = self::request( 'sets/' . rawurlencode( $id ) );
		if ( is_wp_error( $set ) ) {
			return $set;
		}
		if ( empty( $set['set_num'] ) || ! isset( $set['name'] ) || ! is_scalar( $set['name'] ) || ! is_string( $set['set_num'] ) || $id !== $set['set_num'] ) {
			return new \WP_Error( 'coll_rebrickable_response', __( 'Rebrickable returned an invalid set entry. Try again later.', 'collectibles' ) );
		}
		if ( ! empty( $set['theme_id'] ) ) {
			$theme = self::request( 'themes/' . absint( $set['theme_id'] ) );
			if ( ! is_wp_error( $theme ) && isset( $theme['name'] ) && is_string( $theme['name'] ) ) {
				$set['theme_name'] = $theme['name'];
			}
		}
		return $set;
	}

	/**
	 * Read one fixed-host endpoint, caching successful responses for a year.
	 *
	 * @param string $endpoint Internal endpoint path.
	 * @return array|\WP_Error Decoded response or error.
	 */
	private static function request( string $endpoint ) {
		$cache_key = 'coll_rebrickable_' . md5( $endpoint );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$key = self::get_api_key();
		if ( '' === $key ) {
			return new \WP_Error( 'coll_rebrickable_key', __( 'Add your Rebrickable API key under Settings first.', 'collectibles' ) );
		}
		$response = wp_remote_get(
			'https://rebrickable.com/api/v3/lego/' . $endpoint . '/',
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array( 'Authorization' => 'key ' . $key ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'coll_rebrickable_transport', __( 'Rebrickable could not be reached. Try again later.', 'collectibles' ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( 404 === $status ) {
			return new \WP_Error( 'coll_rebrickable_missing', __( 'That set was not found on Rebrickable. Check its number and variant.', 'collectibles' ) );
		}
		if ( 401 === $status || 403 === $status ) {
			return new \WP_Error( 'coll_rebrickable_auth', __( 'Rebrickable rejected the API key. Check it under Settings.', 'collectibles' ) );
		}
		if ( 429 === $status ) {
			return new \WP_Error( 'coll_rebrickable_limit', __( 'Rebrickable is limiting requests. Wait a little before trying again.', 'collectibles' ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || ! is_array( $data ) || empty( $data ) ) {
			return new \WP_Error( 'coll_rebrickable_response', __( 'Rebrickable returned an invalid response. Try again later.', 'collectibles' ) );
		}
		// Do not keep malformed successful responses for the lifetime of the cache.
		if ( ! isset( $data['name'] ) || ! is_string( $data['name'] )
			|| ( 0 === strpos( $endpoint, 'sets/' ) && ( $data['set_num'] ?? '' ) !== rawurldecode( substr( $endpoint, 5 ) ) ) ) {
			return new \WP_Error( 'coll_rebrickable_response', __( 'Rebrickable returned an invalid response. Try again later.', 'collectibles' ) );
		}
		set_transient( $cache_key, $data, YEAR_IN_SECONDS );
		return $data;
	}

	/**
	 * Map catalogue facts, leaving personal condition, quantity and prices alone.
	 *
	 * @param array $set Catalogue set details.
	 * @return array Form prefill in the same shape as Numista's mapping.
	 */
	public static function map_set( array $set ): array {
		$values = array(
			'brand'          => 'LEGO',
			'catalog_number' => sanitize_text_field( preg_replace( '/-\d+$/', '', (string) ( $set['set_num'] ?? '' ) ) ),
			'rebrickable_id' => sanitize_text_field( (string) ( $set['set_num'] ?? '' ) ),
		);
		foreach ( array(
			'year'      => 'year',
			'num_parts' => 'part_count',
		) as $source => $target ) {
			if ( isset( $set[ $source ] ) && is_numeric( $set[ $source ] ) && $set[ $source ] >= 0 ) {
				$values[ $target ] = (string) absint( $set[ $source ] );
			}
		}
		if ( ! empty( $set['theme_name'] ) ) {
			$values['theme'] = sanitize_text_field( (string) $set['theme_name'] );
		}
		return array(
			'title'  => sanitize_text_field( (string) ( $set['name'] ?? '' ) ),
			'values' => $values,
			'tags'   => '',
			'notes'  => '',
		);
	}
}
