<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();

		$aMsgs = [
			'conn_kill' => [
				__( 'Visitor found on the Black List and their connection was killed.', 'wp-simple-firewall' )
			],
			'ip_offense' => [
				__( 'Auto Black List offenses counter was incremented from %s to %s.', 'wp-simple-firewall' )
			],
			'ip_blocked' => [
				__( 'IP blocked after incrementing offenses from %s to %s.', 'wp-simple-firewall' )
			],
		];

		foreach ( $aMsgs as $sKey => &$aMsg ) {
		}

		return $aMsgs;
	}
}