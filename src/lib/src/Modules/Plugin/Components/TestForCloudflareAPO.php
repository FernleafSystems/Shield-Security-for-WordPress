<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;
use FernleafSystems\Wordpress\Services\Utilities\ServiceProviders;

class TestForCloudflareAPO {

	public function run() :bool {
		$visitorIP = Services::IP()->getRequestIp();
		$cfIP = (string)Services::Request()->server( 'HTTP_CF_CONNECTING_IP' );
		return empty( $visitorIP ) && IpID::IsIpInServiceCollection( $cfIP, ServiceProviders::PROVIDER_CLOUDFLARE );
	}
}