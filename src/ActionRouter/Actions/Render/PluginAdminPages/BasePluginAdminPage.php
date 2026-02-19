<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DashboardViewToggle;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminAuthClear;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Dashboard\DashboardViewPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Navigation\BuildBreadCrumbs;

abstract class BasePluginAdminPage extends BaseRender {

	public const TEMPLATE = '/wpadmin/plugin_pages/base_inner_page.twig';

	protected function getPageContextualHrefs() :array {
		return [];
	}

	protected function getPageContextualHrefs_Help() :array {
		return [];
	}

	protected function getInnerPageTitle() :string {
		return '';
	}

	protected function getInnerPageSubTitle() :string {
		return '';
	}

	/**
	 * @throws ActionException
	 */
	protected function getAllRenderDataArrays() :array {
		$data = parent::getAllRenderDataArrays();
		$data[ 25 ] = $this->getCommonAdminPageRenderData();
		return $data;
	}

	protected function getCommonAdminPageRenderData() :array {
		$urls = self::con()->plugin_urls;
		$toggleCurrentView = DashboardViewPreference::VIEW_SIMPLE;
		$toggleIsSimple = true;
		$toggleIsAdvanced = false;
		$toggleCurrentLabel = '';
		$toggleHref = '';
		$toggleTargetLabel = '';
		if ( $this->isDashboardOverviewPage() ) {
			$pref = new DashboardViewPreference();
			$currentView = $pref->getCurrent();
			$targetView = $pref->getToggleTarget();
			$toggleHref = $urls->noncedPluginAction(
				DashboardViewToggle::class,
				$urls->adminTopNav( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_OVERVIEW ),
				[
					'view' => $targetView,
				]
			);
			$toggleCurrentView = $currentView;
			$toggleIsSimple = $currentView === DashboardViewPreference::VIEW_SIMPLE;
			$toggleIsAdvanced = $currentView === DashboardViewPreference::VIEW_ADVANCED;
			$toggleCurrentLabel = $currentView === DashboardViewPreference::VIEW_ADVANCED
				? __( 'Advanced View', 'wp-simple-firewall' )
				: __( 'Simple View', 'wp-simple-firewall' );
			$toggleTargetLabel = $targetView === DashboardViewPreference::VIEW_ADVANCED
				? __( 'Advanced View', 'wp-simple-firewall' )
				: __( 'Simple View', 'wp-simple-firewall' );
		}

		$hrefs = $this->getPageContextualHrefs();
		if ( self::con()->comps->sec_admin->hasActiveSession() ) {
			$hrefs[] = [
				'title' => __( 'End Security Admin Session', 'wp-simple-firewall' ),
				'href'  => $urls->noncedPluginAction( SecurityAdminAuthClear::class, $urls->adminHome() ),
			];
		}
		$hrefs[] = $this->getPageContextualHrefs_Help();

		return [
			'hrefs' => [
				'breadcrumbs'                 => $this->getBreadCrumbs(),
				'dashboard_view_toggle'       => $toggleHref,
				'inner_page_contextual_hrefs' => \array_filter( $hrefs ),
			],
			'vars' => [
				'dashboard_view' => [
					'current'     => $toggleCurrentView,
					'is_simple'   => $toggleIsSimple,
					'is_advanced' => $toggleIsAdvanced,
				],
			],
			'strings' => [
				'dashboard_view_simple_label' => __( 'Simple View', 'wp-simple-firewall' ),
				'dashboard_view_advanced_label' => __( 'Advanced View', 'wp-simple-firewall' ),
				'dashboard_view_current'      => $toggleCurrentView,
				'dashboard_view_current_label' => $toggleCurrentLabel,
				'dashboard_view_toggle_label' => $toggleTargetLabel,
				'dashboard_view_target_label' => $toggleTargetLabel,
			],
		];
	}

	private function isDashboardOverviewPage() :bool {
		return (string)( $this->action_data[ Constants::NAV_ID ] ?? '' ) === PluginNavs::NAV_DASHBOARD
			   && (string)( $this->action_data[ Constants::NAV_SUB_ID ] ?? '' ) === PluginNavs::SUBNAV_DASHBOARD_OVERVIEW;
	}

	protected function getBreadCrumbs() :array {
		return ( new BuildBreadCrumbs() )->current();
	}
}
