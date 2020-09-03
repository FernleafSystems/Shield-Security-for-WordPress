<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class UI extends Base\ShieldUI {

	/**
	 * @return array
	 */
	public function getInsightsConfigCardData() {
		/** @var \ICWP_WPSF_FeatureHandler_Headers $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$data = [
			'strings'      => [
				'title' => __( 'HTTP Security Headers', 'wp-simple-firewall' ),
				'sub'   => __( 'Protect Visitors With Powerful HTTP Headers', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $mod->getUrl_AdminPage()
		];

		if ( !$mod->isModOptEnabled() ) {
			$data[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$bAllEnabled = $opts->isEnabledXFrame() && $opts->isEnabledXssProtection()
						   && $opts->isEnabledContentTypeHeader() && $opts->isReferrerPolicyEnabled();
			$data[ 'key_opts' ][ 'all' ] = [
				'name'    => __( 'HTTP Headers', 'wp-simple-firewall' ),
				'enabled' => $bAllEnabled,
				'summary' => $bAllEnabled ?
					__( 'All important security Headers have been set', 'wp-simple-firewall' )
					: __( "At least one of the HTTP Headers hasn't been set", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_security_headers' ),
			];
			$bCsp = $opts->isEnabledContentSecurityPolicy();
			$data[ 'key_opts' ][ 'csp' ] = [
				'name'    => __( 'Content Security Policies', 'wp-simple-firewall' ),
				'enabled' => $bCsp,
				'summary' => $bCsp ?
					__( 'Content Security Policy is turned on', 'wp-simple-firewall' )
					: __( "Content Security Policies aren't active", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_content_security_policy' ),
			];
		}

		return $data;
	}
}