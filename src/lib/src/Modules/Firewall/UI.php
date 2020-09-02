<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class UI extends Base\ShieldUI {

	/**
	 * @return array
	 */
	public function getInsightsConfigCardData() {
		/** @var \ICWP_WPSF_FeatureHandler_Firewall $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$data = [
			'strings'      => [
				'title' => __( 'Firewall', 'wp-simple-firewall' ),
				'sub'   => __( 'Block Malicious Requests', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $mod->getUrl_AdminPage()
		];

		if ( !$mod->isModOptEnabled() ) {
			$data[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$data[ 'key_opts' ][ 'mod' ] = [
				'name'    => __( 'Firewall', 'wp-simple-firewall' ),
				'enabled' => $mod->isModOptEnabled(),
				'summary' => $mod->isModOptEnabled() ?
					__( 'Your site is protected against malicious requests', 'wp-simple-firewall' )
					: __( 'Your site is not protected against malicious requests', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToOption( $mod->getEnableModOptKey() ),
			];

			//ignoring admin isn't a good idea
			$bAdminIncluded = !$opts->isIgnoreAdmin();
			$data[ 'key_opts' ][ 'admin' ] = [
				'name'    => __( 'Ignore Admins', 'wp-simple-firewall' ),
				'enabled' => $bAdminIncluded,
				'summary' => $bAdminIncluded ?
					__( "Firewall rules are also applied to admins", 'wp-simple-firewall' )
					: __( "Firewall rules aren't applied to admins", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $mod->getUrl_DirectLinkToOption( 'whitelist_admins' ),
			];
		}

		return $data;
	}
}