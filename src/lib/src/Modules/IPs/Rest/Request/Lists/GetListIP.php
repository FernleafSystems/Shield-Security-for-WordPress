<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Request\Lists;

class GetListIP extends Base {

	protected function process() :array {
		$req = $this->getRequestVO();
		return [
			'ip' => $this->getIpData( $req->ip, $req->list )
		];
	}
}