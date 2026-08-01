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

## Requirements

PHP 8.5+

## Installation

```bash
composer require sugarmill/i18n
```

## Usage

```php
use SugarMill\I18n\Translations;

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

`de-AT` inherits everything from `de`, so a regional catalogue only lists what actually differs.

## Finding untranslated strings

```php
new Translations(
    Catalogue::load( $dir, $locale ),
    fn( string $text, string $locale ) => error_log( "untranslated [$locale]: $text" )
);
```

Better still, assert it in a test: collect the source strings your code passes and check each one exists in the catalogue. A missing translation is silent by design — it has to be, or one gap takes the page down — so something has to look.

## Plurals

```php
use SugarMill\I18n\Plural;

Plural::category( 1, 'en' );     // 'one'
Plural::category( 0, 'fr' );     // 'one'  — French puts zero in the singular
Plural::category( 21, 'ru' );    // 'one'
Plural::category( 11, 'ru' );    // 'many' — the teens are the exception
Plural::category( 21, 'pl' );    // 'many' — Polish draws the line differently
Plural::category( 5, 'ja' );     // 'other' — no distinction at all

Plural::categories( 'ar' );      // the six an Arabic translator must fill
```

`categories()` is what a translation UI should ask for. Showing a Japanese translator two boxes, or a Russian one two boxes, is how the wrong form ends up shipped.

## Locale negotiation

```php
use SugarMill\I18n\Locale;

Locale::negotiate( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', [ 'en', 'de', 'pt-BR' ] );

Locale::normalise( 'en_gb' );   // 'en-GB'
Locale::language( 'pt-BR' );    // 'pt'
Locale::is_rtl( 'ar' );         // true
```

Quality weights are honoured and beat header order, so `en;q=0.4,de;q=0.9` picks German. A request for `de-AT` falls back to `de` before moving down the list, and a request for `pt` is satisfied by `pt-BR` when that is all there is.

## Using your framework's translator instead

Implement `Translator` — three methods — and everything that takes one keeps working. That is the point of the interface: an application already inside Laravel or WordPress should not adopt a second translation system.

## Testing

```bash
composer install
composer test
```

47 tests — the CLDR categories for six languages including the Russian teens and the Polish boundary, negotiation with quality weights and regional fallback, placeholder reordering, regional catalogue inheritance, and the fallback-to-source guarantee.

## License

GPL-2.0-or-later
