<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\{
	RetrieveGroupedAssetSummaries
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-type QueueAssetSummaryRecord array{
 *   key:string,
 *   status:string,
 *   icon_class:string,
 *   title:string,
 *   stat_text:string,
 *   meta_text:string,
 *   count_badge:int,
 *   subject_type:string,
 *   subject_id:string,
 *   has_update:bool
 * }
 */
class ActionsQueueScanAssetCardsBuilder {

	use PluginControllerConsumer;

	private ActionsQueueAssetMetadataResolver $assetMetadataResolver;
	private ActionsQueueScanResultsOptions $queueScanResultsOptions;

	public function __construct(
		?ActionsQueueAssetMetadataResolver $assetMetadataResolver = null,
		?ActionsQueueScanResultsOptions $queueScanResultsOptions = null
	) {
		$this->assetMetadataResolver = $assetMetadataResolver ?? new ActionsQueueAssetMetadataResolver();
		$this->queueScanResultsOptions = $queueScanResultsOptions ?? new ActionsQueueScanResultsOptions();
	}

	/**
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
				'meta_text'   => $metadata[ 'subject_id' ],
				'count_badge' => $fileCount,
				'subject_type' => $metadata[ 'subject_type' ],
				'subject_id'  => $metadata[ 'subject_id' ],
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
	 * @return list<array<string,mixed>>
	 */
	public function buildIssueRecords( string $assetType, array $resultsDisplayOptions = [] ) :array {
		$options = $this->queueScanResultsOptions->normalize( $resultsDisplayOptions );
		$records = [];
		foreach ( $this->buildSummaryRecords( $assetType, $options ) as $summary ) {
			$subjectType = $summary[ 'subject_type' ];
			$subjectId = $summary[ 'subject_id' ];
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
				'actions'           => $this->buildAssetActions( $summary, $assetType ),
				'table'             => $this->buildFileStatusTable(
					$subjectType,
					$subjectId,
					$options
				),
				'render_action'     => [],
			];
		}

		return $records;
	}

	/**
	 * @param array{include_ignored:bool,ignored_only:bool} $resultsDisplayOptions
	 * @return list<array{slug:string,file_count:int}>
	 */
	protected function retrieveGroupedAssetSummaries( string $assetType, array $resultsDisplayOptions ) :array {
		return ( new RetrieveGroupedAssetSummaries() )->retrieve( $assetType, $resultsDisplayOptions );
	}

	/**
	 * @param QueueAssetSummaryRecord $summary
	 * @return list<array<string,mixed>>
	 */
	protected function buildAssetActions( array $summary, string $assetType ) :array {
		$actions = [];
		if ( $summary[ 'has_update' ] ) {
			$actions[] = [
				'type'       => 'update',
				'label'      => __( 'Update', 'wp-simple-firewall' ),
				'href'       => \admin_url( 'update-core.php' ),
				'icon'       => 'bi bi-arrow-up-circle-fill',
				'tooltip'    => __( 'Go to updates', 'wp-simple-firewall' ),
				'attributes' => [],
			];
		}
		if ( $assetType === 'plugin' ) {
			$actions[] = [
				'type'       => 'deactivate',
				'label'      => __( 'Deactivate', 'wp-simple-firewall' ),
				'href'       => \admin_url( 'plugins.php' ),
				'icon'       => 'bi bi-power',
				'tooltip'    => __( 'Go to plugins', 'wp-simple-firewall' ),
				'attributes' => [],
			];
		}
		return $actions;
	}

	/**
	 * @param array{include_ignored:bool,ignored_only:bool} $resultsDisplayOptions
	 * @return array<string,mixed>
	 */
	protected function buildFileStatusTable( string $subjectType, string $subjectId, array $resultsDisplayOptions ) :array {
		return ( new InvestigationFileStatusTableContractBuilder() )->build(
			$subjectType,
			$subjectId,
			$this->buildFullLogHref(),
			$this->queueScanResultsOptions->buildActionData( $resultsDisplayOptions )
		);
	}

	protected function buildFullLogHref() :string {
		return self::con()->plugin_urls->actionsQueueScans();
	}

	/**
	 * @param array{include_ignored:bool,ignored_only:bool} $resultsDisplayOptions
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

}
