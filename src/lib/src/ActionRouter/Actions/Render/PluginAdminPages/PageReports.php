<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageReports extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_reports';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/reports.twig';

	protected function getPageContextualHrefs() :array {
		$con = $this->con();
		return [
			[
				'text' => __( 'Configure Activity Logging', 'wp-simple-firewall' ),
				'href' => $con->plugin_urls->offCanvasConfigRender( $con->getModule_AuditTrail()->cfg->slug ),
			]
		];
	}

	protected function getRenderData() :array {
		return [
			'content' => $this->buildContent(),
			'strings' => [
				'inner_page_title'    => $this->getInnerPageTitle(),
				'inner_page_subtitle' => $this->getInnerPageSubTitle(),
			],
		];
	}

	protected function getInnerPageTitle() :string {
		switch ( $this->action_data[ 'nav_sub' ] ) {
			case PluginNavs::SUBNAV_CHANGE_TRACK:
				$title = __( 'Site Change Tracking Reports', 'wp-simple-firewall' );
				break;
			case PluginNavs::SUBNAV_CHARTS:
				$title = __( 'Security Performance Charts', 'wp-simple-firewall' );
				break;
			case PluginNavs::SUBNAV_STATS:
				$title = __( 'Key Stats At A Glance', 'wp-simple-firewall' );
				break;
			default:
				$title = __( 'Security Reports', 'wp-simple-firewall' );
				break;
		}
		return $title;
	}

	protected function getInnerPageSubTitle() :string {
		switch ( $this->action_data[ 'nav_sub' ] ) {
			case PluginNavs::SUBNAV_CHANGE_TRACK:
				$title = __( 'View changes to your site between two dates.', 'wp-simple-firewall' );
				break;
			case PluginNavs::SUBNAV_CHARTS:
				$title = __( 'Basic charts - this is in beta and will be developed over time.', 'wp-simple-firewall' );
				break;
			case PluginNavs::SUBNAV_STATS:
				$title = __( 'View key Shield stats.', 'wp-simple-firewall' );
				break;
			default:
				$title = __( 'Summary Security Reports.', 'wp-simple-firewall' );
				break;
		}
		return $title;
	}

	private function buildContent() :array {
		$AR = $this->con()->action_router;
		switch ( $this->action_data[ 'nav_sub' ] ) {
			case PluginNavs::SUBNAV_CHANGE_TRACK:
				$content = [
					'changetracking_default' => $AR->render( Reports\ChangeTrack\PageReportGenerateNewChangeTrack::SLUG ),
				];
				break;
			case PluginNavs::SUBNAV_CHARTS:
				$content = [
					'summary_stats' => $AR->render( Reports\ChartsSummary::SLUG ),
					'custom_chart'  => $AR->render( Reports\ChartsCustom::SLUG ),
				];
				break;
			default:
				$content = [
				];
				break;
		}
		return $content;
	}
}