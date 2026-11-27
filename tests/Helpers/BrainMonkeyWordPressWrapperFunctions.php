<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use Brain\Monkey\Functions;

/**
 * Stubs for WordPress wrappers around PHP functions, for unit tests that run code using them without WordPress loaded.
 * They match WordPress for ordinary absolute URLs and paths, and wp_strip_all_tags() matches it fully. They skip
 * wp_parse_url()'s handling of relative and scheme-less URLs, and wp_delete_file()'s filter and return value.
 */
trait BrainMonkeyWordPressWrapperFunctions {

	protected function registerWordPressWrapperFunctions() :void {
		Functions\when( 'wp_parse_url' )->alias(
			static fn( string $url, int $component = -1 ) => \parse_url( $url, $component )
		);
		Functions\when( 'wp_strip_all_tags' )->alias(
			static fn( string $text ) :string => \trim( \strip_tags(
				(string)\preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text )
			) )
		);
		Functions\when( 'wp_delete_file' )->alias(
			static function ( string $file ) :void {
				@\unlink( $file );
			}
		);
	}
}
