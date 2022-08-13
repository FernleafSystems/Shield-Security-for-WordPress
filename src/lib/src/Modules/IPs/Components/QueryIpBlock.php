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

		$allIPs = ( new IPs\Lib\Ops\FindIpRuleRecords() )
			->setMod( $mod )
			->setIP( $this->getIP() )
			->setIsIpBlocked( true )
			->all();

		foreach ( $allIPs as $ipRule ) {
			/** @var IPs\Options $opts */
			$opts = $this->getOptions();

			// Clean out expired auto IPs as we go, so they don't show up in future queries.
			if ( $ipRule->type == $dbh::T_AUTO_BLACK
				 && $ipRule->last_access_at < $req->carbon()->subSeconds( $opts->getAutoExpireTime() )->timestamp ) {

				( new IPs\Lib\Ops\DeleteRule() )
					->setMod( $mod )
					->byRecord( $ipRule );
			}
			elseif ( empty( $blockIP ) ) {
				$blockIP = $ipRule;
			}
		}

		return $blockIP;
	}
}