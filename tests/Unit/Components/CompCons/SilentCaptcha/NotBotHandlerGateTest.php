<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\SilentCaptcha;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\Signals\NotBotHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class NotBotHandlerGateTest extends BaseUnitTest {

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	/**
	 * @dataProvider gateProvider
	 */
	public function test_gate_combines_threshold_database_readiness_and_final_override(
		bool $thresholdEnabled,
		bool $databaseReady,
		?bool $override,
		bool $expected
	) :void {
		$this->installController( $thresholdEnabled, $databaseReady );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) use ( $override ) {
				return $hook === 'shield/can_run_antibot' && $override !== null ? $override : $value;
			}
		);

		$this->assertSame( $expected, ( new NotBotHandlerGateTestDouble() )->canRunForTest() );
	}

	public function gateProvider() :array {
		return [
			'enabled and ready'             => [ true, true, null, true ],
			'disabled threshold'            => [ false, true, null, false ],
			'database unavailable'          => [ true, false, null, false ],
			'final override disables'       => [ true, true, false, false ],
			'final override enables score 0' => [ false, true, true, true ],
			'final override enables no DB'   => [ true, false, true, true ],
		];
	}

	private function installController( bool $thresholdEnabled, bool $databaseReady ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = (object)[
			'opts_lookup' => new class( $thresholdEnabled ) {
				private bool $enabled;

				public function __construct( bool $enabled ) {
					$this->enabled = $enabled;
				}

				public function enabledSilentCaptcha() :bool {
					return $this->enabled;
				}
			},
		];
		$controller->db_con = (object)[
			'bot_signals' => new class( $databaseReady ) {
				private bool $ready;

				public function __construct( bool $ready ) {
					$this->ready = $ready;
				}

				public function isReady() :bool {
					return $this->ready;
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}

class NotBotHandlerGateTestDouble extends NotBotHandler {

	public function canRunForTest() :bool {
		return parent::canRun();
	}
}
