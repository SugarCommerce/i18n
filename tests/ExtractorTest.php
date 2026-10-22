<?php
/**
 * Finding strings, comparing catalogues, and the report that does both.
 *
 * @package   SugarCommerce\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.1.0
 */

declare( strict_types=1 );

namespace SugarCommerce\I18n\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SugarCommerce\I18n\{Cli, Diff, Extractor, Placeholders};

#[CoversClass( Extractor::class )]
#[CoversClass( Placeholders::class )]
#[CoversClass( Diff::class )]
#[CoversClass( Cli::class )]
final class ExtractorTest extends TestCase {

	private const string LANG = __DIR__ . '/fixtures/lang';
	private const string CODE = __DIR__ . '/fixtures/code';

	/**
	 * What the fixture code asks to have translated.
	 *
	 * @return array<string, array{plural: string|null, files: string[]}>
	 */
	private static function code(): array {
		return ( new Extractor() )->scan( array( self::CODE ) );
	}

	/* ─── Extracting ────────────────────────────────────────────────── */

	public function test_every_literal_passed_to_a_helper_is_found(): void {
		$this->assertSame(
			array( "It's here", 'Only in the template', 'Order {reference}', 'Read the {link}terms{/link}.', '{count} file' ),
			array_keys( self::code() )
		);
	}

	public function test_a_plural_carries_its_plural_form(): void {
		$code = self::code();

		$this->assertSame( '{count} files', $code['{count} file']['plural'] );
		$this->assertNull( $code['Order {reference}']['plural'] );
	}

	/**
	 * Found in two files: the list says where, so a translator can see
	 * the sentence in context.
	 */
	public function test_each_string_lists_the_files_it_is_in(): void {
		$this->assertCount( 2, self::code()['Order {reference}']['files'] );
	}

	/**
	 * Comments, Blade comments, methods, static calls, look-alike names
	 * and variables are not strings to translate.
	 */
	public function test_what_only_looks_like_a_call_is_ignored(): void {
		$code = self::code();

		foreach ( array( 'Not a real string', 'Commented out', 'A method', 'A static method', "Somebody else's helper", 'A Blade comment' ) as $text ) {
			$this->assertArrayNotHasKey( $text, $code, $text );
		}
	}

	public function test_other_helper_names_can_be_read(): void {
		$found = ( new Extractor( array( 't' ), array( 'tn' ) ) )->in( "t( 'One' ); tn( 'A cat', 'Cats', 2 ); __( 'Not ours' );" );

		$this->assertSame( array( 'One' => null, 'A cat' => 'Cats' ), $found );
	}

	public function test_escapes_are_read_the_way_php_reads_them(): void {
		$found = ( new Extractor() )->in( <<<'PHP'
			__( 'It\'s a back\\slash' ); __( "Tab\there" );
			PHP );

		$this->assertSame( array( "It's a back\\slash", "Tab\there" ), array_keys( $found ) );
	}

	public function test_a_missing_directory_has_nothing_in_it(): void {
		$this->assertSame( array(), ( new Extractor() )->scan( array( __DIR__ . '/nowhere' ) ) );
	}

	/* ─── Placeholders ──────────────────────────────────────────────── */

	public function test_placeholders_are_listed_sorted_and_once(): void {
		$this->assertSame( array( '/link', 'link', 'name' ), Placeholders::of( '{name}, read the {link}terms{/link}, {name}.' ) );
		$this->assertSame( array(), Placeholders::of( 'Nothing to fill in.' ) );
	}

	public function test_a_translation_may_move_placeholders_but_not_lose_or_rename_them(): void {
		$this->assertTrue( Placeholders::match( '{a} then {b}', '{b} dann {a}' ) );
		$this->assertFalse( Placeholders::match( 'Order {reference}', 'Bestellung' ) );
		$this->assertFalse( Placeholders::match( 'Order {reference}', 'Bestellung {referenz}' ) );
		$this->assertFalse( Placeholders::match( 'Read the {link}terms{/link}.', 'Lesen Sie die {link}AGB.' ) );
	}

	/* ─── Comparing ─────────────────────────────────────────────────── */

	public function test_missing_keys_are_listed_as_strings(): void {
		$this->assertSame( array( '1', 'b' ), Diff::missing( array( 1 => 'x', 'a' => 1, 'b' => 2 ), array( 'a' => 1 ) ) );
	}

	/**
	 * Two steps, in the order a string travels: code to the source
	 * catalogue, then the source catalogue to each translation.
	 */
	public function test_the_report_compares_code_to_source_and_source_to_each_translation(): void {
		$report = Diff::report( self::code(), self::LANG, 'en' );

		$this->assertSame( array( 'en', 'de', 'pl', 'pt-BR' ), array_keys( $report ) );
		$this->assertSame( array( "It's here", 'Only in the template' ), $report['en']['missing'] );
		$this->assertSame( array( 'Retired sentence.' ), $report['en']['unused'] );
		$this->assertSame( array( 'Read the {link}terms{/link}.' ), $report['de']['missing'] );
		$this->assertSame( array( 'Gone from English.' ), $report['de']['unused'] );
		$this->assertTrue( Diff::has_missing( $report ) );
	}

	public function test_the_report_can_be_narrowed_to_some_locales(): void {
		$this->assertSame( array( 'en', 'pl' ), array_keys( Diff::report( self::code(), self::LANG, 'en', array( 'pl' ) ) ) );
	}

	public function test_a_report_with_only_unused_entries_has_nothing_missing(): void {
		$this->assertFalse( Diff::has_missing( array( 'en' => array( 'missing' => array(), 'unused' => array( 'x' ) ) ) ) );
	}

	/* ─── The report, run ───────────────────────────────────────────── */

	public function test_the_report_reads_as_a_list_and_fails_when_anything_is_missing(): void {
		[ $code, $text ] = self::report( array( 'strings', 'de' ), self::LANG );

		$this->assertSame( 1, $code );
		$this->assertStringStartsWith( "5 translatable strings in code/.\n", $text );
		$this->assertStringContainsString( "\nen (source): 2 new in the code, 1 no longer used\n  + It's here\n", $text );
		$this->assertStringContainsString( "\nde: 1 to translate, 1 no longer used\n  + Read the {link}terms{/link}.\n  - Gone from English.\n", $text );
		$this->assertStringNotContainsString( "\npl:", $text );
	}

	public function test_the_report_can_be_read_by_a_machine(): void {
		[ , $text ] = self::report( array( 'strings', '--json', 'pl' ), self::LANG );

		$json = json_decode( $text, true );

		$this->assertSame( 5, $json['strings'] );
		$this->assertSame( array( 'en', 'pl' ), array_keys( $json['catalogues'] ) );
	}

	/**
	 * New strings go into the source catalogue mapped to themselves —
	 * plurals with both forms — before the closing bracket, so the file
	 * still loads.
	 */
	public function test_new_strings_can_be_added_to_the_source(): void {
		$dir = sys_get_temp_dir() . '/i18n-cli-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir );
		copy( self::LANG . '/en.php', $dir . '/en.php' );

		try {
			[ , $text ] = self::report( array( 'strings', '--add' ), $dir );

			$entries = require $dir . '/en.php';

			$this->assertStringContainsString( '2 new in the code — added', $text );
			$this->assertSame( "It's here", $entries["It's here"] );
			$this->assertSame( 'Only in the template', $entries['Only in the template'] );
			$this->assertSame( 'Order {reference}', $entries['Order {reference}'] );
		} finally {
			array_map( 'unlink', glob( $dir . '/*' ) ?: array() );
			rmdir( $dir );
		}
	}

	public function test_a_complete_set_of_catalogues_passes(): void {
		$dir = sys_get_temp_dir() . '/i18n-cli-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir );
		file_put_contents( $dir . '/en.php', "<?php\nreturn array( 'Hello' => 'Hello' );\n" );
		file_put_contents( $dir . '/de.php', "<?php\nreturn array( 'Hello' => 'Hallo' );\n" );
		mkdir( $dir . '/src' );
		file_put_contents( $dir . '/src/a.php', "<?php\necho __( 'Hello' );\n" );

		try {
			[ $code, $text ] = self::report( array( 'strings' ), $dir, array( $dir . '/src' ) );

			$this->assertSame( 0, $code );
			$this->assertStringContainsString( "de: 0 to translate, 0 no longer used\n", $text );
		} finally {
			unlink( $dir . '/src/a.php' );
			rmdir( $dir . '/src' );
			array_map( 'unlink', glob( $dir . '/*.php' ) ?: array() );
			rmdir( $dir );
		}
	}

	/**
	 * Strings come out of code that may be a stranger's pull request: an
	 * escape sequence in one must not reach the terminal and rewrite the
	 * report.
	 */
	public function test_control_characters_in_a_string_are_shown_not_sent_to_the_terminal(): void {
		$text = Cli::render(
			1,
			array( 'en' => array( 'missing' => array( "Pay\e[2J\e]0;owned\x07 now\u{009B}" ), 'unused' => array() ) ),
			'en',
			array( 'src' )
		);

		$this->assertStringNotContainsString( "\e", $text );
		$this->assertStringNotContainsString( "\x07", $text );
		$this->assertStringContainsString( '  + Pay\x1B[2J\x1B]0;owned\x07 now\xC2\x9B', $text );
	}

	/**
	 * Whatever a scanned string holds, --add writes it as a string: the
	 * catalogue is required as code, so a quote must not end the literal.
	 */
	public function test_an_added_string_cannot_become_code_in_the_catalogue(): void {
		$file = tempnam( sys_get_temp_dir(), 'i18n' );
		file_put_contents( $file, "<?php\nreturn array(\n);\n" );

		$evil = "x' => 'y', (function () { throw new \\Exception( 'ran' ); })(), '\\";

		try {
			Cli::add( $file, array( $evil ), array( $evil => array( 'plural' => null, 'files' => array() ) ) );

			$this->assertSame( array( $evil => $evil ), require $file );
		} finally {
			unlink( $file );
		}
	}

	public function test_several_scanned_directories_are_named_as_a_sentence(): void {
		$this->assertStringStartsWith(
			'0 translatable strings in src/, lib/ and templates/.',
			Cli::render( 0, array(), 'en', array( '/a/src', '/a/lib', '/a/templates/' ) )
		);
	}

	/**
	 * Run the report into memory.
	 *
	 * @param string[]      $argv  Arguments, the script name first.
	 * @param string        $lang  Catalogue directory.
	 * @param string[]|null $roots Directories to scan; the fixture code
	 *                             when null.
	 *
	 * @return array{0: int, 1: string} Exit code and output.
	 */
	private static function report( array $argv, string $lang, ?array $roots = null ): array {
		$out  = fopen( 'php://memory', 'w+' );
		$code = Cli::run( $argv, $lang, $roots ?? array( self::CODE ), 'en', $out );

		rewind( $out );

		return array( $code, (string) stream_get_contents( $out ) );
	}
}
