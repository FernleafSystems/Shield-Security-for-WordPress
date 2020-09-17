<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\OverviewCards;

class UI extends Base\ShieldUI {

	public function buildInsightsVars() :array {
		$con = $this->getCon();

		return [
			'vars'    => [
				'overview_cards' => ( new OverviewCards() )
					->setMod( $this->getMod() )
					->buildForShuffle(),
			],
			'hrefs'   => [
				'shield_pro_url'           => 'https://shsec.io/shieldpro',
				'shield_pro_more_info_url' => 'https://shsec.io/shld1',
			],
			'flags'   => [
				'show_ads'              => false,
				'show_standard_options' => false,
				'show_alt_content'      => true,
				'is_pro'                => $con->isPremiumActive(),
			],
			'strings' => [
				'title_security_notices'    => __( 'Security Notices', 'wp-simple-firewall' ),
				'subtitle_security_notices' => __( 'Potential security issues on your site right now', 'wp-simple-firewall' ),
				'configuration_summary'     => __( 'Plugin Configuration Summary', 'wp-simple-firewall' ),
				'click_to_toggle'           => __( 'click to toggle', 'wp-simple-firewall' ),
				'go_to_options'             => sprintf(
					__( 'Go To %s', 'wp-simple-firewall' ),
					__( 'Options' )
				),
				'key'                       => __( 'Key' ),
				'key_positive'              => __( 'Positive Security', 'wp-simple-firewall' ),
				'key_warning'               => __( 'Potential Warning', 'wp-simple-firewall' ),
				'key_danger'                => __( 'Potential Danger', 'wp-simple-firewall' ),
				'key_information'           => __( 'Information', 'wp-simple-firewall' ),
			],
		];
	}
}