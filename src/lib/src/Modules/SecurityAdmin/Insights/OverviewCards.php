<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	protected function buildModCards() :array {
		/** @var Shield\Modules\SecurityAdmin\ModCon $mod */
		$mod = $this->getMod();
		/** @var Shield\Modules\SecurityAdmin\Options $opts */
		$opts = $this->getOptions();

		$cards = [];

		$enabled = $mod->getSecurityAdminController()->isEnabledSecAdmin();
		if ( !$enabled ) {
			$cards[ 'mod' ] = [
				'name'    => __( 'Security Admin', 'wp-simple-firewall' ),
				'state'   => -1,
				'summary' => __( 'Security plugin is vulnerable to tampering', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'admin_access_key' ),
			];
		}
		else {
			$cards[ 'mod' ] = [
				'name'    => __( 'Security Admin', 'wp-simple-firewall' ),
				'state'   => 1,
				'summary' => __( 'Security plugin is protected against tampering', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'admin_access_key' ),
			];

			$isWPOptsRestricted = $opts->isRestrictWpOptions();
			$cards[ 'wpopts' ] = [
				'name'    => __( 'Important Options', 'wp-simple-firewall' ),
				'state'   => $isWPOptsRestricted ? 1 : -1,
				'summary' => $isWPOptsRestricted ?
					__( 'Important WP options are protected against tampering', 'wp-simple-firewall' )
					: __( "Important WP options aren't protected against tampering", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'admin_access_restrict_options' ),
			];

			$bUsers = $opts->isSecAdminRestrictUsersEnabled();
			$cards[ 'adminusers' ] = [
				'name'    => __( 'WP Admins', 'wp-simple-firewall' ),
				'state'   => $bUsers ? 1 : -1,
				'summary' => $bUsers ?
					__( 'Admin users are protected against tampering', 'wp-simple-firewall' )
					: __( "Admin users aren't protected against tampering", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'admin_access_restrict_admin_users' ),
			];
		}

		return $cards;
	}

	protected function getSectionTitle() :string {
		return __( 'Security Admin', 'wp-simple-firewall' );
	}

	protected function getSectionSubTitle() :string {
		return sprintf( __( 'Prevent Tampering With %s Settings', 'wp-simple-firewall' ),
			$this->getCon()->getHumanName() );
	}
}