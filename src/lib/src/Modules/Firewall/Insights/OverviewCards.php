<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\ModCon;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	public function build() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'Firewall', 'wp-simple-firewall' ),
			'subtitle'     => __( 'Block Malicious Requests', 'wp-simple-firewall' ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		$modEnabled = $this->getMod()->isModuleEnabled();

		$cards[ 'mod' ] = [
			'name'    => __( 'Firewall', 'wp-simple-firewall' ),
			'state'   => $modEnabled ? 1 : 0,
			'summary' => $modEnabled ?
				__( 'Your site is protected against malicious requests', 'wp-simple-firewall' )
				: __( 'Your site is not protected against malicious requests', 'wp-simple-firewall' ),
			'href'    => $mod->getUrl_DirectLinkToOption( $mod->getEnableModOptKey() ),
		];

		if ( $modEnabled ) {
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