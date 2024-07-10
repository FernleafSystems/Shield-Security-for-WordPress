<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageReports extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_reports';

	protected function getPageContextualHrefs() :array {
		$con = self::con();
		$hrefs = [];
		if ( $con->caps->canReportsLocal() ) {
			\array_unshift( $hrefs, [
				'title'   => __( 'Create Custom Report', 'wp-simple-firewall' ),
				'href'    => '#',
				'classes' => [ 'offcanvas_report_create_form' ],
			] );
		}
		return $hrefs;
	}

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s', __( 'Help', 'wp-simple-firewall' ), __( 'Reports', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/collection/77-reporting',
			'new_window' => true,
		];
	}

	protected function getRenderData() :array {
		return [
			'content' => $this->buildContent(),
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'clipboard-data-fill' ),
			],
			'strings' => [
				'inner_page_title'    => $this->getInnerPageTitle(),
				'inner_page_subtitle' => $this->getInnerPageSubTitle(),
			],
		];
	}

	protected function getInnerPageTitle() :string {
		switch ( $this->action_data[ 'nav_sub' ] ) {
			case PluginNavs::SUBNAV_REPORTS_LIST:
				$title = __( 'View & Create', 'wp-simple-firewall' );
				break;
			default:
				$title = __( 'Security Reports', 'wp-simple-firewall' );
				break;
		}
		return $title;
	}

	protected function getInnerPageSubTitle() :string {
		switch ( $this->action_data[ 'nav_sub' ] ) {
			case PluginNavs::SUBNAV_REPORTS_LIST:
				$title = __( 'View and create new security reports.', 'wp-simple-firewall' );
				break;
			default:
				$title = __( 'Summary Security Reports.', 'wp-simple-firewall' );
				break;
		}
		return $title;
	}

	private function buildContent() :array {
		$AR = self::con()->action_router;
		switch ( $this->action_data[ 'nav_sub' ] ) {
			case PluginNavs::SUBNAV_REPORTS_LIST:
				$content = [
					'create_report' => $AR->render( Reports\PageReportsView::class ),
				];
				break;
			default:
				$content = [];
				break;
		}
		return $content;
	}
}