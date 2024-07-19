<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\CleanQueue;

class PageScansRun extends PageScansBase {

	public const SLUG = 'admin_plugin_page_scans_run';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/scan_run.twig';

	protected function getPageContextualHrefs() :array {
		return [
			[
				'title' => __( 'Scan Results', 'wp-simple-firewall' ),
				'href'  => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
			],
		];
	}

	protected function getRenderData() :array {
		$con = self::con();

		( new CleanQueue() )->execute();

		// Can Scan Checks:
		$reasonsCantScan = $con->comps->scans->getReasonsScansCantExecute();
		return [
			'flags'   => [
				'can_scan' => \count( $reasonsCantScan ) === 0,
			],
			'hrefs'   => [
				'scans_results' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'shield-shaded' ),
				'icon_shield_check'     => $con->svgs->raw( 'shield-check' ),
				'icon_shield_x'         => $con->svgs->raw( 'shield-x' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Run Manual Scan', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Scan your site for file changes, malware and vulnerabilities.', 'wp-simple-firewall' ),

				'never'                => __( 'Never', 'wp-simple-firewall' ),
				'not_available'        => __( 'Sorry, this scan is not available.', 'wp-simple-firewall' ),
				'not_enabled'          => __( 'This scan is not currently enabled.', 'wp-simple-firewall' ),
				'please_enable'        => __( 'Please turn on this scan in the options.', 'wp-simple-firewall' ),
				'scan_options'         => __( 'Scan Options', 'wp-simple-firewall' ),
				'scanselect'           => __( 'Select Scans To Run', 'wp-simple-firewall' ),
				'select_view_results'  => __( 'View Scan Results', 'wp-simple-firewall' ),
				'clear_ignore'         => __( 'Clear Ignore Flags', 'wp-simple-firewall' ),
				'clear_ignore_sub'     => __( 'Previously ignored results will be revealed (for the selected scans only)', 'wp-simple-firewall' ),
				'run_scans_now'        => __( 'Run Scans Now', 'wp-simple-firewall' ),
				'scan_progress'        => __( 'Scan Progress', 'wp-simple-firewall' ),
				'reason_not_call_self' => __( "This site currently can't make HTTP requests to itself.", 'wp-simple-firewall' ),
			],
			'scans'   => $this->buildScansVars(),
			'vars'    => [
				'cannot_scan_reasons' => $reasonsCantScan,
			],
		];
	}

	private function buildScansVars() :array {
		$con = self::con();

		$scans = [];
		foreach ( $con->comps->scans->getAllScanCons() as $scanCon ) {
			$slug = $scanCon->getSlug();

			$subItems = [];
			if ( $slug === $con->comps->scans->AFS()->getSlug() ) {
				foreach ( $con->opts->optDef( 'file_scan_areas' )[ 'value_options' ] as $opt ) {
					$subItems[ $opt[ 'text' ] ] = \in_array( $opt[ 'value_key' ], $con->opts->optGet( 'file_scan_areas' ) );
				}
			}

			$strings = $scanCon->getStrings();
			$strings[ 'sub_items' ] = $subItems;

			$data = [
				'flags'   => [
					'is_available'  => $scanCon->isReady(),
					'is_restricted' => $scanCon->isRestricted(),
					'is_enabled'    => $scanCon->isEnabled(),
					'is_selected'   => $scanCon->isReady()
					//									   && \in_array( $slug, $mod->getUiTrack()->selected_scans ),
				],
				'strings' => $strings,
				'vars'    => [
					'slug' => $scanCon->getSlug(),
				],
			];
			$scans[ $slug ] = $data;
		}

		return $scans;
	}
}