<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use PHPUnit\Framework\TestCase;

class PublicUpgradeUpdateProviderFixtureTest extends TestCase {

	use PluginPathsTrait;

	public function testUpdateProviderBuildsNativeUpdateTransientPayload() :void {
		require_once $this->getPluginFilePath( 'tests/fixtures/upgrade-public/update-provider.php' );

		$config = [
			'plugin'      => 'wp-simple-firewall/icwp-wpsf.php',
			'slug'        => 'wp-simple-firewall',
			'id'          => 'wp-simple-firewall',
			'new_version' => '9.8.7',
			'package'     => 'http://wordpress.test/wp-content/uploads/shield-package-runtime-test/wp-simple-firewall-current.zip',
			'url'         => 'https://wordpress.org/plugins/wp-simple-firewall/',
		];

		$transient = \shield_upgrade_test_apply_update_metadata( new \stdClass(), $config );

		$this->assertIsObject( $transient );
		$this->assertIsArray( $transient->response );
		$this->assertArrayHasKey( 'wp-simple-firewall/icwp-wpsf.php', $transient->response );
		$offer = $transient->response[ 'wp-simple-firewall/icwp-wpsf.php' ];
		$this->assertSame( 'wp-simple-firewall', $offer->slug );
		$this->assertSame( '9.8.7', $offer->new_version );
		$this->assertSame( $config[ 'package' ], $offer->package );
	}

	public function testUpdateProviderAllowsOnlyConfiguredPackageHostForSafeHttpRequests() :void {
		require_once $this->getPluginFilePath( 'tests/fixtures/upgrade-public/update-provider.php' );

		$this->assertTrue(
			\shield_upgrade_test_allow_package_host(
				false,
				'wordpress.test',
				'http://wordpress.test/wp-content/uploads/shield-package-runtime-test/wp-simple-firewall-current.zip',
				[
					'package' => 'http://wordpress.test/wp-content/uploads/shield-package-runtime-test/wp-simple-firewall-current.zip',
				]
			)
		);
		$this->assertFalse(
			\shield_upgrade_test_allow_package_host(
				false,
				'example.test',
				'http://example.test/wp-simple-firewall-current.zip',
				[
					'package' => 'http://wordpress.test/wp-content/uploads/shield-package-runtime-test/wp-simple-firewall-current.zip',
				]
			)
		);
	}
}
