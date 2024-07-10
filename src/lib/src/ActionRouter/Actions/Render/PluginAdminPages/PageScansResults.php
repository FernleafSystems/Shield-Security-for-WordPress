<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Malware,
	Plugins,
	Themes,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\CleanQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\{
	Counts,
	Retrieve\RetrieveCount
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\{
	Apc,
	Wpv
};

class PageScansResults extends PageScansBase {

	public const SLUG = 'admin_plugin_page_scans_results';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/scan_results.twig';

	protected function getPageContextualHrefs() :array {
		return [
			[
				'title'   => __( 'Results Display Options', 'wp-simple-firewall' ),
				'href'    => 'javascript:{}',
				'classes' => [ 'offcanvas_form_scans_results_options' ],
			],
			[
				'title' => __( 'Run Manual Scan', 'wp-simple-firewall' ),
				'href'  => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RUN ),
			],
		];
	}

	protected function getRenderData() :array {
		$con = self::con();
		$scansCon = $con->comps->scans;

		( new CleanQueue() )->execute();
		foreach ( $scansCon->getAllScanCons() as $scanCon ) {
			$scanCon->cleanStalesResults();
		}

		$vulnerableOrAbandonedPlugins = 0;
		$vulnerableOrAbandonedThemes = 0;
		$counter = new Counts( RetrieveCount::CONTEXT_RESULTS_DISPLAY );
		if ( $counter->countVulnerableAssets() > 0 ) {
			foreach ( $scansCon->WPV()->getAllResults()->getAllItems() as $result ) {
				/** @var Wpv\ResultItem $result */
				if ( $result->VO->item_type === Handler::ITEM_TYPE_PLUGIN ) {
					$vulnerableOrAbandonedPlugins++;
				}
				elseif ( $result->VO->item_type === Handler::ITEM_TYPE_THEME ) {
					$vulnerableOrAbandonedThemes++;
				}
			}
		}
		if ( $counter->countAbandoned() > 0 ) {
			foreach ( $scansCon->APC()->getAllResults()->getAllItems() as $result ) {
				/** @var Apc\ResultItem $result */
				if ( $result->VO->item_type === Handler::ITEM_TYPE_PLUGIN ) {
					$vulnerableOrAbandonedPlugins++;
				}
				elseif ( $result->VO->item_type === Handler::ITEM_TYPE_THEME ) {
					$vulnerableOrAbandonedThemes++;
				}
			}
		}

		return [
			'content'     => [
				'section' => [
					'plugins'    => $con->action_router->render( Plugins::class ),
					'themes'     => $con->action_router->render( Themes::class ),
					'wordpress'  => $con->action_router->render( Wordpress::class ),
					'malware'    => $con->action_router->render( Malware::class ),
					'filelocker' => $con->action_router->render( FileLocker::class ),
					'logs'       => 'logs todo',
				]
			],
			'file_locker' => $this->getFileLockerVars(),
			'flags'       => [
				'is_premium' => $con->isPremiumActive(),
			],
			'hrefs'       => [
				'scans_results' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
			],
			'imgs'        => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'shield-shaded' ),
			],
			'strings'     => [
				'inner_page_title'    => __( 'View Results', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'View and manage all scan results.', 'wp-simple-firewall' ),

				'never'                 => __( 'Never', 'wp-simple-firewall' ),
				'not_enabled'           => __( 'This scan is not currently enabled.', 'wp-simple-firewall' ),
				'please_enable'         => __( 'Please turn on this scan in the options.', 'wp-simple-firewall' ),
				'scan_options'          => __( 'Scan Options', 'wp-simple-firewall' ),
				'select_view_results'   => __( 'View Scan Results', 'wp-simple-firewall' ),
				'clear_ignore'          => __( 'Clear Ignore Flags', 'wp-simple-firewall' ),
				'clear_ignore_sub'      => __( 'Previously ignored results will be revealed (for the selected scans only)', 'wp-simple-firewall' ),
				'run_scans_now'         => __( 'Run Scans Now', 'wp-simple-firewall' ),
				'no_entries_to_display' => __( "The previous scan either didn't detect any items that require your attention or they've already been repaired.", 'wp-simple-firewall' ),
				'scan_progress'         => __( 'Scan Progress', 'wp-simple-firewall' ),
				'reason_not_call_self'  => __( "This site currently can't make HTTP requests to itself.", 'wp-simple-firewall' ),
			],
			'vars'        => [
				'sections' => [
					'plugins'   => [
						'count' => $counter->countPluginFiles() + $vulnerableOrAbandonedPlugins,
					],
					'themes'    => [
						'count' => $counter->countThemeFiles() + $vulnerableOrAbandonedThemes,
					],
					'wordpress' => [
						'count' => $counter->countWPFiles(),
					],
					'malware'   => [
						'count' => $counter->countMalware(),
					],
				]
			],
		];
	}

	protected function getFileLockerVars() :array {
		return [
			'strings' => [
				'title' => __( 'File Locker', 'wp-simple-firewall' ),
			],
			'count'   => \count( ( new LoadFileLocks() )->withProblems() )
		];
	}
}