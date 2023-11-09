<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Collate\RecentStats;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	protected function run() {
		( new Lib\BlacklistHandler() )->execute();
		self::con()->getModule_IPs()->getBotSignalsController()->execute();
		self::con()->getModule_IPs()->getCrowdSecCon()->execute();
	}

	public function addAdminBarMenuGroup( array $groups ) :array {
		if ( self::con()->isValidAdminArea() ) {
			$recentStats = new RecentStats();
			$IPs = $recentStats->getRecentlyBlockedIPs();

			if ( !empty( $IPs ) ) {
				$groups[] = [
					'title' => __( 'Recently Blocked IPs', 'wp-simple-firewall' ),
					'href'  => self::con()->plugin_urls->adminIpRules(),
					'items' => \array_map( function ( $ip ) {
						return [
							'id'    => self::con()->prefix( 'ip-'.$ip->id ),
							'title' => $ip->ip,
							'href'  => self::con()->plugin_urls->ipAnalysis( $ip->ip ),
						];
					}, $IPs ),
				];
			}

			$IPs = $recentStats->getRecentlyOffendedIPs();
			if ( !empty( $IPs ) ) {
				$groups[] = [
					'title' => __( 'Recent Offenses', 'wp-simple-firewall' ),
					'href'  => self::con()->plugin_urls->adminIpRules(),
					'items' => \array_map( function ( $ip ) {
						return [
							'id'    => self::con()->prefix( 'ip-'.$ip->id ),
							'title' => $ip->ip,
							'href'  => self::con()->plugin_urls->ipAnalysis( $ip->ip ),
						];
					}, $IPs ),
				];
			}
		}

		return $groups;
	}
}