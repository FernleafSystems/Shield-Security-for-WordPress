<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

class BaseAuth extends Base {

	public function __construct( string $bearerAuth, string $userAgent = '' ) {
		parent::__construct( $userAgent );
		$this->headers = \array_merge(
			$this->headers,
			[
				'Authorization' => 'Bearer '.$bearerAuth
			]
		);
	}
}