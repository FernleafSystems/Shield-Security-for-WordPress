<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Services;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	protected function buildModCards() :array {
		/** @var UserManagement\ModCon $mod */
		$mod = $this->getMod();
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();

		$cards = [];

		if ( $mod->isModOptEnabled() ) {
			$bLocked = $opts->isLockToIp();
			$cards[ 'lockip' ] = [
				'name'    => __( 'Lock To IP', 'wp-simple-firewall' ),
				'state'   => $bLocked ? 1 : -1,
				'summary' => $bLocked ?
					__( 'Sessions are locked to IP address', 'wp-simple-firewall' )
					: __( "Sessions aren't locked to IP address", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'session_lock_location' ),
			];
		}

		return $cards;
	}

	protected function getSectionTitle() :string {
		return __( 'User Management', 'wp-simple-firewall' );
	}

	protected function getSectionSubTitle() :string {
		return __( 'Sessions Control & Password Policies', 'wp-simple-firewall' );
	}
}