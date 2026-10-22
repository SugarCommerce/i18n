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
 * and cost nothing to load. The other side of that is that loading one
 * *runs* it: a catalogue directory is code, and must never be somewhere
 * a visitor, an upload or a translation service can write to. The file
 * name is the one thing taken from outside, and it is only ever built
 * from a tag that has been checked to be a tag — never `../` or a path.
 *
 * @since 1.0.0
 */
final readonly class Catalogue {

	/**
	 * A locale tag as a catalogue file may be named: letters, digits, `-`
	 * and `_`. Anything else — a dot, a slash, a NUL — is not a locale.
	 */
	private const string FILE_NAME = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,34}$/D';

	/**
	 * A catalogue for one locale.
	 *
	 * @since 1.0.0
	 *
	 * @param string                                      $locale  Locale tag.
	 * @param array<string, string|array<string, string>> $entries Source => translation.
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

			$entries = array_replace( $entries, self::read( $file ) );
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

		// scandir() rather than glob(), which would read `[`, `*` or `?` in
		// the directory's own path as a pattern.
		$names = is_dir( $directory ) ? scandir( $directory ) : false;

		foreach ( false === $names ? array() : $names as $name ) {
			if ( ! str_ends_with( $name, '.php' ) ) {
				continue;
			}

			$locale = Locale::normalise( substr( $name, 0, -4 ) );

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
		// A tag with a separator, a dot or a NUL in it is a path, not a
		// locale.
		$file = 1 === preg_match( self::FILE_NAME, $locale ) ? self::path( $directory, $locale ) : null;

		return null === $file ? array() : self::read( $file );
	}

	/**
	 * A catalogue file's entries, keeping only what a catalogue can hold.
	 *
	 * A source string mapped to a string, or to an array of CLDR category
	 * => string. Anything else — a number, a nested array, a list with no
	 * categories, a file that returns nothing — is dropped here rather than reaching the
	 * translator as a value it would print as "Array" or throw on.
	 *
	 * Required inside a static closure, so the file sees no variables of
	 * this class's and cannot change any.
	 *
	 * @since 1.2.0
	 *
	 * @param string $file Path to a catalogue file.
	 *
	 * @return array<string, string|array<string, string>>
	 */
	private static function read( string $file ): array {
		$loaded = ( static fn ( string $__file ): mixed => require $__file )( $file );

		if ( ! is_array( $loaded ) ) {
			return array();
		}

		$entries = array();

		foreach ( $loaded as $source => $translation ) {
			// PHP keys `'404'` as the integer 404; it is still a string
			// somebody wrote, and lookup() still finds it.
			$source = (string) $source;

			if ( is_string( $translation ) ) {
				$entries[ $source ] = $translation;

				continue;
			}

			$forms = is_array( $translation ) ? array_filter(
				$translation,
				static fn ( mixed $form, int|string $category ): bool => is_string( $category ) && is_string( $form ),
				ARRAY_FILTER_USE_BOTH
			) : array();

			if ( array() !== $forms ) {
				$entries[ $source ] = $forms;
			}
		}

		return $entries;
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
	 * @param string $locale    Locale tag, already checked to be one.
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
