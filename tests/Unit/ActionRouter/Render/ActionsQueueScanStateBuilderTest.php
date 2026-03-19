<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueScanStateBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScansResultsRailTabAvailability;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};

class ActionsQueueScanStateBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		UnitTestControllerFactory::install( new UnitTestPluginUrls() );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_routes_abandoned_assets_to_critical_vulnerabilities_state() :void {
		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [
						   'countDistinctVulnerableAssets',
						   'countDistinctAbandonedAssets',
						   'countDistinctVulnerabilityReviewAssets',
					   ] )
					   ->getMock();
		$counts->method( 'countDistinctVulnerableAssets' )->willReturn( 0 );
		$counts->method( 'countDistinctAbandonedAssets' )->willReturn( 2 );
		$counts->method( 'countDistinctVulnerabilityReviewAssets' )->willReturn( 2 );

		$availability = new class extends ScansResultsRailTabAvailability {
			public function build( string $tabKey ) :array {
				return $tabKey === 'vulnerabilities'
					? [
						'is_available'          => true,
						'show_in_actions_queue' => true,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					]
					: [
						'is_available'          => false,
						'show_in_actions_queue' => false,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					];
			}
		};

		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );
		$this->setPrivateProperty( $builder, 'tabAvailability', $availability );

		$state = $builder->build();

		$this->assertSame( 2, $state[ 'tabs' ][ 'vulnerabilities' ][ 'count' ] );
		$this->assertSame( 'critical', $state[ 'tabs' ][ 'vulnerabilities' ][ 'status' ] );
		$this->assertSame( 'critical', $state[ 'tabs' ][ 'summary' ][ 'status' ] );
		$this->assertSame( 'critical', $state[ 'rail_accent_status' ] );
		$this->assertSame( [ 'abandoned' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 'critical', $state[ 'rows' ][ 0 ][ 'severity' ] );
		$this->assertSame( '/admin/scans/overview?zone=scans', $state[ 'rows' ][ 0 ][ 'href' ] );
	}

	private function setPrivateProperty( object $subject, string $property, $value ) :void {
		$reflection = new \ReflectionProperty( $subject, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( $subject, $value );
	}
}
