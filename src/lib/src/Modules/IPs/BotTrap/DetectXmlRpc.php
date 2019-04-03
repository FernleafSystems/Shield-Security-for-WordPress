<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrap;

use FernleafSystems\Wordpress\Services\Services;

class DetectXmlRpc extends Base {

	const OPT_KEY = 'track_xmlrpc';

	protected function process() {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST
			 || preg_match( '#/xmlrpc\.php#', Services::Request()->getPath() ) ) {
			$this->doTransgression();
		}
	}

	/**
	 * @return bool
	 */
	protected function isTransgression() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		return $oFO->isTransgressionXmlRpc();
	}

	/**
	 * @return $this
	 */
	protected function writeAudit() {
		$this->createNewAudit(
			'wpsf',
			sprintf( _wpsf__( 'Attempt to access XML-RPC detected at "%s"' ), Services::Request()->getPath() ),
			2, 'bottrap_xmlrpc'
		);
		return $this;
	}
}
