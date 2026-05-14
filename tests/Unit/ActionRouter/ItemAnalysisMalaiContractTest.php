<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\Render\Components\Scans\ItemAnalysis\Malai,
	Actions\ScansMalaiFileQuery
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\ScanResultVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	Fs,
	General,
	Request,
	Users
};

class ItemAnalysisMalaiContractTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = 'auth' ) :string => 'hash-'.$scheme.'-'.$data
		);
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blog = null, string $path = '' ) :string => '/wp-json/'.\ltrim( $path, '/' )
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				return \is_array( $value )
					? \array_map( 'rawurlencode_deep', $value )
					: \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static fn( array $data, string $url ) :string => $url.'?'.\http_build_query( $data )
		);

		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_wpfs'      => new class extends Fs {
				public function isAccessibleFile( string $path ) :bool {
					return \strtolower( \pathinfo( $path, \PATHINFO_EXTENSION ) ) === 'php';
				}
			},
			'service_wpgeneral' => new class extends General {
				public function ajaxURL() :string {
					return '/wp-admin/admin-ajax.php';
				}
			},
			'service_request'   => new class extends Request {
				public function ip() :string {
					return '127.0.0.1';
				}

				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
			'service_wpusers'   => new class extends Users {
				public function getCurrentWpUserId() {
					return 1;
				}
			},
		] );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_render_data_carries_form_owned_malai_query_action() :void {
		$renderData = ( new ItemAnalysisMalaiContractTestDouble( [
			'scan_item' => $this->buildScanItem( 321 ),
		] ) )->renderDataForTest();

		$this->assertSame( 321, $renderData[ 'vars' ][ 'form' ][ 'rid' ] ?? null );
		$actionData = \json_decode(
			(string)( $renderData[ 'vars' ][ 'malai_query_action' ] ?? '' ),
			true,
			512,
			\JSON_THROW_ON_ERROR
		);

		$this->assertSame( ScansMalaiFileQuery::SLUG, $actionData[ ActionData::FIELD_EXECUTE ] ?? '' );
		$this->assertSame( '/wp-admin/admin-ajax.php', $actionData[ ActionData::FIELD_AJAXURL ] ?? '' );
		$this->assertArrayHasKey( ActionData::FIELD_NONCE, $actionData );
	}

	private function buildScanItem( int $rid ) :ResultItem {
		$item = new ResultItem();
		$item->path_full = 'C:/tmp/shield-malai-fixture.php';
		$item->path_fragment = 'wp-content/plugins/shield-malai-fixture.php';
		$item->is_mal = false;
		$item->VO = new ScanResultVO();
		$item->VO->resultitem_id = $rid;
		return $item;
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->caps = new class {
			public function canScanMalwareMalai() :bool {
				return true;
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}

class ItemAnalysisMalaiContractTestDouble extends Malai {

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}
}
