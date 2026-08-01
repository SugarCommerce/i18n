<?php
/**
 * Plural categories.
 *
 * @package   SugarMill\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\I18n;

/**
 * Class Plural
 *
 * Picks the CLDR plural category for a count in a language.
 *
 * English has two forms and most developers assume that is universal. It
 * is not, and the assumption is why translated interfaces so often read
 * as broken:
 *
 *   Japanese   one form   — no distinction at all
 *   French     two        — but 0 takes the singular
 *   Russian    four       — 1, 2-4, 5+, and fractions
 *   Polish     four       — different boundaries again
 *   Arabic     six
 *
 * Only the categories a language actually uses need translating; asking
 * a translator for "the plural" is the mistake.
 *
 * @since 1.0.0
 */
final readonly class Plural {

	/**
	 * The category for a count.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $count  How many.
	 * @param string $locale Language code; the region is ignored.
	 *
	 * @return string One of `zero`, `one`, `two`, `few`, `many`, `other`.
	 */
	public static function category( int $count, string $locale ): string {
		$language = strtolower( explode( '-', str_replace( '_', '-', trim( $locale ) ) )[0] );
		$n        = abs( $count );

		return match ( $language ) {
			// No plural distinction whatsoever.
			'ja', 'zh', 'ko', 'th', 'vi', 'id', 'ms' => 'other',

			// Zero takes the singular form.
			'fr', 'pt', 'hi' => $n <= 1 ? 'one' : 'other',

			// East Slavic: 1, 2-4, and 5+, with the teens as an exception.
			'ru', 'uk', 'be' => self::east_slavic( $n ),

			// Polish differs from Russian in where "few" ends.
			'pl' => self::polish( $n ),

			'cs', 'sk' => match ( true ) {
				1 === $n            => 'one',
				$n >= 2 && $n <= 4  => 'few',
				default             => 'other',
			},

			'ar' => match ( true ) {
				0 === $n                          => 'zero',
				1 === $n                          => 'one',
				2 === $n                          => 'two',
				$n % 100 >= 3 && $n % 100 <= 10   => 'few',
				$n % 100 >= 11                    => 'many',
				default                           => 'other',
			},

			// Two forms, zero taking the plural. English and most of
			// Germanic and Romance.
			default => 1 === $n ? 'one' : 'other',
		};
	}

	/**
	 * Russian, Ukrainian, Belarusian.
	 *
	 * @since 1.0.0
	 *
	 * @param int $n Absolute count.
	 *
	 * @return string
	 */
	private static function east_slavic( int $n ): string {
		$tens = $n % 100;

		// 11-14 are "many" despite ending in 1-4.
		if ( $tens >= 11 && $tens <= 14 ) {
			return 'many';
		}

		return match ( true ) {
			1 === $n % 10                    => 'one',
			$n % 10 >= 2 && $n % 10 <= 4     => 'few',
			default                          => 'many',
		};
	}

	/**
	 * Polish.
	 *
	 * @since 1.0.0
	 *
	 * @param int $n Absolute count.
	 *
	 * @return string
	 */
	private static function polish( int $n ): string {
		if ( 1 === $n ) {
			return 'one';
		}

		$tens = $n % 100;

		if ( $tens >= 12 && $tens <= 14 ) {
			return 'many';
		}

		return $n % 10 >= 2 && $n % 10 <= 4 ? 'few' : 'many';
	}

	/**
	 * The categories a language uses, for a translator UI.
	 *
	 * @since 1.0.0
	 *
	 * @param string $locale Language code.
	 *
	 * @return string[]
	 */
	public static function categories( string $locale ): array {
		$language = strtolower( explode( '-', str_replace( '_', '-', trim( $locale ) ) )[0] );

		return match ( $language ) {
			'ja', 'zh', 'ko', 'th', 'vi', 'id', 'ms' => array( 'other' ),
			'ru', 'uk', 'be', 'pl'                   => array( 'one', 'few', 'many' ),
			'cs', 'sk'                               => array( 'one', 'few', 'other' ),
			'ar'                                     => array( 'zero', 'one', 'two', 'few', 'many', 'other' ),
			default                                  => array( 'one', 'other' ),
		};
	}
}
