<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 * @phpstan-import-type AssessmentRowsByZone from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type BucketData from ActionsQueueBucketsBuilder
 */
trait BuildsActionsQueueLandingData {

	private ?array $actionsQueueAttentionQueryCache = null;
	private ?array $actionsQueueAssessmentRowsByZoneCache = null;
	private ?array $actionsQueueLandingViewDataCache = null;
	private ?array $actionsQueueBucketsCache = null;

	/**
	 * @return AttentionQuery
	 */
	protected function getAttentionQuery() :array {
		if ( $this->actionsQueueAttentionQueryCache === null ) {
			$this->actionsQueueAttentionQueryCache = $this->buildAttentionQuery();
		}

		return $this->actionsQueueAttentionQueryCache;
	}

	/**
	 * @return AttentionQuery
	 */
	protected function buildAttentionQuery() :array {
		return self::con()->comps->site_query->attention();
	}

	/**
	 * @return AssessmentRowsByZone
	 */
	protected function getAssessmentRowsByZone() :array {
		if ( $this->actionsQueueAssessmentRowsByZoneCache === null ) {
			$this->actionsQueueAssessmentRowsByZoneCache = $this->buildAssessmentRowsByZone();
		}

		return $this->actionsQueueAssessmentRowsByZoneCache;
	}

	/**
	 * @return AssessmentRowsByZone
	 */
	protected function buildAssessmentRowsByZone() :array {
		$builder = new ActionsQueueLandingAssessmentBuilder();

		return [
			'scans'       => $builder->buildForZone( 'scans' ),
			'maintenance' => $builder->buildForZone( 'maintenance' ),
		];
	}

	protected function buildSummarySubtext() :string {
		$latestScanAt = (int)\max( self::con()->comps->site_query->overview()[ 'scans' ][ 'latest_completed_at' ] );

		return $latestScanAt > 0
			? \sprintf(
				__( 'Last scan: %s', 'wp-simple-firewall' ),
				Services::Request()->carbon( true )->setTimestamp( $latestScanAt )->diffForHumans()
			)
			: '';
	}

	/**
	 * @return array{
	 *   summary:array{
	 *     has_items:bool,
	 *     total_items:int,
	 *     severity:string,
	 *     icon_class:string,
	 *     subtext:string
	 *   },
	 *   zones_indexed:array<string,array{
	 *     slug:string,
	 *     label:string,
	 *     icon_class:string,
	 *     severity:string,
	 *     total_issues:int,
	 *     items:list<array<string,mixed>>
	 *   }>,
	 *   zone_tiles:list<array<string,mixed>>,
	 *   status_overview:array{
	 *     severity:string,
	 *     label:string,
	 *     icon_class:string,
	 *     summary_text:string,
	 *     subtext:string,
	 *     total_items:int,
	 *     critical_count:int,
	 *     warning_count:int
	 *   },
	 *   all_clear:array{
	 *     title:string,
	 *     subtitle:string,
	 *     icon_class:string,
	 *     zone_chips:list<array{
	 *       slug:string,
	 *       label:string,
	 *       icon_class:string,
	 *       severity:string
	 *     }>
	 *   }
	 * }
	 */
	protected function getLandingViewData() :array {
		if ( $this->actionsQueueLandingViewDataCache === null ) {
			$this->actionsQueueLandingViewDataCache = ( new ActionsQueueLandingViewBuilder() )
				->build( $this->getAttentionQuery(), $this->getAssessmentRowsByZone(), $this->buildSummarySubtext() );
		}

		return $this->actionsQueueLandingViewDataCache;
	}

	/**
	 * @return list<BucketData>
	 */
	protected function getBucketsData() :array {
		if ( $this->actionsQueueBucketsCache === null ) {
			$this->actionsQueueBucketsCache = ( new ActionsQueueBucketsBuilder() )
				->build( $this->getAttentionQuery(), $this->getAssessmentRowsByZone() );
		}

		return $this->actionsQueueBucketsCache;
	}

	protected function hasDrilldownContent() :bool {
		return !empty( \array_filter(
			$this->getBucketsData(),
			static fn( array $bucket ) :bool => $bucket[ 'is_interactive' ]
		) );
	}

	protected function renderBucketsLayer() :string {
		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/actions_queue/layer_buckets.twig' )
			->setData( [
				'buckets'      => $this->getBucketsData(),
				'looking_good' => ( new ActionsQueueBucketsBuilder() )->buildLookingGood( $this->getAssessmentRowsByZone() ),
			] )
			->render();
	}

	protected function renderAllClearCard() :string {
		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/actions_queue/all_clear.twig' )
			->setData( [
				'all_clear' => $this->getLandingViewData()[ 'all_clear' ],
			] )
			->render();
	}

	protected function buildActionsQueueOperatorRootStep() :array {
		$viewData = $this->getLandingViewData();
		$summary = $viewData[ 'summary' ];
		$statusOverview = $viewData[ 'status_overview' ];
		$totalItems = (int)( $summary[ 'total_items' ] ?? 0 );

		return [
			'breadcrumb_label' => __( 'Actions Queue', 'wp-simple-firewall' ),
			'title'            => __( 'Actions Queue', 'wp-simple-firewall' ),
			'summary'          => $statusOverview[ 'summary_text' ],
			'focus'            => $statusOverview[ 'subtext' ],
			'next_step'        => $this->hasDrilldownContent()
				? __( 'Open a bucket to review grouped findings and run the next action.', 'wp-simple-firewall' )
				: __( 'Nothing requires your action right now.', 'wp-simple-firewall' ),
			'icon_class'       => $summary[ 'icon_class' ],
			'badge'            => $summary[ 'has_items' ]
				? \sprintf(
					_n( '%s item', '%s items', $totalItems, 'wp-simple-firewall' ),
					$totalItems
				)
				: __( 'All Clear', 'wp-simple-firewall' ),
			'badge_status'     => $summary[ 'severity' ],
			'color_key'        => 'actions',
		];
	}

	protected function buildActionsQueueOperatorRootStepJson() :string {
		return (string)( \json_encode( $this->buildActionsQueueOperatorRootStep() ) ?: '' );
	}
}
