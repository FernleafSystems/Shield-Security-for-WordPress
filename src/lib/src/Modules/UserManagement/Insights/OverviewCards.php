<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Options;

class OverviewCards extends Shield\Modules\Base\Insights\BaseOverviewCards {

	public function build() :array {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'User Management', 'wp-simple-firewall' ),
			'subtitle'     => __( 'Sessions Control & Password Policies', 'wp-simple-firewall' ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		if ( !$mod->isModOptEnabled() ) {
			$cards[] = $this->getModDisabledCard();
		}
		else {
			$bHasIdle = $opts->hasSessionIdleTimeout();
			$cards[ 'idle' ] = [
				'name'    => __( 'Idle Users', 'wp-simple-firewall' ),
				'state'   => $bHasIdle ? 1 : -1,
				'summary' => $bHasIdle ?
					sprintf( __( 'Idle sessions are terminated after %s hours', 'wp-simple-firewall' ), $opts->getOpt( 'session_idle_timeout_interval' ) )
					: __( 'Idle sessions wont be terminated', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'session_idle_timeout_interval' ),
			];

			$bLocked = $opts->isLockToIp();
			$cards[ 'lockip' ] = [
				'name'    => __( 'Lock To IP', 'wp-simple-firewall' ),
				'state'   => $bLocked ? 1 : -1,
				'summary' => $bLocked ?
					__( 'Sessions are locked to IP address', 'wp-simple-firewall' )
					: __( "Sessions aren't locked to IP address", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'session_lock_location' ),
			];

			$bPolicies = $opts->isPasswordPoliciesEnabled();

			$bPwned = $bPolicies && $opts->isPassPreventPwned();
			$cards[ 'pwned' ] = [
				'name'    => __( 'Pwned Passwords', 'wp-simple-firewall' ),
				'state'   => $bPwned ? 1 : -1,
				'summary' => $bPwned ?
					__( 'Pwned passwords are blocked on this site', 'wp-simple-firewall' )
					: __( 'Pwned passwords are allowed on this site', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'pass_prevent_pwned' ),
			];

			$bIndepthPolices = $bPolicies && $this->getCon()->isPremiumActive();
			$cards[ 'policies' ] = [
				'name'    => __( 'Password Policies', 'wp-simple-firewall' ),
				'state'   => $bIndepthPolices ? 1 : -1,
				'summary' => $bIndepthPolices ?
					__( 'Several password policies are active', 'wp-simple-firewall' )
					: __( 'Limited or no password polices are active', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_passwords' ),
			];
		}

		$cardSection[ 'cards' ] = $cards;
		return [ 'user_management' => $cardSection ];
	}
}