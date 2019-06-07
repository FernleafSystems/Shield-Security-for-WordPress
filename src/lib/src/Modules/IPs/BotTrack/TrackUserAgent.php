<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Services\Services;

class TrackUserAgent extends Base {

	const OPT_KEY = 'track_useragent';

	protected function process() {
		$sAgent = trim( Services::Request()->getUserAgent() );
		if ( empty( $sAgent ) || strlen( $sAgent ) < 2 ) { //in_array( $sAgent, [ '-' ] )
			$this->doTransgression();
		}
	}
}
