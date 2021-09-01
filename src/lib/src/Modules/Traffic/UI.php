<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class UI extends BaseShield\UI {

	public function renderTrafficTable() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		/** @var Select $dbSel */
		$dbSel = $mod->getDbHandler_Traffic()->getQuerySelector();

		( new Lib\Ops\ConvertLegacy() )
			->setMod( $this->getMod() )
			->run();

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

	protected function getSectionWarnings( string $section ) :array {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$warning = [];

		$srvIP = Services::IP();
		if ( !$srvIP->isValidIp_PublicRange( $srvIP->getRequestIp() ) ) {
			$warning[] = __( 'Traffic Watcher will not run because visitor IP address detection is not correctly configured.', 'wp-simple-firewall' );
		}

		switch ( $section ) {
			case 'section_traffic_limiter':
				if ( $this->getCon()->isPremiumActive() ) {
					if ( !$opts->isTrafficLoggerEnabled() ) {
						$warning[] = sprintf( __( '%s may only be enabled if the Traffic Logger feature is also turned on.', 'wp-simple-firewall' ), __( 'Traffic Rate Limiter', 'wp-simple-firewall' ) );
					}
				}
				else {
					$warning[] = sprintf( __( '%s is a Pro-only feature.', 'wp-simple-firewall' ), __( 'Traffic Rate Limiter', 'wp-simple-firewall' ) );
				}
				break;
		}

		return $warning;
	}
}