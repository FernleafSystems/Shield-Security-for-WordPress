<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\CrowdSecConstants;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseApi;

class Base extends BaseApi {

	public const DEFAULT_URL_STUB = CrowdSecConstants::API_BASE_URL;

	private $userAgent;

	public function __construct( string $userAgent = '' ) {
		$this->api_version = 2;
		$this->headers = [
			'Content-Type' => 'application/json',
		];
		$this->userAgent = $userAgent;
	}

	protected function getRequestParams() :array {
		$params = parent::getRequestParams();
		if ( !empty( $this->userAgent ) ) {
			$params[ 'user-agent' ] = $this->userAgent;
		}
		return $params;
	}
}