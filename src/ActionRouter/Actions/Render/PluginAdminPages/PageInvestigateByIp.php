<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\Container as IpAnalyseContainer;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

class PageInvestigateByIp extends BasePluginAdminPage {

	use InvestigateRenderContracts;

	public const SLUG = 'plugin_admin_page_investigate_by_ip';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_by_ip.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$lookup = $this->getTextInputFromRequestOrActionData( 'analyse_ip' );
		$hasLookup = !empty( $lookup );
		$hasSubject = $hasLookup && Services::IP()->isValidIp( $lookup );
		$subjectHeader = [];

		$ipAnalysis = '';
		if ( $hasSubject ) {
			$ipAnalysis = $con->action_router->render( IpAnalyseContainer::class, [
				'ip' => $lookup,
			] );
			$subjectHeader = [
				'title' => $lookup,
				'meta'  => '',
			];
		}

		return [
			'flags'   => [
				'has_lookup'  => $hasLookup,
				'has_subject' => $hasSubject,
			],
			'hrefs'   => [
				'by_ip' => $con->plugin_urls->investigateByIp(),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'globe2' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Investigate By IP', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Inspect sessions, activity, and request behavior for a specific IP address.', 'wp-simple-firewall' ),
				'lookup_label'        => __( 'IP Lookup', 'wp-simple-firewall' ),
				'lookup_placeholder'  => __( 'Search for an IP address...', 'wp-simple-firewall' ),
				'lookup_submit'       => __( 'Load IP Context', 'wp-simple-firewall' ),
				'lookup_helper'       => __( 'Type at least 2 characters to find matching IP addresses.', 'wp-simple-firewall' ),
				'change_subject'      => __( 'Change IP address', 'wp-simple-firewall' ),
				'no_subject_title'    => __( 'No IP Selected', 'wp-simple-firewall' ),
				'no_subject_text'     => __( 'Use the lookup above to load investigate context for an IP address.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'analyse_ip'      => $lookup,
				'lookup_route'    => $this->buildLookupRouteContract( PluginNavs::SUBNAV_ACTIVITY_BY_IP ),
				'lookup_behavior' => $this->buildLookupBehaviorContract( true, true, true ),
				'lookup_ajax'     => $this->buildLookupAjaxContract( 'ip' ),
				'subject_header'  => $subjectHeader,
			],
			'content' => [
				'ip_analysis' => $ipAnalysis,
			],
		];
	}
}
