<?php
/**
 * A locale's translations.
 *
 * @package   SugarCommerce\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\I18n;

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
			$file = '' === $candidate ? null : self::path( $directory, $candidate );

			if ( null === $file ) {
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
	 * Every locale with a catalogue in a directory, the source first.
	 *
	 * Read from the directory rather than listed in code, so adding a
	 * language is adding a file — a list kept beside the files is a list
	 * that one day names a language nobody shipped, or misses the one
	 * somebody just did.
	 *
	 * The source comes first whether or not it has a file of its own: it
	 * is always available, because it is what the code is written in.
	 *
	 * @since 1.1.0
	 *
	 * @param string $directory Directory holding the catalogues.
	 * @param string $source    The language the source strings are in.
	 *
	 * @return string[] Normalised tags: the source, then the rest sorted.
	 */
	public static function locales( string $directory, string $source = 'en' ): array {
		$source = Locale::normalise( $source );
		$found  = array();

		foreach ( glob( rtrim( $directory, '/' ) . '/*.php' ) ?: array() as $file ) {
			$locale = Locale::normalise( basename( $file, '.php' ) );

			if ( '' !== $locale && $source !== $locale ) {
				$found[] = $locale;
			}
		}

		$found = array_values( array_unique( $found ) );
		sort( $found );

		return array_merge( array( $source ), $found );
	}

	/**
	 * One catalogue file's entries, exactly as written.
	 *
	 * Unlike load(), a regional file is not folded into its language's:
	 * the question here is what this one file has — which is what a script
	 * looking for gaps, or a test comparing two catalogues, is asking.
	 *
	 * @since 1.1.0
	 *
	 * @param string $directory Directory holding the catalogues.
	 * @param string $locale    Locale tag, as the file is named.
	 *
	 * @return array<string, string|array<string, string>> Empty for a
	 *                                                     missing file.
	 */
	public static function file( string $directory, string $locale ): array {
		// A tag with a separator in it is a path, not a locale.
		$file = '' === trim( $locale ) || str_contains( $locale, '/' ) || str_contains( $locale, '\\' )
			? null
			: self::path( $directory, $locale );

		if ( null === $file ) {
			return array();
		}

		$entries = require $file;

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * The file holding a locale's catalogue, or null.
	 *
	 * `pt-BR.php` or `pt_BR.php`: the second is how gettext and ICU spell
	 * it, and a directory listed by locales() must be loadable by load(),
	 * whichever spelling its files use.
	 *
	 * @since 1.1.0
	 *
	 * @param string $directory Directory holding the catalogues.
	 * @param string $locale    Locale tag.
	 *
	 * @return string|null
	 */
	private static function path( string $directory, string $locale ): ?string {
		foreach ( array_unique( array( $locale, Locale::icu( $locale ) ) ) as $name ) {
			$file = rtrim( $directory, '/' ) . '/' . $name . '.php';

			if ( is_file( $file ) ) {
				return $file;
			}
		}

		return null;
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
