<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\BlockRequest;

class SetIpBlocked extends Base {

	const SLUG = 'set_ip_blocked';

	protected function execResponse() :bool {
		$this->getCon()->this_req->is_ip_blocked = true;

		add_action( 'init', function () {
			( new BlockRequest() )
				->setMod( $this->getCon()->getModule_IPs() )
				->execute();
		}, -100000 );

		return true;
	}
}