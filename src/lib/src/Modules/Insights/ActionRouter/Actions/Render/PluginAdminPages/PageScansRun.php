<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\CleanQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Strings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\ScansCheck;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\ScansStart;

class PageScansRun extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_scans_run';
	public const PRIMARY_MOD = 'hack_protect';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/run/index.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		( new CleanQueue() )
			->setMod( $this->primary_mod )
			->execute();

		// Can Scan Checks:
		$reasonsCantScan = $mod->getScansCon()->getReasonsScansCantExecute();
		return [
			'ajax'    => [
				'scans_start' => ActionData::BuildJson( ScansStart::SLUG ),
				'scans_check' => ActionData::BuildJson( ScansCheck::SLUG ),
			],
			'flags'   => [
				'is_premium'      => $con->isPremiumActive(),
				'can_scan'        => count( $reasonsCantScan ) === 0,
				'module_disabled' => !$mod->isModOptEnabled(),
			],
			'strings' => [
				'never'                 => __( 'Never', 'wp-simple-firewall' ),
				'not_available'         => __( 'Sorry, this scan is not available.', 'wp-simple-firewall' ),
				'not_enabled'           => __( 'This scan is not currently enabled.', 'wp-simple-firewall' ),
				'please_enable'         => __( 'Please turn on this scan in the options.', 'wp-simple-firewall' ),
				'title_scan_now'        => __( 'Scan Your Site Now', 'wp-simple-firewall' ),
				'subtitle_scan_now'     => __( 'Run the selected scans on your site now to get the latest results', 'wp-simple-firewall' ),
				'more_items_longer'     => __( 'The more scans that are selected, the longer the scan may take.', 'wp-simple-firewall' ),
				'scan_options'          => __( 'Scan Options', 'wp-simple-firewall' ),
				'scanselect'            => __( 'Select Scans To Run', 'wp-simple-firewall' ),
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
			'scans'   => $this->buildScansVars(),
			'vars'    => [
				'initial_check'       => $mod->getScanQueueController()->hasRunningScans(),
				'cannot_scan_reasons' => $reasonsCantScan,
			],
			'hrefs'   => [
				'scanner_mod_config' => $con->plugin_urls->modOptionSection( $mod, 'section_enable_plugin_feature_hack_protection_tools' ),
				'scans_results'      => $con->plugin_urls->adminTop( PluginURLs::NAV_SCANS_RESULTS ),
			],
			'content' => [
				'section' => [
				]
			],
		];
	}

	private function buildScansVars() :array {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		/** @var Strings $strings */
		$strings = $this->primary_mod->getStrings();
		$scanStrings = $strings->getScanStrings();

		$scans = [];
		foreach ( $mod->getScansCon()->getAllScanCons() as $scanCon ) {
			$slug = $scanCon->getSlug();
			$data = [
				'flags'   => [
					'is_available'  => $scanCon->isReady(),
					'is_restricted' => $scanCon->isRestricted(),
					'is_enabled'    => $scanCon->isEnabled(),
					'is_selected'   => $scanCon->isReady()
									   && in_array( $slug, $mod->getUiTrack()->selected_scans ),
				],
				'strings' => [
					'title'    => $scanStrings[ $slug ][ 'name' ],
					'subtitle' => $scanStrings[ $slug ][ 'subtitle' ],
				],
				'vars'    => [
					'slug' => $scanCon->getSlug(),
				],
			];
			$scans[ $slug ] = $data;
		}

		return $scans;
	}
}