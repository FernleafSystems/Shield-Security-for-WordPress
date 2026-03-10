<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use Brain\Monkey\Functions;

trait BrainMonkeyWordPressTestFunctions {

	protected function registerWordPressPersistenceFunctionMocks() :void {
		$options = [];
		$siteOptions = [];
		$siteTransients = [];

		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'remove_all_filters' )->justReturn( true );

		Functions\when( 'get_option' )->alias(
			static function ( string $key, $default = false ) use ( &$options ) {
				return \array_key_exists( $key, $options ) ? $options[ $key ] : $default;
			}
		);
		Functions\when( 'add_option' )->alias(
			static function ( string $key, $value = '' ) use ( &$options ) :bool {
				if ( \array_key_exists( $key, $options ) ) {
					return false;
				}
				$options[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $key, $value ) use ( &$options ) :bool {
				$options[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( string $key ) use ( &$options ) :bool {
				$exists = \array_key_exists( $key, $options );
				unset( $options[ $key ] );
				return $exists;
			}
		);

		Functions\when( 'get_site_option' )->alias(
			static function ( string $key, $default = false ) use ( &$siteOptions ) {
				return \array_key_exists( $key, $siteOptions ) ? $siteOptions[ $key ] : $default;
			}
		);
		Functions\when( 'add_site_option' )->alias(
			static function ( string $key, $value = '' ) use ( &$siteOptions ) :bool {
				if ( \array_key_exists( $key, $siteOptions ) ) {
					return false;
				}
				$siteOptions[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'update_site_option' )->alias(
			static function ( string $key, $value ) use ( &$siteOptions ) :bool {
				$siteOptions[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_site_option' )->alias(
			static function ( string $key ) use ( &$siteOptions ) :bool {
				$exists = \array_key_exists( $key, $siteOptions );
				unset( $siteOptions[ $key ] );
				return $exists;
			}
		);

		Functions\when( 'get_site_transient' )->alias(
			static function ( string $key ) use ( &$siteTransients ) {
				return BrainMonkeyWordPressTestFunctions::readTransientStore( $siteTransients, $key );
			}
		);
		Functions\when( 'set_site_transient' )->alias(
			static function ( string $key, $value, int $expiration = 0 ) use ( &$siteTransients ) :bool {
				BrainMonkeyWordPressTestFunctions::writeTransientStore( $siteTransients, $key, $value, $expiration );
				return true;
			}
		);
		Functions\when( 'delete_site_transient' )->alias(
			static function ( string $key ) use ( &$siteTransients ) :bool {
				$exists = \array_key_exists( $key, $siteTransients );
				unset( $siteTransients[ $key ] );
				return $exists;
			}
		);
	}

	public static function readTransientStore( array &$store, string $key ) {
		if ( !\array_key_exists( $key, $store ) ) {
			return false;
		}

		$entry = $store[ $key ];
		$expiresAt = (int)( $entry[ 'expires_at' ] ?? 0 );
		if ( $expiresAt > 0 && $expiresAt <= \time() ) {
			unset( $store[ $key ] );
			return false;
		}

		return $entry[ 'value' ] ?? false;
	}

	public static function writeTransientStore( array &$store, string $key, $value, int $expiration ) :void {
		$store[ $key ] = [
			'value'      => $value,
			'expires_at' => $expiration > 0 ? \time() + \max( 0, $expiration ) : 0,
		];
	}
}
