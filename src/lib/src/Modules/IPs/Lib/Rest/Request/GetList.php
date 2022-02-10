<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\RetrieveIpsForLists;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;

class GetList extends Base {

	protected function process() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = $this->getRequestVO();

		$retriever = ( new RetrieveIpsForLists() )
			->setDbHandler( $mod->getDbHandler_IPs() );
		if ( $req->list === 'bypass' ) {
			$list = $retriever->white();
		}
		$list = $retriever->white();

		return $list;
	}
}