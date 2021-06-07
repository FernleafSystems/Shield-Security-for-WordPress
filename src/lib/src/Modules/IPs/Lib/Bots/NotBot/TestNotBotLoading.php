<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class TestNotBotLoading {

	use ModConsumer;

	public function test() :bool {
		$urlToFind = explode( '?', $this->getCon()->urls->forJs( 'shield/notbot' ) )[ 0 ];
		return preg_match(
				   sprintf( '#%s#i', preg_quote( $urlToFind, '#' ) ),
				   Services::HttpRequest()->getContent( network_home_url( '/' ), [
					   'timeout' => 5
				   ] )
			   ) === 1;
	}
}