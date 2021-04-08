<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class QueryIpBlock
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components
 */
class QueryIpBlock {

	use Shield\Modules\ModConsumer;
	use IpAddressConsumer;

	public function run() :bool {
		$isBlocked = false;

		$oIP = $this->getBlockedIpRecord();
		if ( $oIP instanceof Databases\IPs\EntryVO ) {

			$isBlocked = true;

			/** @var IPs\ModCon $mod */
			$mod = $this->getMod();
			/** @var Databases\IPs\Update $upd */
			$upd = $mod->getDbHandler_IPs()->getQueryUpdater();
			$upd->updateLastAccessAt( $oIP );
		}
		return $isBlocked;
	}

	/**
	 * @return Databases\IPs\EntryVO|null
	 */
	private function getBlockedIpRecord() {
		$oBlockIP = null;

		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		$oIP = ( new IPs\Lib\Ops\LookupIpOnList() )
			->setDbHandler( $mod->getDbHandler_IPs() )
			->setIP( $this->getIP() )
			->setListTypeBlock()
			->setIsIpBlocked( true )
			->lookup();

		if ( $oIP instanceof Databases\IPs\EntryVO ) {
			/** @var IPs\Options $oOpts */
			$oOpts = $this->getOptions();

			// Clean out old IPs as we go so they don't show up in future queries.
			if ( $oIP->list == $mod::LIST_AUTO_BLACK
				 && $oIP->last_access_at < Services::Request()->ts() - $oOpts->getAutoExpireTime() ) {

				( new IPs\Lib\Ops\DeleteIp() )
					->setMod( $mod )
					->setIP( Services::IP()->getRequestIp() )
					->fromBlacklist();
			}
			else {
				$oBlockIP = $oIP;
			}
		}

		return $oBlockIP;
	}
}