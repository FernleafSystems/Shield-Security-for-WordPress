<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

/**
 * Integration tests for Shield-specific WordPress integration
 * 
 * This file should ONLY contain tests that verify Shield's actual functionality,
 * not generic WordPress capabilities.
 */
class WordPressHooksTest extends ShieldWordPressTestCase {

	public function testPluginLoadsOnPluginsLoaded() :void {
		// Test that Shield properly hooks into plugins_loaded
		$hookExists = has_action( 'plugins_loaded', 'icwp_wpsf_init' );
		$this->assertNotFalse( $hookExists, 'Shield plugin should hook into plugins_loaded' );
	}

	public function testShieldDoesNotRegisterCustomPostTypes() :void {
		// Shield is a security plugin and should not register custom post types
		$customPostTypes = get_post_types( [ '_builtin' => false ], 'names' );
		
		$shieldPostTypes = array_filter( $customPostTypes, function( $postType ) {
			return strpos( $postType, 'shield' ) !== false || strpos( $postType, 'icwp' ) !== false;
		} );
		
		$this->assertEmpty( $shieldPostTypes, 'Shield should not register custom post types (it\'s a security plugin, not content management)' );
	}
}