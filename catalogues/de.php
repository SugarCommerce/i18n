<?php
/**
 * German translations.
 *
 * Keys are the English source strings. A plural entry is keyed by CLDR
 * category — German uses `one` and `other`.
 *
 * @package SugarCommerce\I18n
 */

declare( strict_types=1 );

return array(

	'You already own {items}.' => 'Sie besitzen {items} bereits.',

	'Sign in to your account to access it — no need to buy again.'
		=> 'Melden Sie sich in Ihrem Konto an, um darauf zuzugreifen — ein erneuter Kauf ist nicht nötig.',

	'{item} is sold out — please remove it to continue.'
		=> '{item} ist ausverkauft — bitte entfernen Sie es, um fortzufahren.',

	'Your cart is empty — some items may no longer be available.'
		=> 'Ihr Warenkorb ist leer — einige Artikel sind möglicherweise nicht mehr verfügbar.',

	'Everything in your cart must be in the same currency.'
		=> 'Alle Artikel in Ihrem Warenkorb müssen dieselbe Währung haben.',

	'A subscription has to be bought on its own — remove the other items and try again.'
		=> 'Ein Abonnement muss separat gekauft werden — entfernen Sie die anderen Artikel und versuchen Sie es erneut.',

	// Plural: German distinguishes only one and other.
	'Only {count} left — reduce the quantity to continue.' => array(
		'one'   => 'Nur noch {count} verfügbar — bitte reduzieren Sie die Menge.',
		'other' => 'Nur noch {count} verfügbar — bitte reduzieren Sie die Menge.',
	),
);
