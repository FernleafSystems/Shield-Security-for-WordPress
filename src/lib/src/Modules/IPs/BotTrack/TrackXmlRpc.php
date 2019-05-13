<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Services\Services;

class TrackXmlRpc extends Base {

	const OPT_KEY = 'track_xmlrpc';

	protected function process() {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST
			 || preg_match( '#/xmlrpc\.php#', Services::Request()->getPath() ) ) {
			$this->doTransgression();
		}
	}

	/**
	 * @return $this
	 */
	protected function getAuditMsg() {
		return sprintf( __( 'Access to XML-RPC detected at "%s".', 'wp-simple-firewall' ), Services::Request()
																								   ->getPath() );
	}
}
