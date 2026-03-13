<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
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
				'enabled'  => true,
				'always'   => true,
				'disabled' => false,
			],
			[
				'scan-results-core' => [
					'title'            => 'WordPress Core Files',
					'desc_protected'   => 'All WordPress Core files appear to be clean and unmodified.',
					'desc_unprotected' => 'At least 1 WordPress Core file appears to be modified or unrecognised.',
					'is_protected'     => true,
					'is_critical'      => true,
					'is_applicable'    => true,
				],
			],
			[
				'wp_updates' => [
					'label'       => 'WordPress Version',
					'description' => 'There is an upgrade available for WordPress.',
					'severity'    => 'warning',
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
			[],
			[]
		);

		$this->assertSame( [], $builder->build() );
	}

	public function test_build_marks_fully_ignored_maintenance_items_as_good() :void {
		$builder = new ActionsQueueLandingAssessmentBuilderTestDouble(
			[],
			[],
			[],
			[
				'system_php_version' => [
					'label'       => 'PHP Version',
					'description' => 'This maintenance item is currently ignored.',
					'severity'    => 'good',
				],
			]
		);

		$rows = $builder->build();

		$this->assertSame( [ 'maintenance' ], \array_keys( $rows ) );
		$this->assertSame( 'system_php_version', $rows[ 'maintenance' ][ 0 ][ 'key' ] ?? '' );
		$this->assertSame( 'good', $rows[ 'maintenance' ][ 0 ][ 'status' ] ?? '' );
		$this->assertStringContainsString( 'ignored', $rows[ 'maintenance' ][ 0 ][ 'description' ] ?? '' );
	}

	public function test_build_includes_only_plugin_files_when_only_plugin_scan_is_available() :void {
		$builder = new ActionsQueueLandingAssessmentBuilderTestDouble(
			[
				[
					'key'                   => 'plugin_files',
					'zone'                  => 'scans',
					'component_class'       => 'plugin-files',
					'availability_strategy' => 'scan_afs_plugins_enabled',
				],
				[
					'key'                   => 'theme_files',
					'zone'                  => 'scans',
					'component_class'       => 'theme-files',
					'availability_strategy' => 'scan_afs_themes_enabled',
				],
			],
			[
				'scan_afs_plugins_enabled' => true,
				'scan_afs_themes_enabled'  => false,
			],
			[
				'plugin-files' => [
					'title'            => 'Plugin Files',
					'desc_protected'   => 'All plugin files appear to be valid.',
					'desc_unprotected' => 'At least 1 plugin file appears to be modified.',
					'is_protected'     => true,
					'is_critical'      => true,
					'is_applicable'    => true,
				],
			]
		);

		$rows = $builder->build();

		$this->assertSame( [ 'plugin_files' ], \array_column( $rows[ 'scans' ] ?? [], 'key' ) );
	}

	public function test_build_includes_only_theme_files_when_only_theme_scan_is_available() :void {
		$builder = new ActionsQueueLandingAssessmentBuilderTestDouble(
			[
				[
					'key'                   => 'plugin_files',
					'zone'                  => 'scans',
					'component_class'       => 'plugin-files',
					'availability_strategy' => 'scan_afs_plugins_enabled',
				],
				[
					'key'                   => 'theme_files',
					'zone'                  => 'scans',
					'component_class'       => 'theme-files',
					'availability_strategy' => 'scan_afs_themes_enabled',
				],
			],
			[
				'scan_afs_plugins_enabled' => false,
				'scan_afs_themes_enabled'  => true,
			],
			[
				'theme-files' => [
					'title'            => 'Theme Files',
					'desc_protected'   => 'All theme files appear to be valid.',
					'desc_unprotected' => 'At least 1 theme file appears to be modified.',
					'is_protected'     => true,
					'is_critical'      => true,
					'is_applicable'    => true,
				],
			]
		);

		$rows = $builder->build();

		$this->assertSame( [ 'theme_files' ], \array_column( $rows[ 'scans' ] ?? [], 'key' ) );
	}

	public function test_build_includes_plugin_and_theme_files_when_both_scans_are_available() :void {
		$builder = new ActionsQueueLandingAssessmentBuilderTestDouble(
			[
				[
					'key'                   => 'plugin_files',
					'zone'                  => 'scans',
					'component_class'       => 'plugin-files',
					'availability_strategy' => 'scan_afs_plugins_enabled',
				],
				[
					'key'                   => 'theme_files',
					'zone'                  => 'scans',
					'component_class'       => 'theme-files',
					'availability_strategy' => 'scan_afs_themes_enabled',
				],
			],
			[
				'scan_afs_plugins_enabled' => true,
				'scan_afs_themes_enabled'  => true,
			],
			[
				'plugin-files' => [
					'title'            => 'Plugin Files',
					'desc_protected'   => 'All plugin files appear to be valid.',
					'desc_unprotected' => 'At least 1 plugin file appears to be modified.',
					'is_protected'     => true,
					'is_critical'      => true,
					'is_applicable'    => true,
				],
				'theme-files' => [
					'title'            => 'Theme Files',
					'desc_protected'   => 'All theme files appear to be valid.',
					'desc_unprotected' => 'At least 1 theme file appears to be modified.',
					'is_protected'     => true,
					'is_critical'      => true,
					'is_applicable'    => true,
				],
			]
		);

		$rows = $builder->build();

		$this->assertSame( [ 'plugin_files', 'theme_files' ], \array_column( $rows[ 'scans' ] ?? [], 'key' ) );
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
	private array $maintenanceStates;

	public function __construct(
		array $definitions,
		array $availableStrategies,
		array $components,
		array $maintenanceStates = []
	) {
		$this->definitions = $definitions;
		$this->availableStrategies = $availableStrategies;
		$this->components = $components;
		$this->maintenanceStates = $maintenanceStates;
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

	protected function buildMaintenanceIssueStateProvider() :MaintenanceIssueStateProvider {
		return new class( $this->maintenanceStates ) extends MaintenanceIssueStateProvider {
			private array $states;

			public function __construct( array $states ) {
				$this->states = $states;
			}

			public function buildStates() :array {
				return \array_map(
					static fn( array $state, string $key ) :array => \array_merge(
						[
							'key'                 => $key,
							'label'               => '',
							'description'         => '',
							'count'               => 0,
							'ignored_count'       => 0,
							'severity'            => 'good',
							'href'                => '',
							'action'              => '',
							'target'              => '',
							'supports_sub_items'  => false,
							'active_identifiers'  => [],
							'ignored_identifiers' => [],
						],
						$state,
						[
							'key' => $key,
						]
					),
					$this->states,
					\array_keys( $this->states )
				);
			}
		};
	}
}
