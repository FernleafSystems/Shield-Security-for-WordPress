#!/usr/bin/env php
<?php declare( strict_types=1 );

$args = \array_slice( $_SERVER[ 'argv' ] ?? [], 1 );
if ( \in_array( '--help', $args, true ) ) {
	echo "Usage: php .github/scripts/prepare-packagist-composer-auth.php [--check-only]\n";
	echo "Prepares COMPOSER_AUTH for Private Packagist using PACKAGIST_TOKEN.\n";
	exit( 0 );
}

$token = \getenv( 'PACKAGIST_TOKEN' );
if ( !\is_string( $token ) || \trim( $token ) === '' ) {
	\fwrite(
		\STDERR,
		"Missing PACKAGIST_TOKEN. Private Packagist Composer access for repo.packagist.com must be configured before Composer install/update.\n"
	);
	exit( 2 );
}

$token = \trim( $token );
if ( \strpbrk( $token, "\r\n" ) !== false ) {
	\fwrite( \STDERR, "PACKAGIST_TOKEN must be a single-line Private Packagist token.\n" );
	exit( 2 );
}

if ( \in_array( '--check-only', $args, true ) ) {
	echo "Private Packagist Composer token is available.\n";
	exit( 0 );
}

$githubEnv = \getenv( 'GITHUB_ENV' );
if ( !\is_string( $githubEnv ) || \trim( $githubEnv ) === '' ) {
	\fwrite( \STDERR, "GITHUB_ENV is not set; cannot persist COMPOSER_AUTH for GitHub Actions.\n" );
	exit( 2 );
}

$composerAuth = \json_encode(
	[
		'http-basic' => [
			'repo.packagist.com' => [
				'username' => 'token',
				'password' => $token,
			],
		],
	],
	\JSON_UNESCAPED_SLASHES
);

if ( $composerAuth === false ) {
	\fwrite( \STDERR, "Failed to encode COMPOSER_AUTH for Private Packagist.\n" );
	exit( 1 );
}

$written = \file_put_contents( $githubEnv, 'COMPOSER_AUTH='.$composerAuth.\PHP_EOL, \FILE_APPEND | \LOCK_EX );
if ( $written === false ) {
	\fwrite( \STDERR, "Failed to write COMPOSER_AUTH to GITHUB_ENV.\n" );
	exit( 1 );
}

echo "Private Packagist Composer auth prepared for repo.packagist.com.\n";
