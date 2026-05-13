<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueAllClearDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class ActionsQueueAllClearDataBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		UnitTestControllerFactory::install(
			null,
			null,
			(object)[
				'comps'  => (object)[],
				'db_con' => (object)[],
			]
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_produces_zone_chip_contract() :void {
		$data = ( new ActionsQueueAllClearDataBuilder() )->build( [
			'scans' => [
				'slug'  => 'scans',
				'label' => 'Scans',
			],
			'maintenance' => [
				'slug'  => 'maintenance',
				'label' => 'Maintenance',
			],
		] );

		$topLevelKeys = \array_keys( $data );
		\sort( $topLevelKeys );
		$this->assertSame( [ 'icon_class', 'subtitle', 'title', 'zone_chips' ], $topLevelKeys );
		$this->assertCount( 2, $data[ 'zone_chips' ] );
		foreach ( $data[ 'zone_chips' ] as $chip ) {
			$chipKeys = \array_keys( $chip );
			\sort( $chipKeys );
			$this->assertSame( [ 'icon_class', 'label', 'severity', 'slug' ], $chipKeys );
		}
		$this->assertSame( [ 'scans', 'maintenance' ], \array_column( $data[ 'zone_chips' ], 'slug' ) );
		$this->assertSame( [ 'good', 'good' ], \array_column( $data[ 'zone_chips' ], 'severity' ) );
	}
}
