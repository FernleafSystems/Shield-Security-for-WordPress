<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers\Options;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	public function build() :array {
		/** @var Shield\Modules\Headers\ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'HTTP Security Headers', 'wp-simple-firewall' ),
			'subtitle'     => __( 'Protect Visitors With Powerful HTTP Headers', 'wp-simple-firewall' ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		if ( !$mod->isModOptEnabled() ) {
			$cards[ 'mod' ] = $this->getModDisabledCard();
		}
		else {
			$bAllEnabled = $opts->isEnabledXFrame() && $opts->isEnabledXssProtection()
						   && $opts->isEnabledContentTypeHeader() && $opts->isReferrerPolicyEnabled();
			$cards[ 'all' ] = [
				'name'    => __( 'HTTP Headers', 'wp-simple-firewall' ),
				'state'   => $bAllEnabled ? 1 : -1,
				'summary' => $bAllEnabled ?
					__( 'All important security Headers have been set', 'wp-simple-firewall' )
					: __( "At least one of the HTTP Headers hasn't been set", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_security_headers' ),
			];

			$cards[ 'csp' ] = [
				'name'    => __( 'Content Security Policies', 'wp-simple-firewall' ),
				'state'   => $opts->isEnabledContentSecurityPolicy() ? 1 : 0,
				'summary' => $opts->isEnabledContentSecurityPolicy() ?
					__( 'Content Security Policy is turned on', 'wp-simple-firewall' )
					: __( "Content Security Policies aren't active or there are no rules provided", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_content_security_policy' ),
			];
		}

		$cardSection[ 'cards' ] = $cards;
		return [ 'headers' => $cardSection ];
	}
}