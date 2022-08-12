<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\CrowdSecConstants;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseApi;

class Base extends BaseApi {

	const DEFAULT_URL_STUB = CrowdSecConstants::API_BASE_URL;

	public function __construct() {
		$this->api_version = 2;
		$this->headers = [
			'Content-Type' => 'application/json',
		];
	}
}