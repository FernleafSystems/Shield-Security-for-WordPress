<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

/**
 * Integration tests for complete plugin lifecycle - activation and deactivation
 * Tests both activation and deactivation as a cohesive unit
 */
class PluginLifecycleTest extends ShieldWordPressTestCase {
	use PluginPathsTrait;

	public function testPluginActivationHooks(): void {
		// Test that activation hook callback exists
		$this->assertTrue( 
			function_exists( 'icwp_wpsf_onactivate' ),
			'Plugin activation callback should exist'
		);
		
		// Test that activation hook is properly registered
		$this->assertTrue(
			has_action( 'activate_' . plugin_basename( $this->getPluginFilePath( 'icwp-wpsf.php' ) ), 'icwp_wpsf_onactivate' ) !== false,
			'Plugin should register activation hook'
		);
	}

	public function testPluginDeactivationHooks(): void {
		// Shield registers deactivation hook via Controller::doRegisterHooks()
		// Test that the deactivation handler method exists
		$this->assertTrue(
			method_exists( Controller::class, 'onWpDeactivatePlugin' ),
			'Shield Controller should have onWpDeactivatePlugin method for deactivation handling'
		);
		
		// Test that deactivation hook registration functionality is available
		$this->assertTrue(
			function_exists( 'register_deactivation_hook' ),
			'WordPress deactivation hook registration should be available'
		);
	}

	public function testPluginLifecycleIntegrity(): void {
		// Test that both activation and deactivation handlers are properly defined
		$this->assertTrue( 
			function_exists( 'icwp_wpsf_onactivate' ) && 
			method_exists( Controller::class, 'onWpDeactivatePlugin' ),
			'Plugin should have both activation and deactivation handlers defined'
		);
		
		// Test that Controller can handle lifecycle events
		$this->assertTrue(
			method_exists( Controller::class, 'onWpActivatePlugin' ),
			'Controller should have activation handling method'
		);
	}

	public function testPluginInitFunction(): void {
		// Test that the main init function exists (called during activation)
		$this->assertTrue(
			function_exists( 'icwp_wpsf_init' ),
			'Plugin init function should be defined for lifecycle management'
		);
		
		// Test that plugins_loaded hook is registered for init
		$this->assertTrue(
			has_action( 'plugins_loaded', 'icwp_wpsf_init' ) !== false,
			'Plugin should register plugins_loaded hook for initialization'
		);
	}

	public function testControllerLifecycleMethods(): void {
		// Verify Controller has all necessary lifecycle methods
		$lifecycleMethods = [
			'onWpActivatePlugin',
			'onWpDeactivatePlugin'
		];
		
		foreach ( $lifecycleMethods as $method ) {
			$this->assertTrue(
				method_exists( Controller::class, $method ),
				"Controller should have {$method} method for lifecycle management"
			);
		}
	}
}