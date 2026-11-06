<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginSetUiPreference;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\ScanResultsLagWarning;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\UiPreferences;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 * @phpstan-import-type AssessmentRowsByZone from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type BucketData from ActionsQueueBucketsBuilder
 * @phpstan-import-type HealthyRow from ActionsQueueBucketsBuilder
 * @phpstan-import-type LandingViewData from ActionsQueueLandingViewBuilder
 */
trait BuildsActionsQueueLandingData {

	private ?array $actionsQueueAttentionQueryCache = null;
	private ?array $actionsQueueAssessmentRowsByZoneCache = null;
	private ?array $actionsQueueLandingViewDataCache = null;
	private ?array $actionsQueueBucketsCache = null;
	private ?array $actionsQueueHealthyRowsCache = null;

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
		return ( new ActionsQueueLandingAssessmentBuilder() )->build();
	}

	protected function buildSummarySubtext() :string {
		$warning = ( new ScanResultsLagWarning() )->getText();
		if ( $warning !== '' ) {
			return $warning;
		}

		$latestScanAt = (int)\max( self::con()->comps->site_query->latestCompletedScanTimestamps() );

		return $latestScanAt > 0
			? \sprintf(
				__( 'Last scan: %s', 'wp-simple-firewall' ),
				Services::Request()->carbon( true )->setTimestamp( $latestScanAt )->diffForHumans()
			)
			: '';
	}

	/**
	 * @return LandingViewData
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

	/**
	 * @return list<HealthyRow>
	 */
	protected function getHealthyRowsData() :array {
		if ( $this->actionsQueueHealthyRowsCache === null ) {
			$this->actionsQueueHealthyRowsCache = ( new ActionsQueueBucketsBuilder() )
				->buildHealthyRows( $this->getAssessmentRowsByZone() );
		}

		return $this->actionsQueueHealthyRowsCache;
	}

	protected function hasDrilldownContent() :bool {
		return !empty( \array_filter(
			$this->getBucketsData(),
			static fn( array $bucket ) :bool => $bucket[ 'is_interactive' ]
		) );
	}

	/**
	 * The landing shows each lane's groups in place, so a group opens its results directly
	 * rather than through a separate lane page.
	 */
	protected function renderBucketsLayer() :string {
		$groupsBuilder = new ActionsQueueGroupsBuilder();
		$bucketGroups = [];
		foreach ( $this->getBucketsData() as $bucket ) {
			if ( $bucket[ 'is_interactive' ] ) {
				$bucketGroups[ $bucket[ 'key' ] ] = $groupsBuilder->build(
					$bucket[ 'key' ],
					$this->getAttentionQuery(),
					$this->getAssessmentRowsByZone()
				);
			}
		}

		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/actions_queue/layer_buckets.twig' )
			->setData( [
				'buckets'       => $this->getBucketsData(),
				'bucket_groups' => $bucketGroups,
				'healthy_rows'  => $this->getHealthyRowsData(),
				'aggregate_group_keys' => ( new ActionsQueueGroupDefinitions() )->reviewMaintenanceAggregateGroupKeys(),
				'view'          => ( new UiPreferences() )->get( UiPreferences::ACTIONS_QUEUE_VIEW ),
				'set_view_action_json' => ActionData::BuildJson( PluginSetUiPreference::class ),
				'hrefs'         => [
					'scan_run' => self::con()->plugin_urls->scansRun(),
				],
				'strings'       => [
					'run_scans_tab' => __( 'Run scans', 'wp-simple-firewall' ),
					'view_label'    => __( 'Layout', 'wp-simple-firewall' ),
					'view_sections' => __( 'Sections', 'wp-simple-firewall' ),
					'view_tabs'     => __( 'Tabs', 'wp-simple-firewall' ),
					'nothing_here'  => __( 'Nothing needs your action right now.', 'wp-simple-firewall' ),
					/* translators: %s: number of checks */
					'passing_label' => __( '%s checks passing', 'wp-simple-firewall' ),
					'passing_tab'   => __( 'Passing', 'wp-simple-firewall' ),
					/* translators: %1$s: the first few check names, %2$s: how many more */
					'passing_more'  => __( '%1$s and %2$s more', 'wp-simple-firewall' ),
					'not_covered_label'   => __( 'Not covered', 'wp-simple-firewall' ),
					'passing_shield' => __( 'Shield protections', 'wp-simple-firewall' ),
					'passing_system' => __( 'System', 'wp-simple-firewall' ),
					'pro_pill'       => __( 'Shield Pro', 'wp-simple-firewall' ),
					'turned_off_pill' => __( 'Turned off', 'wp-simple-firewall' ),
					'compare_plans'  => __( 'Compare plans', 'wp-simple-firewall' ),
					'turn_on'        => __( 'Turn on', 'wp-simple-firewall' ),
					'review'         => __( 'Review', 'wp-simple-firewall' ),
					'ignore_all'     => __( 'Ignore all', 'wp-simple-firewall' ),
					'stop_ignoring_all' => __( 'Stop ignoring all', 'wp-simple-firewall' ),
					/* translators: %s: how many of a group's items are ignored */
					'ignored_count'  => __( '(%s ignored)', 'wp-simple-firewall' ),
					/* translators: %s: how many items are ignored */
					'ignored_toggle' => __( '%s ignored', 'wp-simple-firewall' ),
					// One box for each kind of problem in a lane.
					'kind_headings'  => [
						'wordpress'   => __( 'WordPress core files', 'wp-simple-firewall' ),
						'malware'     => __( 'PHP malware', 'wp-simple-firewall' ),
						'file_locker' => __( 'Critical File Locker', 'wp-simple-firewall' ),
						'plugins'     => __( 'Plugins with scan results', 'wp-simple-firewall' ),
						'themes'      => __( 'Themes with scan results', 'wp-simple-firewall' ),
					],
				],
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
				? __( 'Open a group below to see its results and act on them.', 'wp-simple-firewall' )
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
		return OperatorChromeContract::encodeJson(
			OperatorChromeContract::normalizeStep( $this->buildActionsQueueOperatorRootStep() )
		);
	}
}
