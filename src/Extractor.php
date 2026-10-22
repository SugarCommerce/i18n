<?php
/**
 * Finding the strings code asks to have translated.
 *
 * @package   SugarCommerce\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.1.0
 */

declare( strict_types=1 );

namespace SugarCommerce\I18n;

/**
 * Class Extractor
 *
 * Reads `__( '…' )`, `__html( '…' )` and `_n( '…', '…', … )` — or whatever
 * the helpers are called — out of source files.
 *
 * Missing translations are silent at runtime by design: a gap prints the
 * source text rather than breaking a page. So something has to look, and
 * the script a translator runs and the test that fails the build should
 * look with the same eyes, or they will disagree about what is missing.
 *
 * A regular expression rather than a parser, because templates are not PHP
 * until they are compiled. It finds literal strings only: a call made with a
 * variable cannot be checked, which is one more reason to pass literals.
 * Comments are removed first, so a docblock showing how to call `__()` is
 * not a string anybody needs translated.
 *
 * @since 1.1.0
 */
final readonly class Extractor {

	/**
	 * A quoted PHP string literal, single or double.
	 *
	 * Each alternative consumes a different first character, so the
	 * pattern cannot backtrack catastrophically on an unclosed quote.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	private const string LITERAL = '(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")';

	/**
	 * An extractor for some helper names and file extensions.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string> $singular   Helpers whose first argument is a
	 *                                  sentence.
	 * @param array<string> $plural     Helpers whose first two arguments
	 *                                  are a singular and its plural.
	 * @param array<string> $extensions File extensions to read. `blade.php`
	 *                                  files are matched by `php`.
	 */
	public function __construct(
		private array $singular = array( '__', '__html' ),
		private array $plural = array( '_n' ),
		private array $extensions = array( 'php' ),
	) {}

	/**
	 * Every translatable string under some directories.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string> $roots Directories to read, recursively. One
	 *                             that does not exist is skipped.
	 *
	 * @return array<string, array{plural: string|null, files: list<string>}>
	 *         Keyed by the source string, sorted.
	 */
	public function scan( array $roots ): array {
		$found = array();

		foreach ( $roots as $root ) {
			foreach ( $this->files( $root ) as $file ) {
				foreach ( $this->in( self::source( $file ) ) as $text => $plural ) {
					$found[ $text ]['plural']  = $plural ?? ( $found[ $text ]['plural'] ?? null );
					$found[ $text ]['files'][] = $file;
				}
			}
		}

		ksort( $found );

		return $found;
	}

	/**
	 * The translatable strings in one piece of source.
	 *
	 * @since 1.1.0
	 *
	 * @param string $source PHP or template source, comments removed.
	 *
	 * @return array<string, string|null> Source string => its plural, or
	 *                                    null for a string without one.
	 */
	public function in( string $source ): array {
		$found = array();

		// Not preceded by a word character, `$`, `>` or `:` — so `$x->__(`,
		// `Foo::__(` and `my__(` are not mistaken for the helpers.
		$guard = '(?<![\w$>:])';

		if ( array() !== $this->singular ) {
			// A failed match (a literal past the backtrack limit) finds
			// nothing rather than leaving $singles without its groups.
			if ( false === preg_match_all( '/' . $guard . self::names( $this->singular ) . '\(\s*' . self::LITERAL . '/', $source, $singles ) ) {
				$singles = array( 1 => array() );
			}

			foreach ( $singles[1] as $literal ) {
				$found[ self::unquote( $literal ) ] ??= null;
			}
		}

		if ( array() !== $this->plural ) {
			if ( false === preg_match_all( '/' . $guard . self::names( $this->plural ) . '\(\s*' . self::LITERAL . '\s*,\s*' . self::LITERAL . '/', $source, $plurals, PREG_SET_ORDER ) ) {
				$plurals = array();
			}

			foreach ( $plurals as $match ) {
				$found[ self::unquote( $match[1] ) ] = self::unquote( $match[2] );
			}
		}

		return $found;
	}

	/**
	 * A file's source with its comments taken out.
	 *
	 * PHP comments through the tokenizer, so a `//` inside a string is left
	 * alone; Blade's `{{-- --}}` by pattern, since a template is not PHP yet.
	 *
	 * @since 1.1.0
	 *
	 * @param string $file Path.
	 *
	 * @return string '' for a file that cannot be read.
	 */
	public static function source( string $file ): string {
		$source = is_file( $file ) ? (string) file_get_contents( $file ) : '';

		if ( str_ends_with( $file, '.blade.php' ) ) {
			return (string) preg_replace( '/\{\{--.*?--\}\}/s', '', $source );
		}

		$kept = '';

		foreach ( token_get_all( $source ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$kept .= is_array( $token ) ? $token[1] : $token;
		}

		return $kept;
	}

	/**
	 * The files under a directory with one of the extensions, sorted.
	 *
	 * @since 1.1.0
	 *
	 * @param string $root Directory.
	 *
	 * @return list<string>
	 */
	private function files( string $root ): array {
		if ( ! is_dir( $root ) ) {
			return array();
		}

		$files = array();

		// Symlinked directories are not followed (no FOLLOW_SYMLINKS), so a
		// link back up the tree cannot loop, and one pointing elsewhere on
		// the machine is not read; a directory that cannot be opened is
		// skipped rather than ending the scan.
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY,
			\RecursiveIteratorIterator::CATCH_GET_CHILD
		);

		foreach ( $iterator as $file ) {
			if ( $file instanceof \SplFileInfo && $file->isFile() && in_array( $file->getExtension(), $this->extensions, true ) ) {
				$files[] = $file->getPathname();
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * An alternation of helper names, quoted for a pattern.
	 *
	 * Longest first, so `__html` is tried before `__`.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string> $names Function names.
	 *
	 * @return string
	 */
	private static function names( array $names ): string {
		usort( $names, static fn ( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );

		return '(?:' . implode( '|', array_map( static fn ( string $name ): string => preg_quote( $name, '/' ), $names ) ) . ')';
	}

	/**
	 * The value of a PHP string literal.
	 *
	 * @since 1.1.0
	 *
	 * @param string $literal Quoted, as written.
	 *
	 * @return string
	 */
	private static function unquote( string $literal ): string {
		$body = substr( $literal, 1, -1 );

		return "'" === $literal[0]
			? strtr( $body, array( '\\\\' => '\\', "\\'" => "'" ) )
			: stripcslashes( $body );
	}
}
