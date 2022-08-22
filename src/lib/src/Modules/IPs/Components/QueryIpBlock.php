<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

/**
 * @deprecated 16.0
 */
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
		$blockIP = null;

		$ruleStatus = ( new IpRuleStatus( $this->getIP() ) )->setMod( $this->getMod() );

		foreach ( $ruleStatus->getRulesForManualBlock() as $ipRule ) {
			$blockIP = $ipRule;
		}
		if ( empty( $blockIP ) ) {
			$blockIP = $ruleStatus->getRuleForAutoBlock();
		}

		return $blockIP;
	}
}