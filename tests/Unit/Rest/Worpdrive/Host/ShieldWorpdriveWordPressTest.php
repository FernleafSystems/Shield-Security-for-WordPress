<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Host;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Host\ShieldWorpdriveWordPress;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures\{
	WorpdriveTestGeneral,
	WorpdriveTestPlugins,
	WorpdriveTestRequest,
	WorpdriveTestThemes
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\WorpdriveUnitTestCase;

class ShieldWorpdriveWordPressTest extends WorpdriveUnitTestCase {

	protected function setUp() :void {
		parent::setUp();

		ServicesState::mergeItems( [
			'service_request'    => new WorpdriveTestRequest(),
			'service_wpgeneral'  => new WorpdriveTestGeneral(),
			'service_wpplugins'  => new WorpdriveTestPlugins(),
			'service_wpthemes'   => new WorpdriveTestThemes(),
		] );
		Functions\when( 'is_plugin_active' )->alias( static fn( string $file ) :bool => $file === 'zeta/zeta.php' );
		Functions\when( 'rest_url' )->justReturn( 'https://wp.test/wp-json/' );
		Functions\when( 'content_url' )->justReturn( 'https://wp.test/wp-content' );
		Functions\when( 'get_locale' )->justReturn( 'en_GB' );
		Functions\when( 'wp_timezone_string' )->justReturn( 'Europe/London' );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'get_bloginfo' )->alias(
			static fn( string $key ) :string => $key === 'version' ? '6.8.1' : ''
		);
	}

	public function test_wordpress_adapter_preserves_plugin_and_theme_shapes() :void {
		$adapter = new ShieldWorpdriveWordPress();

		$this->assertSame(
			[
				[
					'name'    => 'Alpha',
					'version' => '1.0.0',
					'dir'     => 'alpha',
					'active'  => 0,
				],
				[
					'name'    => 'Single File',
					'version' => '',
					'dir'     => '.',
					'active'  => 0,
				],
				[
					'name'    => 'Zeta',
					'version' => '2.0.0',
					'dir'     => 'zeta',
					'active'  => 1,
				],
			],
			$adapter->plugins()
		);

		$this->assertSame(
			[
				[
					'name'    => 'Alpha Theme',
					'dir'     => 'alpha-theme',
					'version' => '1.0.0',
					'active'  => 0,
				],
				[
					'name'    => 'Zeta Theme',
					'dir'     => 'zeta-theme',
					'version' => '2.0.0',
					'active'  => 1,
				],
			],
			$adapter->themes()
		);
	}

	public function test_wordpress_adapter_preserves_shield_url_and_environment_sources() :void {
		$adapter = new ShieldWorpdriveWordPress();

		$this->assertSame( 'https://home.test/', $adapter->homeUrl() );
		$this->assertSame( 'https://wp.test/', $adapter->wpUrl() );
		$this->assertSame( 'https://wp.test/wp-json/', $adapter->restUrl() );
		$this->assertSame( 'https://wp.test/wp-content', $adapter->contentUrl() );
		$this->assertSame( 'en_GB', $adapter->locale() );
		$this->assertSame( 'Europe/London', $adapter->timezoneString() );
		$this->assertFalse( $adapter->isMultisite() );
		$this->assertSame( '6.8.1', $adapter->version() );
		$this->assertSame( ABSPATH.'index.php', $adapter->scriptFilename() );
	}
}
