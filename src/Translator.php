<?php
/**
 * Translation contract.
 *
 * @package   SugarMill\I18n
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarMill\I18n;

/**
 * Interface Translator
 *
 * Deliberately small, so an application already inside a framework can
 * satisfy it with a one-line adapter over `__()`, `trans()` or gettext
 * rather than adopting a second translation system.
 *
 * Implementations **must always return something printable**. A missing
 * translation should fall back to the English source, never to an empty
 * string — a blank button is worse than an untranslated one.
 *
 * @since 1.0.0
 */
interface Translator {

	/**
	 * Translate a phrase.
	 *
	 * @since 1.0.0
	 *
	 * @param string                $text        English source text, which
	 *                                           doubles as the lookup key.
	 * @param array<string, string> $replacements Named placeholders, e.g.
	 *                                           `[ 'name' => 'Dave' ]` for
	 *                                           `Hello {name}`.
	 *
	 * @return string
	 */
	public function get( string $text, array $replacements = array() ): string;

	/**
	 * Translate with a count, choosing the right plural form.
	 *
	 * @since 1.0.0
	 *
	 * @param string                $singular     English singular.
	 * @param string                $plural       English plural.
	 * @param int                   $count        How many.
	 * @param array<string, string> $replacements Named placeholders.
	 *                                            `{count}` is supplied.
	 *
	 * @return string
	 */
	public function plural( string $singular, string $plural, int $count, array $replacements = array() ): string;

	/**
	 * The locale in use.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function locale(): string;
}
