<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\OverviewCards;

class UI extends Base\ShieldUI {

	public function buildInsightsVars() :array {
		$con = $this->getCon();

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\UI $uiReporting */
		$uiReporting = $con->getModule_Reporting()->getUIHandler();

		return [
			'content' => [
				'tab_updates'   => $this->renderTabUpdates(),
				'summary_stats' => $uiReporting->renderSummaryStats()
			],
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
				'tab_security_glance' => __( 'Security At A Glance', 'wp-simple-firewall' ),
				'tab_updates'         => __( 'Updates and Changes', 'wp-simple-firewall' ),
				'tab_summary_stats'   => __( 'Summary Stats', 'wp-simple-firewall' ),
				'click_filter_status' => __( 'Click To Filter By Security Status', 'wp-simple-firewall' ),
				'click_filter_area'   => __( 'Click To Filter By Security Area', 'wp-simple-firewall' ),
				'discover'            => __( 'Discover where your site security is doing well or areas that can be improved', 'wp-simple-firewall' ),
				'clear_filter'        => __( 'Clear Filter', 'wp-simple-firewall' ),
				'click_to_toggle'     => __( 'click to toggle', 'wp-simple-firewall' ),
				'go_to_options'       => sprintf(
					__( 'Go To %s', 'wp-simple-firewall' ),
					__( 'Options' )
				),
				'key'                 => __( 'Key' ),
				'key_positive'        => __( 'Positive Security', 'wp-simple-firewall' ),
				'key_warning'         => __( 'Potential Warning', 'wp-simple-firewall' ),
				'key_danger'          => __( 'Potential Danger', 'wp-simple-firewall' ),
				'key_information'     => __( 'Information', 'wp-simple-firewall' ),
			],
		];
	}

	private function renderTabUpdates() :string {
		$con = $this->getCon();

		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/overview/updates/index.twig',
						[
							'vars'    => [
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
								'tab_security_glance' => __( 'Security At A Glance', 'wp-simple-firewall' ),
							],
						],
						true
					);
	}
}