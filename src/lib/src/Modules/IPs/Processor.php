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
		/** @var EntryVO[] $IPs */
		$IPs = $sel->filterByBlocked( true )
				   ->setOrderBy( 'blocked_at' )
				   ->setLimit( 10 )
				   ->query();

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

		/** @var EntryVO[] $IPs */
		$IPs = $sel->filterByBlocked( false )
				   ->filterByList( ModCon::LIST_AUTO_BLACK )
				   ->setOrderBy( 'last_access_at' )
				   ->setLimit( 10 )
				   ->query();
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