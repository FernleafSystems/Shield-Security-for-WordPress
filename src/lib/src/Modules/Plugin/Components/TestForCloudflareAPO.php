<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Wordpress\Services\Services;

class TestForCloudflareAPO {

	public function run() :bool {
		$req = Services::Request();
		$srvIP = Services::IP();
		$visitorIP = $srvIP->getRequestIp();
		$cfIP = $req->server( 'HTTP_CF_CONNECTING_IP' );
		return empty( $visitorIP ) && Services::ServiceProviders()->isIp_Cloudflare( $cfIP );
	}
}