<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Services\Services;

class TrackUserAgent extends Base {

	const OPT_KEY = 'track_useragent';

	protected function process() {
		$sAgent = Services::Request()->getUserAgent();
		if ( empty( $sAgent ) ) {
			$this->doTransgression();
		}
	}

	/**
	 * @return $this
	 */
	protected function getAuditMsg() {
		return sprintf( _wpsf__( 'Empty user agent detected at "%s"' ), Services::Request()->getPath() );
	}
}
