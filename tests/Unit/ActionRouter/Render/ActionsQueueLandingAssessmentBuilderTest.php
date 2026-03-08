<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueLandingAssessmentBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class ActionsQueueLandingAssessmentBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_groups_rows_by_zone_and_filters_unavailable_definitions() :void {
		$builder = new ActionsQueueLandingAssessmentBuilderTestDouble(
			[
				[
					'key'                   => 'wp_files',
					'zone'                  => 'scans',
					'component_class'       => 'scan-results-core',
					'availability_strategy' => 'enabled',
				],
				[
					'key'                   => 'wp_updates',
					'zone'                  => 'maintenance',
					'component_class'       => 'wp-updates',
					'availability_strategy' => 'always',
				],
				[
					'key'                   => 'abandoned',
					'zone'                  => 'scans',
					'component_class'       => 'scan-results-apc',
					'availability_strategy' => 'disabled',
				],
			],
			[
				'enabled' => true,
				'always'  => true,
				'disabled' => false,
			],
			[
				'scan-results-core' => [
					'title'          => 'WordPress Core Files',
					'desc_protected' => 'All WordPress Core files appear to be clean and unmodified.',
					'desc_unprotected' => 'At least 1 WordPress Core file appears to be modified or unrecognised.',
					'is_protected'   => true,
					'is_critical'    => true,
					'is_applicable'  => true,
				],
				'wp-updates' => [
					'title'          => 'WordPress Version',
					'desc_protected' => 'WordPress has all available upgrades applied.',
					'desc_unprotected' => 'There is an upgrade available for WordPress.',
					'is_protected'   => false,
					'is_critical'    => false,
					'is_applicable'  => true,
				],
			]
		);

		$rows = $builder->build();

		$this->assertSame( [ 'scans', 'maintenance' ], \array_keys( $rows ) );
		$this->assertSame( [ 'wp_files' ], \array_column( $rows[ 'scans' ], 'key' ) );
		$this->assertSame( [ 'wp_updates' ], \array_column( $rows[ 'maintenance' ], 'key' ) );
		$this->assertSame( 'good', $rows[ 'scans' ][ 0 ][ 'status' ] ?? '' );
		$this->assertSame( 'Good', $rows[ 'scans' ][ 0 ][ 'status_label' ] ?? '' );
		$this->assertSame( 'bi bi-check-circle-fill', $rows[ 'scans' ][ 0 ][ 'status_icon_class' ] ?? '' );
		$this->assertSame( 'warning', $rows[ 'maintenance' ][ 0 ][ 'status' ] ?? '' );
		$this->assertSame( 'Warning', $rows[ 'maintenance' ][ 0 ][ 'status_label' ] ?? '' );
		$this->assertSame( 'bi bi-exclamation-circle-fill', $rows[ 'maintenance' ][ 0 ][ 'status_icon_class' ] ?? '' );
	}

	public function test_build_skips_non_applicable_components() :void {
		$builder = new ActionsQueueLandingAssessmentBuilderTestDouble(
			[
				[
					'key'                   => 'wp_updates',
					'zone'                  => 'maintenance',
					'component_class'       => 'wp-updates',
					'availability_strategy' => 'always',
				],
			],
			[
				'always' => true,
			],
			[
				'wp-updates' => [
					'title'          => 'WordPress Version',
					'desc_protected' => 'WordPress has all available upgrades applied.',
					'desc_unprotected' => 'There is an upgrade available for WordPress.',
					'is_protected'   => true,
					'is_critical'    => false,
					'is_applicable'  => false,
				],
			]
		);

		$this->assertSame( [], $builder->build() );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		PluginControllerInstaller::install( $controller );
	}
}

class ActionsQueueLandingAssessmentBuilderTestDouble extends ActionsQueueLandingAssessmentBuilder {

	private array $definitions;
	private array $availableStrategies;
	private array $components;

	public function __construct( array $definitions, array $availableStrategies, array $components ) {
		$this->definitions = $definitions;
		$this->availableStrategies = $availableStrategies;
		$this->components = $components;
	}

	protected function getDefinitions() :array {
		return $this->definitions;
	}

	protected function isAvailableForStrategy( string $strategy ) :bool {
		return (bool)( $this->availableStrategies[ $strategy ] ?? false );
	}

	protected function buildAssessmentComponent( string $componentClass ) :array {
		return $this->components[ $componentClass ];
	}
}
