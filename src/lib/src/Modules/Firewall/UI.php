<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class UI extends Base\ShieldUI {

	public function getInsightsOverviewCards() :array {
		/** @var \ICWP_WPSF_FeatureHandler_Firewall $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'Firewall', 'wp-simple-firewall' ),
			'subtitle'     => __( 'Block Malicious Requests', 'wp-simple-firewall' ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		$cards[ 'mod' ] = [
			'name'    => __( 'Firewall', 'wp-simple-firewall' ),
			'state'   => $this->isEnabledForUiSummary() ? 1 : 0,
			'summary' => $this->isEnabledForUiSummary() ?
				__( 'Your site is protected against malicious requests', 'wp-simple-firewall' )
				: __( 'Your site is not protected against malicious requests', 'wp-simple-firewall' ),
			'href'    => $mod->getUrl_DirectLinkToOption( $mod->getEnableModOptKey() ),
		];

		if ( $this->isEnabledForUiSummary() ) {
			//ignoring admin isn't a good idea
			$bAdminIncluded = !$opts->isIgnoreAdmin();
			$cards[ 'admin' ] = [
				'name'    => $bAdminIncluded ?
					__( "Include Admins", 'wp-simple-firewall' )
					: __( "Ignore Admins", 'wp-simple-firewall' ),
				'state'   => $bAdminIncluded ? 1 : 0,
				'summary' => $bAdminIncluded ?
					__( "Firewall rules are also applied to admins", 'wp-simple-firewall' )
					: __( "Firewall rules aren't applied to admins", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'whitelist_admins' ),
			];
		}

		$cardSection[ 'cards' ] = $cards;
		return [ 'firewall' => $cardSection ];
	}
}