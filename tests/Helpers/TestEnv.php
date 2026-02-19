<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

class TestEnv {

	/**
	 * @var string[]
	 */
	private const TRUTHY_VALUES = [ '1', 'true', 'yes', 'on' ];

	public static function isTruthy( string $name ) :bool {
		$value = \getenv( $name );
		if ( !\is_string( $value ) || $value === '' ) {
			return false;
		}
		return \in_array( \strtolower( $value ), self::TRUTHY_VALUES, true );
	}

	public static function isVerbose() :bool {
		return self::isTruthy( 'SHIELD_TEST_VERBOSE' )
			|| self::isTruthy( 'SHIELD_DEBUG' )
			|| self::isTruthy( 'SHIELD_DEBUG_PATHS' );
	}

	public static function isPathDebug() :bool {
		return self::isTruthy( 'SHIELD_DEBUG_PATHS' );
	}

	public static function isExplicitDockerMode() :bool {
		return \getenv( 'SHIELD_TEST_MODE' ) === 'docker';
	}

	public static function isDockerModeHeuristic() :bool {
		return \is_dir( '/tmp/wordpress' );
	}

	public static function shouldFailMissingWordPressEnv() :bool {
		if ( self::isExplicitDockerMode() ) {
			return true;
		}

		return self::isTruthy( 'CI' )
			|| self::isTruthy( 'GITHUB_ACTIONS' )
			|| self::isDockerModeHeuristic();
	}

	public static function normalizePathForLog( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}
}
