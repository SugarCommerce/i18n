<?php
/**
 * Translation, plurals and locale negotiation.
 *
 * @package   SugarCommerce\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\I18n\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SugarCommerce\I18n\Catalogue;
use SugarCommerce\I18n\Locale;
use SugarCommerce\I18n\Plural;
use SugarCommerce\I18n\Translations;

#[CoversClass( Translations::class )]
#[CoversClass( Catalogue::class )]
#[CoversClass( Locale::class )]
#[CoversClass( Plural::class )]
final class I18nTest extends TestCase {

	private function catalogues(): string {
		return dirname( __DIR__ ) . '/catalogues';
	}

	private function german(): Translations {
		return Translations::for( $this->catalogues(), 'de' );
	}

	/* ─── Translation ───────────────────────────────────────────────── */

	public function test_a_translated_phrase_is_returned(): void {
		$this->assertSame(
			'Sie besitzen Waveform Pro bereits.',
			$this->german()->get( 'You already own {items}.', array( 'items' => 'Waveform Pro' ) )
		);
	}

	/**
	 * A blank string is worse than an untranslated one — a customer sees
	 * an empty button rather than an English one.
	 */
	public function test_a_missing_translation_falls_back_to_the_source(): void {
		$this->assertSame(
			'Nobody has translated this.',
			$this->german()->get( 'Nobody has translated this.' )
		);
	}

	public function test_an_untranslated_locale_returns_english(): void {
		$japanese = Translations::for( $this->catalogues(), 'ja' );

		$this->assertSame( 'You already own {items}.', $japanese->get( 'You already own {items}.' ) );
	}

	public function test_placeholders_are_interpolated(): void {
		$this->assertSame(
			'Hello Dave, you have 3 items.',
			Translations::interpolate( 'Hello {name}, you have {count} items.', array( 'name' => 'Dave', 'count' => '3' ) )
		);
	}

	public function test_an_unused_placeholder_is_left_alone(): void {
		$this->assertSame( 'Hello {name}.', Translations::interpolate( 'Hello {name}.', array( 'other' => 'x' ) ) );
	}

	/**
	 * Named rather than positional, because a translator often has to
	 * reorder a sentence and `%s … %s` gives no way to tell them apart.
	 */
	public function test_placeholders_survive_reordering(): void {
		$this->assertSame(
			'3 of Widget remain',
			Translations::interpolate( '{count} of {item} remain', array( 'item' => 'Widget', 'count' => '3' ) )
		);

		$this->assertSame(
			'Widget: 3 remain',
			Translations::interpolate( '{item}: {count} remain', array( 'item' => 'Widget', 'count' => '3' ) )
		);
	}

	public function test_missing_translations_can_be_reported(): void {
		$seen = array();

		$translator = new Translations(
			Catalogue::load( $this->catalogues(), 'de' ),
			static function ( string $text ) use ( &$seen ): void {
				$seen[] = $text;
			}
		);

		$translator->get( 'You already own {items}.' );
		$translator->get( 'Not in the catalogue.' );

		$this->assertSame( array( 'Not in the catalogue.' ), $seen );
	}

	/* ─── Plurals ───────────────────────────────────────────────────── */

	public function test_the_english_source_pluralises_on_count(): void {
		$english = Translations::for( $this->catalogues(), 'en' );

		$this->assertSame( '1 item', $english->plural( '{count} item', '{count} items', 1 ) );
		$this->assertSame( '3 items', $english->plural( '{count} item', '{count} items', 3 ) );
		$this->assertSame( '0 items', $english->plural( '{count} item', '{count} items', 0 ) );
	}

	public function test_count_is_supplied_as_a_placeholder(): void {
		$english = Translations::for( $this->catalogues(), 'en' );

		$this->assertStringContainsString( '7', $english->plural( '{count} item', '{count} items', 7 ) );
	}

	public function test_a_translated_plural_uses_the_catalogue_form(): void {
		$result = $this->german()->plural(
			'Only {count} left — reduce the quantity to continue.',
			'Only {count} left — reduce the quantity to continue.',
			3
		);

		$this->assertStringContainsString( 'Nur noch 3', $result );
	}

	/**
	 * English has two forms and most developers assume that is universal.
	 * These are the CLDR categories, and getting them wrong is why
	 * translated interfaces read as broken.
	 */
	#[DataProvider( 'plural_categories' )]
	public function test_plural_categories( string $locale, int $count, string $expected ): void {
		$this->assertSame( $expected, Plural::category( $count, $locale ) );
	}

	/**
	 * @return array<string, array{0: string, 1: int, 2: string}>
	 */
	public static function plural_categories(): array {
		return array(
			'en 1'     => array( 'en', 1, 'one' ),
			'en 0'     => array( 'en', 0, 'other' ),
			'en 2'     => array( 'en', 2, 'other' ),
			// French puts zero in the singular.
			'fr 0'     => array( 'fr', 0, 'one' ),
			'fr 1'     => array( 'fr', 1, 'one' ),
			'fr 2'     => array( 'fr', 2, 'other' ),
			// Russian: 1, 2-4, 5+, with the teens as an exception.
			'ru 1'     => array( 'ru', 1, 'one' ),
			'ru 2'     => array( 'ru', 2, 'few' ),
			'ru 5'     => array( 'ru', 5, 'many' ),
			'ru 11'    => array( 'ru', 11, 'many' ),
			'ru 21'    => array( 'ru', 21, 'one' ),
			'ru 22'    => array( 'ru', 22, 'few' ),
			// Polish draws the "few" boundary differently from Russian.
			'pl 21'    => array( 'pl', 21, 'many' ),
			'pl 22'    => array( 'pl', 22, 'few' ),
			// No distinction at all.
			'ja 1'     => array( 'ja', 1, 'other' ),
			'ja 5'     => array( 'ja', 5, 'other' ),
			// Six categories.
			'ar 0'     => array( 'ar', 0, 'zero' ),
			'ar 2'     => array( 'ar', 2, 'two' ),
			'ar 3'     => array( 'ar', 3, 'few' ),
			// The region is ignored.
			'pt-BR 1'  => array( 'pt-BR', 1, 'one' ),
		);
	}

	public function test_negative_counts_use_the_absolute_value(): void {
		$this->assertSame( 'one', Plural::category( -1, 'en' ) );
		$this->assertSame( 'other', Plural::category( -5, 'en' ) );
	}

	public function test_the_categories_a_language_needs_are_listed(): void {
		$this->assertSame( array( 'one', 'other' ), Plural::categories( 'en' ) );
		$this->assertSame( array( 'other' ), Plural::categories( 'ja' ) );
		$this->assertCount( 6, Plural::categories( 'ar' ) );
	}

	/* ─── Catalogues ────────────────────────────────────────────────── */

	public function test_a_missing_catalogue_is_not_an_error(): void {
		$catalogue = Catalogue::load( $this->catalogues(), 'xx' );

		$this->assertTrue( $catalogue->is_empty() );
		$this->assertSame( 0, $catalogue->count() );
	}

	/**
	 * `de-AT` should only need a file for what actually differs from `de`.
	 */
	public function test_a_region_inherits_its_base_language(): void {
		$austrian = Translations::for( $this->catalogues(), 'de-AT' );

		$this->assertSame( 'de-AT', $austrian->locale() );
		$this->assertStringContainsString( 'Sie besitzen', $austrian->get( 'You already own {items}.' ) );
	}

	public function test_the_catalogue_carries_entries(): void {
		$catalogue = Catalogue::load( $this->catalogues(), 'de' );

		$this->assertFalse( $catalogue->is_empty() );
		$this->assertGreaterThan( 3, $catalogue->count() );
		$this->assertNull( $catalogue->lookup( 'nothing like this exists' ) );
	}

	/* ─── Locale negotiation ────────────────────────────────────────── */

	#[DataProvider( 'negotiations' )]
	public function test_locale_negotiation( string $header, string $expected ): void {
		$this->assertSame( $expected, Locale::negotiate( $header, array( 'en', 'de', 'fr', 'pt-BR' ) ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function negotiations(): array {
		return array(
			// A reader wanting Austrian German is better served by German
			// than by English.
			'region falls back'   => array( 'de-AT,de;q=0.9,en;q=0.7', 'de' ),
			'exact'               => array( 'fr', 'fr' ),
			'base finds a region' => array( 'pt,es;q=0.8', 'pt-BR' ),
			// Quality weights beat header order.
			'quality wins'        => array( 'en;q=0.4,de;q=0.9', 'de' ),
			'nothing matches'     => array( 'ja,ko;q=0.8', 'en' ),
			'wildcard'            => array( '*', 'en' ),
			'empty'               => array( '', 'en' ),
			'malformed'           => array( ';;;q=', 'en' ),
		);
	}

	public function test_header_order_breaks_ties(): void {
		$this->assertSame( 'de', Locale::negotiate( 'de,fr', array( 'en', 'de', 'fr' ) ) );
		$this->assertSame( 'fr', Locale::negotiate( 'fr,de', array( 'en', 'de', 'fr' ) ) );
	}

	public function test_locales_are_normalised(): void {
		$this->assertSame( 'en-GB', Locale::normalise( 'en_gb' ) );
		$this->assertSame( 'de', Locale::normalise( ' DE ' ) );
		$this->assertSame( '', Locale::normalise( 'not a locale' ) );
		$this->assertSame( 'pt', Locale::language( 'pt-BR' ) );
	}

	/**
	 * Without `dir="rtl"` an Arabic page renders left-aligned and reads as
	 * broken even when every string is translated.
	 */
	public function test_right_to_left_languages_are_identified(): void {
		foreach ( array( 'ar', 'he', 'fa', 'ur', 'ar-EG' ) as $locale ) {
			$this->assertTrue( Locale::is_rtl( $locale ), $locale );
		}

		foreach ( array( 'en', 'de', 'ja', 'pt-BR' ) as $locale ) {
			$this->assertFalse( Locale::is_rtl( $locale ), $locale );
		}
	}

	public function test_negotiation_with_nothing_available_returns_the_fallback(): void {
		$this->assertSame( 'en', Locale::negotiate( 'de', array() ) );
	}

	/* ─── One tag ───────────────────────────────────────────────────── */

	/**
	 * @return array<string, array{0: string|null, 1: string}>
	 */
	public static function single_tags(): array {
		return array(
			'regional to base'     => array( 'de-AT', 'de' ),
			'base to the regional' => array( 'pt', 'pt-BR' ),
			'underscore and case'  => array( 'pt_br', 'pt-BR' ),
			'source language'      => array( 'en-GB', 'en' ),
			'auto'                 => array( 'auto', '' ),
			'unknown'              => array( 'xx', '' ),
			'garbage'              => array( '../../etc', '' ),
			'empty'                => array( '', '' ),
			'null'                 => array( null, '' ),
		);
	}

	/**
	 * "No preference" is a different answer from "the source language",
	 * and only the caller knows what to fall back to.
	 */
	#[DataProvider( 'single_tags' )]
	public function test_one_tag_is_matched_to_what_we_have_or_to_nothing( ?string $tag, string $expected ): void {
		$this->assertSame( $expected, Locale::match( $tag, array( 'en', 'de', 'pt-BR' ) ) );
	}

	/* ─── Picking a request's language ───────────────────────────────── */

	/**
	 * @return array<string, array{0: array<array-key, mixed>, 1: string, 2: string}>
	 */
	public static function picks(): array {
		return array(
			'the query beats everything'        => array( array( 'de', 'pt' ), 'en', 'de' ),
			'then the cookie'                   => array( array( null, 'pt' ), 'de', 'pt-BR' ),
			'then the header'                   => array( array( null, null ), 'fr;q=0.2, de;q=0.9', 'de' ),
			'a region is served by its base'    => array( array( 'de-AT' ), '', 'de' ),
			'an empty query is not a choice'    => array( array( '', 'de' ), '', 'de' ),
			'auto is not a choice'              => array( array( 'auto' ), 'pt', 'pt-BR' ),
			'garbage is skipped'                => array( array( '../../etc', '<b>' ), 'de', 'de' ),
			'a non-string is skipped'           => array( array( array( 'de' ), 7, 'pt' ), '', 'pt-BR' ),
			'an unsupported choice falls past'  => array( array( 'ja' ), 'de', 'de' ),
			'nothing at all gives the fallback' => array( array(), '', 'fr' ),
			'nothing matching gives it too'     => array( array( 'ja' ), 'ko, zh;q=0.5', 'fr' ),
		);
	}

	/**
	 * What the visitor said, in order, then the browser, then the fallback.
	 *
	 * @param array<array-key, mixed> $preferences
	 */
	#[DataProvider( 'picks' )]
	public function test_a_language_is_picked_from_choices_then_the_header( array $preferences, string $header, string $expected ): void {
		$this->assertSame( $expected, Locale::pick( $preferences, $header, array( 'en', 'de', 'pt-BR' ), 'fr' ) );
	}

	public function test_a_picked_language_is_spelt_as_the_application_spells_it(): void {
		$this->assertSame( 'pt_BR', Locale::pick( array( 'PT-br' ), '', array( 'en', 'pt_BR' ) ) );
		$this->assertSame( 'en', Locale::pick( array( 'xx' ), '', array() ) );
	}

	public function test_icu_spells_a_tag_with_an_underscore(): void {
		$this->assertSame( 'pt_BR', Locale::icu( 'pt-BR' ) );
		$this->assertSame( 'de', Locale::icu( 'de' ) );
	}

	/* ─── Names ─────────────────────────────────────────────────────── */

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function endonyms(): array {
		return array(
			'German'               => array( 'de', 'Deutsch' ),
			'Japanese'             => array( 'ja', '日本語' ),
			'Polish'               => array( 'pl', 'Polski' ),
			'Brazilian Portuguese' => array( 'pt-BR', 'Português (Brasil)' ),
			'lower-case region'    => array( 'pt_br', 'Português (Brasil)' ),
			'script subtag'        => array( 'zh-hant', '中文（繁體）' ),
		);
	}

	/**
	 * In itself, because the person looking for German cannot read the
	 * English word for it.
	 */
	#[DataProvider( 'endonyms' )]
	public function test_a_language_is_named_in_itself( string $locale, string $expected ): void {
		$this->assertSame( $expected, Locale::endonym( $locale ) );
	}

	/**
	 * A region with no entry of its own: ext-intl's name where it is
	 * loaded, the language's without it — German either way.
	 */
	public function test_a_region_without_an_entry_is_still_named_in_its_language(): void {
		$this->assertStringStartsWith( 'Deutsch', Locale::endonym( 'de-AT' ) );
	}

	public function test_an_unknown_language_is_named_by_its_tag_rather_than_nothing(): void {
		$this->assertSame( 'xx', Locale::endonym( 'xx' ) );
	}

	/**
	 * A tag from a query string must not come back as the "name" of a
	 * language and land in a picker as markup.
	 */
	public function test_something_that_is_not_a_tag_has_no_name(): void {
		$this->assertSame( '', Locale::endonym( '<script>alert(1)</script>' ) );
		$this->assertSame( '', Locale::endonym( 'xx" onmouseover="alert(1)' ) );
		$this->assertSame( '', Locale::endonym( "xx\n" . 'yy' ) );
	}

	/**
	 * `q=0` is the reader saying "not this one".
	 */
	public function test_a_language_weighted_zero_is_never_chosen(): void {
		$this->assertSame( 'fr', Locale::negotiate( 'en;q=0, de;q=0.0', array( 'en', 'de' ), 'fr' ) );
		$this->assertSame( array( 'de' ), Locale::parse( 'en;q=0,de' ) );
	}

	/**
	 * The header is the visitor's to write: an oversized one is read only
	 * as far as a browser would ever send.
	 */
	public function test_only_the_first_ranges_of_a_long_header_are_read(): void {
		$header = implode( ',', array_fill( 0, 5000, 'xx' ) ) . ',de';

		$this->assertCount( 32, Locale::parse( $header ) );
		$this->assertSame( 'en', Locale::negotiate( $header, array( 'en', 'de' ), 'en' ) );
	}

	/**
	 * abs( PHP_INT_MIN ) is a float, which the rules would refuse.
	 */
	public function test_the_smallest_int_has_a_category(): void {
		$this->assertSame( 'many', Plural::category( PHP_INT_MIN, 'ru' ) );
		$this->assertSame( 'many', Plural::category( PHP_INT_MIN, 'pl' ) );
		$this->assertSame( 'other', Plural::category( PHP_INT_MIN, 'en' ) );
		$this->assertSame(
			'{count} items',
			Translations::for( $this->catalogues(), 'ru' )->plural( '{count} item', '{count} items', PHP_INT_MIN, array( 'count' => '{count}' ) )
		);
	}

	/* ─── Catalogue files ───────────────────────────────────────────── */

	public function test_the_locales_are_read_from_the_directory_with_the_source_first(): void {
		$this->assertSame(
			array( 'en', 'de', 'pl', 'pt-BR' ),
			Catalogue::locales( __DIR__ . '/fixtures/lang' )
		);
	}

	public function test_the_source_is_available_even_without_a_file(): void {
		$this->assertSame( array( 'en', 'de' ), Catalogue::locales( $this->catalogues() ) );
		$this->assertSame( array( 'en' ), Catalogue::locales( __DIR__ . '/nowhere' ) );
	}

	/**
	 * What one file has, not what loading a locale would give: a gap
	 * check that saw inherited entries would miss the gaps.
	 */
	public function test_a_file_is_read_as_written(): void {
		$german = Catalogue::file( __DIR__ . '/fixtures/lang', 'de' );

		$this->assertSame( 'Bestellung {reference}', $german['Order {reference}'] );
		$this->assertSame( array(), Catalogue::file( __DIR__ . '/fixtures/lang', 'fr' ) );
		$this->assertSame( array(), Catalogue::file( __DIR__ . '/fixtures', 'lang/de' ) );
	}

	/**
	 * A catalogue file is code, so its name is only ever built from a
	 * tag: never a path, a dot or a NUL.
	 */
	public function test_a_locale_cannot_name_a_file_outside_the_directory(): void {
		$lang = __DIR__ . '/fixtures/lang';

		foreach ( array( '../lang/de', '..\\lang\\de', '../../../etc/passwd', '..', '.', 'de.php', '' ) as $locale ) {
			$this->assertSame( array(), Catalogue::file( $lang, $locale ), $locale );
			$this->assertTrue( Catalogue::load( $lang, $locale )->is_empty(), $locale );
		}

		$this->assertSame( array(), Catalogue::file( $lang, "de\0" ) );
		$this->assertSame( array(), Catalogue::file( $lang, "de\n" ) );
	}

	/**
	 * A value a catalogue cannot hold is dropped at load, rather than
	 * reaching the translator and throwing a TypeError mid-page.
	 */
	public function test_a_malformed_catalogue_entry_is_dropped_rather_than_printed(): void {
		$french = Translations::for( __DIR__ . '/fixtures/malformed', 'fr' );

		$this->assertSame( 'A number', $french->get( 'A number' ) );
		$this->assertSame( 'Bien', $french->get( 'Fine' ) );
		$this->assertSame( 'Introuvable', $french->get( '404' ) );
		$this->assertSame( 'Imbriqués', $french->plural( 'Nested', 'Nested', 1 ) );
		$this->assertSame( 'A list', $french->get( 'A list' ) );
		$this->assertSame(
			array( 'Nested', 'Fine', '404' ),
			array_map( 'strval', array_keys( Catalogue::file( __DIR__ . '/fixtures/malformed', 'fr' ) ) )
		);
	}

	/**
	 * glob() would read the brackets in the directory's own name as a
	 * pattern and find nothing.
	 */
	public function test_locales_are_listed_from_a_directory_with_brackets_in_its_name(): void {
		$directory = sys_get_temp_dir() . '/i18n-[' . bin2hex( random_bytes( 4 ) ) . ']';
		mkdir( $directory );
		copy( __DIR__ . '/fixtures/lang/de.php', $directory . '/de.php' );

		try {
			$this->assertSame( array( 'en', 'de' ), Catalogue::locales( $directory ) );
		} finally {
			unlink( $directory . '/de.php' );
			rmdir( $directory );
		}
	}

	/* ─── Plural examples ───────────────────────────────────────────── */

	public function test_each_category_has_a_smallest_example(): void {
		$this->assertSame( 1, Plural::example( 'one', 'en' ) );
		$this->assertSame( 0, Plural::example( 'other', 'en' ) );
		$this->assertSame( 2, Plural::example( 'few', 'pl' ) );
		$this->assertSame( 0, Plural::example( 'many', 'pl' ) );
		$this->assertSame( 3, Plural::example( 'few', 'ar' ) );
		$this->assertSame( 100, Plural::example( 'other', 'ar' ) );
		$this->assertSame( -1, Plural::example( 'few', 'en' ) );
	}

	public function test_every_form_of_a_sentence_is_given_for_a_script(): void {
		$polish = new Translations( Catalogue::load( __DIR__ . '/fixtures/lang', 'pl' ) );

		$forms = Plural::forms(
			'pl',
			static fn ( int $n ): string => $polish->plural( '{count} file', '{count} files', $n, array( 'count' => '{count}' ) )
		);

		$this->assertSame(
			array( 'one' => '{count} plik', 'few' => '{count} pliki', 'many' => '{count} plików' ),
			$forms
		);
	}
}
