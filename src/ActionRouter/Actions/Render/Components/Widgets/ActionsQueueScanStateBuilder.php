<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueScanResultsTableBuilder,
	ActionsQueueScanAssetCardsBuilder,
	ScanResultsDisplayOptions,
	ScansResultsRailTabAvailability
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\GetPendingFileLockDisplays;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\{
	Counts
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\{
	StatusPriority,
	WordpressReleaseChannel
};

/**
 * @phpstan-type RailTabAvailability array{
 *   is_available:bool,
 *   show_in_actions_queue:bool,
 *   disabled_message:string,
 *   disabled_status:string
 * }
 * @phpstan-type ActionsQueueScanRow array{
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
 *   rows:list<ActionsQueueScanRow>,
 *   tabs:array<string,ActionsQueueScanTabMetrics>,
 *   rail_accent_status:string
 * }
 */
class ActionsQueueScanStateBuilder {

	use PluginControllerConsumer;

	private ?ScansResultsRailTabAvailability $tabAvailability = null;
	private ?Counts $displayCounts = null;
	private ?ActionsQueueScanAssetCardsBuilder $scanAssetCardsBuilder = null;
	private ?ActionsQueueScanResultsTableBuilder $scanResultsTableBuilder = null;
	private ?ScanResultsDisplayOptions $queueScanResultsOptions = null;

	/**
	 * @return ActionsQueueScanState
	 */
	public function build() :array {
		/** @var list<ActionsQueueScanRow> $rows */
		$rows = [];
		/** @var array<string,ActionsQueueScanTabMetrics> $tabs */
		$tabs = [];
		/** @var list<string> $accentStatuses */
		$accentStatuses = [];

		$this->appendWordpressState( $rows, $tabs, $accentStatuses );
		$this->appendAssetState( $rows, $tabs, $accentStatuses, 'plugins' );
		$this->appendAssetState( $rows, $tabs, $accentStatuses, 'themes' );
		$vulnerabilityReviewCount = $this->appendVulnerabilityStates( $rows, $tabs, $accentStatuses );
		$this->appendMalwareState( $rows, $tabs, $accentStatuses );
		$this->appendFileLockerState( $rows, $tabs, $accentStatuses );

		$tabs = \array_merge( [
			'summary' => [
				'count'  => $this->buildSummaryCount( $tabs, $vulnerabilityReviewCount ),
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
	 * @param list<ActionsQueueScanRow> $rows
	 * @param array<string,ActionsQueueScanTabMetrics> $tabs
	 * @param list<string> $accentStatuses
	 */
	private function appendWordpressState( array &$rows, array &$tabs, array &$accentStatuses ) :void {
		$availability = $this->getTabAvailability( 'wordpress' );
		if ( !$availability[ 'show_in_actions_queue' ] || !$availability[ 'is_available' ] ) {
			return;
		}

		$count = $this->getDisplayCounts()->countWPFiles();
		$ignoredCount = $count === 0 ? $this->countIgnoredResultsForScope( 'wordpress', 'wordpress' ) : 0;
		$displayCount = $count > 0 ? $count : $ignoredCount;
		$status = $count > 0 ? 'critical' : ( $ignoredCount > 0 ? 'warning' : 'good' );
		$tabs[ 'wordpress' ] = [
			'count'  => $displayCount,
			'status' => $status,
		];
		$accentStatuses[] = $status;

		$row = $this->buildScanRow(
			'wp_files',
			$this->scanSectionLabel( 'wp_files', __( 'WordPress Files', 'wp-simple-firewall' ) ),
			$count,
			'critical',
			\sprintf(
				_n( '%s WordPress core file needs review.', '%s WordPress core files need review.', $count, 'wp-simple-firewall' ),
				$count
			),
			( new WordpressReleaseChannel() )->isDevelopmentBuild()
				? __( 'Review', 'wp-simple-firewall' )
				: __( 'Repair', 'wp-simple-firewall' )
		);
		if ( $row !== null ) {
			$rows[] = $row;
		}

		if ( $count === 0 ) {
			$ignoredRow = $this->buildScanRow(
				'wp_files_ignored',
				$this->scanSectionLabel( 'wp_files_ignored', __( 'WordPress Files', 'wp-simple-firewall' ) ),
				$ignoredCount,
				'warning',
				\sprintf(
					_n( '%s WordPress core file is currently ignored.', '%s WordPress core files are currently ignored.', $ignoredCount, 'wp-simple-firewall' ),
					$ignoredCount
				),
				__( 'Review', 'wp-simple-firewall' )
			);
			if ( $ignoredRow !== null ) {
				$rows[] = $ignoredRow;
			}
		}
	}

	/**
	 * @param list<ActionsQueueScanRow> $rows
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
		$assetType = $tabKey === 'plugins' ? 'plugin' : 'theme';
		$ignoredSummaryKey = $tabKey === 'plugins' ? 'plugin_files_ignored' : 'theme_files_ignored';
		$fullyIgnoredCount = \count( $this->getScanAssetCardsBuilder()->buildFullyIgnoredSummaryRecords( $assetType ) );
		$totalCount = $count + $fullyIgnoredCount;
		$status = $count > 0
			? 'critical'
			: ( $fullyIgnoredCount > 0 ? 'warning' : 'good' );
		$tabs[ $tabKey ] = [
			'count'  => $totalCount,
			'status' => $status,
		];
		$accentStatuses[] = $tabs[ $tabKey ][ 'status' ];

		$row = $this->buildScanRow(
			$tabKey === 'plugins' ? 'plugin_files' : 'theme_files',
			$this->scanSectionLabel(
				$tabKey === 'plugins' ? 'plugin_files' : 'theme_files',
				$tabKey === 'plugins'
					? __( 'Plugin Files', 'wp-simple-firewall' )
					: __( 'Theme Files', 'wp-simple-firewall' )
			),
			$count,
			'critical',
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

		$ignoredRow = $this->buildScanRow(
			$ignoredSummaryKey,
			$this->scanSectionLabel(
				$ignoredSummaryKey,
				$tabKey === 'plugins'
					? __( 'Plugin Files', 'wp-simple-firewall' )
					: __( 'Theme Files', 'wp-simple-firewall' )
			),
			$fullyIgnoredCount,
			'warning',
			\sprintf(
				_n(
					$tabKey === 'plugins'
						? '%s plugin has discovered files currently ignored.'
						: '%s theme has discovered files currently ignored.',
					$tabKey === 'plugins'
						? '%s plugins have discovered files currently ignored.'
						: '%s themes have discovered files currently ignored.',
					$fullyIgnoredCount,
					'wp-simple-firewall'
				),
				$fullyIgnoredCount
			),
			__( 'Review', 'wp-simple-firewall' )
		);
		if ( $ignoredRow !== null ) {
			$rows[] = $ignoredRow;
		}
	}

	/**
	 * @param list<ActionsQueueScanRow> $rows
	 * @param array<string,ActionsQueueScanTabMetrics> $tabs
	 * @param list<string> $accentStatuses
	 */
	private function appendVulnerabilityStates( array &$rows, array &$tabs, array &$accentStatuses ) :int {
		$vulnerabilitiesAvailability = $this->getTabAvailability( 'vulnerabilities' );
		$abandonedAvailability = $this->getTabAvailability( 'abandoned' );

		if ( !$vulnerabilitiesAvailability[ 'show_in_actions_queue' ]
			 && !$abandonedAvailability[ 'show_in_actions_queue' ] ) {
			return 0;
		}

		$displayCounts = $this->getDisplayCounts();
		$vulnerableAssetsCount = $displayCounts->countDistinctVulnerableAssets();
		$abandonedAssetsCount = $displayCounts->countDistinctAbandonedAssets();

		if ( $vulnerabilitiesAvailability[ 'show_in_actions_queue' ] ) {
			if ( !$vulnerabilitiesAvailability[ 'is_available' ] ) {
				$tabs[ 'vulnerabilities' ] = $this->buildDisabledTabMetrics();
			}
			else {
				$tabs[ 'vulnerabilities' ] = [
					'count'  => $vulnerableAssetsCount,
					'status' => $vulnerableAssetsCount > 0 ? 'critical' : 'good',
				];
				$accentStatuses[] = $tabs[ 'vulnerabilities' ][ 'status' ];
			}
		}

		if ( $abandonedAvailability[ 'show_in_actions_queue' ] ) {
			if ( !$abandonedAvailability[ 'is_available' ] ) {
				$tabs[ 'abandoned' ] = $this->buildDisabledTabMetrics();
			}
			else {
				$tabs[ 'abandoned' ] = [
					'count'  => $abandonedAssetsCount,
					'status' => $abandonedAssetsCount > 0 ? 'critical' : 'good',
				];
				$accentStatuses[] = $tabs[ 'abandoned' ][ 'status' ];
			}
		}

		if ( $vulnerabilitiesAvailability[ 'is_available' ] ) {
			$vulnerableRow = $this->buildScanRow(
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
		}

		if ( $abandonedAvailability[ 'is_available' ] ) {
			$abandonedRow = $this->buildScanRow(
				'abandoned',
				__( 'Abandoned Assets', 'wp-simple-firewall' ),
				$abandonedAssetsCount,
				'critical',
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

		return ( $vulnerabilitiesAvailability[ 'is_available' ] || $abandonedAvailability[ 'is_available' ] )
			? $displayCounts->countDistinctVulnerabilityReviewAssets()
			: 0;
	}

	/**
	 * @param list<ActionsQueueScanRow> $rows
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
		$ignoredCount = $count === 0 ? $this->countIgnoredResultsForScope( 'malware', 'malware' ) : 0;
		$displayCount = $count > 0 ? $count : $ignoredCount;
		$status = $count > 0 ? 'critical' : ( $ignoredCount > 0 ? 'warning' : 'good' );
		$tabs[ 'malware' ] = [
			'count'  => $displayCount,
			'status' => $status,
		];
		$accentStatuses[] = $status;

		$row = $this->buildScanRow(
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

		if ( $count === 0 ) {
			$ignoredRow = $this->buildScanRow(
				'malware_ignored',
				$this->scanSectionLabel( 'malware_ignored', __( 'Malware', 'wp-simple-firewall' ) ),
				$ignoredCount,
				'warning',
				\sprintf(
					_n( '%s malware result is currently ignored.', '%s malware results are currently ignored.', $ignoredCount, 'wp-simple-firewall' ),
					$ignoredCount
				),
				__( 'Review', 'wp-simple-firewall' )
			);
			if ( $ignoredRow !== null ) {
				$rows[] = $ignoredRow;
			}
		}
	}

	/**
	 * @param list<ActionsQueueScanRow> $rows
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

		$count = $this->getProblemFileLockerCount();
		$pendingCount = $this->getPendingFileLockerCount();
		$status = $count > 0 ? 'warning' : 'good';
		$tabs[ 'file_locker' ] = [
			'count'  => $count,
			'status' => $status,
		];
		$accentStatuses[] = $status;

		$row = $this->buildScanRow(
			'file_locker',
			$this->scanSectionLabel( 'file_locker', __( 'File Locker', 'wp-simple-firewall' ) ),
			$count,
			$status,
			$count > 0
				? \sprintf(
					_n( '%s locked file needs review.', '%s locked files need review.', $count, 'wp-simple-firewall' ),
					$count
				)
				: ( $pendingCount > 0
					? $this->describePendingFileLockerCount( $pendingCount )
					: __( "Locked files don't appear to have any changes that need review.", 'wp-simple-firewall' ) ),
			__( 'Review', 'wp-simple-firewall' ),
			true
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
	 * @return ActionsQueueScanRow|null
	 */
	private function buildScanRow(
		string $key,
		string $label,
		int $count,
		string $severity,
		string $text,
		string $action,
		bool $includeWhenZero = false
	) :?array {
		if ( $count < 0 || ( $count === 0 && !$includeWhenZero ) ) {
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

	protected function getProblemFileLockerCount() :int {
		return \count( ( new LoadFileLocks() )->withProblems() );
	}

	protected function getPendingFileLockerCount() :int {
		return $this->pendingFileLockDisplays()->count();
	}

	protected function describePendingFileLockerCount( int $pendingCount ) :string {
		return $this->pendingFileLockDisplays()->describeCount( $pendingCount );
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
			$this->displayCounts = self::con()->comps->scans->getScanResultsCount();
		}

		return $this->displayCounts;
	}

	private function getScanAssetCardsBuilder() :ActionsQueueScanAssetCardsBuilder {
		if ( $this->scanAssetCardsBuilder === null ) {
			$this->scanAssetCardsBuilder = new ActionsQueueScanAssetCardsBuilder();
		}

		return $this->scanAssetCardsBuilder;
	}

	private function countIgnoredResultsForScope( string $type, string $file ) :int {
		return $this->getScanResultsTableBuilder()->countForScope(
			$type,
			$file,
			$this->getQueueScanResultsOptions()->ignoredOnly()
		);
	}

	private function getScanResultsTableBuilder() :ActionsQueueScanResultsTableBuilder {
		if ( $this->scanResultsTableBuilder === null ) {
			$this->scanResultsTableBuilder = new ActionsQueueScanResultsTableBuilder();
		}

		return $this->scanResultsTableBuilder;
	}

	private function getQueueScanResultsOptions() :ScanResultsDisplayOptions {
		if ( $this->queueScanResultsOptions === null ) {
			$this->queueScanResultsOptions = new ScanResultsDisplayOptions();
		}

		return $this->queueScanResultsOptions;
	}

	private function scanSectionLabel( string $summaryKey, string $fallback ) :string {
		$definition = PluginNavs::actionsQueueScanDefinitionForSummaryKey( $summaryKey );
		return $definition[ 'label' ] ?? $fallback;
	}

	private function pendingFileLockDisplays() :GetPendingFileLockDisplays {
		return new GetPendingFileLockDisplays();
	}

	/**
	 * @param array<string,ActionsQueueScanTabMetrics> $tabs
	 */
	private function buildSummaryCount( array $tabs, int $vulnerabilityReviewCount ) :int {
		$count = $vulnerabilityReviewCount;

		foreach ( $tabs as $tabKey => $tab ) {
			if ( \in_array( $tabKey, [ 'vulnerabilities', 'abandoned' ], true ) ) {
				continue;
			}
			$count += (int)( $tab[ 'count' ] ?? 0 );
		}

		return $count;
	}
}
