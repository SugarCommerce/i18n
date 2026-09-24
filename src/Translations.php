<?php
/**
 * Catalogue-backed translator.
 *
 * @package   SugarCommerce\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\I18n;

/**
 * Class Translations
 *
 * The default {@see Translator}: looks a phrase up in a {@see Catalogue},
 * falls back to the English source, and interpolates named placeholders.
 *
 * @since 1.0.0
 */
final readonly class Translations implements Translator {

	/**
	 * @since 1.0.0
	 *
	 * @param Catalogue     $catalogue Translations for the active locale.
	 * @param callable|null $missing   Called with the source text whenever
	 *                                 a translation is absent. Wire it to a
	 *                                 log in development to find the gaps;
	 *                                 leave it null in production.
	 */
	public function __construct(
		private Catalogue $catalogue,
		private mixed $missing = null,
	) {}

	/**
	 * Build from a directory of catalogues.
	 *
	 * @since 1.0.0
	 *
	 * @param string        $directory Catalogue directory.
	 * @param string        $locale    Locale tag.
	 * @param callable|null $missing   Missing-translation callback.
	 *
	 * @return self
	 */
	public static function for( string $directory, string $locale, ?callable $missing = null ): self {
		return new self( Catalogue::load( $directory, $locale ), $missing );
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function get( string $text, array $replacements = array() ): string {
		$found = $this->catalogue->lookup( $text );

		if ( is_array( $found ) ) {
			// A plural entry reached through the singular accessor: take
			// the "other" form rather than printing an array.
			$found = $found['other'] ?? $found['one'] ?? null;
		}

		if ( null === $found || '' === $found ) {
			$this->report( $text );

			$found = $text;
		}

		return self::interpolate( $found, $replacements );
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function plural( string $singular, string $plural, int $count, array $replacements = array() ): string {
		$replacements = array_merge( array( 'count' => (string) $count ), $replacements );
		$found        = $this->catalogue->lookup( $singular );
		$category     = Plural::category( $count, $this->catalogue->locale );

		if ( is_array( $found ) ) {
			// Fall back through the categories rather than to the English:
			// a language whose catalogue only fills `one` and `other` is
			// still better served by `other` than by the source text.
			$form = $found[ $category ] ?? $found['other'] ?? $found['many'] ?? $found['one'] ?? null;

			if ( null !== $form && '' !== $form ) {
				return self::interpolate( $form, $replacements );
			}
		}

		$this->report( $singular );

		// English source: two forms, and everything but exactly one is plural.
		return self::interpolate( 1 === abs( $count ) ? $singular : $plural, $replacements );
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function locale(): string {
		return $this->catalogue->locale;
	}

	/**
	 * Replace `{name}` placeholders.
	 *
	 * Named rather than positional, because a translator frequently has to
	 * reorder a sentence and `%s ... %s` gives them no way to know which
	 * is which.
	 *
	 * @since 1.0.0
	 *
	 * @param string                $text         Text with placeholders.
	 * @param array<string, string> $replacements Values.
	 *
	 * @return string
	 */
	public static function interpolate( string $text, array $replacements ): string {
		if ( array() === $replacements ) {
			return $text;
		}

		$pairs = array();

		foreach ( $replacements as $name => $value ) {
			$pairs[ '{' . $name . '}' ] = (string) $value;
		}

		return strtr( $text, $pairs );
	}

	/**
	 * Hand a missing translation to the callback, if one is set.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Source text.
	 *
	 * @return void
	 */
	private function report( string $text ): void {
		if ( null !== $this->missing ) {
			( $this->missing )( $text, $this->catalogue->locale );
		}
	}
}
