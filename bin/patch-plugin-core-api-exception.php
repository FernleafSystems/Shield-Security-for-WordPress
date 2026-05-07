<?php declare( strict_types=1 );

$file = \dirname( __DIR__ ).'/vendor/fernleafsystems/wordpress-plugin-core/src/Rest/Exceptions/ApiException.php';

if ( !\is_file( $file ) ) {
	return;
}

$contents = (string)\file_get_contents( $file );
$search = 'int $subCode = 0, \Throwable $previous = null';
$replace = 'int $subCode = 0, ?\Throwable $previous = null';

if ( \strpos( $contents, $replace ) !== false ) {
	return;
}

$patched = \str_replace( $search, $replace, $contents, $count );
if ( $count !== 1 ) {
	\fwrite( \STDERR, "Unable to patch wordpress-plugin-core ApiException signature.\n" );
	exit( 1 );
}

\file_put_contents( $file, $patched );
