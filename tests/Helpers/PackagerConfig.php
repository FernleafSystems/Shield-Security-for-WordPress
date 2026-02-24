<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use Symfony\Component\Filesystem\Path;

class PackagerConfig {

	/**
	 * Resolve Strauss version for tests.
	 * Priority: env SHIELD_STRAUSS_VERSION > config file > null.
	 */
	public static function getStraussVersion() :?string {
		$env = getenv( 'SHIELD_STRAUSS_VERSION' );
		if ( is_string( $env ) && $env !== '' ) {
			return ltrim( trim( $env ), 'v' );
		}

		$value = self::getConfigValue( 'STRAUSS_VERSION' );
		return $value !== null ? ltrim( $value, 'v' ) : null;
	}

	/**
	 * Resolve custom Strauss fork repo URL.
	 * Priority: env SHIELD_STRAUSS_FORK_REPO > config file > null.
	 */
	public static function getStraussForkRepo() :?string {
		$env = getenv( 'SHIELD_STRAUSS_FORK_REPO' );
		if ( is_string( $env ) && $env !== '' ) {
			return trim( $env );
		}

		$value = self::getConfigValue( 'STRAUSS_FORK_REPO' );
		return $value !== null ? trim( $value ) : null;
	}

	/**
	 * Extract a value from packager.conf.
	 * Simple approach: find line starting with KEY=, get everything after =, trim quotes.
	 */
	private static function getConfigValue( string $key ) :?string {
		$root = self::getPluginRoot();
		if ( $root === '' ) {
			return null;
		}

		$configPath = Path::normalize( Path::join( $root, '.github', 'config', 'packager.conf' ) );
		if ( !file_exists( $configPath ) ) {
			return null;
		}

		$lines = file( $configPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( $lines === false ) {
			return null;
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Skip comments
			if ( $line === '' || $line[0] === '#' ) {
				continue;
			}

			// Look for KEY= at the start of the line
			if ( strpos( $line, $key.'=' ) === 0 ) {
				$value = substr( $line, strlen( $key ) + 1 );
				// Trim quotes and whitespace
				return trim( $value, " \t\n\r\0\x0B\"'" );
			}
		}

		return null;
	}

	private static function getPluginRoot() :string {
		$envPath = getenv( 'SHIELD_PACKAGE_PATH' );
		if ( is_string( $envPath ) && $envPath !== '' ) {
			return Path::normalize( $envPath );
		}

		$fromTraitPerspective = realpath( Path::join( __DIR__, '../..' ) );
		if ( is_string( $fromTraitPerspective ) ) {
			return Path::normalize( $fromTraitPerspective );
		}

		$cwd = getcwd();
		return is_string( $cwd ) ? Path::normalize( $cwd ) : '';
	}
}
