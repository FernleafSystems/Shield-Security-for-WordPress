<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	PageActivityLogTable,
	PageDebug,
	PageDocs,
	PageImportExport,
	PageReports,
	PageSecurityAdminRestricted,
	PageTrafficLogTable
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\NavMenuBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\Handler;
use FernleafSystems\Wordpress\Services\Services;

class PageAdminPlugin extends BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'page_admin_plugin';
	public const TEMPLATE = '/wpadmin_pages/insights/index.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$req = Services::Request();

		$nav = $con->getModule_Plugin()->isAccessRestricted()
			? PluginNavs::NAV_RESTRICTED
			: $req->query( Constants::NAV_ID, PluginNavs::NAV_DASHBOARD );
		$subNav = $nav === PluginNavs::NAV_RESTRICTED ? '' : (string)$req->query( Constants::NAV_SUB_ID );

		// The particular renderer for the main page body area, based on navigation
		$delegateAction = null;
		foreach ( $this->getDelegateActionRenderers() as $actionClass => $navs ) {
			if ( \in_array( $nav.$subNav, $navs ) || \in_array( $nav, $navs ) ) {
				$delegateAction = $actionClass;
				break;
			}
		}
		if ( empty( $delegateAction ) ) {
			throw new ActionException( 'Unavailable nav handling: '.$nav.' '.$subNav );
		}

		$pageTitle = \implode( ' > ', $this->getPageTitles( $nav, $subNav ) );
		if ( $nav === PluginNavs::NAV_OPTIONS_CONFIG && !empty( $subNav ) ) {
			$activeMod = $con->getModule( $subNav );
			$pageTitle = sprintf( '%s > %s',
				__( 'Configuration', 'wp-simple-firewall' ), empty( $activeMod ) ? 'Unknown Module' : $activeMod->getMainFeatureName() );
		}

		return [
			'classes' => [
				'page_container' => 'page-insights page-'.$nav
			],
			'content' => [
				'rendered_page_body' => self::con()->action_router->render( $delegateAction::SLUG, [
					Constants::NAV_ID     => $nav,
					Constants::NAV_SUB_ID => $subNav,
				] ),
			],
			'flags'   => [
				'is_advanced' => $con->getModule_Plugin()->isShowAdvanced(),
			],
			'imgs'    => [
				'logo_banner' => $con->labels->url_img_pagebanner,
			],
			'strings' => [
				'page_title'        => $pageTitle,
				'top_page_warnings' => $this->buildTopPageWarnings(),
			],
			'vars'    => [
				'active_module_settings' => $subNav,
				'navbar_menu'            => ( new NavMenuBuilder() )->build(),
			],
		];
	}

	protected function buildTopPageWarnings() :array {
		return \array_filter(
			( new Handler() )->build(),
			function ( array $issue ) {
				return \in_array( 'shield_admin_top_page', $issue[ 'locations' ] );
			}
		);
	}

	private function getDelegateActionRenderers() :array {
		return [
			PageActivityLogTable::class                 => [
				PluginNavs::NAV_ACTIVITY.PluginNavs::SUBNAV_ACTIVITY_LOG
			],
			PageImportExport::class                     => [
				PluginNavs::NAV_TOOLS.PluginNavs::SUBNAV_TOOLS_IMPORT
			],
			PluginAdminPages\PageIpRulesTable::class    => [
				PluginNavs::NAV_IPS.PluginNavs::SUBNAV_IPS_RULES
			],
			PluginAdminPages\PageLicense::class         => [
				PluginNavs::NAV_LICENSE.PluginNavs::SUBNAV_LICENSE_CHECK
			],
			PluginAdminPages\PageDashboardMeters::class => [
				PluginNavs::NAV_DASHBOARD.PluginNavs::SUBNAV_DASHBOARD_GRADES,
			],
			PluginAdminPages\PageDashboardOverview::class => [
				PluginNavs::NAV_DASHBOARD.PluginNavs::SUBNAV_DASHBOARD_OVERVIEW,
			],
			PageSecurityAdminRestricted::class          => [
				PluginNavs::NAV_RESTRICTED
			],
			PluginAdminPages\PageDynamicLoad::class     => [
				PluginNavs::NAV_OPTIONS_CONFIG
			],
			PageReports::class                          => [
				PluginNavs::NAV_REPORTS,
				PluginNavs::NAV_REPORTS.PluginNavs::SUBNAV_REPORTS_LIST,
				PluginNavs::NAV_REPORTS.PluginNavs::SUBNAV_REPORTS_CHARTS,
				PluginNavs::NAV_REPORTS.PluginNavs::SUBNAV_REPORTS_STATS,
			],
			PluginAdminPages\PageRulesSummary::class    => [
				PluginNavs::NAV_RULES_VIEW
			],
			PluginAdminPages\PageScansResults::class    => [
				PluginNavs::NAV_SCANS.PluginNavs::SUBNAV_SCANS_RESULTS
			],
			PluginAdminPages\PageScansRun::class        => [
				PluginNavs::NAV_SCANS.PluginNavs::SUBNAV_SCANS_RUN
			],
			PluginAdminPages\PageStats::class           => [
				PluginNavs::NAV_STATS
			],
			PageDocs::class                             => [
				PluginNavs::NAV_TOOLS.PluginNavs::SUBNAV_TOOLS_DOCS
			],
			PageDebug::class                            => [
				PluginNavs::NAV_TOOLS.PluginNavs::SUBNAV_TOOLS_DEBUG
			],
			PageTrafficLogTable::class                  => [
				PluginNavs::NAV_TRAFFIC.PluginNavs::SUBNAV_TRAFFIC_LOG
			],
			PluginAdminPages\PageUserSessions::class    => [
				PluginNavs::NAV_TOOLS.PluginNavs::SUBNAV_TOOLS_SESSIONS
			],
			PluginAdminPages\PageMerlin::class          => [
				PluginNavs::NAV_WIZARD
			],
		];
	}

	private function getPageTitles( string $nav, string $subnav ) :array {
		$key = empty( $subnav ) ? $nav : $nav.$subnav;
		return [
				   PluginNavs::NAV_RESTRICTED                                      => [
					   __( 'Restricted', 'wp-simple-firewall' ),
				   ],
				   PluginNavs::NAV_STATS                                           => [
					   __( 'Reporting', 'wp-simple-firewall' ),
					   __( 'Quick Stats', 'wp-simple-firewall' ),
				   ],
				   PluginNavs::NAV_REPORTS                                         => [
					   __( 'Reporting', 'wp-simple-firewall' ),
					   __( 'Charts', 'wp-simple-firewall' ),
				   ],
				   PluginNavs::NAV_OPTIONS_CONFIG                                  => [
					   __( 'Plugin Config', 'wp-simple-firewall' )
				   ],
				   PluginNavs::NAV_DASHBOARD.PluginNavs::SUBNAV_DASHBOARD_GRADES => [
					   __( 'Security Overview (Meters)', 'wp-simple-firewall' )
				   ],
				   PluginNavs::NAV_DASHBOARD.PluginNavs::SUBNAV_DASHBOARD_OVERVIEW => [
					   __( 'Security Overview', 'wp-simple-firewall' )
				   ],
				   PluginNavs::NAV_SCANS.PluginNavs::SUBNAV_SCANS_RESULTS          => [
					   __( 'Scans', 'wp-simple-firewall' ),
					   __( 'Scan Results', 'wp-simple-firewall' ),
				   ],
				   PluginNavs::NAV_SCANS.PluginNavs::SUBNAV_SCANS_RUN              => [
					   __( 'Scans', 'wp-simple-firewall' ),
					   __( 'Run Manual Scan', 'wp-simple-firewall' ),
				   ],
				   PluginNavs::NAV_IPS.PluginNavs::SUBNAV_IPS_RULES                => [
					   __( 'IPs', 'wp-simple-firewall' ),
					   __( 'Management & Analysis', 'wp-simple-firewall' ),
				   ],
				   PluginNavs::NAV_ACTIVITY.PluginNavs::SUBNAV_ACTIVITY_LOG        => [
					   __( 'Logs', 'wp-simple-firewall' ),
					   __( 'View Activity Logs', 'wp-simple-firewall' ),
				   ],
				   PluginNavs::NAV_TRAFFIC.PluginNavs::SUBNAV_TRAFFIC_LOG          => [
					   __( 'Logs', 'wp-simple-firewall' ),
					   __( 'View Traffic Logs', 'wp-simple-firewall' ),
				   ],
				   PluginNavs::NAV_LICENSE                                         => [
					   __( 'Licensing', 'wp-simple-firewall' ),
					   __( 'ShieldPRO', 'wp-simple-firewall' ),
				   ],
				   'free_trial'                                                    => [
					   __( 'Licensing', 'wp-simple-firewall' ),
					   __( 'Free Trial', 'wp-simple-firewall' ),
				   ],
				   PluginNavs::NAV_TOOLS.PluginNavs::SUBNAV_TOOLS_SESSIONS         => [
					   __( 'Users', 'wp-simple-firewall' ),
					   __( 'Sessions', 'wp-simple-firewall' ),
				   ],
				   PluginNavs::NAV_TOOLS.PluginNavs::SUBNAV_TOOLS_IMPORT           => [
					   __( 'Tools', 'wp-simple-firewall' ),
					   sprintf( '%s / %s', __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) ),
				   ],
				   PluginNavs::NAV_TOOLS.PluginNavs::SUBNAV_TOOLS_DEBUG            => [
					   __( 'Tools', 'wp-simple-firewall' ),
					   __( 'Debug', 'wp-simple-firewall' ),
				   ],
				   PluginNavs::NAV_TOOLS.PluginNavs::SUBNAV_TOOLS_DOCS             => [
					   __( 'Tools', 'wp-simple-firewall' ),
					   __( 'Docs', 'wp-simple-firewall' ),
				   ],
				   PluginNavs::NAV_RULES_VIEW                                      => [
					   __( 'Tools', 'wp-simple-firewall' ),
					   __( 'Rules', 'wp-simple-firewall' ),
				   ],
				   PluginNavs::NAV_WIZARD                                          => [
					   __( 'Wizard', 'wp-simple-firewall' ),
					   __( 'Guided Setup', 'wp-simple-firewall' ),
				   ],
			   ][ $key ] ?? [
				   __( 'UNSET TITLE', 'wp-simple-firewall' ),
			   ];
	}
}