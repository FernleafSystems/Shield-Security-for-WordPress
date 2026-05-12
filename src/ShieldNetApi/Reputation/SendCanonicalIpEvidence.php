<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation;

use FernleafSystems\Wordpress\Services\Services;

class SendCanonicalIpEvidence extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2 {

	public const API_ACTION = 'ip/evidence/receive';

	public function send( array $evidenceData ) :bool {
		$this->request_method = 'post';
		$this->shield_net_params_required = false;
		$this->params_body = [
			'reporting_url' => Services::WpGeneral()->getHomeUrl( '', true ),
			'ip_evidence'   => $evidenceData,
		];
		$raw = $this->sendReq();
		return \is_array( $raw ) && empty( $raw[ 'error' ] );
	}
}
