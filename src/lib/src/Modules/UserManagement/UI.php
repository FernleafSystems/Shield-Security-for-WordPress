<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	/**
	 * @return array
	 */
	public function getInsightsConfigCardData() {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$data = [
			'strings'      => [
				'title' => __( 'User Management', 'wp-simple-firewall' ),
				'sub'   => __( 'Sessions Control & Password Policies', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $mod->getUrl_AdminPage()
		];

		if ( !$mod->isModOptEnabled() ) {
			$data[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$bHasIdle = $opts->hasSessionIdleTimeout();
			$data[ 'key_opts' ][ 'idle' ] = [
				'name'    => __( 'Idle Users', 'wp-simple-firewall' ),
				'enabled' => $bHasIdle,
				'summary' => $bHasIdle ?
					sprintf( __( 'Idle sessions are terminated after %s hours', 'wp-simple-firewall' ), $opts->getOpt( 'session_idle_timeout_interval' ) )
					: __( 'Idle sessions wont be terminated', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToOption( 'session_idle_timeout_interval' ),
			];

			$bLocked = $opts->isLockToIp();
			$data[ 'key_opts' ][ 'lock' ] = [
				'name'    => __( 'Lock To IP', 'wp-simple-firewall' ),
				'enabled' => $bLocked,
				'summary' => $bLocked ?
					__( 'Sessions are locked to IP address', 'wp-simple-firewall' )
					: __( "Sessions aren't locked to IP address", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $mod->getUrl_DirectLinkToOption( 'session_lock_location' ),
			];

			$bPolicies = $opts->isPasswordPoliciesEnabled();

			$bPwned = $bPolicies && $opts->isPassPreventPwned();
			$data[ 'key_opts' ][ 'pwned' ] = [
				'name'    => __( 'Pwned Passwords', 'wp-simple-firewall' ),
				'enabled' => $bPwned,
				'summary' => $bPwned ?
					__( 'Pwned passwords are blocked on this site', 'wp-simple-firewall' )
					: __( 'Pwned passwords are allowed on this site', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToOption( 'pass_prevent_pwned' ),
			];

			$bIndepthPolices = $bPolicies && $this->getCon()->isPremiumActive();
			$data[ 'key_opts' ][ 'policies' ] = [
				'name'    => __( 'Password Policies', 'wp-simple-firewall' ),
				'enabled' => $bIndepthPolices,
				'summary' => $bIndepthPolices ?
					__( 'Several password policies are active', 'wp-simple-firewall' )
					: __( 'Limited or no password polices are active', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_passwords' ),
			];
		}

		return $data;
	}

	/**
	 * @return array
	 */
	public function getInsightsNoticesData() {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$notices = [
			'title'    => __( 'Users', 'wp-simple-firewall' ),
			'messages' => []
		];

		{ //admin user
			$oAdmin = Services::WpUsers()->getUserByUsername( 'admin' );
			if ( !empty( $oAdmin ) && user_can( $oAdmin, 'manage_options' ) ) {
				$notices[ 'messages' ][ 'admin' ] = [
					'title'   => 'Admin User',
					'message' => sprintf( __( "Default 'admin' user still available.", 'wp-simple-firewall' ) ),
					'href'    => '',
					'rec'     => __( "Default 'admin' user should be disabled or removed.", 'wp-simple-firewall' )
				];
			}
		}

		{//password policies
			if ( !$opts->isPasswordPoliciesEnabled() ) {
				$notices[ 'messages' ][ 'password' ] = [
					'title'   => __( 'Password Policies', 'wp-simple-firewall' ),
					'message' => __( "Strong password policies are not enforced.", 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_passwords' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Password policies should be turned-on.', 'wp-simple-firewall' )
				];
			}
		}

		return $notices;
	}
}