<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class DeleteIp {

	use Shield\Modules\ModConsumer;
	use IPs\Components\IpAddressConsumer;

	public function fromBlacklist() :bool {
		$this->getCon()->fireEvent( 'ip_unblock' );
		return (bool)$this->getDeleter()
						  ->filterByBlacklist()
						  ->query();
	}

	public function fromWhiteList() :bool {
		$this->getCon()->fireEvent( 'ip_bypass_remove', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );
		return (bool)$this->getDeleter()
						  ->filterByWhitelist()
						  ->query();
	}

	private function getDeleter() :Databases\IPs\Delete {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var Databases\IPs\Delete $deleter */
		$deleter = $mod->getDbHandler_IPs()->getQueryDeleter();
		return $deleter->filterByIp( $this->getIP() )
					   ->setLimit( 1 );
	}
}