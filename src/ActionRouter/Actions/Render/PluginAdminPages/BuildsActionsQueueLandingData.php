<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 * @phpstan-import-type BucketData from ActionsQueueBucketsBuilder
 * @phpstan-type AssessmentRow array{
 *   key:string,
 *   label:string,
 *   description:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string
 * }
 * @phpstan-type AssessmentRowsByZone array{
 *   scans:list<AssessmentRow>,
 *   maintenance:list<AssessmentRow>
 * }
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
	 *   severity_strip:array{
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

	protected function renderBucketsLayer() :string {
		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/actions_queue/layer_buckets.twig' )
			->setData( [
				'buckets' => $this->getBucketsData(),
				'heading' => __( 'Choose where to start', 'wp-simple-firewall' ),
			] )
			->render();
	}

	protected function renderSeverityStripSection() :string {
		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/actions_queue/severity_strip.twig' )
			->setData( [
				'strip'                => $this->getLandingViewData()[ 'severity_strip' ],
				'severity_strip_label' => __( 'Queue Status', 'wp-simple-firewall' ),
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
}
