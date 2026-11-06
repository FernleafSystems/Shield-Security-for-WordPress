<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansBase;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\FileScanning;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class PageScansRun extends PageScansBase {

	public const SLUG = 'admin_plugin_page_scans_run';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/scan_run.twig';

	private const STEPS = [ 'choose', 'scanning', 'results' ];

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
			'hrefs'   => [
				// Back to choosing, even straight after a scan, when the page would otherwise open on its result.
				'scan_again' => URL::Build( $con->plugin_urls->scansRun(), [ 'step' => 'choose' ] ),
			],
			'strings' => [
				'scanselect'            => __( 'Select Scans To Run', 'wp-simple-firewall' ),
				'clear_ignore'          => __( 'Clear Ignore Flags', 'wp-simple-firewall' ),
				'clear_ignore_sub'      => __( 'Previously ignored results will be revealed (for the selected scans only)', 'wp-simple-firewall' ),
				'run_scans_now'         => __( 'Run Scans Now', 'wp-simple-firewall' ),
				'reason_not_call_self'  => __( "This site currently can't make HTTP requests to itself.", 'wp-simple-firewall' ),
				'scans_disabled_prefix' => __( 'Scans are currently disabled because:', 'wp-simple-firewall' ),
				'steps_label'           => __( 'Scan steps', 'wp-simple-firewall' ),
				'configure_areas'       => __( 'Configure which file areas are scanned', 'wp-simple-firewall' ),
				'checking_progress'     => __( 'Checking on your scans', 'wp-simple-firewall' ),
				'select_a_scan'         => __( 'Select at least one scan to run.', 'wp-simple-firewall' ),
				'try_again'             => __( 'Try again', 'wp-simple-firewall' ),
			],
			'scans'   => $this->buildScansVars(),
			'vars'    => [
				'cannot_scan_reasons' => $reasonsCantScan,
				'opening_step'        => $step,
				'steps'               => $this->buildSteps( $step ),
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

	/**
	 * The three steps in order, with the ones before the current step done.
	 * @return list<array{key:string,number:int,label:string,is_done:bool,is_current:bool}>
	 */
	private function buildSteps( string $current ) :array {
		$labels = [
			'choose'   => __( 'Choose scans', 'wp-simple-firewall' ),
			'scanning' => __( 'Scanning', 'wp-simple-firewall' ),
			'results'  => __( 'Results', 'wp-simple-firewall' ),
		];
		$currentIndex = \array_search( $current, self::STEPS, true );
		return \array_map(
			fn( string $key, int $index ) :array => [
				'key'        => $key,
				'number'     => $index + 1,
				'label'      => $labels[ $key ],
				'is_done'    => $index < $currentIndex,
				'is_current' => $index === $currentIndex,
			],
			self::STEPS,
			\array_keys( self::STEPS )
		);
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

	/**
	 * Each scan, in the order the page lists them.
	 * @return array<string,array{
	 *   flags:array{is_available:bool,is_selected:bool},
	 *   strings:array<string,mixed>,
	 *   vars:array{slug:string,last_ran:string}
	 * }>
	 */
	private function buildScansVars() :array {
		$con = self::con();
		$lastRanBySlug = ScansProgress::LastFinishedBySlug();

		$scans = [];
		foreach ( ScansProgress::SCAN_ORDER as $slug ) {
			$scanCon = $con->comps->scans->getScanCon( $slug );

			$subItems = [];
			if ( $slug === $con->comps->scans->AFS()->getSlug() ) {
				foreach ( $con->opts->optDef( 'file_scan_areas' )[ 'value_options' ] as $opt ) {
					$subItems[ $opt[ 'text' ] ] = \in_array( $opt[ 'value_key' ], $con->opts->optGet( 'file_scan_areas' ) );
				}
			}

			$strings = $scanCon->getStrings();
			$strings[ 'sub_items' ] = $subItems;

			$scans[ $slug ] = [
				'flags'   => [
					'is_available' => $scanCon->isReady(),
					'is_selected'  => $scanCon->isReady(),
				],
				'strings' => $strings,
				'vars'    => [
					'slug'     => $slug,
					'last_ran' => $lastRanBySlug[ $slug ] > 0
						? \sprintf( __( 'Last ran %s', 'wp-simple-firewall' ),
							Services::Request()->carbon( true )->setTimestamp( $lastRanBySlug[ $slug ] )->diffForHumans() )
						: __( 'Not run yet', 'wp-simple-firewall' ),
				],
			];
		}

		return $scans;
	}
}
