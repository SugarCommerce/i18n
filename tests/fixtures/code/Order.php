<?php
/**
 * Fixture code for the extractor. Not loaded.
 *
 * A docblock showing __( 'Not a real string' ) is not a string to translate.
 */

// Nor is a comment: __( 'Commented out' ).

$heading = __( 'Order {reference}', array( 'reference' => $reference ) );
$count   = _n( '{count} file', '{count} files', $files );
$terms   = __html( "Read the {link}terms{/link}." );
$quoted  = __( 'It\'s here' );

// Methods and other functions that happen to end in the same letters.
$object->__( 'A method' );
Foo::__( 'A static method' );
my__( 'Somebody else\'s helper' );
$dynamic = __( $variable );
