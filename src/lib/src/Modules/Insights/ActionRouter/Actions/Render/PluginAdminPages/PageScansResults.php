<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\CleanQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Malware,
	Plugins,
	Themes,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\{
	ScansCheck,
	ScansStart
};

class PageScansResults extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_scans_results';
	public const PRIMARY_MOD = 'hack_protect';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/index.twig';

	protected function getRenderData() :array {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		/** @var Options $opts */
		$opts = $this->primary_mod->getOptions();

		( new CleanQueue() )
			->setMod( $this->primary_mod )
			->execute();
		foreach ( $opts->getScanSlugs() as $scan ) {
			$mod->getScanCon( $scan )->cleanStalesResults();
		}

		$actionRouter = $this->getCon()
							 ->getModule_Insights()
							 ->getActionRouter();
		$counter = $mod->getScansCon()->getScanResultsCount();

		// Can Scan Checks:
		$reasonsCantScan = $mod->getScansCon()->getReasonsScansCantExecute();
		return [
			'ajax'        => [
				'scans_start' => ActionData::BuildJson( ScansStart::SLUG ),
				'scans_check' => ActionData::BuildJson( ScansCheck::SLUG ),
			],
			'flags'       => [
				'is_premium'      => $this->getCon()->isPremiumActive(),
				'can_scan'        => count( $reasonsCantScan ) === 0,
				'module_disabled' => !$mod->isModOptEnabled(),
			],
			'strings'     => [
				'never'                 => __( 'Never', 'wp-simple-firewall' ),
				'not_enabled'           => __( 'This scan is not currently enabled.', 'wp-simple-firewall' ),
				'please_enable'         => __( 'Please turn on this scan in the options.', 'wp-simple-firewall' ),
				'title_scan_now'        => __( 'Scan Your Site Now', 'wp-simple-firewall' ),
				'subtitle_scan_now'     => __( 'Run the selected scans on your site now to get the latest results', 'wp-simple-firewall' ),
				'more_items_longer'     => __( 'The more scans that are selected, the longer the scan may take.', 'wp-simple-firewall' ),
				'scan_options'          => __( 'Scan Options', 'wp-simple-firewall' ),
				'select_view_results'   => __( 'View Scan Results', 'wp-simple-firewall' ),
				'clear_ignore'          => __( 'Clear Ignore Flags', 'wp-simple-firewall' ),
				'clear_ignore_sub'      => __( 'Previously ignored results will be revealed (for the selected scans only)', 'wp-simple-firewall' ),
				'clear_suppression'     => __( 'Remove Notification Suppression', 'wp-simple-firewall' ),
				'clear_suppression_sub' => __( 'Allow notification emails to be resent (for the selected scans only)', 'wp-simple-firewall' ),
				'run_scans_now'         => __( 'Run Scans Now', 'wp-simple-firewall' ),
				'no_entries_to_display' => __( "The previous scan either didn't detect any items that require your attention or they've already been repaired.", 'wp-simple-firewall' ),
				'scan_progress'         => __( 'Scan Progress', 'wp-simple-firewall' ),
				'reason_not_call_self'  => __( "This site currently can't make HTTP requests to itself.", 'wp-simple-firewall' ),
				'module_disabled'       => __( "Scans can't run because the module that controls them is currently disabled.", 'wp-simple-firewall' ),
				'review_scanner_config' => __( "Review Scanner Module configuration", 'wp-simple-firewall' ),
			],
			'vars'        => [
				'initial_check'       => $mod->getScanQueueController()->hasRunningScans(),
				'cannot_scan_reasons' => $reasonsCantScan,
				'sections'            => [
					'plugins'   => [
						'count' => $counter->countPluginFiles()
					],
					'themes'    => [
						'count' => $counter->countThemeFiles()
					],
					'wordpress' => [
						'count' => $counter->countWPFiles()
					],
					'malware'   => [
						'count' => $counter->countMalware()
					],
				]
			],
			'hrefs'       => [
				'scanner_mod_config' => $mod->getUrl_DirectLinkToSection( 'section_enable_plugin_feature_hack_protection_tools' ),
				'scans_results'      => $this->getCon()
											 ->getModule_Insights()
											 ->getUrl_ScansResults(),
			],
			'content'     => [
				'section' => [
					'plugins'    => $actionRouter->render( Plugins::SLUG ),
					'themes'     => $actionRouter->render( Themes::SLUG ),
					'wordpress'  => $actionRouter->render( Wordpress::SLUG ),
					'malware'    => $actionRouter->render( Malware::SLUG ),
					'filelocker' => $actionRouter->render( FileLocker::SLUG ),
					'logs'       => 'logs todo',
				]
			],
			'file_locker' => $this->getFileLockerVars(),
		];
	}

	protected function getFileLockerVars() :array {
		return [
			'strings' => [
				'title' => __( 'File Locker', 'wp-simple-firewall' ),
			],
			'count'   => count( ( new LoadFileLocks() )
				->setMod( $this->primary_mod )
				->withProblems() )
		];
	}
}