<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops\Delete;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class DeleteTest extends TestCase {

	protected function tearDown() :void {
		PluginStore::$plugin = null;
		parent::tearDown();
	}

	public function testDeleteDisablesOrderByBeforeFilteringAndQuery() :void {
		$deleter = new class {
			public bool $noOrderBySet = false;
			public string $slug = '';
			public bool $queried = false;
			public array $calls = [];

			public function setNoOrderBy() :self {
				$this->noOrderBySet = true;
				$this->calls[] = __FUNCTION__;
				return $this;
			}

			public function filterBySlug( string $slug ) :self {
				$this->slug = $slug;
				$this->calls[] = __FUNCTION__;
				return $this;
			}

			public function query() :bool {
				$this->queried = true;
				$this->calls[] = __FUNCTION__;
				return true;
			}
		};

		$controller = ( new \ReflectionClass( Controller::class ) )
			->newInstanceWithoutConstructor();
		$controller->db_con = new class( $deleter ) {
			public $activity_snapshots;

			public function __construct( $deleter ) {
				$this->activity_snapshots = new class( $deleter ) {
					private $deleter;

					public function __construct( $deleter ) {
						$this->deleter = $deleter;
					}

					public function getQueryDeleter() {
						return $this->deleter;
					}
				};
			}
		};

		PluginStore::$plugin = new class( $controller ) {
			private Controller $controller;

			public function __construct( Controller $controller ) {
				$this->controller = $controller;
			}

			public function getController() :Controller {
				return $this->controller;
			}
		};

		$result = ( new Delete() )->delete( 'wordpress' );

		$this->assertTrue( $result );
		$this->assertTrue( $deleter->noOrderBySet );
		$this->assertSame( 'wordpress', $deleter->slug );
		$this->assertTrue( $deleter->queried );
		$this->assertSame( [ 'setNoOrderBy', 'filterBySlug', 'query' ], $deleter->calls );
	}
}
