<?php
/**
 * The language of this request.
 *
 * @package   SugarCommerce\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.1.0
 */

declare( strict_types=1 );

namespace SugarCommerce\I18n;

/**
 * Class Current
 *
 * Which catalogue `__()` reads from, and what catalogues there are.
 *
 * Mutable static state, deliberately — the one exception in this package to
 * "readonly", and the reason is the alternative. A translation helper that
 * had to be handed a translator would have to be handed one by every
 * template, partial and email that prints a sentence, and the first one that
 * forgot would print English in the middle of a German page. PHP serves one
 * request per process, so "the language of this request" is a fact about
 * the process.
 *
 * The danger with process state is leaking it: a test, or a queue run that
 * sends a German email, leaving the next thing speaking German. within() is
 * the guard — it scopes a language to a piece of work and puts back whatever
 * was there, even when the work throws. Prefer it to use(), which is for the
 * case where the language is only learnt part-way through a scope.
 *
 * The source language's text is the key — see Catalogue for why — so a
 * string nobody translated prints its source, which is what makes it safe
 * to wrap strings one at a time.
 *
 * @since 1.1.0
 */
final class Current {

	/**
	 * Where the catalogues live, or '' before configure().
	 */
	private static string $directory = '';

	/**
	 * The language the source strings are written in.
	 */
	private static string $source = 'en';

	/**
	 * The locale in use. Null until something sets one: then the source.
	 */
	private static ?string $locale = null;

	/**
	 * Translators already loaded, by locale.
	 *
	 * @var array<string, Translator>
	 */
	private static array $loaded = array();

	/**
	 * The locales there are catalogues for, memoised.
	 *
	 * @var string[]|null
	 */
	private static ?array $available = null;

	/**
	 * Say where the catalogues are and what the source language is.
	 *
	 * Once, at boot. Calling it again starts afresh: the memoised list and
	 * translators belong to the old directory, and the locale goes back to
	 * the source.
	 *
	 * @since 1.1.0
	 *
	 * @param string $directory Directory of `<locale>.php` catalogues.
	 * @param string $source    The language the source strings are in.
	 *
	 * @return void
	 */
	public static function configure( string $directory, string $source = 'en' ): void {
		self::reset();

		self::$directory = rtrim( $directory, '/' );
		self::$source    = Locale::normalise( $source ) ?: 'en';
	}

	/**
	 * The catalogue directory.
	 *
	 * @since 1.1.0
	 *
	 * @return string '' before configure().
	 */
	public static function directory(): string {
		return self::$directory;
	}

	/**
	 * The source language.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public static function source(): string {
		return self::$source;
	}

	/**
	 * Every locale with a catalogue, the source first.
	 *
	 * @since 1.1.0
	 *
	 * @return string[]
	 */
	public static function available(): array {
		return self::$available ??= '' === self::$directory
			? array( self::$source )
			: Catalogue::locales( self::$directory, self::$source );
	}

	/**
	 * The available locale that best serves a tag, or ''.
	 *
	 * `de-AT` is served by `de` and `pt` by `pt-BR`; `auto`, `xx` and
	 * garbage are served by nothing, so a caller can tell "no preference"
	 * from the source language.
	 *
	 * @since 1.1.0
	 *
	 * @param string|null $tag A locale tag from anywhere.
	 *
	 * @return string
	 */
	public static function supported( ?string $tag ): string {
		return Locale::match( $tag, self::available() );
	}

	/**
	 * The language to serve this request in, from what the visitor said and
	 * what their browser asks for.
	 *
	 * Locale::pick() against the catalogues there are, falling back to the
	 * source. Only answers the question: pass the result to use() or
	 * within() to act on it.
	 *
	 * @since 1.3.0
	 *
	 * @param array<array-key, mixed> $preferences     Tags the visitor gave,
	 *                                                 most authoritative
	 *                                                 first: query, cookie, …
	 * @param string                  $accept_language Raw `Accept-Language`
	 *                                                 value, '' for none.
	 *
	 * @return string An available locale.
	 */
	public static function pick( array $preferences, string $accept_language = '' ): string {
		return Locale::pick( $preferences, $accept_language, self::available(), self::$source );
	}

	/**
	 * The languages there are, for a language picker.
	 *
	 * Each named in itself — see Locale::endonym() — with a link to the
	 * same page in it and whether it is the one in use. How a page links to
	 * itself in another language (a query parameter, a path prefix, a
	 * subdomain) is the application's business, so it is asked of $url.
	 *
	 * Empty when there is only one language: a menu of one is not a choice,
	 * and an empty list lets a template draw nothing without counting.
	 *
	 * @since 1.3.0
	 *
	 * @param \Closure(string): string $url Given an available locale, the
	 *                                      link to this page in it.
	 *
	 * @return list<array{locale: string, name: string, url: string, current: bool}>
	 */
	public static function choices( \Closure $url ): array {
		$available = self::available();

		if ( count( $available ) < 2 ) {
			return array();
		}

		$current = self::locale();
		$choices = array();

		foreach ( $available as $locale ) {
			$choices[] = array(
				'locale'  => $locale,
				'name'    => Locale::endonym( $locale ),
				'url'     => $url( $locale ),
				'current' => $locale === $current,
			);
		}

		return $choices;
	}

	/**
	 * The locale in use.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public static function locale(): string {
		return self::$locale ?? self::$source;
	}

	/**
	 * The translator for the locale in use.
	 *
	 * @since 1.1.0
	 *
	 * @return Translator
	 */
	public static function translator(): Translator {
		return self::translator_for( self::locale() );
	}

	/**
	 * The translator for a locale, loaded once per process.
	 *
	 * Kept by normalised tag, so `de_DE`, `de-de` and `DE-DE` share one
	 * translator, and a long-running worker handed tags from outside
	 * keeps at most one per real locale rather than one per spelling.
	 *
	 * @since 1.1.0
	 *
	 * @param string $locale A supported locale.
	 *
	 * @return Translator
	 */
	public static function translator_for( string $locale ): Translator {
		$locale = Locale::normalise( $locale );

		return self::$loaded[ $locale ] ??= '' === self::$directory
			? new Translations( new Catalogue( $locale ) )
			: Translations::for( self::$directory, $locale );
	}

	/**
	 * Switch language for the rest of this scope.
	 *
	 * Inside within() the switch ends with the scope. For where the language
	 * is only known part-way through: a page that learns its order's
	 * language after it has found the order.
	 *
	 * @since 1.1.0
	 *
	 * @param string $tag Any tag. Unsupported means the source.
	 *
	 * @return void
	 */
	public static function use( string $tag ): void {
		self::$locale = self::supported( $tag ) ?: self::$source;
	}

	/**
	 * Do some work in a language, then put the previous one back.
	 *
	 * Restored in a `finally`, so work that throws cannot leave the next
	 * thing in the wrong language.
	 *
	 * @since 1.1.0
	 *
	 * @template T
	 *
	 * @param string        $tag  Any tag. Unsupported or empty means the
	 *                            source.
	 * @param callable(): T $work What to do.
	 *
	 * @return T
	 */
	public static function within( string $tag, callable $work ): mixed {
		$previous = self::$locale;

		self::use( $tag );

		try {
			return $work();
		} finally {
			self::$locale = $previous;
		}
	}

	/**
	 * Load the `__()`, `_n()` and `__html()` helpers.
	 *
	 * Opt-in rather than autoloaded: they are global functions, and an
	 * application inside WordPress already has a `__()`. Each one is defined
	 * only where nothing else has taken the name.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function helpers(): void {
		require_once __DIR__ . '/helpers.php';
	}

	/**
	 * Forget everything: the language, the memoised list, the translators.
	 *
	 * For tests, and for configure(). The directory and source stay as
	 * configure() left them when called from outside.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$locale    = null;
		self::$loaded    = array();
		self::$available = null;
	}
}
