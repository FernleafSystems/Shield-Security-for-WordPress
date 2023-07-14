<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansCheck;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansStart;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\CleanQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Strings;

class PageScansRun extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_scans_run';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/scan_run.twig';

	protected function getPageContextualHrefs() :array {
		$con = $this->con();
		return [
			[
				'text' => __( 'Scan Results', 'wp-simple-firewall' ),
				'href' => $con->plugin_urls->adminTopNav( PluginURLs::NAV_SCANS_RESULTS ),
			],
			[
				'text' => __( 'Configure Scans', 'wp-simple-firewall' ),
				'href' => $con->plugin_urls->offCanvasConfigRender( $con->getModule_HackGuard()->cfg->slug ),
			],
		];
	}

	protected function getRenderData() :array {
		$con = $this->con();
		$mod = $con->getModule_HackGuard();

		( new CleanQueue() )->execute();

		// Can Scan Checks:
		$reasonsCantScan = $mod->getScansCon()->getReasonsScansCantExecute();
		return [
			'ajax'    => [
				'scans_start' => ActionData::BuildJson( ScansStart::class ),
				'scans_check' => ActionData::BuildJson( ScansCheck::class ),
			],
			'flags'   => [
				'can_scan'        => \count( $reasonsCantScan ) === 0,
				'module_disabled' => !$mod->isModOptEnabled(),
			],
			'hrefs'   => [
				'scanner_mod_config' => $con->plugin_urls->modCfgSection( $mod, 'section_enable_plugin_feature_hack_protection_tools' ),
				'scans_results'      => $con->plugin_urls->adminTopNav( PluginURLs::NAV_SCANS_RESULTS ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Scan Your Site', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Scan your site for file changes, malware and vulnerabilities.', 'wp-simple-firewall' ),

				'never'                 => __( 'Never', 'wp-simple-firewall' ),
				'not_available'         => __( 'Sorry, this scan is not available.', 'wp-simple-firewall' ),
				'not_enabled'           => __( 'This scan is not currently enabled.', 'wp-simple-firewall' ),
				'please_enable'         => __( 'Please turn on this scan in the options.', 'wp-simple-firewall' ),
				'scan_options'          => __( 'Scan Options', 'wp-simple-firewall' ),
				'scanselect'            => __( 'Select Scans To Run', 'wp-simple-firewall' ),
				'select_view_results'   => __( 'View Scan Results', 'wp-simple-firewall' ),
				'clear_ignore'          => __( 'Clear Ignore Flags', 'wp-simple-firewall' ),
				'clear_ignore_sub'      => __( 'Previously ignored results will be revealed (for the selected scans only)', 'wp-simple-firewall' ),
				'run_scans_now'         => __( 'Run Scans Now', 'wp-simple-firewall' ),
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
		];
	}

	private function buildScansVars() :array {
		$mod = $this->con()->getModule_HackGuard();
		/** @var Strings $strings */
		$strings = $mod->getStrings();
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