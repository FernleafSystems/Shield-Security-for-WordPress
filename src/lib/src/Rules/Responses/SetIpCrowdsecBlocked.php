<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\BlockRequestCrowdsec;

class SetIpCrowdsecBlocked extends Base {

	const SLUG = 'set_ip_crowdsec_blocked';

	protected function execResponse() :bool {
		$this->getCon()->this_req->is_ip_blocked_crowdsec = true;

		add_action( 'init', function () {
			( new BlockRequestCrowdsec() )
				->setMod( $this->getCon()->getModule_IPs() )
				->execute();
		}, -100000 );

		return true;
	}
}