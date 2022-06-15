<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class QueryIpBlock {

	use Shield\Modules\ModConsumer;
	use IpAddressConsumer;

	public function run() :bool {
		$isBlocked = false;

		$IP = $this->getBlockedIpRecord();
		if ( !empty( $IP ) ) {

			$isBlocked = true;

			/** @var IPs\ModCon $mod */
			$mod = $this->getMod();
			/** @var Databases\IPs\Update $upd */
			$upd = $mod->getDbHandler_IPs()->getQueryUpdater();
			$upd->updateLastAccessAt( $IP );
		}
		return $isBlocked;
	}

	/**
	 * @return Databases\IPs\EntryVO|null
	 */
	private function getBlockedIpRecord() {
		$blockIP = null;

		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		$IP = ( new IPs\Lib\Ops\LookupIpOnList() )
			->setDbHandler( $mod->getDbHandler_IPs() )
			->setIP( $this->getIP() )
			->setListTypeBlock()
			->setIsIpBlocked( true )
			->lookup();

		if ( !empty( $IP ) ) {
			/** @var IPs\Options $opts */
			$opts = $this->getOptions();

			// Clean out old IPs as we go so they don't show up in future queries.
			if ( $IP->list == $mod::LIST_AUTO_BLACK
				 && $IP->last_access_at < Services::Request()->ts() - $opts->getAutoExpireTime() ) {

				( new IPs\Lib\Ops\DeleteIp() )
					->setMod( $mod )
					->setIP( $this->getCon()->this_req->ip )
					->fromBlacklist();
			}
			else {
				$blockIP = $IP;
			}
		}

		return $blockIP;
	}
}