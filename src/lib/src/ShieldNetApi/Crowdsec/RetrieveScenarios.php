<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Crowdsec;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

class RetrieveScenarios extends Common\BaseShieldNetApiV2 {

	const API_ACTION = 'crowdsec/scenarios';

	public function retrieve() :array {
		$raw = $this->sendReq();
		return ( is_array( $raw ) && empty( $raw[ 'error' ] )
				 && !empty( $raw[ 'scenarios' ] ) && is_array( $raw[ 'scenarios' ] ) ) ? $raw[ 'scenarios' ] : [];
	}
}