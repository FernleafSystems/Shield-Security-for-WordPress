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

	public function test_build_produces_expected_title_subtitle_and_zone_chips() :void {
		$data = ( new ActionsQueueAllClearDataBuilder() )->build( [
			'scans' => [
				'label' => 'Scans',
			],
			'maintenance' => [
				'label' => 'Maintenance',
			],
		] );

		$this->assertNotSame( '', \trim( $data[ 'title' ] ) );
		$this->assertNotSame( '', \trim( $data[ 'subtitle' ] ) );
		$this->assertSame( 'bi bi-shield-check', $data[ 'icon_class' ] );
		$this->assertSame( [ 'scans', 'maintenance' ], \array_column( $data[ 'zone_chips' ], 'slug' ) );
		$this->assertSame( [ 'Scans', 'Maintenance' ], \array_column( $data[ 'zone_chips' ], 'label' ) );
		$this->assertSame( [ 'good', 'good' ], \array_column( $data[ 'zone_chips' ], 'severity' ) );
	}
}
