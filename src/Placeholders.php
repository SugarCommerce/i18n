<?php
/**
 * The named placeholders in a sentence.
 *
 * @package   SugarCommerce\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.1.0
 */

declare( strict_types=1 );

namespace SugarCommerce\I18n;

/**
 * Class Placeholders
 *
 * A translation that drops `{reference}` prints a sentence with a hole in
 * it; one that misspells it as `{referenz}` prints the braces. Neither
 * fails at runtime — interpolation leaves an unknown name alone — so the
 * check belongs in a test that compares every translation with its source.
 *
 * Closing markers such as `{/link}` count too: a translation that opens a
 * link and never closes it links the rest of the page.
 *
 * @since 1.1.0
 */
final readonly class Placeholders {

	/**
	 * The `{name}` and `{/name}` placeholders in a string, sorted and unique.
	 *
	 * @since 1.1.0
	 *
	 * @param string $text Text.
	 *
	 * @return string[] Names without braces; a closer keeps its slash.
	 */
	public static function of( string $text ): array {
		preg_match_all( '/\{(\/?[a-z_]+)\}/', $text, $matches );

		$names = array_values( array_unique( $matches[1] ) );
		sort( $names );

		return $names;
	}

	/**
	 * Whether a translation has exactly the source's placeholders.
	 *
	 * Order is free — moving them is a translator's job — but the set must
	 * be the same.
	 *
	 * @since 1.1.0
	 *
	 * @param string $source     Source text.
	 * @param string $translated Its translation.
	 *
	 * @return bool
	 */
	public static function match( string $source, string $translated ): bool {
		return self::of( $source ) === self::of( $translated );
	}
}
