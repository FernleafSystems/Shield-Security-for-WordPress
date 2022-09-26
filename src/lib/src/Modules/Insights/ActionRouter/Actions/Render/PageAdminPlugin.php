<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionRoutingController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\NavMenuBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class PageAdminPlugin extends BaseRender {

	use SecurityAdminNotRequired;

	const SLUG = 'page_admin_plugin';
	const PRIMARY_MOD = 'insights';
	const TEMPLATE = '/wpadmin_pages/insights/index.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$nav = $this->primary_mod->isAccessRestricted() ? 'restricted' : $req->query( ActionRoutingController::NAV_ID, Constants::ADMIN_PAGE_OVERVIEW );
		$subNav = (string)$req->query( ActionRoutingController::NAV_SUB_ID );

		// The particular renderer for the main page body area, based on navigation
		$delegateAction = $this->getDelegateActionRenderer()[ $nav ] ?? null;
		if ( empty( $delegateAction ) ) {
			throw new ActionException( 'Unavailable inav handling: '.$nav );
		}

		$pageTitleData = $this->getPageTitles()[ $nav ];
		$pageTitle = is_array( $pageTitleData ) ? implode( ' > ', $pageTitleData ) : $pageTitleData;
		if ( $nav === Constants::ADMIN_PAGE_CONFIG && !empty( $subNav ) ) {
			$activeMod = $con->getModule( $subNav );
			$pageTitle = sprintf( '%s > %s',
				__( 'Configuration', 'wp-simple-firewall' ), empty( $activeMod ) ? 'Unknown Module' : $activeMod->getMainFeatureName() );
		}

		return Services::DataManipulation()->mergeArraysRecursive(
			$mod->getUIHandler()->getBaseDisplayData(),
			[
				'classes' => [
					'page_container' => 'page-insights page-'.$nav
				],
				'content' => [
					'rendered_page_body' => $this->getCon()
												 ->getModule_Insights()
												 ->getActionRouter()
												 ->render( $delegateAction::SLUG, [
													 ActionRoutingController::NAV_ID     => $nav,
													 ActionRoutingController::NAV_SUB_ID => $subNav,
												 ] )->render_data[ 'output' ],
				],
				'flags'   => [
					'is_advanced' => $con->getModule_Plugin()->isShowAdvanced()
				],
				'hrefs'   => [
					'go_pro' => 'https://shsec.io/shieldgoprofeature',
				],
				'imgs'    => [
					'logo_banner' => $con->labels->url_img_pagebanner,
				],
				'strings' => [
					'page_title' => $pageTitle
				],
				'vars'    => [
					'active_module_settings' => $subNav,
					'navbar_menu'            => ( new NavMenuBuilder() )
						->setMod( $mod )
						->build()
				],
			]
		);
	}

	/**
	 * @return PluginAdminPages\BasePluginAdminPage[]
	 */
	private function getDelegateActionRenderer() :array {
		return [
			'audit_trail'                  => PluginAdminPages\ActivityLogTable::class,
			'debug'                        => PluginAdminPages\PageDebug::class,
			'docs'                         => PluginAdminPages\PageDocs::class,
			'importexport'                 => PluginAdminPages\PageImportExport::class,
			'ips'                          => PluginAdminPages\PageIpRulesTable::class,
			'license'                      => PluginAdminPages\PageLicense::class,
			'notes'                        => PluginAdminPages\PageAdminNotes::class,
			'merlin'                       => PluginAdminPages\PageMerlin::class,
			Constants::ADMIN_PAGE_OVERVIEW => PluginAdminPages\PageOverview::class,
			'reports'                      => PluginAdminPages\PageReports::class,
			'restricted'                   => PluginAdminPages\PageSecurityAdminRestricted::class,
			'rules'                        => PluginAdminPages\PageRulesSummary::class,
			'scans_results'                => PluginAdminPages\PageScansResults::class,
			'scans_run'                    => PluginAdminPages\PageScansRun::class,
			Constants::ADMIN_PAGE_CONFIG   => PluginAdminPages\PageConfig::class,
			'stats'                        => PluginAdminPages\PageStats::class,
			'traffic'                      => PluginAdminPages\TrafficLogTable::class,
			'users'                        => PluginAdminPages\PageUserSessions::class,
		];
	}

	private function getPageTitles() :array {
		return [
			'restricted'                   => [
				__( 'Restricted', 'wp-simple-firewall' ),
			],
			'stats'                        => [
				__( 'Reporting', 'wp-simple-firewall' ),
				__( 'Quick Stats', 'wp-simple-firewall' ),
			],
			'reports'                      => [
				__( 'Reporting', 'wp-simple-firewall' ),
				__( 'Charts', 'wp-simple-firewall' ),
			],
			Constants::ADMIN_PAGE_CONFIG   => __( 'Plugin Settings', 'wp-simple-firewall' ),
			'dashboard'                    => __( 'Dashboard', 'wp-simple-firewall' ),
			Constants::ADMIN_PAGE_OVERVIEW => __( 'Security Overview', 'wp-simple-firewall' ),
			'scans_results'                => [
				__( 'Scans', 'wp-simple-firewall' ),
				__( 'Scan Results', 'wp-simple-firewall' ),
			],
			'scans_run'                    => [
				__( 'Scans', 'wp-simple-firewall' ),
				__( 'Run Scans', 'wp-simple-firewall' ),
			],
			'ips'                          => [
				__( 'IPs', 'wp-simple-firewall' ),
				__( 'Management & Analysis', 'wp-simple-firewall' ),
			],
			'audit'                        => __( 'Activity Log', 'wp-simple-firewall' ),
			'audit_trail'                  => [
				__( 'Logs', 'wp-simple-firewall' ),
				__( 'View Activity Logs', 'wp-simple-firewall' ),
			],
			'traffic'                      => [
				__( 'Logs', 'wp-simple-firewall' ),
				__( 'View Traffic Logs', 'wp-simple-firewall' ),
			],
			'users'                        => [
				__( 'Users', 'wp-simple-firewall' ),
				__( 'Sessions', 'wp-simple-firewall' ),
			],
			'license'                      => [
				__( 'Licensing', 'wp-simple-firewall' ),
				__( 'ShieldPRO', 'wp-simple-firewall' ),
			],
			'free_trial'                   => [
				__( 'Licensing', 'wp-simple-firewall' ),
				__( 'Free Trial', 'wp-simple-firewall' ),
			],
			'importexport'                 => [
				__( 'Tools', 'wp-simple-firewall' ),
				sprintf( '%s / %s', __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) ),
			],
			'notes'                        => [
				__( 'Tools', 'wp-simple-firewall' ),
				__( 'Admin Notes', 'wp-simple-firewall' ),
			],
			'debug'                        => [
				__( 'Tools', 'wp-simple-firewall' ),
				__( 'Debug', 'wp-simple-firewall' ),
			],
			'docs'                         => [
				__( 'Tools', 'wp-simple-firewall' ),
				__( 'Docs', 'wp-simple-firewall' ),
			],
			'rules'                        => [
				__( 'Tools', 'wp-simple-firewall' ),
				__( 'Rules', 'wp-simple-firewall' ),
			],
			'merlin'                       => [
				__( 'Wizard', 'wp-simple-firewall' ),
				__( 'Guided Setup', 'wp-simple-firewall' ),
			],
		];
	}
}