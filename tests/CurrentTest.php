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

	/* ─── Picking and switching ─────────────────────────────────────── */

	public function test_the_request_language_is_picked_from_what_there_is(): void {
		$this->assertSame( 'pl', Current::pick( array( 'pl', 'de' ), 'de' ) );
		$this->assertSame( 'pt-BR', Current::pick( array( null, 'pt' ) ) );
		$this->assertSame( 'de', Current::pick( array( 'auto' ), 'de-AT,en;q=0.5' ) );
		$this->assertSame( 'en', Current::pick( array( 'fr' ), 'ja' ), 'Nothing we have means the source.' );
		$this->assertSame( 'en', Current::locale(), 'Picking does not switch.' );
	}

	public function test_a_picker_lists_every_language_named_in_itself(): void {
		$choices = Current::within( 'pl', static fn (): array => Current::choices(
			static fn ( string $locale ): string => '?lang=' . $locale
		) );

		$this->assertSame(
			array(
				array( 'locale' => 'en', 'name' => 'English', 'url' => '?lang=en', 'current' => false, 'dir' => 'ltr' ),
				array( 'locale' => 'de', 'name' => 'Deutsch', 'url' => '?lang=de', 'current' => false, 'dir' => 'ltr' ),
				array( 'locale' => 'pl', 'name' => 'Polski', 'url' => '?lang=pl', 'current' => true, 'dir' => 'ltr' ),
				array( 'locale' => 'pt-BR', 'name' => 'Português (Brasil)', 'url' => '?lang=pt-BR', 'current' => false, 'dir' => 'ltr' ),
			),
			$choices
		);
	}

	/**
	 * A menu of one is not a choice.
	 */
	public function test_a_single_language_has_no_picker(): void {
		Current::configure( __DIR__ . '/fixtures/code' );

		$this->assertSame( array( 'en' ), Current::available() );
		$this->assertSame( array(), Current::choices( static fn ( string $locale ): string => '/' . $locale ) );
	}

	/**
	 * "العربية" in an English menu is still written right to left.
	 */
	public function test_a_picker_says_which_way_each_name_is_written(): void {
		Current::configure( __DIR__ . '/fixtures/rtl' );

		$this->assertSame(
			array(
				array( 'locale' => 'en', 'name' => 'English', 'url' => '?lang=en', 'current' => true, 'dir' => 'ltr' ),
				array( 'locale' => 'ar', 'name' => 'العربية', 'url' => '?lang=ar', 'current' => false, 'dir' => 'rtl' ),
			),
			Current::choices( Current::switcher( array() ) )
		);
	}

	/* ─── The switcher link ─────────────────────────────────────────── */

	public function test_the_switcher_links_by_query_parameter(): void {
		$link = Current::switcher( array() );

		$this->assertSame( '?lang=de', $link( 'de' ) );
		$this->assertSame( '?lang=pt-BR', $link( 'pt-BR' ) );
	}

	/**
	 * A cart, or a list on page two, should still be there in German.
	 */
	public function test_the_switcher_keeps_the_rest_of_the_query_and_replaces_the_language(): void {
		$link = Current::switcher( array( 'page' => '2', 'lang' => 'pl', 'sort' => 'price' ) );

		$this->assertSame( '?lang=de&page=2&sort=price', $link( 'de' ) );
	}

	public function test_the_switcher_encodes_what_it_carries_over(): void {
		$link = Current::switcher( array( 'q' => 'a b&c=d', 'name' => 'Zoë', 'next' => '/cart?x=1#top' ) );

		$this->assertSame( '?lang=de&q=a+b%26c%3Dd&name=Zo%C3%AB&next=%2Fcart%3Fx%3D1%23top', $link( 'de' ) );
	}

	public function test_the_switcher_carries_array_values_over(): void {
		$link = Current::switcher( array( 'tag' => array( 'red', 'blue' ), 'filter' => array( 'size' => 'L' ) ) );

		$this->assertSame( '?lang=de&tag%5B0%5D=red&tag%5B1%5D=blue&filter%5Bsize%5D=L', $link( 'de' ) );
	}

	/**
	 * `?lang[]=de` arrives as an array; carried over, it would add a second
	 * language to every link.
	 */
	public function test_the_switcher_replaces_a_language_that_arrived_as_an_array(): void {
		$link = Current::switcher( array( 'lang' => array( 'de', 'pl' ), 'page' => '2' ) );

		$this->assertSame( '?lang=pl&page=2', $link( 'pl' ) );
	}

	public function test_the_switcher_takes_the_parameter_name(): void {
		$link = Current::switcher( array( 'lang' => 'pl', 'locale' => 'de', 'page' => '2' ), 'locale' );

		$this->assertSame( '?locale=pt-BR&lang=pl&page=2', $link( 'pt-BR' ) );
	}

	/**
	 * choices() only passes available locales, but a link handed anything
	 * still cannot break out of its own value.
	 */
	public function test_the_switcher_encodes_the_locale_it_is_given(): void {
		$link = Current::switcher( array( 'page' => '2' ) );

		$this->assertSame( '?lang=x%26page%3D9&page=2', $link( 'x&page=9' ) );
	}

	public function test_the_switcher_feeds_the_picker(): void {
		$choices = Current::within( 'de', static fn (): array => Current::choices( Current::switcher( array( 'lang' => 'de', 'id' => '7' ) ) ) );

		$this->assertSame(
			array( '?lang=en&id=7', '?lang=de&id=7', '?lang=pl&id=7', '?lang=pt-BR&id=7' ),
			array_column( $choices, 'url' )
		);
	}

	/* ─── Remembering a choice ──────────────────────────────────────── */

	public function test_a_choice_is_remembered_for_a_year(): void {
		$before = time();
		$cookie = Current::remember( 'lang', 'de', true );
		$after  = time();

		$this->assertSame( 'lang', $cookie['name'] ?? null );
		$this->assertSame( 'de', $cookie['value'] ?? null );

		$options = $cookie['options'] ?? array();
		$expires = $options['expires'] ?? 0;
		unset( $options['expires'] );

		$this->assertGreaterThanOrEqual( $before + Current::YEAR, $expires );
		$this->assertLessThanOrEqual( $after + Current::YEAR, $expires );
		$this->assertSame( array( 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax' ), $options );
	}

	public function test_the_cookie_is_shaped_by_the_caller(): void {
		$before = time();
		$cookie = Current::remember( 'site_language', 'pl', false, 3600, '/shop' );

		$this->assertSame( 'site_language', $cookie['name'] ?? null );
		$this->assertSame( '/shop', $cookie['options']['path'] ?? null );
		$this->assertFalse( $cookie['options']['secure'] ?? true );
		$this->assertGreaterThanOrEqual( $before + 3600, $cookie['options']['expires'] ?? 0 );
		$this->assertLessThan( $before + 3600 + 60, $cookie['options']['expires'] ?? PHP_INT_MAX );
	}

	public function test_no_lifetime_is_a_session_cookie(): void {
		$this->assertSame( 0, Current::remember( 'lang', 'de', true, 0 )['options']['expires'] ?? null );
		$this->assertSame( 0, Current::remember( 'lang', 'de', true, -60 )['options']['expires'] ?? null );
	}

	/**
	 * The locale it was matched to, never the tag as it was typed.
	 */
	public function test_what_is_remembered_is_the_language_that_serves_the_choice(): void {
		$this->assertSame( 'pt-BR', Current::remember( 'lang', 'pt', true )['value'] ?? null );
		$this->assertSame( 'de', Current::remember( 'lang', 'de_AT', true )['value'] ?? null );
		$this->assertSame( 'en', Current::remember( 'lang', 'EN', true )['value'] ?? null, 'The source is a choice too.' );
	}

	public function test_a_right_to_left_choice_is_remembered_like_any_other(): void {
		Current::configure( __DIR__ . '/fixtures/rtl' );

		$this->assertSame( 'ar', Current::remember( 'lang', 'ar-EG', true )['value'] ?? null );
	}

	/**
	 * A page that only read the cookie has nothing new to remember, and a
	 * language there is no catalogue for is not worth a year.
	 */
	public function test_nothing_is_remembered_without_a_choice_we_can_serve(): void {
		foreach ( array( null, '', 'auto', 'xx', 'fr', 'de;q=1', '../../etc/passwd', "de\r\nSet-Cookie: x=1" ) as $choice ) {
			$this->assertSame( array(), Current::remember( 'lang', $choice, true ), var_export( $choice, true ) );
		}
	}

	/**
	 * `?lang[]=de` arrives as an array; cast, it would be "Array".
	 */
	public function test_a_choice_that_is_not_a_string_is_not_remembered(): void {
		foreach ( array( array( 'de' ), array(), 1, 1.5, true, new \stdClass() ) as $choice ) {
			$this->assertSame( array(), Current::remember( 'lang', $choice, true ) );
		}
	}

	public function test_remembering_does_not_switch(): void {
		Current::remember( 'lang', 'de', true );

		$this->assertSame( 'en', Current::locale() );
	}
}
