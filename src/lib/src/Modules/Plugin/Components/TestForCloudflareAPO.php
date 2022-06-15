<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;
use FernleafSystems\Wordpress\Services\Utilities\ServiceProviders;

class TestForCloudflareAPO {

	public function run() :bool {
		$req = Services::Request();
		$visitorIP = $req->ip();
		$cfIP = (string)$req->server( 'HTTP_CF_CONNECTING_IP' );
		return empty( $visitorIP ) && IpID::IsIpInServiceCollection( $cfIP, ServiceProviders::PROVIDER_CLOUDFLARE );
	}
}