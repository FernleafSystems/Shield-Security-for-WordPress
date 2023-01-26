<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\NavMenuBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class PageAdminPlugin extends BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'page_admin_plugin';
	public const PRIMARY_MOD = 'insights';
	public const TEMPLATE = '/wpadmin_pages/insights/index.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$nav = $this->primary_mod->isAccessRestricted() ? 'restricted' : $req->query( Constants::NAV_ID, PluginURLs::NAV_OVERVIEW );
		$subNav = (string)$req->query( Constants::NAV_SUB_ID );

		// The particular renderer for the main page body area, based on navigation
		$delegateAction = $this->getDelegateActionRenderer()[ $nav ] ?? null;
		if ( empty( $delegateAction ) ) {
			throw new ActionException( 'Unavailable nav handling: '.$nav );
		}

		$pageTitleData = $this->getPageTitles()[ $nav ];
		$pageTitle = is_array( $pageTitleData ) ? implode( ' > ', $pageTitleData ) : $pageTitleData;
		if ( $nav === PluginURLs::NAV_OPTIONS_CONFIG && !empty( $subNav ) ) {
			$activeMod = $con->getModule( $subNav );
			$pageTitle = sprintf( '%s > %s',
				__( 'Configuration', 'wp-simple-firewall' ), empty( $activeMod ) ? 'Unknown Module' : $activeMod->getMainFeatureName() );
		}

		return [
			'classes' => [
				'page_container' => 'page-insights page-'.$nav
			],
			'content' => [
				'rendered_page_body' => $this->getCon()->action_router->render( $delegateAction::SLUG, [
					Constants::NAV_ID     => $nav,
					Constants::NAV_SUB_ID => $subNav,
				] ),
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
		];
	}

	/**
	 * @return PluginAdminPages\BasePluginAdminPage[]
	 */
	private function getDelegateActionRenderer() :array {
		return [
			PluginURLs::NAV_ACTIVITY_LOG   => PluginAdminPages\PageActivityLogTable::class,
			PluginURLs::NAV_DEBUG          => PluginAdminPages\PageDebug::class,
			PluginURLs::NAV_DOCS           => PluginAdminPages\PageDocs::class,
			PluginURLs::NAV_IMPORT_EXPORT  => PluginAdminPages\PageImportExport::class,
			PluginURLs::NAV_IP_RULES       => PluginAdminPages\PageIpRulesTable::class,
			PluginURLs::NAV_LICENSE        => PluginAdminPages\PageLicense::class,
			PluginURLs::NAV_NOTES          => PluginAdminPages\PageAdminNotes::class,
			PluginURLs::NAV_OVERVIEW       => PluginAdminPages\PageOverview::class,
			PluginURLs::NAV_RESTRICTED     => PluginAdminPages\PageSecurityAdminRestricted::class,
			PluginURLs::NAV_OPTIONS_CONFIG => PluginAdminPages\PageConfig::class,
			PluginURLs::NAV_REPORTS        => PluginAdminPages\PageReports::class,
			PluginURLs::NAV_RULES_VIEW     => PluginAdminPages\PageRulesSummary::class,
			PluginURLs::NAV_SCANS_RESULTS  => PluginAdminPages\PageScansResults::class,
			PluginURLs::NAV_SCANS_RUN      => PluginAdminPages\PageScansRun::class,
			PluginURLs::NAV_STATS          => PluginAdminPages\PageStats::class,
			PluginURLs::NAV_TRAFFIC_VIEWER => PluginAdminPages\PageTrafficLogTable::class,
			PluginURLs::NAV_USER_SESSIONS  => PluginAdminPages\PageUserSessions::class,
			PluginURLs::NAV_WIZARD         => PluginAdminPages\PageMerlin::class,
		];
	}

	private function getPageTitles() :array {
		return [
			PluginURLs::NAV_RESTRICTED     => [
				__( 'Restricted', 'wp-simple-firewall' ),
			],
			PluginURLs::NAV_STATS          => [
				__( 'Reporting', 'wp-simple-firewall' ),
				__( 'Quick Stats', 'wp-simple-firewall' ),
			],
			PluginURLs::NAV_REPORTS        => [
				__( 'Reporting', 'wp-simple-firewall' ),
				__( 'Charts', 'wp-simple-firewall' ),
			],
			PluginURLs::NAV_OPTIONS_CONFIG => __( 'Plugin Settings', 'wp-simple-firewall' ),
			'dashboard'                    => __( 'Dashboard', 'wp-simple-firewall' ),
			PluginURLs::NAV_OVERVIEW       => __( 'Security Overview', 'wp-simple-firewall' ),
			PluginURLs::NAV_SCANS_RESULTS  => [
				__( 'Scans', 'wp-simple-firewall' ),
				__( 'Scan Results', 'wp-simple-firewall' ),
			],
			PluginURLs::NAV_SCANS_RUN      => [
				__( 'Scans', 'wp-simple-firewall' ),
				__( 'Run Scans', 'wp-simple-firewall' ),
			],
			PluginURLs::NAV_IP_RULES       => [
				__( 'IPs', 'wp-simple-firewall' ),
				__( 'Management & Analysis', 'wp-simple-firewall' ),
			],
			'audit'                        => __( 'Activity Log', 'wp-simple-firewall' ),
			PluginURLs::NAV_ACTIVITY_LOG   => [
				__( 'Logs', 'wp-simple-firewall' ),
				__( 'View Activity Logs', 'wp-simple-firewall' ),
			],
			PluginURLs::NAV_TRAFFIC_VIEWER => [
				__( 'Logs', 'wp-simple-firewall' ),
				__( 'View Traffic Logs', 'wp-simple-firewall' ),
			],
			PluginURLs::NAV_USER_SESSIONS  => [
				__( 'Users', 'wp-simple-firewall' ),
				__( 'Sessions', 'wp-simple-firewall' ),
			],
			PluginURLs::NAV_LICENSE        => [
				__( 'Licensing', 'wp-simple-firewall' ),
				__( 'ShieldPRO', 'wp-simple-firewall' ),
			],
			'free_trial'                   => [
				__( 'Licensing', 'wp-simple-firewall' ),
				__( 'Free Trial', 'wp-simple-firewall' ),
			],
			PluginURLs::NAV_IMPORT_EXPORT  => [
				__( 'Tools', 'wp-simple-firewall' ),
				sprintf( '%s / %s', __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) ),
			],
			PluginURLs::NAV_NOTES          => [
				__( 'Tools', 'wp-simple-firewall' ),
				__( 'Admin Notes', 'wp-simple-firewall' ),
			],
			PluginURLs::NAV_DEBUG          => [
				__( 'Tools', 'wp-simple-firewall' ),
				__( 'Debug', 'wp-simple-firewall' ),
			],
			PluginURLs::NAV_DOCS           => [
				__( 'Tools', 'wp-simple-firewall' ),
				__( 'Docs', 'wp-simple-firewall' ),
			],
			PluginURLs::NAV_RULES_VIEW     => [
				__( 'Tools', 'wp-simple-firewall' ),
				__( 'Rules', 'wp-simple-firewall' ),
			],
			PluginURLs::NAV_WIZARD         => [
				__( 'Wizard', 'wp-simple-firewall' ),
				__( 'Guided Setup', 'wp-simple-firewall' ),
			],
		];
	}
}