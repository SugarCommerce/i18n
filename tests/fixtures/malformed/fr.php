<?php
/**
 * French, written badly: values a catalogue cannot hold, beside ones it can.
 */

declare( strict_types=1 );

return array(
	'A number'   => 42,
	'A list'     => array( 'Un', 'Deux' ),
	'Nested'     => array(
		'one'   => array( 'Imbriqué' ),
		'other' => 'Imbriqués',
	),
	'Fine'       => 'Bien',
	'404'        => 'Introuvable',
);
