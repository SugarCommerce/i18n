# i18n

Translation for plain PHP: English source strings as keys, CLDR plural categories, named placeholders, and `Accept-Language` negotiation. Catalogues are PHP arrays, so they are opcached and need no parser. Zero dependencies.

## Why

Three things go wrong when an application is translated after the fact.

**English's two plural forms are assumed to be universal.** They are not. Japanese has one form, French puts zero in the singular, Russian and Polish have four with *different* boundaries, Arabic has six. Asking a translator for "the plural" produces an interface that reads as broken.

**Sentences get assembled from fragments.** `'You already own ' . $list . '. Sign in to access ' . $noun` cannot be translated: a translator cannot reorder around a noun spliced into the middle, and languages that inflect it have nothing to work with.

**Positional placeholders lose their meaning.** `%s … %s` gives a translator no way to know which is which, and reordering a sentence — which they routinely must — silently swaps them.

## Features

- 🔑 **Source text as the key** — a missing translation degrades to readable English, never to `checkout.error.sold_out` leaking onto the page.
- 🔢 **CLDR plural categories** for the languages that need them.
- 🏷️ **Named placeholders** — `{name}`, safe to reorder.
- 🌐 **`Accept-Language` negotiation** with quality weights and regional fallback.
- 📦 **Catalogues are PHP arrays** — opcached, no parser, no build step.
- ↔️ **RTL detection**, because a translated Arabic page still reads as broken without `dir="rtl"`.
- 🗣️ **Languages named in themselves** for a picker — "Deutsch", not "German".
- 🎯 **A scoped request language** with opt-in `__()`, `_n()` and `__html()` helpers that never clash with WordPress's.
- 🔍 **String extraction and a catalogue report** — `vendor/bin/i18n-strings` finds what each catalogue is missing and fails the build on it.

## Requirements

PHP 8.5+

## Installation

```bash
composer require sugarcommerce/i18n
```

## Usage

```php
use SugarCommerce\I18n\Translations;

$t = Translations::for( __DIR__ . '/languages', 'de' );

$t->get( 'Your cart is empty.' );
$t->get( 'You already own {items}.', [ 'items' => 'Waveform Pro' ] );
$t->plural( '{count} item', '{count} items', 3 );   // {count} is supplied
```

A catalogue is `languages/de.php` returning an array:

```php
return [
    'Your cart is empty.' => 'Ihr Warenkorb ist leer.',

    // Plural entries are keyed by CLDR category.
    '{count} item' => [
        'one'   => '{count} Artikel',
        'other' => '{count} Artikel',
    ],
];
```

A catalogue is PHP, so loading one runs it: keep the directory out of reach of uploads and anything else a visitor or a translation service can write to. The file name is only ever built from a checked locale tag, never a path, and an entry that is not a string or an array of category => string is dropped as the file loads.

`de-AT` inherits everything from `de`, so a regional catalogue only lists what actually differs.

## Finding untranslated strings

```php
new Translations(
    Catalogue::load( $dir, $locale ),
    fn( string $text, string $locale ) => error_log( "untranslated [$locale]: $text" )
);
```

Better still, assert it in a test: collect the source strings your code passes and check each one exists in the catalogue. A missing translation is silent by design — it has to be, or one gap takes the page down — so something has to look.

`Extractor` collects them. It reads `__( '…' )`, `__html( '…' )` and `_n( '…', '…', … )` — literal strings only, comments and Blade `{{-- --}}` removed first — from PHP and template files:

```php
use SugarCommerce\I18n\{Catalogue, Diff, Extractor, Placeholders};

$code   = ( new Extractor() )->scan( [ __DIR__ . '/src', __DIR__ . '/templates' ] );
$report = Diff::report( $code, __DIR__ . '/lang', 'en' );
// [ 'en' => [ 'missing' => [...new in the code], 'unused' => [...] ],
//   'de' => [ 'missing' => [...to translate],    'unused' => [...] ], … ]

Placeholders::match( 'Order {reference}', 'Bestellung {referenz}' );   // false
```

Two steps, in the order a string travels: the code against the source catalogue (a string wrapped in the code and not listed there is new), then the source catalogue against each translation. `new Extractor( [ 't' ], [ 'tn' ] )` reads helpers with other names.

The same report from a terminal, exiting 1 when anything is missing so it can gate a build:

```bash
vendor/bin/i18n-strings --dir=lang --scan=src --scan=templates          # every catalogue
vendor/bin/i18n-strings --dir=lang --scan=src de fr                     # only these
vendor/bin/i18n-strings --dir=lang --scan=src --add                     # append new strings to en.php
vendor/bin/i18n-strings --dir=lang --scan=src --json
```

An application with its directories fixed can call `Cli::run( $argv, $lang_dir, $roots )` from a script of its own.

## The language of this request

```php
use SugarCommerce\I18n\Current;

Current::configure( __DIR__ . '/lang' );   // once, at boot
Current::helpers();                        // opt in to __(), _n(), __html()

Current::available();                      // [ 'en', 'de', 'pt-BR' ] — read from the directory
Current::supported( 'pt' );                // 'pt-BR'; '' for 'auto', 'xx' or garbage

Current::within( 'de', fn() => $mailer->send( $order ) );   // German, then back
echo __( 'Order {reference}', [ 'reference' => 'SC-7K2Q9X' ] );
echo _n( '{count} file', '{count} files', 3 );
echo __html( 'Read the {link}terms{/link}.', [ 'link' => '<a href="/terms">', '/link' => '</a>' ] );
```

`Current` is the one mutable, static class in the package, on purpose. A helper that had to be handed a translator would have to be handed one by every template, partial and email that prints a sentence, and the first one that forgot would print English in the middle of a German page. PHP serves one request per process, so the request's language is a fact about the process. The risk with process state is leaking it — a queue run that sends a German email leaving the next one in German — which is what `within()` is for: it scopes a language to a piece of work and restores the previous one in a `finally`.

The helpers are opt-in rather than autoloaded, and each is defined only if the name is free: WordPress already has a `__()`.

## Language names

```php
Locale::endonym( 'de' );      // 'Deutsch'
Locale::endonym( 'pt-BR' );   // 'Português (Brasil)'
Locale::match( 'de-AT', [ 'en', 'de' ] );   // 'de' — one tag, '' when nothing serves it
Locale::icu( 'pt-BR' );       // 'pt_BR', for IntlDateFormatter and NumberFormatter
```

A language picker names each language in itself, because the person looking for German is the one who cannot read the English word for it. A built-in table covers the common languages and the regional variants whose names differ; anything else is asked of ext-intl when it is loaded, and failing that the tag is returned. Something that is not a well-formed tag — markup, a path — gets `''`, so a tag from a query string cannot come back as a language's "name".

## Plurals

```php
use SugarCommerce\I18n\Plural;

Plural::category( 1, 'en' );     // 'one'
Plural::category( 0, 'fr' );     // 'one'  — French puts zero in the singular
Plural::category( 21, 'ru' );    // 'one'
Plural::category( 11, 'ru' );    // 'many' — the teens are the exception
Plural::category( 21, 'pl' );    // 'many' — Polish draws the line differently
Plural::category( 5, 'ja' );     // 'other' — no distinction at all

Plural::categories( 'ar' );      // the six an Arabic translator must fill
```

`categories()` is what a translation UI should ask for. Showing a Japanese translator two boxes, or a Russian one two boxes, is how the wrong form ends up shipped.

For a script that counts in the browser and so cannot ask the server, hand it every form and let it pick with `Intl.PluralRules`:

```php
Plural::example( 'few', 'pl' );   // 2 — the smallest count in that category

Plural::forms( 'pl', fn( int $n ) => $t->plural( '{count} file', '{count} files', $n, [ 'count' => '{count}' ] ) );
// [ 'one' => '{count} plik', 'few' => '{count} pliki', 'many' => '{count} plików' ]
```

## Locale negotiation

```php
use SugarCommerce\I18n\Locale;

Locale::negotiate( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', [ 'en', 'de', 'pt-BR' ] );

Locale::normalise( 'en_gb' );   // 'en-GB'
Locale::language( 'pt-BR' );    // 'pt'
Locale::is_rtl( 'ar' );         // true
```

Quality weights are honoured and beat header order, so `en;q=0.4,de;q=0.9` picks German. A request for `de-AT` falls back to `de` before moving down the list, and a request for `pt` is satisfied by `pt-BR` when that is all there is. A range weighted `q=0` is never chosen, and only the first 32 ranges of a header are read.

## Using your framework's translator instead

Implement `Translator` — three methods — and everything that takes one keeps working. That is the point of the interface: an application already inside Laravel or WordPress should not adopt a second translation system.

## Testing

```bash
composer install
composer test
```

112 tests — the CLDR categories for six languages including the Russian teens and the Polish boundary, plural examples and per-category forms, negotiation with quality weights and regional fallback, single-tag matching that tells "no preference" from the source, endonyms, placeholder reordering and checking, regional catalogue inheritance, the fallback-to-source guarantee, the request scope restoring its language after work that throws, the opt-in helpers, string extraction (comments, Blade comments, methods and look-alike names ignored), the catalogue diff, the report's text, JSON and `--add` output, and the hardening: tags that try to name a path, malformed catalogue entries, `q=0` and oversized headers, non-tags given no name, `PHP_INT_MIN` counts, and control characters kept off the terminal.

## License

GPL-2.0-or-later
