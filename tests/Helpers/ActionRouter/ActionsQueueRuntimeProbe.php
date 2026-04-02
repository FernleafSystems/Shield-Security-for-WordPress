<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ActionsQueueScanRailMetrics;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueDrillDownDetail,
	ActionsQueueGroupsBuilder,
	ActionsQueueLandingAssessmentBuilder,
	ActionsQueueScanResultsOptions,
	PageActionsQueueLanding,
	ScansResultsViewBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScanResultsTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * @phpstan-type ScanAttentionSummary array{key:string,count:int,severity:string}
 * @phpstan-type BucketGroupSummary array{
 *   active_group_keys:list<string>,
 *   healthy_group_keys:list<string>
 * }
 * @phpstan-type RuntimeDiagnostics array{
 *   landing_has_shell:bool,
 *   queue_flags:array{queue_is_empty:bool,has_drilldown_content:bool},
 *   scan_attention:list<ScanAttentionSummary>,
 *   pane_counts:array{
 *     active_plugins:int,
 *     ignored_plugins:int,
 *     active_themes:int,
 *     ignored_themes:int,
 *     file_locker:int
 *   },
 *   file_locker_state:array{
 *     premium_active:bool,
 *     shieldnet_handshake:bool,
 *     file_locker_enabled:bool,
 *     files_to_lock:list<string>,
 *     lock_count:int,
 *     problem_lock_count:int
 *   },
 *   rail_tabs:array<string,array{count:int,status:string}>,
 *   buckets:array<string,BucketGroupSummary>
 * }
 * @phpstan-type GroupContext array{
 *   bucket_key:string,
 *   group_key:string,
 *   group_section:'active'|'healthy'
 * }
 * @phpstan-type DetailSummary array{
 *   detail_shell:string,
 *   panel_target:string,
 *   is_lazy_panel:bool,
 *   has_scan_results_table:bool,
 *   datatable_records_total:int,
 *   datatable_records_filtered:int,
 *   datatable_row_count:int
 * }
 */
class ActionsQueueRuntimeProbe {

	private const BUCKET_KEYS = [
		'critical',
		'review',
	];

	public function __construct(
		private ?PluginAdminRouteRuntime $routeRuntime = null
	) {
	}

	private ?array $landingPayload = null;
	private ?array $metricsPayload = null;
	private ?array $attentionQuery = null;
	private ?array $assessmentRowsByZone = null;
	private ?ActionsQueueScanResultsOptions $queueScanResultsOptions = null;
	private ?ScansResultsViewBuilder $viewBuilder = null;
	private ?array $paneCounts = null;
	private ?array $fileLockerState = null;
	private ?array $railTabs = null;
	private array $groupsByBucket = [];
	private array $detailSummaries = [];

	/**
	 * @return RuntimeDiagnostics
	 */
	public function inspect() :array {
		$landingHtml = (string)( $this->landingPayload()[ 'render_output' ] ?? '' );
		$flags = $this->landingFlags();
		$attentionItems = $this->attentionItems();

		$buckets = [];
		foreach ( self::BUCKET_KEYS as $bucketKey ) {
			$groups = $this->groupsLayerForBucket( $bucketKey );
			$buckets[ $bucketKey ] = [
				'active_group_keys'  => $this->extractSectionGroupKeys( $groups, 'active_sections' ),
				'healthy_group_keys' => $this->extractSectionGroupKeys( $groups, 'healthy_sections' ),
			];
		}

		return [
			'landing_has_shell' => \strpos( $landingHtml, 'data-actions-landing="1"' ) !== false,
			'queue_flags'       => [
				'queue_is_empty'        => !empty( $flags[ 'queue_is_empty' ] ),
				'has_drilldown_content' => !empty( $flags[ 'has_drilldown_content' ] ),
			],
			'scan_attention'    => \array_values( \array_map(
				static fn( array $item ) :array => [
					'key'      => (string)( $item[ 'key' ] ?? '' ),
					'count'    => (int)( $item[ 'count' ] ?? 0 ),
					'severity' => (string)( $item[ 'severity' ] ?? '' ),
				],
				$attentionItems
			) ),
			'pane_counts'       => $this->paneCounts(),
			'file_locker_state' => $this->fileLockerState(),
			'rail_tabs'         => $this->railTabs(),
			'buckets'           => $buckets,
		];
	}

	/**
	 * @return GroupContext|null
	 */
	public function locateGroupContext( string $targetGroupKey ) :?array {
		foreach ( self::BUCKET_KEYS as $bucketKey ) {
			$groups = $this->groupsLayerForBucket( $bucketKey );

			foreach ( [
				'active'  => $this->extractSectionGroups( $groups, 'active_sections' ),
				'healthy' => $this->extractSectionGroups( $groups, 'healthy_sections' ),
			] as $groupSection => $groups ) {
				foreach ( $groups as $group ) {
					if ( (string)( $group[ 'key' ] ?? '' ) === $targetGroupKey ) {
						return [
							'bucket_key'    => $bucketKey,
							'group_key'     => $targetGroupKey,
							'group_section' => $groupSection,
						];
					}
				}
			}
		}

		return null;
	}

	/**
	 * @phpstan-param GroupContext $groupContext
	 * @return DetailSummary
	 */
	public function inspectDetail( array $groupContext ) :array {
		$cacheKey = $this->detailCacheKey( $groupContext[ 'bucket_key' ], $groupContext[ 'group_key' ] );
		if ( !isset( $this->detailSummaries[ $cacheKey ] ) ) {
			$detailPayload = $this->routeRuntime()
				->processActionPayloadWithAdminBypass( ActionsQueueDrillDownDetail::SLUG, [
					'bucket' => $groupContext[ 'bucket_key' ],
					'group'  => $groupContext[ 'group_key' ],
				] );
			$groupSelection = \is_array( $detailPayload[ 'group_selection' ] ?? null )
				? $detailPayload[ 'group_selection' ]
				: [];
			$detailShell = (string)( $groupSelection[ 'detail_shell' ] ?? '' );
			$detailDom = $this->createDomXPath( (string)( $detailPayload[ 'html' ] ?? '' ) );

			$this->detailSummaries[ $cacheKey ] = [
				'detail_shell'            => $detailShell,
				'panel_target'            => $detailShell === 'asset_cards'
					? $this->extractPanelTarget( $detailDom )
					: '',
				'is_lazy_panel'           => $detailShell === 'asset_cards'
					&& $this->extractLazyPanelFlag( $detailDom ),
				'has_scan_results_table'  => $this->hasScanResultsTableContract( $detailDom ),
				'datatable_records_total' => 0,
				'datatable_records_filtered' => 0,
				'datatable_row_count'     => 0,
			];
			$tableData = $this->executeScanResultsTableAction( $detailDom );
			if ( $tableData !== [] ) {
				$this->detailSummaries[ $cacheKey ][ 'datatable_records_total' ] = (int)( $tableData[ 'recordsTotal' ] ?? 0 );
				$this->detailSummaries[ $cacheKey ][ 'datatable_records_filtered' ] = (int)( $tableData[ 'recordsFiltered' ] ?? 0 );
				$this->detailSummaries[ $cacheKey ][ 'datatable_row_count' ] = \count(
					\is_array( $tableData[ 'data' ] ?? null ) ? $tableData[ 'data' ] : []
				);
			}
		}

		return $this->detailSummaries[ $cacheKey ];
	}

	private function routeRuntime() :PluginAdminRouteRuntime {
		if ( $this->routeRuntime === null ) {
			$this->routeRuntime = new PluginAdminRouteRuntime();
		}

		return $this->routeRuntime;
	}

	private function landingPayload() :array {
		if ( $this->landingPayload === null ) {
			$this->landingPayload = $this->routeRuntime()
				->processActionPayloadWithAdminBypass( PageActionsQueueLanding::SLUG, [
					Constants::NAV_ID     => PluginNavs::NAV_SCANS,
					Constants::NAV_SUB_ID => PluginNavs::SUBNAV_SCANS_OVERVIEW,
				] );
		}

		return $this->landingPayload;
	}

	private function landingFlags() :array {
		$renderData = $this->landingRenderData();

		return \is_array( $renderData[ 'flags' ] ?? null )
			? $renderData[ 'flags' ]
			: [];
	}

	private function landingRenderData() :array {
		$renderData = $this->landingPayload()[ 'render_data' ] ?? null;
		return \is_array( $renderData )
			? $renderData
			: [];
	}

	private function attentionItems() :array {
		$items = $this->attentionQuery()[ 'groups' ][ 'scans' ][ 'items' ] ?? null;
		return \is_array( $items )
			? $items
			: [];
	}

	private function attentionQuery() :array {
		if ( $this->attentionQuery === null ) {
			$this->attentionQuery = RuntimeTestState::controller()->comps->site_query->attention();
		}

		return $this->attentionQuery;
	}

	private function assessmentRowsByZone() :array {
		if ( $this->assessmentRowsByZone === null ) {
			$this->assessmentRowsByZone = ( new ActionsQueueLandingAssessmentBuilder() )->build();
		}

		return $this->assessmentRowsByZone;
	}

	private function queueScanResultsOptions() :ActionsQueueScanResultsOptions {
		if ( $this->queueScanResultsOptions === null ) {
			$this->queueScanResultsOptions = new ActionsQueueScanResultsOptions();
		}

		return $this->queueScanResultsOptions;
	}

	private function viewBuilder() :ScansResultsViewBuilder {
		if ( $this->viewBuilder === null ) {
			$this->viewBuilder = new ScansResultsViewBuilder();
		}

		return $this->viewBuilder;
	}

	private function paneCounts() :array {
		if ( $this->paneCounts === null ) {
			$scanResultsOptions = $this->queueScanResultsOptions();
			$viewBuilder = $this->viewBuilder();
			$this->paneCounts = [
				'active_plugins'  => \count( $viewBuilder->buildActionsQueuePluginsPane()[ 'cards' ] ?? [] ),
				'ignored_plugins' => \count( $viewBuilder->buildActionsQueuePluginsPane(
					$scanResultsOptions->ignoredOnly()
				)[ 'cards' ] ?? [] ),
				'active_themes'   => \count( $viewBuilder->buildActionsQueueThemesPane()[ 'cards' ] ?? [] ),
				'ignored_themes'  => \count( $viewBuilder->buildActionsQueueThemesPane(
					$scanResultsOptions->ignoredOnly()
				)[ 'cards' ] ?? [] ),
				'file_locker'     => $this->fileLockerState()[ 'problem_lock_count' ],
			];
		}

		return $this->paneCounts;
	}

	private function fileLockerState() :array {
		if ( $this->fileLockerState === null ) {
			$controller = RuntimeTestState::controller();
			$fileLockerController = $controller->comps->file_locker;
			$shieldNetState = $controller->opts->optGet( 'snapi_data' );
			$shieldNetState = \is_array( $shieldNetState ) ? $shieldNetState : [];
			$shieldNetHandshake = (int)( $shieldNetState[ 'last_handshake_at' ] ?? 0 ) > 0;
			$filesToLock = \array_values( \array_map(
				static fn( $fileKey ) :string => (string)$fileKey,
				$fileLockerController->getFilesToLock()
			) );
			$fileLockerEnabled = \count( $filesToLock ) > 0
				&& $controller->db_con->file_locker->isReady()
				&& $shieldNetHandshake;
			$fileLockerCards = $fileLockerEnabled
				? ( $this->viewBuilder()->buildActionsQueueFileLockerPane()[ 'cards' ] ?? [] )
				: [];

			$this->fileLockerState = [
				'premium_active'      => $controller->isPremiumActive(),
				'shieldnet_handshake' => $shieldNetHandshake,
				'file_locker_enabled' => $fileLockerEnabled,
				'files_to_lock'       => $filesToLock,
				'lock_count'          => \count( $fileLockerController->getLocks() ),
				'problem_lock_count'  => \count( $fileLockerCards ),
			];
		}

		return $this->fileLockerState;
	}

	private function metricsPayload() :array {
		if ( $this->metricsPayload === null ) {
			$this->metricsPayload = $this->routeRuntime()->processActionPayloadWithAdminBypass( ActionsQueueScanRailMetrics::SLUG );
		}

		return $this->metricsPayload;
	}

	private function railTabs() :array {
		if ( $this->railTabs === null ) {
			$tabs = $this->metricsPayload()[ 'tabs' ] ?? null;
			$tabs = \is_array( $tabs )
				? $tabs
				: [];
			$this->railTabs = [];
			foreach ( $tabs as $key => $tab ) {
				$this->railTabs[ (string)$key ] = [
					'count'  => (int)( $tab[ 'count' ] ?? 0 ),
					'status' => (string)( $tab[ 'status' ] ?? '' ),
				];
			}
		}

		return $this->railTabs;
	}

	private function groupsLayerForBucket( string $bucketKey ) :array {
		if ( !isset( $this->groupsByBucket[ $bucketKey ] ) ) {
			$this->groupsByBucket[ $bucketKey ] = ( new ActionsQueueGroupsBuilder() )->build(
				$bucketKey,
				$this->attentionQuery(),
				$this->assessmentRowsByZone()
			);
		}

		return $this->groupsByBucket[ $bucketKey ];
	}

	/**
	 * @return list<string>
	 */
	private function extractSectionGroupKeys( array $payload, string $sectionKey ) :array {
		return \array_values( \array_map(
			static fn( array $group ) :string => (string)( $group[ 'key' ] ?? '' ),
			$this->extractSectionGroups( $payload, $sectionKey )
		) );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function extractSectionGroups( array $payload, string $sectionKey ) :array {
		$sections = \is_array( $payload[ $sectionKey ] ?? null )
			? $payload[ $sectionKey ]
			: [];
		$groups = [];

		foreach ( $sections as $section ) {
			foreach ( \is_array( $section[ 'groups' ] ?? null ) ? $section[ 'groups' ] : [] as $group ) {
				$groups[] = $group;
			}
		}

		return $groups;
	}

	private function createDomXPath( string $html ) :\DOMXPath {
		$dom = new \DOMDocument();
		$previous = \libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><div>'.$html.'</div>', \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD );
		\libxml_clear_errors();
		\libxml_use_internal_errors( $previous );
		return new \DOMXPath( $dom );
	}

	private function extractPanelTarget( \DOMXPath $xpath ) :string {
		$panel = $xpath->query( '//*[@data-mode-panel="1"]' )->item( 0 );
		if ( !$panel instanceof \DOMElement ) {
			return '';
		}

		return \trim( $panel->getAttribute( 'data-mode-panel-target-default' ) ?: $panel->getAttribute( 'data-mode-panel-target' ) );
	}

	private function extractLazyPanelFlag( \DOMXPath $xpath ) :bool {
		$panel = $xpath->query( '//*[@data-mode-panel="1"]' )->item( 0 );
		return $panel instanceof \DOMElement
			&& $panel->getAttribute( 'data-actions-queue-asset-panel-lazy' ) === '1';
	}

	private function hasScanResultsTableContract( \DOMXPath $xpath ) :bool {
		return $xpath->query( '//*[@data-scan-results-table="1"]' )->length > 0;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function executeScanResultsTableAction( \DOMXPath $xpath ) :array {
		$table = $xpath->query( '//*[@data-scan-results-table="1"]' )->item( 0 );
		if ( !$table instanceof \DOMElement ) {
			return [];
		}

		$tableAction = $this->decodeJsonAttr( $table->getAttribute( 'data-table-action' ) );
		if ( $tableAction === [] ) {
			return [];
		}

		$payload = $this->routeRuntime()->processActionPayloadWithAdminBypass(
			ScanResultsTableAction::SLUG,
			\array_merge( $tableAction, [
				'sub_action'   => 'retrieve_table_data',
				'table_data'   => $this->datatablePayloadFixture(),
			] )
		);

		$datatableData = $payload[ 'datatable_data' ] ?? null;
		return \is_array( $datatableData ) ? $datatableData : [];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function datatablePayloadFixture() :array {
		return [
			'search'  => [ 'value' => '' ],
			'start'   => 0,
			'length'  => 10,
			'order'   => [],
			'columns' => [],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decodeJsonAttr( string $attr ) :array {
		if ( \trim( $attr ) === '' ) {
			return [];
		}

		$decoded = \json_decode( \html_entity_decode( $attr, \ENT_QUOTES ), true );
		return \is_array( $decoded ) ? $decoded : [];
	}

	private function detailCacheKey( string $bucketKey, string $groupKey ) :string {
		return $bucketKey.'|'.$groupKey;
	}
}
