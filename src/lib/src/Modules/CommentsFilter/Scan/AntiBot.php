<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class AntiBot {

	use ModConsumer;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function scan() :bool {
		$isBot = $this->getCon()
					  ->getModule_IPs()
					  ->getBotSignalsController()
					  ->isBot();
		if ( $isBot ) {
			throw new \Exception( __( 'Failed AntiBot Verification', 'wp-simple-firewall' ) );
		}
		return true;
	}
}
