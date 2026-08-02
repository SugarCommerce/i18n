<?php
/**
 * A locale's translations.
 *
 * @package   Sugar\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace Sugar\I18n;

/**
 * Class Catalogue
 *
 * Translations for one locale, keyed by the English source string.
 *
 * Using the source text as the key rather than an identifier like
 * `checkout.error.sold_out` is a deliberate trade. It means a missing
 * translation degrades to readable English instead of a dotted path
 * leaking onto the page, and it removes a whole class of bug where the
 * key and the English drift apart. The cost is that editing the English
 * orphans the translation — which is the right way round, because
 * changing the wording usually should invalidate the translation.
 *
 * A plural entry is an array keyed by CLDR category:
 *
 *     'one item' => [ 'one' => '1 Artikel', 'other' => '{count} Artikel' ]
 *
 * Catalogues are plain PHP arrays so they are opcached, need no parser,
 * and cost nothing to load.
 *
 * @since 1.0.0
 */
final readonly class Catalogue {

	/**
	 * @since 1.0.0
	 *
	 * @param string                              $locale  Locale tag.
	 * @param array<string, string|array<string,string>> $entries Source => translation.
	 */
	public function __construct(
		public string $locale,
		public array $entries = array(),
	) {}

	/**
	 * Load a catalogue from a directory of `<locale>.php` files.
	 *
	 * Each file returns an array. A missing file is not an error — it
	 * means that locale is untranslated, and everything falls back to the
	 * source text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $directory Directory holding the catalogues.
	 * @param string $locale    Locale tag.
	 *
	 * @return self
	 */
	public static function load( string $directory, string $locale ): self {
		$locale = Locale::normalise( $locale );

		// A region-tagged locale inherits its base language, so `de-AT`
		// only needs a file for what actually differs from `de`.
		$entries = array();

		foreach ( array_unique( array( Locale::language( $locale ), $locale ) ) as $candidate ) {
			$file = rtrim( $directory, '/' ) . '/' . $candidate . '.php';

			if ( '' === $candidate || ! is_file( $file ) ) {
				continue;
			}

			$loaded = require $file;

			if ( is_array( $loaded ) ) {
				$entries = array_replace( $entries, $loaded );
			}
		}

		return new self( $locale, $entries );
	}

	/**
	 * The translation for a source string, or null when absent.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Source text.
	 *
	 * @return string|array<string, string>|null
	 */
	public function lookup( string $text ): string|array|null {
		return $this->entries[ $text ] ?? null;
	}

	/**
	 * Whether anything is translated.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return array() === $this->entries;
	}

	/**
	 * How many entries this holds.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->entries );
	}
}
