<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

class BaseAuth extends Base {

	public function __construct( string $bearerAuth ) {
		parent::__construct();
		$this->headers = array_merge(
			$this->headers,
			[
				'Authorization' => 'Bearer '.$bearerAuth
			]
		);
	}
}