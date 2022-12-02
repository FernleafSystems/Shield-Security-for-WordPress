<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Debug;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\RunTests;

class SimplePluginTests extends BaseAction {

	public const SLUG = 'debug_simple_plugin_tests';

	protected function exec() {
		$testMethod = $this->action_data[ 'test' ];
		if ( !method_exists( $this, $testMethod ) ) {
			throw new ActionException( sprintf( 'There is no test method: %s', $testMethod ) );
		}
		ob_start();
		$this->{$testMethod}();
		$this->response()->action_response_data = [ ob_get_clean() ];
	}

	private function handshake() {
		$success = ( new ShieldNetApi\Handshake\Verify() )
			->setMod( $this->getMod() )
			->run();
		var_dump( $success );
	}

	private function plugin_tests() {
		ob_start();
		( new RunTests() )
			->setCon( $this->getCon() )
			->run();
		$output = ob_get_clean();
		return [ print_r( $output, true ) ];
	}
}