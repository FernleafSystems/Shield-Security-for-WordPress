<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Collate\RecentStats;

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getBlacklistHandler()->execute();
		$mod->getBotSignalsController()->execute();
		$mod->getCrowdSecCon()->execute();
	}

	public function addAdminBarMenuGroup( array $groups ) :array {
		$modInsights = $this->getCon()->getModule_Insights();
		$recentStats = ( new RecentStats() )->setCon( $this->getCon() );
		$IPs = $recentStats->getRecentlyBlockedIPs();

		if ( !empty( $IPs ) ) {
			$groups[] = [
				'title' => __( 'Recently Blocked IPs', 'wp-simple-firewall' ),
				'href'  => $modInsights->getUrl_IPs(),
				'items' => array_map( function ( $ip ) {
					return [
						'id'    => $this->getCon()->prefix( 'ip-'.$ip->id ),
						'title' => $ip->ip,
						'href'  => $this->getCon()->getModule_Insights()->getUrl_IpAnalysis( $ip->ip ),
					];
				}, $IPs ),
			];
		}

		$IPs = $recentStats->getRecentlyOffendedIPs();
		if ( !empty( $IPs ) ) {
			$groups[] = [
				'title' => __( 'Recent Offenses', 'wp-simple-firewall' ),
				'href'  => $modInsights->getUrl_IPs(),
				'items' => array_map( function ( $ip ) {
					return [
						'id'    => $this->getCon()->prefix( 'ip-'.$ip->id ),
						'title' => $ip->ip,
						'href'  => $this->getCon()->getModule_Insights()->getUrl_IpAnalysis( $ip->ip ),
					];
				}, $IPs ),
			];
		}

		return $groups;
	}
}