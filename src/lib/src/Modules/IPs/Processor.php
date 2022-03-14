<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\{
	EntryVO,
	Select
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getBlacklistHandler()->execute();
		$mod->getBotSignalsController()->execute();
	}

	public function addAdminBarMenuGroup( array $groups ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$modInsights = $this->getCon()->getModule_Insights();
		/** @var Select $sel */
		$sel = $mod->getDbHandler_IPs()->getQuerySelector();
		$ips = $sel->filterByBlocked( true )
				   ->setOrderBy( 'blocked_at' )
				   ->setLimit( 10 )
				   ->query();

		$thisGroup = [
			'title' => __( 'Recently Blocked IPs', 'wp-simple-firewall' ),
			'href'  => $modInsights->getUrl_IPs(),
			'items' => [],
		];
		/** @var EntryVO $ip */
		foreach ( $ips as $ip ) {
			$thisGroup[ 'items' ][] = [
				'id'    => $mod->prefix( 'ip-'.$ip->id ),
				'title' => $ip->ip,
				'href'  => $modInsights->getUrl_IpAnalysis( $ip->ip ),
			];
		}

		if ( !empty( $thisGroup[ 'items' ] ) ) {
			$groups[] = $thisGroup;
		}

		return $groups;
	}
}