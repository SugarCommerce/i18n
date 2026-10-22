<?php
/**
 * Translation helpers for templates, emails and messages.
 *
 * Opt-in: load them with Current::helpers(). Each is defined only when the
 * name is free — WordPress, for one, already has a `__()` — and reads the
 * language from Current.
 *
 * The source text is the key and the fallback, so wrapping a string never
 * changes what a source-language reader sees, and a string nobody has
 * translated yet still prints.
 *
 * @package   SugarCommerce\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.1.0
 */

declare( strict_types=1 );

use SugarCommerce\I18n\{Current, Translations};

if ( ! function_exists( '__' ) ) {
	/**
	 * Translate a sentence.
	 *
	 * Plain text in, plain text out: escape it like any other value.
	 * Placeholders are named — `{seller}`, not `%s` — because a translator
	 * often has to move them, and a name says which is which.
	 *
	 * @since 1.1.0
	 *
	 * @param string                    $text         Source text.
	 * @param array<string, string|int> $replacements Values for its placeholders.
	 *
	 * @return string
	 */
	function __( string $text, array $replacements = array() ): string {
		return Current::translator()->get( $text, array_map( 'strval', $replacements ) );
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * Translate a sentence with a count in it, in the right plural form.
	 *
	 * `{count}` is filled in. The catalogue gives a form per CLDR category
	 * the language uses — one for Japanese, three for Polish — so never
	 * build a plural by appending an "s".
	 *
	 * @since 1.1.0
	 *
	 * @param string                    $singular     Source, for exactly one.
	 * @param string                    $plural       Source, for any other count.
	 * @param int                       $count        How many.
	 * @param array<string, string|int> $replacements Other placeholders.
	 *
	 * @return string
	 */
	function _n( string $singular, string $plural, int $count, array $replacements = array() ): string {
		return Current::translator()->plural( $singular, $plural, $count, array_map( 'strval', $replacements ) );
	}
}

if ( ! function_exists( '__html' ) ) {
	/**
	 * Translate a sentence that has markup inside it, such as a link.
	 *
	 * The translated text is escaped first — a malformed byte in it becomes
	 * U+FFFD rather than emptying the sentence — and the replacements go in
	 * after, as given — so a sentence can carry `{link}…{/link}` round the
	 * words a translator chooses to link, without being cut in two around
	 * them. Every replacement is HTML: escape any value of your own before
	 * passing it.
	 *
	 * @since 1.1.0
	 *
	 * @param string                $text Source text.
	 * @param array<string, string> $html Placeholder => HTML.
	 *
	 * @return string HTML.
	 */
	function __html( string $text, array $html = array() ): string {
		return Translations::interpolate(
			htmlspecialchars( Current::translator()->get( $text ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
			$html
		);
	}
}
