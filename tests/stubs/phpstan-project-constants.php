<?php declare( strict_types=1 );

if ( !\defined( 'DB_HOST' ) ) {
	\define( 'DB_HOST', 'localhost' );
}
if ( !\defined( 'DB_NAME' ) ) {
	\define( 'DB_NAME', 'wordpress' );
}
if ( !\defined( 'DB_USER' ) ) {
	\define( 'DB_USER', 'wordpress' );
}
if ( !\defined( 'DB_PASSWORD' ) ) {
	\define( 'DB_PASSWORD', '' );
}

if ( !\defined( 'LOGGED_IN_COOKIE' ) ) {
	\define( 'LOGGED_IN_COOKIE', 'wordpress_logged_in_' );
}
if ( !\defined( 'WPINC' ) ) {
	\define( 'WPINC', 'wp-includes' );
}

if ( !\defined( 'PCLZIP_OPT_REMOVE_PATH' ) ) {
	\define( 'PCLZIP_OPT_REMOVE_PATH', 77001 );
}

if ( !\defined( 'CFCORE_VER' ) ) {
	\define( 'CFCORE_VER', '0.0.0' );
}
if ( !\defined( 'GROUNDHOGG_VERSION' ) ) {
	\define( 'GROUNDHOGG_VERSION', '0.0.0' );
}
if ( !\defined( 'WPSC_VERSION' ) ) {
	\define( 'WPSC_VERSION', '0.0.0' );
}
if ( !\defined( 'WEFORMS_VERSION' ) ) {
	\define( 'WEFORMS_VERSION', '0.0.0' );
}
if ( !\defined( 'LLMS_VERSION' ) ) {
	\define( 'LLMS_VERSION', '0.0.0' );
}
