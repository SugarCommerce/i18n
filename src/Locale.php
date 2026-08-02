<?php
/**
 * Locale negotiation.
 *
 * @package   Sugar\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace Sugar\I18n;

/**
 * Class Locale
 *
 * Works out which language to serve.
 *
 * @since 1.0.0
 */
final readonly class Locale {

	/**
	 * Pick the best available locale for an `Accept-Language` header.
	 *
	 * The header is an ordered list with quality weights, e.g.
	 * `de-AT,de;q=0.9,en;q=0.7`. A reader wanting Austrian German is
	 * happier with German than with English, so a region-tagged request
	 * falls back to its base language before moving down the list.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $header    Raw `Accept-Language` value.
	 * @param string[] $available Locales this application has.
	 * @param string   $fallback  Returned when nothing matches.
	 *
	 * @return string
	 */
	public static function negotiate( string $header, array $available, string $fallback = 'en' ): string {
		if ( array() === $available ) {
			return $fallback;
		}

		// Index by lowercase so matching is case-insensitive without
		// losing the caller's preferred spelling.
		$offered = array();

		foreach ( $available as $locale ) {
			$offered[ strtolower( self::normalise( $locale ) ) ] = $locale;
		}

		foreach ( self::parse( $header ) as $wanted ) {
			$wanted = strtolower( $wanted );

			if ( isset( $offered[ $wanted ] ) ) {
				return $offered[ $wanted ];
			}

			// de-AT → de
			$base = explode( '-', $wanted )[0];

			if ( isset( $offered[ $base ] ) ) {
				return $offered[ $base ];
			}

			// A request for `de` is satisfied by `de-DE` if that is all
			// there is.
			foreach ( $offered as $key => $locale ) {
				if ( str_starts_with( $key, $base . '-' ) ) {
					return $locale;
				}
			}
		}

		return $fallback;
	}

	/**
	 * Parse an `Accept-Language` header into locales, best first.
	 *
	 * @since 1.0.0
	 *
	 * @param string $header Raw header value.
	 *
	 * @return string[]
	 */
	public static function parse( string $header ): array {
		$weighted = array();

		foreach ( explode( ',', $header ) as $index => $part ) {
			$bits   = explode( ';', trim( $part ) );
			$locale = self::normalise( trim( $bits[0] ) );

			if ( '' === $locale || '*' === $locale ) {
				continue;
			}

			$quality = 1.0;

			foreach ( array_slice( $bits, 1 ) as $parameter ) {
				if ( 1 === preg_match( '/^\s*q\s*=\s*([0-9.]+)/i', $parameter, $m ) ) {
					$quality = (float) $m[1];
				}
			}

			// Index breaks ties, so the header's own order is preserved
			// among equal weights — sorting on quality alone reorders them
			// arbitrarily.
			$weighted[] = array( 'locale' => $locale, 'q' => $quality, 'i' => $index );
		}

		usort(
			$weighted,
			static fn( array $a, array $b ): int => $b['q'] <=> $a['q'] ?: $a['i'] <=> $b['i']
		);

		return array_column( $weighted, 'locale' );
	}

	/**
	 * Normalise a locale tag to `language-REGION` form.
	 *
	 * @since 1.0.0
	 *
	 * @param string $locale Raw tag, `en_GB` or `en-gb`.
	 *
	 * @return string
	 */
	public static function normalise( string $locale ): string {
		$locale = str_replace( '_', '-', trim( $locale ) );

		if ( 1 !== preg_match( '/^([A-Za-z]{2,3})(?:-([A-Za-z]{2}|[0-9]{3}))?$/', $locale, $m ) ) {
			return '';
		}

		return isset( $m[2] ) ? strtolower( $m[1] ) . '-' . strtoupper( $m[2] ) : strtolower( $m[1] );
	}

	/**
	 * The language part of a locale.
	 *
	 * @since 1.0.0
	 *
	 * @param string $locale Locale tag.
	 *
	 * @return string
	 */
	public static function language( string $locale ): string {
		return explode( '-', self::normalise( $locale ) )[0];
	}

	/**
	 * Whether a language is written right to left.
	 *
	 * Needed for `dir="rtl"` on the document — without it an Arabic or
	 * Hebrew page renders left-aligned and reads as broken even when
	 * every string is translated.
	 *
	 * @since 1.0.0
	 *
	 * @param string $locale Locale tag.
	 *
	 * @return bool
	 */
	public static function is_rtl( string $locale ): bool {
		return in_array(
			self::language( $locale ),
			array( 'ar', 'he', 'fa', 'ur', 'ps', 'sd', 'yi', 'dv' ),
			true
		);
	}
}
