<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Options;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	protected function buildModCards() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$cards = [];

		if ( $mod->isModOptEnabled() ) {
			//ignoring admin isn't a good idea
			$includeAdmin = !$opts->isIgnoreAdmin();
			$cards[ 'admin' ] = [
				'name'    => $includeAdmin ?
					__( "Include Admins", 'wp-simple-firewall' )
					: __( "Ignore Admins", 'wp-simple-firewall' ),
				'state'   => $includeAdmin ? 1 : 0,
				'summary' => $includeAdmin ?
					__( "Firewall rules are also applied to admins", 'wp-simple-firewall' )
					: __( "Firewall rules aren't applied to admins", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'whitelist_admins' ),
			];
		}

		return $cards;
	}

	protected function getSectionTitle() :string {
		return __( 'Firewall', 'wp-simple-firewall' );
	}

	protected function getSectionSubTitle() :string {
		return __( 'Block Malicious Requests', 'wp-simple-firewall' );
	}

	protected function getModDisabledCard() :array {
		$mod = $this->getMod();
		return [
			'name'    => __( 'Firewall', 'wp-simple-firewall' ),
			'state'   => $mod->isModOptEnabled() ? 1 : -2,
			'summary' => $mod->isModOptEnabled() ?
				__( "The Firewall is protecting your site against malicious requests", 'wp-simple-firewall' )
				: __( "The Firewall is disabled so your site isn't protected against malicious requests", 'wp-simple-firewall' ),
			'href'    => $mod->getUrl_DirectLinkToOption( $mod->getEnableModOptKey() ),
		];
	}
}