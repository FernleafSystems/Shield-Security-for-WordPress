<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\Debug;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\Collate;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};

class CollateTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '203.0.113.10' ),
		] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_shield_summary_exposes_current_cache_directory_path() :void {
		$cacheDir = 'D:/wordpress/wp-content/shield';
		$this->installControllerStub( $cacheDir );

		$summary = $this->invokeNonPublicMethod( new Collate(), 'getShieldSummary' );

		$this->assertArrayHasKey( 'Current Cache Directory Path', $summary );
		$this->assertStringContainsString( $cacheDir, $summary[ 'Current Cache Directory Path' ] );
		$this->assertArrayNotHasKey( 'TMP Dir', $summary );
	}

	private function installControllerStub( string $cacheDir ) :void {
		$controller = new class( $cacheDir ) extends Controller {
			public function __construct( string $cacheDir ) {
				$this->cfg = new class {
					public function version() :string {
						return '1.2.3';
					}
				};
				$this->comps = (object)[
					'api_token' => new class {
						public function getPreviousAttemptAt() :int {
							return 0;
						}

						public function hasToken() :bool {
							return false;
						}

						public function getToken() :string {
							return '';
						}
					},
					'sec_admin' => new class {
						public function isEnabledSecAdmin() :bool {
							return false;
						}
					},
				];
				$this->opts = new class {
					public function optDef( string $key ) :array {
						unset( $key );
						return [
							'value_options' => [
								[
									'value_key' => 'AUTO_DETECT_IP',
									'text'      => 'Automatic',
								],
							],
						];
					}

					public function optGet( string $key ) :string {
						unset( $key );
						return 'AUTO_DETECT_IP';
					}
				};
				$this->cache_dir_handler = new class( $cacheDir ) {
					private string $cacheDir;

					public function __construct( string $cacheDir ) {
						$this->cacheDir = $cacheDir;
					}

					public function dir( bool $retest = false ) :string {
						unset( $retest );
						return $this->cacheDir;
					}
				};
			}

			public function isPremiumActive() :bool {
				return false;
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
