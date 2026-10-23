<?php
/**
 * Locale negotiation.
 *
 * @package   SugarCommerce\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\I18n;

/**
 * Class Locale
 *
 * Works out which language to serve.
 *
 * @since 1.0.0
 */
final readonly class Locale {

	/**
	 * The most language ranges read from one `Accept-Language` header.
	 *
	 * Browsers send a handful. The header is the visitor's to write, so
	 * the rest of an oversized one is ignored rather than parsed, sorted
	 * and matched against every locale on each request.
	 *
	 * @since 1.2.0
	 *
	 * @var int
	 */
	private const int MAX_RANGES = 32;

	/**
	 * A well-formed tag, loosely after BCP 47: a language, then subtags.
	 *
	 * Only something this shape is handed to ICU or given back as a name.
	 *
	 * @since 1.2.0
	 *
	 * @var string
	 */
	private const string TAG = '/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/D';

	/**
	 * Languages' names in themselves, by tag.
	 *
	 * Regional entries are listed only where the name differs from the
	 * language's own — Brazilian and European Portuguese are spelt and read
	 * differently enough that a reader looks for the one they speak.
	 *
	 * @since 1.1.0
	 *
	 * @var array<string, string>
	 */
	private const array ENDONYMS = array(
		'af'      => 'Afrikaans',
		'ar'      => 'العربية',
		'bg'      => 'Български',
		'bn'      => 'বাংলা',
		'ca'      => 'Català',
		'cs'      => 'Čeština',
		'cy'      => 'Cymraeg',
		'da'      => 'Dansk',
		'de'      => 'Deutsch',
		'el'      => 'Ελληνικά',
		'en'      => 'English',
		'en-GB'   => 'English (UK)',
		'en-US'   => 'English (US)',
		'es'      => 'Español',
		'es-419'  => 'Español (Latinoamérica)',
		'es-MX'   => 'Español (México)',
		'et'      => 'Eesti',
		'eu'      => 'Euskara',
		'fa'      => 'فارسی',
		'fi'      => 'Suomi',
		'fil'     => 'Filipino',
		'fr'      => 'Français',
		'fr-CA'   => 'Français (Canada)',
		'ga'      => 'Gaeilge',
		'gl'      => 'Galego',
		'he'      => 'עברית',
		'hi'      => 'हिन्दी',
		'hr'      => 'Hrvatski',
		'hu'      => 'Magyar',
		'id'      => 'Bahasa Indonesia',
		'is'      => 'Íslenska',
		'it'      => 'Italiano',
		'ja'      => '日本語',
		'ko'      => '한국어',
		'lt'      => 'Lietuvių',
		'lv'      => 'Latviešu',
		'ms'      => 'Bahasa Melayu',
		'mt'      => 'Malti',
		'nb'      => 'Norsk bokmål',
		'nl'      => 'Nederlands',
		'no'      => 'Norsk',
		'pl'      => 'Polski',
		'pt'      => 'Português',
		'pt-BR'   => 'Português (Brasil)',
		'pt-PT'   => 'Português (Portugal)',
		'ro'      => 'Română',
		'ru'      => 'Русский',
		'sk'      => 'Slovenčina',
		'sl'      => 'Slovenščina',
		'sr'      => 'Српски',
		'sv'      => 'Svenska',
		'sw'      => 'Kiswahili',
		'ta'      => 'தமிழ்',
		'th'      => 'ไทย',
		'tr'      => 'Türkçe',
		'uk'      => 'Українська',
		'ur'      => 'اردو',
		'vi'      => 'Tiếng Việt',
		'zh'      => '中文',
		'zh-CN'   => '中文（简体）',
		'zh-Hans' => '中文（简体）',
		'zh-Hant' => '中文（繁體）',
		'zh-HK'   => '中文（香港）',
		'zh-TW'   => '中文（台灣）',
	);

	/**
	 * Pick the best available locale for an `Accept-Language` header.
	 *
	 * The header is an ordered list with quality weights, e.g.
	 * `de-AT,de;q=0.9,en;q=0.7`. A reader wanting Austrian German is
	 * happier with German than with English, so a region-tagged request
	 * falls back to its base language before moving down the list.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $header    Raw `Accept-Language` value.
	 * @param string[] $available Locales this application has.
	 * @param string   $fallback  Returned when nothing matches.
	 *
	 * @return string
	 */
	public static function negotiate( string $header, array $available, string $fallback = 'en' ): string {
		if ( array() === $available ) {
			return $fallback;
		}

		// Index by lowercase so matching is case-insensitive without
		// losing the caller's preferred spelling.
		$offered = array();

		foreach ( $available as $locale ) {
			$offered[ strtolower( self::normalise( $locale ) ) ] = $locale;
		}

		foreach ( self::parse( $header ) as $wanted ) {
			$wanted = strtolower( $wanted );

			if ( isset( $offered[ $wanted ] ) ) {
				return $offered[ $wanted ];
			}

			// de-AT → de
			$base = explode( '-', $wanted )[0];

			if ( isset( $offered[ $base ] ) ) {
				return $offered[ $base ];
			}

			// A request for `de` is satisfied by `de-DE` if that is all
			// there is.
			foreach ( $offered as $key => $locale ) {
				if ( str_starts_with( $key, $base . '-' ) ) {
					return $locale;
				}
			}
		}

		return $fallback;
	}

	/**
	 * Parse an `Accept-Language` header into locales, best first.
	 *
	 * A range weighted `q=0` is one the reader has said they do not want,
	 * so it is left out rather than tried last. Only the first
	 * {@see self::MAX_RANGES} ranges are read.
	 *
	 * @since 1.0.0
	 *
	 * @param string $header Raw header value.
	 *
	 * @return list<string>
	 */
	public static function parse( string $header ): array {
		$weighted = array();
		$parts    = array_slice( explode( ',', $header, self::MAX_RANGES + 1 ), 0, self::MAX_RANGES );

		foreach ( $parts as $index => $part ) {
			$bits   = explode( ';', trim( $part ) );
			$locale = self::normalise( trim( $bits[0] ) );

			if ( '' === $locale || '*' === $locale ) {
				continue;
			}

			$quality = 1.0;

			foreach ( array_slice( $bits, 1 ) as $parameter ) {
				if ( 1 === preg_match( '/^\s*q\s*=\s*([0-9.]+)/i', $parameter, $m ) ) {
					$quality = (float) $m[1];
				}
			}

			if ( $quality <= 0.0 ) {
				continue;
			}

			// Index breaks ties, so the header's own order is preserved
			// among equal weights — sorting on quality alone reorders them
			// arbitrarily.
			$weighted[] = array( 'locale' => $locale, 'q' => $quality, 'i' => $index );
		}

		usort(
			$weighted,
			static fn( array $a, array $b ): int => $b['q'] <=> $a['q'] ?: $a['i'] <=> $b['i']
		);

		return array_column( $weighted, 'locale' );
	}

	/**
	 * Normalise a locale tag to `language-REGION` form.
	 *
	 * @since 1.0.0
	 *
	 * @param string $locale Raw tag, `en_GB` or `en-gb`.
	 *
	 * @return string
	 */
	public static function normalise( string $locale ): string {
		$locale = str_replace( '_', '-', trim( $locale ) );

		if ( 1 !== preg_match( '/^([A-Za-z]{2,3})(?:-([A-Za-z]{2}|[0-9]{3}))?$/D', $locale, $m ) ) {
			return '';
		}

		return isset( $m[2] ) ? strtolower( $m[1] ) . '-' . strtoupper( $m[2] ) : strtolower( $m[1] );
	}

	/**
	 * The language part of a locale.
	 *
	 * @since 1.0.0
	 *
	 * @param string $locale Locale tag.
	 *
	 * @return string
	 */
	public static function language( string $locale ): string {
		return explode( '-', self::normalise( $locale ) )[0];
	}

	/**
	 * Whether a language is written right to left.
	 *
	 * Needed for `dir="rtl"` on the document — without it an Arabic or
	 * Hebrew page renders left-aligned and reads as broken even when
	 * every string is translated.
	 *
	 * @since 1.0.0
	 *
	 * @param string $locale Locale tag.
	 *
	 * @return bool
	 */
	public static function is_rtl( string $locale ): bool {
		return in_array(
			self::language( $locale ),
			array( 'ar', 'he', 'fa', 'ur', 'ps', 'sd', 'yi', 'dv' ),
			true
		);
	}

	/**
	 * A language's name in itself: "Deutsch", "日本語", "Português (Brasil)".
	 *
	 * In itself, because the person looking for German in a language picker
	 * is exactly the person who cannot read the English word for it. A
	 * picker that lists "German, Japanese, Portuguese" is a picker only the
	 * people who need it least can use.
	 *
	 * The built-in table covers the common languages and the regional
	 * variants whose names differ. Anything else is asked of ext-intl when
	 * it is loaded, and failing that the tag itself comes back — something
	 * printable, never an empty option.
	 *
	 * Only a well-formed tag is echoed back. Anything else — markup, a
	 * path, a sentence — gives '', so a tag taken from a query string or a
	 * cookie cannot turn up on the page as the "name" of a language.
	 *
	 * @since 1.1.0
	 *
	 * @param string $locale Locale tag, any case, `-` or `_`.
	 *
	 * @return string '' for something that is not a tag.
	 */
	public static function endonym( string $locale ): string {
		$tag = self::canonical( $locale );

		if ( isset( self::ENDONYMS[ $tag ] ) ) {
			return self::ENDONYMS[ $tag ];
		}

		if ( 1 !== preg_match( self::TAG, $tag ) ) {
			return '';
		}

		if ( class_exists( \Locale::class ) ) {
			$icu  = self::icu( $tag );
			$name = \Locale::getDisplayName( $icu, $icu );

			// ICU answers a tag it does not know with the tag itself.
			if ( is_string( $name ) && strtolower( $name ) !== strtolower( $icu ) ) {
				// Lower-case is ICU's convention for running text; an option
				// in a menu starts with a capital in every script that has
				// one.
				return mb_strtoupper( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 );
			}
		}

		return self::ENDONYMS[ explode( '-', $tag )[0] ] ?? trim( $locale );
	}

	/**
	 * The locale we have that best serves one tag, or '' for none.
	 *
	 * The single-tag sibling of negotiate(), for a tag that arrives from
	 * somewhere other than a header: a query string, a cookie, a payment
	 * provider, the database. `de-AT` is served by `de` and `pt` by `pt-BR`,
	 * the way an `Accept-Language` header would be.
	 *
	 * '' rather than a default for `auto`, `xx` and garbage, so the caller
	 * can tell "no preference" from "English" — they are different answers,
	 * and only the caller knows what to fall back to.
	 *
	 * @since 1.1.0
	 *
	 * @param string|null $tag       A locale tag, or null.
	 * @param string[]    $available Locales this application has.
	 *
	 * @return string One of $available, or ''.
	 */
	public static function match( ?string $tag, array $available ): string {
		$tag = (string) $tag;

		return '' === self::normalise( $tag ) ? '' : self::negotiate( $tag, $available, '' );
	}

	/**
	 * The language to serve: what the visitor said, else what their
	 * browser asks for, else the fallback.
	 *
	 * The order a page settles its language in. A choice the visitor made —
	 * `?lang=de` from a picker, then the cookie that remembers it — beats
	 * the browser, because someone who picked German on one page and got
	 * English on the next would think the picker was broken. Anything else
	 * an application knows (the language an order was placed in, an
	 * account's setting) slots into the list wherever it ranks. The
	 * `Accept-Language` header is the guess for everybody who said nothing.
	 *
	 * Each preference is matched as match() matches one tag, so `de-AT` is
	 * served by `de`, and `auto`, null, '' and garbage are skipped rather
	 * than read as the source language: an empty query string is not a
	 * choice of English. So is anything that is not a string — `?lang[]=de`
	 * arrives as an array — so `$_GET` and `$_COOKIE` values can be handed
	 * over as they come.
	 *
	 * @since 1.3.0
	 *
	 * @param array<array-key, mixed> $preferences     Tags the visitor gave,
	 *                                                 most authoritative
	 *                                                 first: query, cookie, …
	 * @param string                  $accept_language Raw `Accept-Language`
	 *                                                 value, '' for none.
	 * @param string[]                $available       Locales this
	 *                                                 application has.
	 * @param string                  $fallback        Returned when nothing
	 *                                                 matches.
	 *
	 * @return string One of $available, or $fallback.
	 */
	public static function pick( array $preferences, string $accept_language, array $available, string $fallback = 'en' ): string {
		foreach ( $preferences as $tag ) {
			$locale = is_string( $tag ) ? self::match( $tag, $available ) : '';

			if ( '' !== $locale ) {
				return $locale;
			}
		}

		return self::negotiate( $accept_language, $available, $fallback );
	}

	/**
	 * A locale tag as ICU spells it: `pt_BR`, not `pt-BR`.
	 *
	 * ext-intl's formatters take the underscore everywhere; handed the
	 * hyphen, some builds quietly fall back to the root locale and format
	 * a German date in English with no error at all.
	 *
	 * @since 1.1.0
	 *
	 * @param string $locale Locale tag.
	 *
	 * @return string
	 */
	public static function icu( string $locale ): string {
		return str_replace( '-', '_', trim( $locale ) );
	}

	/**
	 * A tag in its conventional case, for a table lookup.
	 *
	 * Language lower, script title, region upper: `zh-hant` → `zh-Hant`,
	 * `pt_br` → `pt-BR`. Unlike normalise(), a script subtag survives.
	 *
	 * @since 1.1.0
	 *
	 * @param string $locale Raw tag.
	 *
	 * @return string
	 */
	private static function canonical( string $locale ): string {
		$parts = explode( '-', str_replace( '_', '-', trim( $locale ) ) );

		foreach ( $parts as $i => $part ) {
			$parts[ $i ] = match ( true ) {
				0 === $i              => strtolower( $part ),
				4 === strlen( $part ) => ucfirst( strtolower( $part ) ),
				default               => strtoupper( $part ),
			};
		}

		return implode( '-', $parts );
	}
}
