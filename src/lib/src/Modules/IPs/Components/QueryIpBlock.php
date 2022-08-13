<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
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
			/** @var IPs\DB\IpRules\Ops\Update $upd */
			$upd = $mod->getDbH_IPRules()->getQueryUpdater();
			$upd->updateLastAccessAt( $IP );
		}
		return $isBlocked;
	}

	/**
	 * @return IpRuleRecord|null
	 */
	private function getBlockedIpRecord() {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();
		$dbh = $mod->getDbH_IPRules();

		$blockIP = null;

		$IP = ( new IPs\Lib\Ops\LookupIP() )
			->setMod( $mod )
			->setIP( $this->getIP() )
			->setListTypeBlock()
			->setIsIpBlocked( true )
			->lookup();

		if ( !empty( $IP ) ) {
			/** @var IPs\Options $opts */
			$opts = $this->getOptions();

			// Clean out expired IPs as we go, so they don't show up in future queries.
			if ( $IP->type == $dbh::T_AUTO_BLACK
				 && $IP->last_access_at < $req->carbon()->subSeconds( $opts->getAutoExpireTime() )->timestamp ) {

				( new IPs\Lib\Ops\DeleteIP() )
					->setMod( $mod )
					->setIP( $IP->ip )
					->fromBlacklist();
			}
			elseif ( $IP->isBlocked() ) {
				$blockIP = $IP;
			}
		}

		return $blockIP;
	}
}