<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

/**
 * Class DeleteIp
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops
 */
class DeleteIp {

	// TODO Add ModConsumer for events
	use Databases\Base\HandlerConsumer;
	use IPs\Components\IpAddressConsumer;

	public function fromBlacklist() :bool {
//		$this->getCon()->fireEvent( 'ip_unblock' );
		return (bool)$this->getDeleter()
						  ->filterByBlacklist()
						  ->query();
	}

	public function fromWhiteList() :bool {
		return (bool)$this->getDeleter()
						  ->filterByWhitelist()
						  ->query();
	}

	private function getDeleter() :Databases\IPs\Delete {
		/** @var Databases\IPs\Delete $oDel */
		$oDel = $this->getDbHandler()->getQueryDeleter();
		return $oDel->filterByIp( $this->getIP() )
					->setLimit( 1 );
	}
}