<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScansResultsRailTabAvailability;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\{
	Counts,
	Retrieve\RetrieveCount
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-type RailTabAvailability array{
 *   is_available:bool,
 *   show_in_actions_queue:bool,
 *   disabled_message:string,
 *   disabled_status:string
 * }
 * @phpstan-type ActionsQueueScanIssueRow array{
 *   key:string,
 *   zone:'scans',
 *   label:string,
 *   text:string,
 *   count:int,
 *   severity:string,
 *   href:string,
 *   action:string,
 *   target:string
 * }
 * @phpstan-type ActionsQueueScanTabMetrics array{
 *   count:int,
 *   status:string
 * }
 * @phpstan-type ActionsQueueScanState array{
 *   rows:list<ActionsQueueScanIssueRow>,
 *   tabs:array<string,ActionsQueueScanTabMetrics>,
 *   rail_accent_status:string
 * }
 */
class ActionsQueueScanStateBuilder {

	use PluginControllerConsumer;

	private ?ScansResultsRailTabAvailability $tabAvailability = null;
	private ?Counts $displayCounts = null;

	/**
	 * @return ActionsQueueScanState
	 */
	public function build() :array {
		/** @var list<ActionsQueueScanIssueRow> $rows */
		$rows = [];
		/** @var array<string,ActionsQueueScanTabMetrics> $tabs */
		$tabs = [];
		/** @var list<string> $accentStatuses */
		$accentStatuses = [];

		$this->appendWordpressState( $rows, $tabs, $accentStatuses );
		$this->appendAssetState( $rows, $tabs, $accentStatuses, 'plugins' );
		$this->appendAssetState( $rows, $tabs, $accentStatuses, 'themes' );
		$this->appendVulnerabilitiesState( $rows, $tabs, $accentStatuses );
		$this->appendMalwareState( $rows, $tabs, $accentStatuses );
		$this->appendFileLockerState( $rows, $tabs, $accentStatuses );

		$tabs = \array_merge( [
			'summary' => [
				'count'  => (int)\array_sum( \array_column( $tabs, 'count' ) ),
				'status' => StatusPriority::highest( \array_column( $tabs, 'status' ), 'good' ),
			],
		], $tabs );

		return [
			'rows'               => $rows,
			'tabs'               => $tabs,
			'rail_accent_status' => StatusPriority::highest( $accentStatuses, 'good' ),
		];
	}

	/**
	 * @param list<ActionsQueueScanIssueRow> $rows
	 * @param array<string,ActionsQueueScanTabMetrics> $tabs
	 * @param list<string> $accentStatuses
	 */
	private function appendWordpressState( array &$rows, array &$tabs, array &$accentStatuses ) :void {
		$availability = $this->getTabAvailability( 'wordpress' );
		if ( !$availability[ 'show_in_actions_queue' ] || !$availability[ 'is_available' ] ) {
			return;
		}

		$count = $this->getDisplayCounts()->countWPFiles();
		$status = $count > 0 ? 'critical' : 'good';
		$tabs[ 'wordpress' ] = [
			'count'  => $count,
			'status' => $status,
		];
		$accentStatuses[] = $status;

		$row = $this->buildIssueRow(
			'wp_files',
			$this->scanSectionLabel( 'wp_files', __( 'WordPress Files', 'wp-simple-firewall' ) ),
			$count,
			'critical',
			\sprintf(
				_n( '%s WordPress core file needs review.', '%s WordPress core files need review.', $count, 'wp-simple-firewall' ),
				$count
			),
			__( 'Repair', 'wp-simple-firewall' )
		);
		if ( $row !== null ) {
			$rows[] = $row;
		}
	}

	/**
	 * @param list<ActionsQueueScanIssueRow> $rows
	 * @param array<string,ActionsQueueScanTabMetrics> $tabs
	 * @param list<string> $accentStatuses
	 */
	private function appendAssetState( array &$rows, array &$tabs, array &$accentStatuses, string $tabKey ) :void {
		$availability = $this->getTabAvailability( $tabKey );
		if ( !$availability[ 'show_in_actions_queue' ] ) {
			return;
		}

		if ( !$availability[ 'is_available' ] ) {
			$tabs[ $tabKey ] = $this->buildDisabledTabMetrics();
			return;
		}

		$count = $tabKey === 'plugins'
			? $this->getDisplayCounts()->countAffectedPluginAssets()
			: $this->getDisplayCounts()->countAffectedThemeAssets();
		$tabs[ $tabKey ] = [
			'count'  => $count,
			'status' => $count > 0 ? 'warning' : 'good',
		];
		$accentStatuses[] = $tabs[ $tabKey ][ 'status' ];

		$row = $this->buildIssueRow(
			$tabKey === 'plugins' ? 'plugin_files' : 'theme_files',
			$this->scanSectionLabel(
				$tabKey === 'plugins' ? 'plugin_files' : 'theme_files',
				$tabKey === 'plugins'
					? __( 'Plugin Files', 'wp-simple-firewall' )
					: __( 'Theme Files', 'wp-simple-firewall' )
			),
			$count,
			'warning',
			\sprintf(
				_n(
					$tabKey === 'plugins' ? '%s plugin needs review.' : '%s theme needs review.',
					$tabKey === 'plugins' ? '%s plugins need review.' : '%s themes need review.',
					$count,
					'wp-simple-firewall'
				),
				$count
			),
			__( 'Repair', 'wp-simple-firewall' )
		);
		if ( $row !== null ) {
			$rows[] = $row;
		}
	}

	/**
	 * @param list<ActionsQueueScanIssueRow> $rows
	 * @param array<string,ActionsQueueScanTabMetrics> $tabs
	 * @param list<string> $accentStatuses
	 */
	private function appendVulnerabilitiesState( array &$rows, array &$tabs, array &$accentStatuses ) :void {
		$availability = $this->getTabAvailability( 'vulnerabilities' );
		if ( !$availability[ 'show_in_actions_queue' ] ) {
			return;
		}

		if ( !$availability[ 'is_available' ] ) {
			$tabs[ 'vulnerabilities' ] = $this->buildDisabledTabMetrics();
			return;
		}

		$displayCounts = $this->getDisplayCounts();
		$vulnerableAssetsCount = $displayCounts->countDistinctVulnerableAssets();
		$abandonedAssetsCount = $displayCounts->countDistinctAbandonedAssets();
		$tabs[ 'vulnerabilities' ] = [
			'count'  => $displayCounts->countDistinctVulnerabilityReviewAssets(),
			'status' => $vulnerableAssetsCount > 0
				? 'critical'
				: ( $abandonedAssetsCount > 0 ? 'warning' : 'good' ),
		];
		$accentStatuses[] = $tabs[ 'vulnerabilities' ][ 'status' ];

		$vulnerableRow = $this->buildIssueRow(
			'vulnerable_assets',
			__( 'Vulnerable Assets', 'wp-simple-firewall' ),
			$vulnerableAssetsCount,
			'critical',
			\sprintf(
				_n( '%s vulnerable asset detected.', '%s vulnerable assets detected.', $vulnerableAssetsCount, 'wp-simple-firewall' ),
				$vulnerableAssetsCount
			),
			__( 'Update', 'wp-simple-firewall' )
		);
		if ( $vulnerableRow !== null ) {
			$rows[] = $vulnerableRow;
		}

		$abandonedRow = $this->buildIssueRow(
			'abandoned',
			__( 'Abandoned Assets', 'wp-simple-firewall' ),
			$abandonedAssetsCount,
			'warning',
			\sprintf(
				_n( '%s abandoned asset detected.', '%s abandoned assets detected.', $abandonedAssetsCount, 'wp-simple-firewall' ),
				$abandonedAssetsCount
			),
			__( 'Update', 'wp-simple-firewall' )
		);
		if ( $abandonedRow !== null ) {
			$rows[] = $abandonedRow;
		}
	}

	/**
	 * @param list<ActionsQueueScanIssueRow> $rows
	 * @param array<string,ActionsQueueScanTabMetrics> $tabs
	 * @param list<string> $accentStatuses
	 */
	private function appendMalwareState( array &$rows, array &$tabs, array &$accentStatuses ) :void {
		$availability = $this->getTabAvailability( 'malware' );
		if ( !$availability[ 'show_in_actions_queue' ] ) {
			return;
		}

		if ( !$availability[ 'is_available' ] ) {
			$tabs[ 'malware' ] = $this->buildDisabledTabMetrics();
			return;
		}

		$count = $this->getDisplayCounts()->countMalware();
		$status = $count > 0 ? 'critical' : 'good';
		$tabs[ 'malware' ] = [
			'count'  => $count,
			'status' => $status,
		];
		$accentStatuses[] = $status;

		$row = $this->buildIssueRow(
			'malware',
			$this->scanSectionLabel( 'malware', __( 'Malware', 'wp-simple-firewall' ) ),
			$count,
			'critical',
			\sprintf(
				_n( '%s malware issue detected.', '%s malware issues detected.', $count, 'wp-simple-firewall' ),
				$count
			),
			__( 'Review', 'wp-simple-firewall' )
		);
		if ( $row !== null ) {
			$rows[] = $row;
		}
	}

	/**
	 * @param list<ActionsQueueScanIssueRow> $rows
	 * @param array<string,ActionsQueueScanTabMetrics> $tabs
	 * @param list<string> $accentStatuses
	 */
	private function appendFileLockerState( array &$rows, array &$tabs, array &$accentStatuses ) :void {
		$availability = $this->getTabAvailability( 'file_locker' );
		if ( !$availability[ 'show_in_actions_queue' ] ) {
			return;
		}

		if ( !$availability[ 'is_available' ] ) {
			$tabs[ 'file_locker' ] = $this->buildDisabledTabMetrics();
			return;
		}

		$count = \count( ( new LoadFileLocks() )->withProblems() );
		$status = $count > 0 ? 'warning' : 'good';
		$tabs[ 'file_locker' ] = [
			'count'  => $count,
			'status' => $status,
		];
		$accentStatuses[] = $status;

		$row = $this->buildIssueRow(
			'file_locker',
			$this->scanSectionLabel( 'file_locker', __( 'File Locker', 'wp-simple-firewall' ) ),
			$count,
			'warning',
			\sprintf(
				_n( '%s locked file needs review.', '%s locked files need review.', $count, 'wp-simple-firewall' ),
				$count
			),
			__( 'Review', 'wp-simple-firewall' )
		);
		if ( $row !== null ) {
			$rows[] = $row;
		}
	}

	/**
	 * @return ActionsQueueScanTabMetrics
	 */
	private function buildDisabledTabMetrics() :array {
		return [
			'count'  => 0,
			'status' => 'neutral',
		];
	}

	/**
	 * @return ActionsQueueScanIssueRow|null
	 */
	private function buildIssueRow(
		string $key,
		string $label,
		int $count,
		string $severity,
		string $text,
		string $action
	) :?array {
		if ( $count <= 0 ) {
			return null;
		}

		return [
			'key'      => $key,
			'zone'     => 'scans',
			'label'    => $label,
			'text'     => $text,
			'count'    => $count,
			'severity' => $severity,
			'href'     => self::con()->plugin_urls->actionsQueueScans(),
			'action'   => $action,
			'target'   => '',
		];
	}

	/**
	 * @return RailTabAvailability
	 */
	private function getTabAvailability( string $tabKey ) :array {
		return $this->getTabAvailabilityBuilder()->build( $tabKey );
	}

	private function getTabAvailabilityBuilder() :ScansResultsRailTabAvailability {
		if ( $this->tabAvailability === null ) {
			$this->tabAvailability = new ScansResultsRailTabAvailability();
		}

		return $this->tabAvailability;
	}

	private function getDisplayCounts() :Counts {
		if ( $this->displayCounts === null ) {
			$this->displayCounts = new Counts( RetrieveCount::CONTEXT_RESULTS_DISPLAY );
		}

		return $this->displayCounts;
	}

	private function scanSectionLabel( string $summaryKey, string $fallback ) :string {
		$definition = PluginNavs::actionsLandingScanDefinitionForSummaryKey( $summaryKey );
		return $definition[ 'label' ] ?? $fallback;
	}
}
