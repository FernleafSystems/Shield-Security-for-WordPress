<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

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

	public function test_build_counts_plugin_tab_from_queue_visible_asset_summaries() :void {
		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [ 'countAffectedPluginAssets' ] )
					   ->getMock();
		$counts->method( 'countAffectedPluginAssets' )->willReturn( 4 );

		$availability = new class extends ScansResultsRailTabAvailability {
			public function build( string $tabKey ) :array {
				return $tabKey === 'plugins'
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
		$this->setPrivateProperty( $builder, 'tabAvailability', $availability );
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );

		$state = $builder->build();

		$this->assertSame( 4, $state[ 'tabs' ][ 'plugins' ][ 'count' ] );
		$this->assertSame( 4, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( [ 'plugin_files' ], \array_column( $state[ 'rows' ], 'key' ) );
	}

	public function test_build_routes_abandoned_assets_to_separate_abandoned_tab_and_deduped_summary() :void {
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
				return \in_array( $tabKey, [ 'vulnerabilities', 'abandoned' ], true )
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

		$this->assertSame( 0, $state[ 'tabs' ][ 'vulnerabilities' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'vulnerabilities' ][ 'status' ] );
		$this->assertSame( 2, $state[ 'tabs' ][ 'abandoned' ][ 'count' ] );
		$this->assertSame( 'critical', $state[ 'tabs' ][ 'abandoned' ][ 'status' ] );
		$this->assertSame( 2, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( 'critical', $state[ 'tabs' ][ 'summary' ][ 'status' ] );
		$this->assertSame( 'critical', $state[ 'rail_accent_status' ] );
		$this->assertSame( [ 'abandoned' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 'critical', $state[ 'rows' ][ 0 ][ 'severity' ] );
		$this->assertSame( '/admin/scans/overview?zone=scans', $state[ 'rows' ][ 0 ][ 'href' ] );
	}

	public function test_build_keeps_summary_vulnerability_count_deduped_when_same_asset_is_vulnerable_and_abandoned() :void {
		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [
						   'countDistinctVulnerableAssets',
						   'countDistinctAbandonedAssets',
						   'countDistinctVulnerabilityReviewAssets',
					   ] )
					   ->getMock();
		$counts->method( 'countDistinctVulnerableAssets' )->willReturn( 1 );
		$counts->method( 'countDistinctAbandonedAssets' )->willReturn( 1 );
		$counts->method( 'countDistinctVulnerabilityReviewAssets' )->willReturn( 1 );

		$availability = new class extends ScansResultsRailTabAvailability {
			public function build( string $tabKey ) :array {
				return \in_array( $tabKey, [ 'vulnerabilities', 'abandoned' ], true )
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

		$this->assertSame( 1, $state[ 'tabs' ][ 'vulnerabilities' ][ 'count' ] );
		$this->assertSame( 1, $state[ 'tabs' ][ 'abandoned' ][ 'count' ] );
		$this->assertSame( 1, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( [ 'vulnerable_assets', 'abandoned' ], \array_column( $state[ 'rows' ], 'key' ) );
	}

	private function setPrivateProperty( object $subject, string $property, $value ) :void {
		$reflection = new \ReflectionProperty( $subject, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( $subject, $value );
	}
}
