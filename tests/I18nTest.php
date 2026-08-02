<?php
/**
 * Translation, plurals and locale negotiation.
 *
 * @package   Sugar\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace Sugar\I18n\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sugar\I18n\Catalogue;
use Sugar\I18n\Locale;
use Sugar\I18n\Plural;
use Sugar\I18n\Translations;

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
}
