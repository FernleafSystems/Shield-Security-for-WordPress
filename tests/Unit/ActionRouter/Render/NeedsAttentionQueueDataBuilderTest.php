<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones\ZoneRenderDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueueDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class NeedsAttentionQueueDataBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_uses_latest_completed_scan_timestamps_without_calling_overview() :void {
		UnitTestControllerFactory::install(
			null,
			null,
			(object)[
				'comps' => (object)[
					'site_query' => new class {
						public function attention() :array {
							return [
								'summary' => [
									'total' => 2,
									'severity' => 'warning',
									'is_all_clear' => false,
								],
								'groups' => [
									'scans' => [
										'zone' => 'scans',
										'total' => 2,
										'severity' => 'warning',
										'items' => [
											[
												'key' => 'plugin_files',
												'zone' => 'scans',
												'label' => 'Plugin Files',
												'count' => 2,
												'severity' => 'warning',
												'description' => 'Plugin files need review.',
												'href' => '/admin/scans',
												'action' => 'Review',
												'target' => '',
											],
										],
									],
								],
							];
						}

						public function latestCompletedScanTimestamps() :array {
							return [
								'malware' => 0,
								'vulnerabilities' => 0,
								'abandoned' => 0,
								'core_files' => 0,
								'plugin_files' => 0,
								'theme_files' => 0,
							];
						}

						public function overview() :array {
							throw new \LogicException( 'overview() should not be called.' );
						}
					},
				],
			]
		);

		$builder = new NeedsAttentionQueueDataBuilder();
		$this->setPrivateProperty( $builder, 'zoneRenderDataBuilder', new class extends ZoneRenderDataBuilder {
			public function getZoneSlugs() :array {
				return [ 'scans', 'maintenance' ];
			}

			public function getZonesIndexed() :array {
				return [
					'scans' => [
						'label' => 'Scans',
						'icon_class' => 'bi bi-shield',
					],
					'maintenance' => [
						'label' => 'Maintenance',
						'icon_class' => 'bi bi-tools',
					],
				];
			}
		} );

		$data = $builder->build();

		$this->assertSame( 2, $data[ 'vars' ][ 'total_items' ] );
		$this->assertSame( '', $data[ 'strings' ][ 'last_scan_subtext' ] );
		$this->assertSame( 'warning', $data[ 'vars' ][ 'overall_severity' ] );
		$this->assertSame( [ 'scans' ], \array_column( $data[ 'vars' ][ 'zone_groups' ], 'slug' ) );
		$this->assertSame( [ 'scans', 'maintenance' ], \array_column( $data[ 'vars' ][ 'zone_chips' ], 'slug' ) );
	}

	private function setPrivateProperty( object $subject, string $property, $value ) :void {
		$reflection = new \ReflectionProperty( $subject, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( $subject, $value );
	}
}
