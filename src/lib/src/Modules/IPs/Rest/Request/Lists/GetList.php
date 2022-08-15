<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Request\Lists;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\RetrieveIpsForLists;

class GetList extends Base {

	protected function process() :array {
		$req = $this->getRequestVO();

		$retriever = ( new RetrieveIpsForLists() )->setMod( $this->getMod() );
		if ( $req->list === 'block' ) {
			$list = $retriever->black();
		}
		else {
			$list = $retriever->white();
		}

		return $list;
	}
}