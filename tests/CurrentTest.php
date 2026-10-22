<?php
/**
 * The language of this request, and the helpers that read it.
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
use SugarCommerce\I18n\Current;

#[CoversClass( Current::class )]
final class CurrentTest extends TestCase {

	protected function setUp(): void {
		Current::configure( __DIR__ . '/fixtures/lang' );
		Current::helpers();
	}

	protected function tearDown(): void {
		Current::reset();
	}

	/* ─── Scope ─────────────────────────────────────────────────────── */

	public function test_the_source_is_in_use_until_something_says_otherwise(): void {
		$this->assertSame( 'en', Current::locale() );
		$this->assertSame( 'en', Current::source() );
		$this->assertSame( __DIR__ . '/fixtures/lang', Current::directory() );
	}

	public function test_what_is_available_is_read_from_the_directory(): void {
		$this->assertSame( array( 'en', 'de', 'pl', 'pt-BR' ), Current::available() );
		$this->assertSame( 'pt-BR', Current::supported( 'pt' ) );
		$this->assertSame( '', Current::supported( 'auto' ) );
	}

	/**
	 * Scoped, so a German email sent from a queue run cannot leave the
	 * next one in German.
	 */
	public function test_within_puts_the_previous_language_back(): void {
		$inside = Current::within( 'pl', static function (): string {
			Current::use( 'de' );

			return Current::locale();
		} );

		$this->assertSame( 'de', $inside );
		$this->assertSame( 'en', Current::locale() );
	}

	public function test_within_puts_it_back_after_an_exception_too(): void {
		try {
			Current::within( 'de', static fn () => throw new \RuntimeException( 'boom' ) );
		} catch ( \RuntimeException ) {
			// Expected.
		}

		$this->assertSame( 'en', Current::locale() );
	}

	public function test_nested_scopes_unwind_in_order(): void {
		$seen = Current::within( 'de', static fn (): array => array(
			Current::locale(),
			Current::within( 'pl', Current::locale( ... ) ),
			Current::locale(),
		) );

		$this->assertSame( array( 'de', 'pl', 'de' ), $seen );
	}

	public function test_an_unknown_language_means_the_source(): void {
		$this->assertSame( 'en', Current::within( 'xx', Current::locale( ... ) ) );
		$this->assertSame( 'en', Current::within( '', Current::locale( ... ) ) );
	}

	public function test_a_translator_is_loaded_once_per_locale(): void {
		$this->assertSame( Current::translator_for( 'de' ), Current::translator_for( 'de' ) );
		$this->assertSame( 'pl', Current::within( 'pl', static fn (): string => Current::translator()->locale() ) );
	}

	/**
	 * One translator per locale however it is spelt, so a worker handed
	 * tags from outside cannot fill memory with spellings of German.
	 */
	public function test_a_translator_is_kept_by_its_normalised_tag(): void {
		$this->assertSame( Current::translator_for( 'de' ), Current::translator_for( 'DE' ) );
		$this->assertSame( Current::translator_for( 'pt-BR' ), Current::translator_for( 'pt_br' ) );
		$this->assertSame( 'pt-BR', Current::translator_for( 'pt_br' )->locale() );
	}

	/**
	 * Before configure() there are no catalogues, and every sentence
	 * prints as written rather than failing.
	 */
	public function test_with_no_directory_everything_prints_its_source(): void {
		Current::configure( '' );

		$this->assertSame( array( 'en' ), Current::available() );
		$this->assertSame( 'Order 7', Current::within( 'de', static fn (): string => __( 'Order {reference}', array( 'reference' => 7 ) ) ) );
	}

	/* ─── Helpers ───────────────────────────────────────────────────── */

	public function test_a_sentence_is_translated_with_its_placeholders(): void {
		$this->assertSame(
			'Bestellung SC-7K2Q9X',
			Current::within( 'de', static fn (): string => __( 'Order {reference}', array( 'reference' => 'SC-7K2Q9X' ) ) )
		);
	}

	public function test_a_regional_catalogue_named_with_an_underscore_is_found(): void {
		$this->assertSame( 'Pedido 1', Current::within( 'pt-BR', static fn (): string => __( 'Order {reference}', array( 'reference' => 1 ) ) ) );
	}

	public function test_counts_take_the_languages_own_forms(): void {
		$forms = Current::within( 'pl', static fn (): array => array_map(
			static fn ( int $n ): string => _n( '{count} file', '{count} files', $n ),
			array( 1, 2, 5, 12, 22 )
		) );

		$this->assertSame( array( '1 plik', '2 pliki', '5 plików', '12 plików', '22 pliki' ), $forms );
	}

	public function test_markup_goes_in_after_the_text_is_escaped(): void {
		$this->assertSame(
			'Fish &amp; chips: <b>yes</b>',
			__html( 'Fish & chips: {answer}', array( 'answer' => '<b>yes</b>' ) )
		);
	}

	/**
	 * A malformed byte would make htmlspecialchars() return '' and blank
	 * the whole sentence.
	 */
	public function test_a_malformed_byte_does_not_empty_the_sentence(): void {
		$this->assertSame( "Caf\u{FFFD} &amp; {x}", __html( "Caf\xE9 & {x}" ) );
	}

	/**
	 * Another library's `__()` — WordPress's, say — is left alone rather
	 * than redeclared, which would be fatal.
	 */
	public function test_loading_the_helpers_twice_is_harmless(): void {
		Current::helpers();

		$this->assertTrue( function_exists( '__' ) && function_exists( '_n' ) && function_exists( '__html' ) );
	}
}
