<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class UI extends Base\ShieldUI {

	public function getInsightsOverviewCards() :array {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'Security Admin', 'wp-simple-firewall' ),
			'subtitle'     => sprintf( __( 'Prevent Tampering With %s Settings', 'wp-simple-firewall' ),
				$this->getCon()->getHumanName() ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		if ( !$this->isEnabledForUiSummary() ) {
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

			$bWpOpts = $opts->getAdminAccessArea_Options();
			$cards[ 'wpopts' ] = [
				'name'    => __( 'Important Options', 'wp-simple-firewall' ),
				'state'   => $bWpOpts ? 1 : -1,
				'summary' => $bWpOpts ?
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

		$cardSection[ 'cards' ] = $cards;
		return [ 'sec_admin' => $cardSection ];
	}

	protected function getSectionWarnings( string $section ) :array {
		/** @var \ICWP_WPSF_FeatureHandler_AdminAccessRestriction $mod */
		$mod = $this->getMod();
		$aWarnings = [];

		switch ( $section ) {
			case 'section_whitelabel':
				if ( !$mod->isEnabledSecurityAdmin() ) {
					$aWarnings[] = __( 'Please also supply a Security Admin PIN, as whitelabel settings are only applied when the Security Admin feature is active.', 'wp-simple-firewall' );
				}
				break;
		}

		return $aWarnings;
	}

	public function getInsightsNoticesData() :array {
		/** @var \ICWP_WPSF_FeatureHandler_AdminAccessRestriction $mod */
		$mod = $this->getMod();

		$notices = [
			'title'    => __( 'Security Admin Protection', 'wp-simple-firewall' ),
			'messages' => []
		];

		{//sec admin
			if ( !$mod->isEnabledSecurityAdmin() ) {
				$notices[ 'messages' ][ 'sec_admin' ] = [
					'title'   => __( 'Security Plugin Unprotected', 'wp-simple-firewall' ),
					'message' => sprintf(
						__( "The Security Admin protection is not active.", 'wp-simple-firewall' ),
						$this->getCon()->getHumanName()
					),
					'href'    => $mod->getUrl_AdminPage(),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options' ) ),
					'rec'     => __( 'Security Admin should be turned-on to protect your security settings.', 'wp-simple-firewall' )
				];
			}
		}

		return $notices;
	}

	public function getInsightsConfigCardData() :array {
		/** @var \ICWP_WPSF_FeatureHandler_AdminAccessRestriction $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$data = [
			'strings'      => [
				'title' => __( 'Security Admin', 'wp-simple-firewall' ),
				'sub'   => sprintf( __( 'Prevent Tampering With %s Settings', 'wp-simple-firewall' ), $this->getCon()
																										   ->getHumanName() ),
			],
			'key_opts'     => [],
			'href_options' => $mod->getUrl_AdminPage()
		];

		if ( !$this->isEnabledForUiSummary() ) {
			$data[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$data[ 'key_opts' ][ 'mod' ] = [
				'name'    => __( 'Security Admin', 'wp-simple-firewall' ),
				'enabled' => true,
				'summary' => true ?
					__( 'Security plugin is protected against tampering', 'wp-simple-firewall' )
					: __( 'Security plugin is vulnerable to tampering', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToOption( 'admin_access_key' ),
			];

			$bWpOpts = $opts->getAdminAccessArea_Options();
			$data[ 'key_opts' ][ 'wpopts' ] = [
				'name'    => __( 'Important Options', 'wp-simple-firewall' ),
				'enabled' => $bWpOpts,
				'summary' => $bWpOpts ?
					__( 'Important WP options are protected against tampering', 'wp-simple-firewall' )
					: __( "Important WP options aren't protected against tampering", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToOption( 'admin_access_restrict_options' ),
			];

			$bUsers = $opts->isSecAdminRestrictUsersEnabled();
			$data[ 'key_opts' ][ 'adminusers' ] = [
				'name'    => __( 'WP Admins', 'wp-simple-firewall' ),
				'enabled' => $bUsers,
				'summary' => $bUsers ?
					__( 'Admin users are protected against tampering', 'wp-simple-firewall' )
					: __( "Admin users aren't protected against tampering", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $mod->getUrl_DirectLinkToOption( 'admin_access_restrict_admin_users' ),
			];
		}

		return $data;
	}

	public function isEnabledForUiSummary() :bool {
		/** @var \ICWP_WPSF_FeatureHandler_AdminAccessRestriction $mod */
		$mod = $this->getMod();
		return parent::isEnabledForUiSummary() && $mod->isEnabledSecurityAdmin();
	}
}