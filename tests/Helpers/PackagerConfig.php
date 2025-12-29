<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

class PackagerConfig {

	/**
	 * Resolve Strauss version for tests.
	 * Priority: env SHIELD_STRAUSS_VERSION > config file > null.
	 */
	public static function getStraussVersion() :?string {
		$env = getenv( 'SHIELD_STRAUSS_VERSION' );
		if ( is_string( $env ) && $env !== '' ) {
			return ltrim( $env, "v \t\n\r\0\x0B" );
		}

		$root = self::getPluginRoot();
		$config = $root === '' ? null : $root.'/.github/config/packager.conf';
		if ( is_string( $config ) && file_exists( $config ) ) {
			$content = file_get_contents( $config );
			if ( is_string( $content ) ) {
				if ( preg_match( '/STRAUSS_VERSION\s*=\s*"?([^\s"]+)"?/i', $content, $m ) ) {
					return ltrim( $m[1], "v \t\n\r\0\x0B" );
				}
			}
		}

		return null;
	}

	private static function getPluginRoot() :string {
		$envPath = getenv( 'SHIELD_PACKAGE_PATH' );
		if ( is_string( $envPath ) && $envPath !== '' ) {
			return $envPath;
		}

		$fromTraitPerspective = realpath( __DIR__.'/../..' );
		if ( is_string( $fromTraitPerspective ) ) {
			return $fromTraitPerspective;
		}

		$cwd = getcwd();
		return is_string( $cwd ) ? $cwd : '';
	}
}

