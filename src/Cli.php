<?php
/**
 * The catalogue report, for a terminal or a build.
 *
 * @package   SugarCommerce\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.1.0
 */

declare( strict_types=1 );

namespace SugarCommerce\I18n;

/**
 * Class Cli
 *
 * What `vendor/bin/i18n-strings` runs, and what an application's own script
 * can call with its directories filled in:
 *
 *     exit( Cli::run( $argv, __DIR__ . '/../lang', [ __DIR__ . '/../src' ] ) );
 *
 * Arguments are locales to check (every catalogue when none) and flags:
 *
 *   --add   append strings new in the code to the source catalogue, mapped
 *           to themselves, under a heading that says they are new
 *   --json  machine-readable
 *
 * Returns 1 when anything is missing, so it can gate a build. A missing
 * translation prints the source text at runtime rather than failing, which
 * is right for a page and wrong for finding gaps; this is what finds them.
 *
 * @since 1.1.0
 */
final readonly class Cli {

	/**
	 * Run the report.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string>  $argv      As PHP gives it: the script name
	 *                                  first.
	 * @param string         $directory Catalogue directory.
	 * @param array<string>  $roots     Directories whose code is scanned.
	 * @param string         $source    The source locale.
	 * @param resource       $out       Where the report goes.
	 * @param Extractor|null $extractor How to find strings; the default
	 *                                  helpers when null.
	 *
	 * @return int Exit code: 1 when anything is missing.
	 */
	public static function run(
		array $argv,
		string $directory,
		array $roots,
		string $source = 'en',
		mixed $out = STDOUT,
		?Extractor $extractor = null,
	): int {
		$arguments = array_map( 'strval', array_slice( $argv, 1 ) );
		$flags     = array_values( array_filter( $arguments, static fn ( string $arg ): bool => str_starts_with( $arg, '--' ) ) );
		$locales   = array_values( array_diff( $arguments, $flags ) );
		$add       = in_array( '--add', $flags, true );

		$code   = ( $extractor ?? new Extractor() )->scan( $roots );
		$report = Diff::report( $code, $directory, $source, $locales );

		if ( $add && $report[ $source ]['missing'] ) {
			self::add( rtrim( $directory, '/' ) . '/' . $source . '.php', $report[ $source ]['missing'], $code );
		}

		if ( in_array( '--json', $flags, true ) ) {
			fwrite( $out, json_encode( array( 'strings' => count( $code ), 'catalogues' => $report ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
		} else {
			fwrite( $out, self::render( count( $code ), $report, $source, $roots, $add ) );
		}

		return Diff::has_missing( $report ) ? 1 : 0;
	}

	/**
	 * The report, for a person.
	 *
	 * Strings are read out of code that may not be the reader's own — a
	 * pull request run in CI — so control characters in them are shown
	 * as `\x1B` rather than written to the terminal, where an escape
	 * sequence could rewrite what the report appears to say.
	 *
	 * @since 1.1.0
	 *
	 * @param int                                                               $strings How many strings the code has.
	 * @param array<string, array{missing: list<string>, unused: list<string>}> $report  From Diff::report().
	 * @param string                                                            $source  The source locale.
	 * @param array<string>                                                     $roots   What was scanned.
	 * @param bool                                                              $added   Whether new strings were just added.
	 *
	 * @return string
	 */
	public static function render( int $strings, array $report, string $source, array $roots, bool $added = false ): string {
		$text = sprintf( "%d translatable strings in %s.\n", $strings, self::places( $roots ) );

		foreach ( $report as $locale => $found ) {
			$text .= ( $source === $locale
				? sprintf( "\n%s (source): %d new in the code%s, %d no longer used", $locale, count( $found['missing'] ), $added && $found['missing'] ? ' — added' : '', count( $found['unused'] ) )
				: sprintf( "\n%s: %d to translate, %d no longer used", $locale, count( $found['missing'] ), count( $found['unused'] ) ) ) . "\n";

			foreach ( $found['missing'] as $key ) {
				$text .= '  + ' . self::printable( $key ) . "\n";
			}

			foreach ( $found['unused'] as $key ) {
				$text .= '  - ' . self::printable( $key ) . "\n";
			}
		}

		return $text;
	}

	/**
	 * Append new source strings to the source catalogue, mapped to
	 * themselves.
	 *
	 * Before the closing `);`, under a heading that says they are new, so
	 * the file's existing order and comments are left alone and the
	 * additions are easy to find, review and move.
	 *
	 * Each string is written as a single-quoted literal with its `\\` and
	 * `'` escaped, so whatever a scanned string contains it stays a string
	 * in the catalogue and never becomes code in it.
	 *
	 * @since 1.1.0
	 *
	 * @param string                                                         $file Path to the source catalogue.
	 * @param array<string>                                                  $keys The new strings.
	 * @param array<string, array{plural: string|null, files: list<string>}> $code What Extractor::scan() found, for
	 *                                                                             each plural.
	 *
	 * @return bool False when the file has no `);` to add before.
	 */
	public static function add( string $file, array $keys, array $code ): bool {
		$quote = static fn ( string $text ): string => "'" . strtr( $text, array( '\\' => '\\\\', "'" => "\\'" ) ) . "'";
		$lines = "\n\t// ── New: found in the code, not yet translated ─────────────────────\n\n";

		foreach ( $keys as $key ) {
			$plural = $code[ $key ]['plural'] ?? null;

			$lines .= null === $plural
				? "\t" . $quote( $key ) . ' => ' . $quote( $key ) . ",\n"
				: "\t" . $quote( $key ) . " => array(\n\t\t'one'   => " . $quote( $key ) . ",\n\t\t'other' => " . $quote( $plural ) . ",\n\t),\n";
		}

		$contents = is_file( $file ) ? (string) file_get_contents( $file ) : '';
		$at       = strrpos( $contents, ');' );

		if ( false === $at ) {
			fwrite( STDERR, "Could not find the end of $file; nothing added.\n" );

			return false;
		}

		file_put_contents( $file, substr( $contents, 0, $at ) . $lines . substr( $contents, $at ) );

		return true;
	}

	/**
	 * A string with its control characters made visible.
	 *
	 * C0, DEL and C1 become `\xNN`; everything else, including any
	 * printable Unicode, is left as it is.
	 *
	 * @since 1.2.0
	 *
	 * @param string $text Text bound for a terminal.
	 *
	 * @return string
	 */
	private static function printable( string $text ): string {
		return (string) preg_replace_callback(
			'/[\x00-\x1F\x7F]|\xC2[\x80-\x9F]/',
			static fn ( array $match ): string => implode( '', array_map( static fn ( string $byte ): string => sprintf( '\\x%02X', ord( $byte ) ), str_split( $match[0] ) ) ),
			$text
		);
	}

	/**
	 * The scanned directories as a sentence: "src/ and templates/".
	 *
	 * @since 1.1.0
	 *
	 * @param array<string> $roots Directories.
	 *
	 * @return string
	 */
	private static function places( array $roots ): string {
		$names = array_map( static fn ( string $root ): string => basename( rtrim( $root, '/' ) ) . '/', array_values( $roots ) );

		if ( count( $names ) < 2 ) {
			return $names[0] ?? 'nothing';
		}

		$last = array_pop( $names );

		return implode( ', ', $names ) . ' and ' . $last;
	}
}
