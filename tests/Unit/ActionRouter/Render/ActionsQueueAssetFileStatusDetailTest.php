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
	ActionsQueueScanResultsTableBuilder
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
		$action = new class( [
			'subject_type'            => 'plugin',
			'subject_id'              => 'akismet/akismet.php',
			'results_display_options' => [
				'include_ignored' => true,
				'ignored_only'    => true,
			],
		] ) extends ActionsQueueAssetFileStatusDetail {

			public function exposeRenderData() :array {
				return $this->getRenderData();
			}

			protected function buildScanResultsTableBuilder() :ActionsQueueScanResultsTableBuilder {
				return new class extends ActionsQueueScanResultsTableBuilder {
					public function buildPluginTable( string $pluginFile, ?array $options = null ) :array {
						return [
							'route'                   => 'plugin',
							'subject_id'              => $pluginFile,
							'results_display_options' => $options,
						];
					}
				};
			}
		};

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
		$action = new class( [
			'subject_type' => 'theme',
			'subject_id'   => 'twentytwentyfive',
		] ) extends ActionsQueueAssetFileStatusDetail {

			public function exposeRenderData() :array {
				return $this->getRenderData();
			}

			protected function buildScanResultsTableBuilder() :ActionsQueueScanResultsTableBuilder {
				return new class extends ActionsQueueScanResultsTableBuilder {
					public function buildThemeTable( string $stylesheet, ?array $options = null ) :array {
						return [
							'route'                   => 'theme',
							'subject_id'              => $stylesheet,
							'results_display_options' => $options,
						];
					}
				};
			}
		};

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

	public function testWordpressRouteUsesDedicatedWordpressTableBuilder() :void {
		$action = new class( [
			'display_context'         => 'actions_queue',
			'results_display_options' => [
				'include_ignored' => true,
				'ignored_only'    => true,
			],
		] ) extends Wordpress {

			public function exposeRenderData() :array {
				return $this->getRenderData();
			}

			protected function buildScanResultsTableBuilder() :ActionsQueueScanResultsTableBuilder {
				return new class extends ActionsQueueScanResultsTableBuilder {
					public function buildWordpressTable( ?array $options = null ) :array {
						return [
							'route'                   => 'wordpress',
							'results_display_options' => $options,
						];
					}
				};
			}
		};

		$renderData = $action->exposeRenderData();
		$this->assertArrayHasKey( 'table', $renderData );
		$table = $renderData[ 'table' ];

		$this->assertSame( 'wordpress', $table[ 'route' ] );
		$this->assertTrue( $table[ 'results_display_options' ][ 'ignored_only' ] );
	}

	public function testMalwareRouteUsesDedicatedMalwareTableBuilder() :void {
		$action = new class( [
			'display_context' => 'actions_queue',
		] ) extends Malware {

			public function exposeRenderData() :array {
				return $this->getRenderData();
			}

			protected function buildScanResultsTableBuilder() :ActionsQueueScanResultsTableBuilder {
				return new class extends ActionsQueueScanResultsTableBuilder {
					public function buildMalwareTable( ?array $options = null ) :array {
						return [
							'route'                   => 'malware',
							'results_display_options' => $options,
						];
					}
				};
			}
		};

		$renderData = $action->exposeRenderData();
		$this->assertArrayHasKey( 'table', $renderData );
		$table = $renderData[ 'table' ];

		$this->assertSame( 'malware', $table[ 'route' ] );
		$this->assertArrayHasKey( 'results_display_options', $table );
		$this->assertNull( $table[ 'results_display_options' ] );
	}

}
