<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansBase;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\FileScanning;
use FernleafSystems\Wordpress\Services\Services;

class PageScansRun extends PageScansBase {

	public const SLUG = 'admin_plugin_page_scans_run';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/scan_run.twig';

	protected function getPageContextualHrefs() :array {
		return [
			[
				'title' => CommonDisplayStrings::get( 'scan_results_label' ),
				'href'  => self::con()->plugin_urls->actionsQueueScans(),
			],
		];
	}

	protected function getRenderData() :array {
		$con = self::con();

		// Can Scan Checks:
		$reasonsCantScan = $con->comps->scans->getReasonsScansCantExecute();
		$step = $this->openingStep();
		return \array_replace_recursive( parent::getRenderData(), [
			'flags'   => [
				'can_scan' => \count( $reasonsCantScan ) === 0,
			],
			'imgs'    => [
				'icon_shield_check'     => $con->svgs->iconClass( 'shield-check' ),
				'icon_shield_x'         => $con->svgs->iconClass( 'shield-x' ),
			],
			'strings' => [
				'never'                 => CommonDisplayStrings::get( 'never_label' ),
				'not_available'         => __( 'Sorry, this scan is not available.', 'wp-simple-firewall' ),
				'not_enabled'           => __( 'This scan is not currently enabled.', 'wp-simple-firewall' ),
				'please_enable'         => __( 'Please turn on this scan in the options.', 'wp-simple-firewall' ),
				'scan_options'          => __( 'Scan Options', 'wp-simple-firewall' ),
				'scanselect'            => __( 'Select Scans To Run', 'wp-simple-firewall' ),
				'clear_ignore'          => __( 'Clear Ignore Flags', 'wp-simple-firewall' ),
				'clear_ignore_sub'      => __( 'Previously ignored results will be revealed (for the selected scans only)', 'wp-simple-firewall' ),
				'run_scans_now'         => __( 'Run Scans Now', 'wp-simple-firewall' ),
				'reason_not_call_self'  => __( "This site currently can't make HTTP requests to itself.", 'wp-simple-firewall' ),
				'scans_disabled_prefix' => __( 'Scans are currently disabled because:', 'wp-simple-firewall' ),
				'step_choose'           => __( 'Choose scans', 'wp-simple-firewall' ),
				'step_scanning'         => __( 'Scanning', 'wp-simple-firewall' ),
				'step_results'          => __( 'Results', 'wp-simple-firewall' ),
				'steps_label'           => __( 'Scan steps', 'wp-simple-firewall' ),
				'configure_areas'       => __( 'Configure which file areas are scanned', 'wp-simple-firewall' ),
				'checking_progress'     => __( 'Checking on your scans', 'wp-simple-firewall' ),
				'select_a_scan'         => __( 'Select at least one scan to run.', 'wp-simple-firewall' ),
			],
			'scans'   => $this->buildScansVars(),
			'vars'    => [
				'cannot_scan_reasons' => $reasonsCantScan,
				'opening_step'        => $step,
				'results_html'        => $step === 'results' ? $this->renderRecentResults() : '',
				'scan_areas_action'   => [
					'zone_component_action' => ZoneComponentConfig::SLUG,
					'zone_component_slug'   => FileScanning::Slug(),
					'option_keys'           => 'file_scan_areas',
					// Not "offcanvas", so saving reloads the page and the areas listed here follow.
					'form_context'          => 'scan_run',
				],
			],
		] );
	}

	/**
	 * The page opens where things stand: on the scans while they run, on the result of a scan that
	 * finished in the last few minutes, otherwise on choosing. "Scan again" asks for the choosing step,
	 * so a recent result never keeps you from starting another scan.
	 */
	private function openingStep() :string {
		if ( Services::Request()->query( 'step' ) === 'choose' ) {
			return 'choose';
		}
		if ( self::con()->comps->scans_queue->hasRunningScans() ) {
			return 'scanning';
		}
		return empty( ScansProgress::RecentlyFinishedScanSlugs() ) ? 'choose' : 'results';
	}

	private function renderRecentResults() :string {
		return self::con()->action_router->render( ScansProgress::class, [
			'modal_state'     => ScansBase::SCAN_MODAL_STATE_COMPLETED,
			'current_scan'    => '',
			'remaining_scans' => '',
			'progress'        => 100,
			'scan_rows'       => [],
			'scan_slugs'      => ScansProgress::RecentlyFinishedScanSlugs(),
			'display'         => ScansBase::DISPLAY_PAGE,
		] );
	}

	protected function getLandingTitle() :string {
		return __( 'Run Manual Scan', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Scan your site for file changes, malware and vulnerabilities.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'shield-shaded';
	}

	private function buildScansVars() :array {
		$con = self::con();
		$lastRanBySlug = ScansProgress::LastFinishedBySlug();

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
					'slug'     => $scanCon->getSlug(),
					'last_ran' => ( $lastRanBySlug[ $slug ] ?? 0 ) > 0
						? sprintf( __( 'Last ran %s', 'wp-simple-firewall' ),
							Services::Request()->carbon( true )->setTimestamp( $lastRanBySlug[ $slug ] )->diffForHumans() )
						: __( 'Not run yet', 'wp-simple-firewall' ),
				],
			];
			$scans[ $slug ] = $data;
		}

		return $scans;
	}
}
