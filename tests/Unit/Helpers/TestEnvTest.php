<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestEnv;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class TestEnvTest extends TestCase {

	/**
	 * @var array<string,string|false>
	 */
	private array $originalEnv = [];

	protected function set_up() :void {
		parent::set_up();

		foreach ( [
			'SHIELD_TEST_VERBOSE',
			'SHIELD_DEBUG',
			'SHIELD_DEBUG_PATHS',
			'SHIELD_TEST_MODE',
			'CI',
			'GITHUB_ACTIONS',
		] as $name ) {
			$this->originalEnv[ $name ] = \getenv( $name );
			$this->unsetEnv( $name );
		}
	}

	protected function tear_down() :void {
		foreach ( $this->originalEnv as $name => $value ) {
			if ( $value === false ) {
				$this->unsetEnv( $name );
			}
			else {
				$this->setEnv( $name, $value );
			}
		}

		parent::tear_down();
	}

	public function testVerboseUsesCanonicalFlagAndAliases() :void {
		$this->assertFalse( TestEnv::isVerbose() );

		$this->setEnv( 'SHIELD_TEST_VERBOSE', '1' );
		$this->assertTrue( TestEnv::isVerbose() );
		$this->unsetEnv( 'SHIELD_TEST_VERBOSE' );

		$this->setEnv( 'SHIELD_DEBUG', '1' );
		$this->assertTrue( TestEnv::isVerbose() );
		$this->unsetEnv( 'SHIELD_DEBUG' );

		$this->setEnv( 'SHIELD_DEBUG_PATHS', '1' );
		$this->assertTrue( TestEnv::isVerbose() );
	}

	public function testPathDebugOnlyUsesExplicitPathDebugSignal() :void {
		$this->assertFalse( TestEnv::isPathDebug() );

		$this->setEnv( 'SHIELD_TEST_VERBOSE', '1' );
		$this->assertFalse( TestEnv::isPathDebug() );
		$this->unsetEnv( 'SHIELD_TEST_VERBOSE' );

		$this->setEnv( 'SHIELD_DEBUG', '1' );
		$this->assertFalse( TestEnv::isPathDebug() );
		$this->unsetEnv( 'SHIELD_DEBUG' );

		$this->setEnv( 'SHIELD_DEBUG_PATHS', '1' );
		$this->assertTrue( TestEnv::isPathDebug() );
	}

	public function testStrictFailureUsesExplicitModeAndFallbackSignals() :void {
		$this->setEnv( 'SHIELD_TEST_MODE', 'docker' );
		$this->assertTrue( TestEnv::shouldFailMissingWordPressEnv() );
		$this->unsetEnv( 'SHIELD_TEST_MODE' );

		$this->setEnv( 'CI', '1' );
		$this->assertTrue( TestEnv::shouldFailMissingWordPressEnv() );
		$this->unsetEnv( 'CI' );

		$this->setEnv( 'GITHUB_ACTIONS', '1' );
		$this->assertTrue( TestEnv::shouldFailMissingWordPressEnv() );
	}

	public function testNormalizePathForLogConvertsWindowsSeparators() :void {
		$this->assertSame(
			'C:/temp/shield/file.php',
			TestEnv::normalizePathForLog( 'C:\\temp\\shield\\file.php' )
		);
	}

	private function setEnv( string $name, string $value ) :void {
		\putenv( $name.'='.$value );
		$_ENV[ $name ] = $value;
		$_SERVER[ $name ] = $value;
	}

	private function unsetEnv( string $name ) :void {
		\putenv( $name );
		unset( $_ENV[ $name ], $_SERVER[ $name ] );
	}
}
