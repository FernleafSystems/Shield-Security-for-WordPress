<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	Malware,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueAssetFileStatusDetail,
	ActionsQueueScanResultsTableBuilder,
	ScansResultsViewBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request,
	Users
};

class ActionsQueueAssetFileStatusDetailTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias( static fn( string $value ) :string => \strtolower( \preg_replace( '/[^a-z0-9_]/', '', $value ) ?? '' ) );
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = 'auth' ) :string => 'hash-'.$scheme.'-'.$data
		);
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blog = null, string $path = '' ) :string => '/wp-json/'.\ltrim( $path, '/' )
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				if ( \is_array( $value ) ) {
					return \array_map(
						static fn( $item ) :string => \rawurlencode( (string)$item ),
						$value
					);
				}
				return \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				if ( empty( $params ) ) {
					return $url;
				}
				$pieces = [];
				foreach ( $params as $key => $value ) {
					$pieces[] = $key.'='.( \is_array( $value ) ? \rawurlencode( (string)\json_encode( $value ) ) : $value );
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pieces );
			}
		);

		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_wpgeneral' => new class extends General {
				public function ajaxURL() :string {
					return '/admin-ajax.php';
				}
			},
			'service_request' => new class extends Request {
				public function ip() :string {
					return '127.0.0.1';
				}

				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
			'service_wpusers' => new class extends Users {
				public function getCurrentWpUserId() {
					return 0;
				}
			},
		] );
		UnitTestControllerFactory::install();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function testDetailRenderUsesPluginSubjectRouteData() :void {
		$viewBuilder = $this->buildGatedViewBuilder();
		$action = $this->buildAssetDetailAction( [
			'subject_type'            => 'plugin',
			'subject_id'              => 'akismet/akismet.php',
			'results_display_options' => [
				'include_ignored' => true,
				'ignored_only'    => true,
			],
		], $viewBuilder );

		$renderData = $action->exposeRenderData();
		$this->assertArrayHasKey( 'table', $renderData );
		$table = $renderData[ 'table' ];

		$this->assertSame( 'plugin', $table[ 'route' ] );
		$this->assertSame( 'akismet/akismet.php', $table[ 'subject_id' ] );
		$this->assertSame(
			[
				'include_ignored'  => true,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => true,
			],
			$table[ 'results_display_options' ]
		);
	}

	public function testDetailRenderUsesThemeSubjectRouteData() :void {
		$viewBuilder = $this->buildGatedViewBuilder();
		$action = $this->buildAssetDetailAction( [
			'subject_type' => 'theme',
			'subject_id'   => 'twentytwentyfive',
		], $viewBuilder );

		$renderData = $action->exposeRenderData();
		$this->assertArrayHasKey( 'table', $renderData );
		$table = $renderData[ 'table' ];

		$this->assertSame( 'theme', $table[ 'route' ] );
		$this->assertSame( 'twentytwentyfive', $table[ 'subject_id' ] );
		$this->assertSame(
			[
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$table[ 'results_display_options' ]
		);
	}

	public function testPluginSubjectDetailReturnsDisabledPayloadBeforeBuildingTableWhenUnavailable() :void {
		$viewBuilder = $this->buildGatedViewBuilder( false, true, 'plugins-disabled-sentinel' );
		$action = $this->buildAssetDetailAction( [
			'subject_type' => 'plugin',
			'subject_id'   => 'akismet/akismet.php',
		], $viewBuilder );

		$renderData = $action->exposeRenderData();
		$this->assertTrue( $renderData[ 'flags' ][ 'is_disabled' ] ?? false );
		$this->assertSame( 'plugins-disabled-sentinel', $renderData[ 'strings' ][ 'disabled_message' ] ?? '' );
		$this->assertSame( [], $renderData[ 'table' ] ?? [ 'unexpected' ] );
		$this->assertSame( 0, $viewBuilder->tableBuildCalls );
	}

	public function testThemeSubjectDetailReturnsDisabledPayloadBeforeBuildingTableWhenUnavailable() :void {
		$viewBuilder = $this->buildGatedViewBuilder( true, false, 'themes-disabled-sentinel' );
		$action = $this->buildAssetDetailAction( [
			'subject_type' => 'theme',
			'subject_id'   => 'twentytwentyfive',
		], $viewBuilder );

		$renderData = $action->exposeRenderData();
		$this->assertTrue( $renderData[ 'flags' ][ 'is_disabled' ] ?? false );
		$this->assertSame( 'themes-disabled-sentinel', $renderData[ 'strings' ][ 'disabled_message' ] ?? '' );
		$this->assertSame( [], $renderData[ 'table' ] ?? [ 'unexpected' ] );
		$this->assertSame( 0, $viewBuilder->tableBuildCalls );
	}

	public function testUnsupportedSubjectTypeIsRejectedBeforeBuildingTable() :void {
		$viewBuilder = $this->buildGatedViewBuilder();
		$action = $this->buildAssetDetailAction( [
			'subject_type' => 'core',
			'subject_id'   => 'core',
		], $viewBuilder );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Unsupported scan result subject type "core".' );

		try {
			$action->exposeRenderData();
		}
		finally {
			$this->assertSame( 0, $viewBuilder->tableBuildCalls );
		}
	}

	public function testDirectTablePaneRejectsAreasWithoutDirectTables() :void {
		$viewBuilder = $this->buildGatedViewBuilder();

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Scan result area "plugins" has no direct table.' );

		$viewBuilder->buildActionsQueueDirectTablePane( 'plugins' );
	}

	public function testWordpressRouteUsesDedicatedWordpressTableBuilder() :void {
		$viewBuilder = $this->buildGatedViewBuilder();
		$action = new class( [
			'display_context'         => 'actions_queue',
			'results_display_options' => [
				'include_ignored' => true,
				'ignored_only'    => true,
			],
		], $viewBuilder ) extends Wordpress {

			private ScansResultsViewBuilder $viewBuilder;

			public function __construct( array $data, ScansResultsViewBuilder $viewBuilder ) {
				parent::__construct( $data );
				$this->viewBuilder = $viewBuilder;
			}

			public function exposeRenderData() :array {
				return $this->getRenderData();
			}

			protected function buildScansResultsViewBuilder() :ScansResultsViewBuilder {
				return $this->viewBuilder;
			}
		};

		$renderData = $action->exposeRenderData();
		$this->assertArrayHasKey( 'table', $renderData );
		$table = $renderData[ 'table' ];

		$this->assertSame( 'wordpress', $table[ 'route' ] );
		$this->assertTrue( $table[ 'results_display_options' ][ 'ignored_only' ] );
	}

	public function testMalwareRouteUsesDedicatedMalwareTableBuilder() :void {
		$viewBuilder = $this->buildGatedViewBuilder();
		$action = new class( [
			'display_context' => 'actions_queue',
		], $viewBuilder ) extends Malware {

			private ScansResultsViewBuilder $viewBuilder;

			public function __construct( array $data, ScansResultsViewBuilder $viewBuilder ) {
				parent::__construct( $data );
				$this->viewBuilder = $viewBuilder;
			}

			public function exposeRenderData() :array {
				return $this->getRenderData();
			}

			protected function buildScansResultsViewBuilder() :ScansResultsViewBuilder {
				return $this->viewBuilder;
			}
		};

		$renderData = $action->exposeRenderData();
		$this->assertArrayHasKey( 'table', $renderData );
		$table = $renderData[ 'table' ];

		$this->assertSame( 'malware', $table[ 'route' ] );
		$this->assertArrayHasKey( 'results_display_options', $table );
		$this->assertNull( $table[ 'results_display_options' ] );
	}

	private function buildAssetDetailAction( array $data, ScansResultsViewBuilder $viewBuilder ) :ActionsQueueAssetFileStatusDetail {
		return new class( $data, $viewBuilder ) extends ActionsQueueAssetFileStatusDetail {

			private ScansResultsViewBuilder $viewBuilder;

			public function __construct( array $data, ScansResultsViewBuilder $viewBuilder ) {
				parent::__construct( $data );
				$this->viewBuilder = $viewBuilder;
			}

			public function exposeRenderData() :array {
				return $this->getRenderData();
			}

			protected function buildScansResultsViewBuilder() :ScansResultsViewBuilder {
				return $this->viewBuilder;
			}
		};
	}

	private function buildGatedViewBuilder(
		bool $pluginsAvailable = true,
		bool $themesAvailable = true,
		string $disabledMessage = 'scan-disabled-sentinel'
	) :ScansResultsViewBuilder {
		return new class( $pluginsAvailable, $themesAvailable, $disabledMessage ) extends ScansResultsViewBuilder {

			public int $tableBuildCalls = 0;
			private bool $pluginsAvailable;
			private bool $themesAvailable;
			private string $disabledMessage;

			public function __construct( bool $pluginsAvailable, bool $themesAvailable, string $disabledMessage ) {
				$this->pluginsAvailable = $pluginsAvailable;
				$this->themesAvailable = $themesAvailable;
				$this->disabledMessage = $disabledMessage;
			}

			protected function getRailTabAvailability( string $tabKey ) :array {
				$isAvailable = !\in_array( $tabKey, [ 'plugins', 'themes' ], true )
					|| ( $tabKey === 'plugins' ? $this->pluginsAvailable : $this->themesAvailable );

				return [
					'is_available'          => $isAvailable,
					'show_in_actions_queue' => true,
					'show_in_fix_now'       => true,
					'disabled_reason'       => $isAvailable ? '' : 'upgrade_required',
					'disabled_message'      => $isAvailable ? '' : $this->disabledMessage,
					'disabled_status'       => 'neutral',
					'disabled_actions'      => $isAvailable ? [] : [
						[
							'type'         => 'navigate',
							'label'        => 'View Plans',
							'href'         => '/go-pro',
							'icon_class'   => 'bi bi-arrow-right-circle-fill',
							'tooltip_attr' => '',
							'class_name'   => '',
							'target'       => '_blank',
							'rel'          => 'noopener noreferrer',
							'attributes'   => [],
						],
					],
				];
			}

			protected function buildScanResultsTableBuilder() :ActionsQueueScanResultsTableBuilder {
				return new class( $this ) extends ActionsQueueScanResultsTableBuilder {

					private object $recorder;

					public function __construct( object $recorder ) {
						$this->recorder = $recorder;
					}

					public function buildPluginTable( string $pluginFile, ?array $options = null ) :array {
						$this->recorder->tableBuildCalls++;
						return [
							'route'                   => 'plugin',
							'subject_id'              => $pluginFile,
							'results_display_options' => $options,
						];
					}

					public function buildThemeTable( string $stylesheet, ?array $options = null ) :array {
						$this->recorder->tableBuildCalls++;
						return [
							'route'                   => 'theme',
							'subject_id'              => $stylesheet,
							'results_display_options' => $options,
						];
					}

					public function buildWordpressTable( ?array $options = null ) :array {
						$this->recorder->tableBuildCalls++;
						return [
							'route'                   => 'wordpress',
							'results_display_options' => $options,
						];
					}

					public function buildMalwareTable( ?array $options = null ) :array {
						$this->recorder->tableBuildCalls++;
						return [
							'route'                   => 'malware',
							'results_display_options' => $options,
						];
					}
				};
			}
		};
	}

}
