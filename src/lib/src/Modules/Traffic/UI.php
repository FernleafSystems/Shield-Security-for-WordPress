<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	public function renderTrafficTable() :string {
		/** @var \ICWP_WPSF_FeatureHandler_Traffic $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		/** @var Select $dbSel */
		$dbSel = $mod->getDbHandler_Traffic()->getQuerySelector();

		return $mod->renderTemplate(
			'/wpadmin_pages/insights/traffic/traffic_table.twig',
			[
				'ajax'    => [
					'render_table_traffic' => $mod->getAjaxActionData( 'render_table_traffic', true )
				],
				'flags'   => [
					'is_enabled' => $opts->isTrafficLoggerEnabled(),
				],
				'hrefs'   => [
					'please_enable' => $mod->getUrl_DirectLinkToOption( 'enable_logger' ),
				],
				'strings' => [
					'title_filter_form'       => __( 'Traffic Table Filters', 'wp-simple-firewall' ),
					'traffic_title'           => __( 'Traffic Watch', 'wp-simple-firewall' ),
					'traffic_subtitle'        => __( 'Watch and review requests to your site', 'wp-simple-firewall' ),
					'response'                => __( 'Response', 'wp-simple-firewall' ),
					'path_contains'           => __( 'Page/Path Contains', 'wp-simple-firewall' ),
					'exclude_your_ip'         => __( 'Exclude Your Current IP', 'wp-simple-firewall' ),
					'exclude_your_ip_tooltip' => __( 'Exclude Your IP From Results', 'wp-simple-firewall' ),
					'username_ignores'        => __( "Providing a username will cause the 'logged-in' filter to be ignored.", 'wp-simple-firewall' ),
				],
				'vars'    => [
					'unique_ips'       => $dbSel->getDistinctIps(),
					'unique_responses' => $dbSel->getDistinctCodes(),
					'unique_users'     => $dbSel->getDistinctUsernames(),
				],
			],
			true
		);
	}

	/**
	 * @param string $section
	 * @return array
	 */
	protected function getSectionWarnings( string $section ) :array {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		$aWarnings = [];

		$oIp = Services::IP();
		if ( !$oIp->isValidIp_PublicRange( $oIp->getRequestIp() ) ) {
			$aWarnings[] = __( 'Traffic Watcher will not run because visitor IP address detection is not correctly configured.', 'wp-simple-firewall' );
		}

		switch ( $section ) {
			case 'section_traffic_limiter':
				if ( $this->getCon()->isPremiumActive() ) {
					if ( !$oOpts->isTrafficLoggerEnabled() ) {
						$aWarnings[] = sprintf( __( '%s may only be enabled if the Traffic Logger feature is also turned on.', 'wp-simple-firewall' ), __( 'Traffic Rate Limiter', 'wp-simple-firewall' ) );
					}
				}
				else {
					$aWarnings[] = sprintf( __( '%s is a Pro-only feature.', 'wp-simple-firewall' ), __( 'Traffic Rate Limiter', 'wp-simple-firewall' ) );
				}
				break;
		}

		return $aWarnings;
	}

	protected function getSettingsRelatedLinks() :array {
		$modInsights = $this->getCon()->getModule_Insights();
		return [
			[
				'href'  => $modInsights->getUrl_SubInsightsPage( 'traffic' ),
				'title' => __( 'Traffic Log', 'wp-simple-firewall' ),
			]
		];
	}
}