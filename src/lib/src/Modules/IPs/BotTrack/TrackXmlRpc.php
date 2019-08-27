<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Services\Services;

class TrackXmlRpc extends Base {

	const OPT_KEY = 'track_xmlrpc';

	protected function process() {
		if ( Services::WpGeneral()->isXmlrpc()
			 || preg_match( '#/xmlrpc\.php#', Services::Request()->getPath() ) ) {
			$this->doTransgression();
		}
	}
}