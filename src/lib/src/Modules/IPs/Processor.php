<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Collate\RecentStats;

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		( new Lib\BlacklistHandler() )
			->setMod( $mod )
			->execute();
		$mod->getBotSignalsController()->execute();
		$mod->getCrowdSecCon()->execute();
	}

	public function addAdminBarMenuGroup( array $groups ) :array {
		$con = $this->getCon();
		if ( $con->isValidAdminArea() ) {
			$recentStats = new RecentStats();
			$IPs = $recentStats->getRecentlyBlockedIPs();

			if ( !empty( $IPs ) ) {
				$groups[] = [
					'title' => __( 'Recently Blocked IPs', 'wp-simple-firewall' ),
					'href'  => $con->plugin_urls->adminIpRules(),
					'items' => array_map( function ( $ip ) use ( $con ) {
						return [
							'id'    => $con->prefix( 'ip-'.$ip->id ),
							'title' => $ip->ip,
							'href'  => $con->plugin_urls->ipAnalysis( $ip->ip ),
						];
					}, $IPs ),
				];
			}

			$IPs = $recentStats->getRecentlyOffendedIPs();
			if ( !empty( $IPs ) ) {
				$groups[] = [
					'title' => __( 'Recent Offenses', 'wp-simple-firewall' ),
					'href'  => $con->plugin_urls->adminIpRules(),
					'items' => array_map( function ( $ip ) use ( $con ) {
						return [
							'id'    => $con->prefix( 'ip-'.$ip->id ),
							'title' => $ip->ip,
							'href'  => $con->plugin_urls->ipAnalysis( $ip->ip ),
						];
					}, $IPs ),
				];
			}
		}

		return $groups;
	}
}