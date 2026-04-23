<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveGroupedAssetSummaries;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-type AssetType 'plugin'|'theme'
 * @phpstan-type QueueAssetAction array{
 *   type:string,
 *   label:string,
 *   href:string,
 *   icon_class:string,
 *   tooltip_attr:string,
 *   attributes:array<string,string>
 * }
 * @phpstan-type QueueAssetSummaryRecord array{
 *   key:string,
 *   status:string,
 *   icon_class:string,
 *   title:string,
 *   stat_text:string,
 *   meta_text:string,
 *   count_badge:int,
 *   type:string,
 *   file:string,
 *   has_update:bool
 * }
 * @phpstan-type QueueAssetIssueRecord array{
 *   key:string,
 *   panel_id:string,
 *   panel_target:string,
 *   expand_target:string,
 *   status:string,
 *   icon_class:string,
 *   title:string,
 *   stat_text:string,
 *   meta_text:string,
 *   show_meta_in_tile:bool,
 *   count_badge:int,
 *   panel_data:array<string,string>,
 *   actions:list<QueueAssetAction>,
 *   table:array<string,mixed>
 * }
 */
class ActionsQueueScanAssetCardsBuilder {

	use PluginControllerConsumer;

	private ActionsQueueAssetMetadataResolver $assetMetadataResolver;
	private ScanResultsDisplayOptions $queueScanResultsOptions;

	public function __construct(
		?ActionsQueueAssetMetadataResolver $assetMetadataResolver = null,
		?ScanResultsDisplayOptions $queueScanResultsOptions = null
	) {
		$this->assetMetadataResolver = $assetMetadataResolver ?? new ActionsQueueAssetMetadataResolver();
		$this->queueScanResultsOptions = $queueScanResultsOptions ?? new ScanResultsDisplayOptions();
	}

	/**
	 * @phpstan-param AssetType $assetType
	 * @return list<QueueAssetSummaryRecord>
	 */
	public function buildSummaryRecords( string $assetType, array $resultsDisplayOptions = [] ) :array {
		$options = $this->queueScanResultsOptions->normalize( $resultsDisplayOptions );
		$records = [];

		foreach ( $this->retrieveGroupedAssetSummaries( $assetType, $options ) as $summary ) {
			$metadata = $this->assetMetadataResolver->resolve( $assetType, $summary[ 'slug' ] );
			if ( $metadata === null ) {
				continue;
			}

			$fileCount = \max( 0, (int)( $summary[ 'file_count' ] ?? 0 ) );
			if ( $fileCount < 1 ) {
				continue;
			}

			$records[] = [
				'key'         => $summary[ 'slug' ],
				'status'      => 'warning',
				'icon_class'  => $metadata[ 'icon_class' ],
				'title'       => $metadata[ 'title' ],
				'stat_text'   => $this->buildQueueAssetStatText( $fileCount, $options ),
				'meta_text'   => $metadata[ 'file' ],
				'count_badge' => $fileCount,
				'type'        => $metadata[ 'type' ],
				'file'        => $metadata[ 'file' ],
				'has_update'  => $metadata[ 'has_update' ],
			];
		}

		\usort( $records, static function ( array $a, array $b ) :int {
			$countCmp = $b[ 'count_badge' ] <=> $a[ 'count_badge' ];
			return $countCmp !== 0
				? $countCmp
				: \strcmp( $a[ 'title' ], $b[ 'title' ] );
		} );

		return $records;
	}

	/**
	 * @phpstan-param AssetType $assetType
	 * @return list<QueueAssetIssueRecord>
	 */
	public function buildIssueRecords( string $assetType, array $resultsDisplayOptions = [] ) :array {
		$options = $this->queueScanResultsOptions->normalize( $resultsDisplayOptions );
		$records = [];
		foreach ( $this->buildSummaryRecords( $assetType, $options ) as $summary ) {
			$records[] = [
				'key'               => $summary[ 'key' ],
				'panel_id'          => 'actions-queue-'.$assetType.'-card-'.\sanitize_key( $summary[ 'key' ] ),
				'panel_target'      => 'actions-queue-'.$assetType.'-'.\sanitize_key( $summary[ 'key' ] ),
				'expand_target'     => 'scan-files-'.$assetType.'-'.\sanitize_key( $summary[ 'key' ] ),
				'status'            => $summary[ 'status' ],
				'icon_class'        => $summary[ 'icon_class' ],
				'title'             => $summary[ 'title' ],
				'stat_text'         => $summary[ 'stat_text' ],
				'meta_text'         => $summary[ 'meta_text' ],
				'show_meta_in_tile' => true,
				'count_badge'       => $summary[ 'count_badge' ],
				'panel_data'        => $this->buildImmediatePanelData(),
				'actions'           => $this->buildAssetActions( $summary, $assetType ),
				'table'             => $this->buildFileStatusTable(
					$summary[ 'type' ],
					$summary[ 'file' ],
					$options
				),
			];
		}

		return $records;
	}

	/**
	 * @return list<QueueAssetSummaryRecord>
	 */
	public function buildFullyIgnoredPluginSummaryRecords() :array {
		$activeSlugs = \array_fill_keys(
			\array_column( $this->buildSummaryRecords( 'plugin', $this->queueScanResultsOptions->activeOnly() ), 'key' ),
			true
		);

		return \array_values( \array_map(
			fn( array $summary ) :array => \array_merge(
				$summary,
				[
					'stat_text' => $this->buildQueueAssetDiscoveredIgnoredStatText(
						(int)( $summary[ 'count_badge' ] ?? 0 )
					),
				]
			),
			\array_filter(
				$this->buildSummaryRecords( 'plugin', $this->queueScanResultsOptions->ignoredOnly() ),
				static fn( array $summary ) :bool => !isset( $activeSlugs[ $summary[ 'key' ] ] )
			)
		) );
	}

	/**
	 * @phpstan-param AssetType $assetType
	 * @param array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * } $resultsDisplayOptions
	 * @return list<array{slug:string,file_count:int}>
	 */
	protected function retrieveGroupedAssetSummaries( string $assetType, array $resultsDisplayOptions ) :array {
		return ( new RetrieveGroupedAssetSummaries() )->retrieve( $assetType, $resultsDisplayOptions );
	}

	/**
	 * @param QueueAssetSummaryRecord $summary
	 * @return list<QueueAssetAction>
	 */
	protected function buildAssetActions( array $summary, string $assetType ) :array {
		$actions = [];
		if ( $summary[ 'has_update' ] ) {
			$actions[] = [
				'type'         => 'update',
				'label'        => __( 'Update', 'wp-simple-firewall' ),
				'href'         => \admin_url( 'update-core.php' ),
				'icon_class'   => 'bi bi-arrow-up-circle-fill',
				'tooltip_attr' => __( 'Go to updates', 'wp-simple-firewall' ),
				'attributes'   => [],
			];
		}
		if ( $assetType === 'plugin' ) {
			$actions[] = [
				'type'         => 'deactivate',
				'label'        => __( 'Deactivate', 'wp-simple-firewall' ),
				'href'         => \admin_url( 'plugins.php' ),
				'icon_class'   => 'bi bi-power',
				'tooltip_attr' => __( 'Go to plugins', 'wp-simple-firewall' ),
				'attributes'   => [],
			];
		}
		return $actions;
	}

	/**
	 * @return array<string,string>
	 */
	protected function buildImmediatePanelData() :array {
		return [
			'actions-queue-asset-panel-loaded' => '1',
			'actions-queue-asset-panel-lazy'   => '0',
		];
	}

	/**
	 * @param array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * } $resultsDisplayOptions
	 * @return array<string,mixed>
	 */
	protected function buildFileStatusTable( string $type, string $file, array $resultsDisplayOptions ) :array {
		return $this->buildScanResultsTableBuilder()->buildTableForScope(
			$type,
			$file,
			$type === 'theme'
				? __( "Previous scans didn't detect any modified, missing, or unrecognised files in theme directories.", 'wp-simple-firewall' )
				: __( "Previous scans didn't detect any modified, missing, or unrecognised files in plugin directories.", 'wp-simple-firewall' ),
			$resultsDisplayOptions
		);
	}

	protected function buildScanResultsTableBuilder() :ActionsQueueScanResultsTableBuilder {
		return new ActionsQueueScanResultsTableBuilder();
	}

	protected function buildFullLogHref() :string {
		return self::con()->plugin_urls->actionsQueueScans();
	}

	/**
	 * @param array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * } $resultsDisplayOptions
	 */
	protected function buildQueueAssetStatText( int $fileCount, array $resultsDisplayOptions ) :string {
		if ( $resultsDisplayOptions[ 'ignored_only' ] ) {
			return \sprintf(
				_n( '%s ignored file is available for review', '%s ignored files are available for review', $fileCount, 'wp-simple-firewall' ),
				$fileCount
			);
		}

		return \sprintf(
			_n( '%s file needs review', '%s files need review', $fileCount, 'wp-simple-firewall' ),
			$fileCount
		);
	}

	protected function buildQueueAssetDiscoveredIgnoredStatText( int $fileCount ) :string {
		return \sprintf(
			_n(
				'%s discovered file is currently ignored.',
				'%s discovered files are currently ignored.',
				$fileCount,
				'wp-simple-firewall'
			),
			$fileCount
		);
	}

}
