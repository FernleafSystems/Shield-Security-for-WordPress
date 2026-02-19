<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

/**
 * Base test case for Shield WordPress integration tests.
 */
abstract class ShieldWordPressTestCase extends \WP_UnitTestCase {

	protected function isVerbose() :bool {
		return \function_exists( 'shield_test_verbose' ) && \shield_test_verbose();
	}

	protected function verboseLog( string $message ) :void {
		if ( $this->isVerbose() ) {
			echo $message.\PHP_EOL;
		}
	}

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();

		$this->verboseLog( '=== Shield WordPress TestCase Setup ===' );
		$this->verboseLog( 'WordPress environment ready (guaranteed by WP_UnitTestCase)' );

		$this->assertTrue( \class_exists( 'ICWP_WPSF_Shield_Security' ), 'Shield main class should be loaded' );
		$this->verboseLog( 'Shield main class loaded' );

		$this->assertTrue(
			\class_exists( 'FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller' ),
			'Shield Controller class should be loaded'
		);
		$this->verboseLog( 'Shield Controller class loaded' );

		$packagePath = \getenv( 'SHIELD_PACKAGE_PATH' );
		if ( $packagePath !== false && !empty( $packagePath ) ) {
			$this->verboseLog( 'Package testing mode - SHIELD_PACKAGE_PATH: '.$packagePath );
			$this->assertDirectoryExists( $packagePath, 'Package directory should exist' );
		}
		else {
			$this->verboseLog( 'Source testing mode' );
		}

		$this->verboseLog( '=== Shield WordPress TestCase Setup Complete ===' );
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down() {
		$this->verboseLog( '=== Shield WordPress TestCase Teardown ===' );

		$this->cleanUpShieldTestData();
		$this->verboseLog( 'Shield test data cleaned up' );

		parent::tear_down();

		$this->verboseLog( '=== Shield WordPress TestCase Teardown Complete ===' );
	}

	/**
	 * Clean up Shield-specific test data.
	 */
	protected function cleanUpShieldTestData() {
		global $wpdb;

		if ( isset( $wpdb ) && $wpdb instanceof \wpdb ) {
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'icwp_%'" );
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'shield_%'" );
		}
	}

	/**
	 * Assert that Shield plugin is properly loaded and functional.
	 */
	protected function assertShieldIsLoaded() {
		$this->assertTrue( \class_exists( 'ICWP_WPSF_Shield_Security' ), 'Shield main class should be loaded' );
		$this->assertTrue(
			\class_exists( 'FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller' ),
			'Shield Controller class should be loaded'
		);

		$this->verboseLog( 'Shield plugin verified as loaded' );
	}

	/**
	 * Get Shield plugin instance if available.
	 */
	protected function getShieldPlugin() {
		if ( \function_exists( 'shield_security_get_plugin' ) ) {
			return \shield_security_get_plugin();
		}

		return null;
	}

	/**
	 * Get the Shield plugin controller instance.
	 */
	protected static function con() {
		if ( \function_exists( 'shield_security_get_plugin' ) ) {
			$plugin = \shield_security_get_plugin();
			if ( $plugin && \method_exists( $plugin, 'getController' ) ) {
				return $plugin->getController();
			}
		}
		return null;
	}

	/**
	 * Output debug information about the test environment.
	 */
	protected function debugTestEnvironment() {
		$this->verboseLog( '=== Test Environment Debug Info ===' );
		$this->verboseLog( 'PHP Version: '.\PHP_VERSION );
		$this->verboseLog( 'WordPress Version: '.( \function_exists( 'get_bloginfo' ) ? \get_bloginfo( 'version' ) : 'Unknown' ) );
		$this->verboseLog( 'Package Path: '.( \getenv( 'SHIELD_PACKAGE_PATH' ) ?: 'Not set (source testing)' ) );
		$this->verboseLog( 'Shield Plugin Dir: '.( \defined( 'ICWP_WPSF_FULL_PATH' ) ? \ICWP_WPSF_FULL_PATH : 'Not defined' ) );
		$this->verboseLog( '=== End Debug Info ===' );
	}
}
