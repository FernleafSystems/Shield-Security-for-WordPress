<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Tools\DetectNotBot;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class TestNotBotLoading {

	use PluginControllerConsumer;

	public function test() :bool {
		return $this->testInternally() || $this->testViaShieldNet();
	}

	public function testInternally() :bool {
		$urlToFind = \explode( '?', self::con()->urls->forDistJS( 'notbot' ) )[ 0 ];
		return \preg_match(
				   sprintf( '#%s#i', \preg_quote( $urlToFind, '#' ) ),
				   Services::HttpRequest()->getContent(
					   URL::Build( network_home_url( '/' ), [ 'force_notbot' => '1' ] ),
					   [ 'timeout' => 5 ]
				   )
			   ) === 1;
	}

	public function testViaShieldNet() :bool {
		return ( new DetectNotBot() )->run( \explode( '?', self::con()->urls->forDistJS( 'notbot' ) )[ 0 ] );
	}
}