<?php
/**
 * What each catalogue is missing, and what it no longer needs.
 *
 * @package   SugarCommerce\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.1.0
 */

declare( strict_types=1 );

namespace SugarCommerce\I18n;

/**
 * Class Diff
 *
 * Compares in two steps, because that is the order a string travels:
 *
 *   - the code with the source catalogue — a string wrapped in the code and
 *     not listed there is new, and every other catalogue is checked against
 *     the source catalogue, so it has to go there first;
 *   - the source catalogue with each translation — what a translator still
 *     has to do, and what was translated for a string the code no longer
 *     has.
 *
 * @since 1.1.0
 */
final readonly class Diff {

	/**
	 * Keys one list has and the other does not.
	 *
	 * @since 1.1.0
	 *
	 * @param array<array-key, mixed> $wanted What should be there, keyed by
	 *                                        string.
	 * @param array<array-key, mixed> $have   What is there, keyed by string.
	 *
	 * @return list<string>
	 */
	public static function missing( array $wanted, array $have ): array {
		// strval: PHP keys `'404'` as the integer 404.
		return array_map( 'strval', array_keys( array_diff_key( $wanted, $have ) ) );
	}

	/**
	 * The whole report: the source against the code, each translation
	 * against the source.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, array{plural: string|null, files: list<string>}> $code      What Extractor::scan() found.
	 * @param string                                                         $directory Catalogue directory.
	 * @param string                                                         $source    The source locale.
	 * @param array<string>                                                  $locales   Translations to check. Empty
	 *                                                                                  for every catalogue in the
	 *                                                                                  directory.
	 *
	 * @return array<string, array{missing: list<string>, unused: list<string>}>
	 *         Keyed by locale, the source first.
	 */
	public static function report( array $code, string $directory, string $source, array $locales = array() ): array {
		$english = Catalogue::file( $directory, $source );
		$report  = array(
			$source => array(
				'missing' => self::missing( $code, $english ),
				'unused'  => self::missing( $english, $code ),
			),
		);

		foreach ( array_diff( $locales ?: Catalogue::locales( $directory, $source ), array( $source ) ) as $locale ) {
			$catalogue = Catalogue::file( $directory, $locale );

			$report[ $locale ] = array(
				'missing' => self::missing( $english, $catalogue ),
				'unused'  => self::missing( $catalogue, $english ),
			);
		}

		return $report;
	}

	/**
	 * Whether a report has anything left to do.
	 *
	 * Unused entries do not count: they are tidying, not a gap a reader
	 * would see.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, array{missing: list<string>, unused: list<string>}> $report From report().
	 *
	 * @return bool
	 */
	public static function has_missing( array $report ): bool {
		return array() !== array_filter( array_column( $report, 'missing' ) );
	}
}
