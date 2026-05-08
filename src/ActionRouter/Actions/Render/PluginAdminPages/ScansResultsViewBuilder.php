<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansFileLockerDiff;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\GetPendingFileLockDisplays;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-type QueueAssetAction array{
 *   type:string,
 *   label:string,
 *   href:string,
 *   is_action:bool,
 *   icon_class:string,
 *   tooltip_attr:string,
 *   attributes:array<string,string>
 * }
 * @phpstan-type QueueAssetPanelData array<string,string>
 * @phpstan-type QueueAssetCard array{
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
 *   count_badge:int|null,
 *   body_notice:string,
 *   body_notice_variant:string,
 *   panel_data:QueueAssetPanelData,
 *   actions:list<QueueAssetAction>,
 *   table:array<string,mixed>
 * }
 * @phpstan-type QueueFileLockerCard array{
 *   key:string,
 *   panel_id:string,
 *   panel_target:string,
 *   status:string,
 *   icon_class:string,
 *   title:string,
 *   rail_title:string,
 *   stat_text:string,
 *   meta_text:string,
 *   show_meta_in_tile:bool,
 *   count_badge:null,
 *   body_notice:string,
 *   body_notice_variant:string,
 *   panel_data:QueueAssetPanelData,
 *   actions:list<QueueAssetAction>,
 *   table:array<string,mixed>
 * }
 * @phpstan-import-type PendingFileLockDisplay from GetPendingFileLockDisplays
 * @phpstan-type DisabledPaneAction array{
 *   type:string,
 *   label:string,
 *   href:string,
 *   is_action:bool,
 *   icon_class:string,
 *   tooltip_attr:string,
 *   class_name:string,
 *   target:string,
 *   rel:string,
 *   attributes:array<string,string>
 * }
 * @phpstan-import-type VulnerabilityAction from ScansVulnerabilitiesBuilder
 * @phpstan-import-type VulnerabilityItem from ScansVulnerabilitiesBuilder
 * @phpstan-import-type VulnerabilitySection from ScansVulnerabilitiesBuilder
 * @phpstan-import-type VulnerabilitiesPayload from ScansVulnerabilitiesBuilder
 * @phpstan-type QueueAssetPane array{
 *   is_disabled:bool,
 *   disabled_message:string,
 *   disabled_actions:list<DisabledPaneAction>,
 *   cards:list<QueueAssetCard>
 * }
 * @phpstan-type QueueFileLockerPane array{
 *   is_disabled:bool,
 *   disabled_message:string,
 *   disabled_actions:list<DisabledPaneAction>,
 *   cards:list<QueueFileLockerCard>
 * }
 * @phpstan-type ScanResultAccessDetailPane array{
 *   flags:array{is_disabled:bool},
 *   strings:array{disabled_message:string},
 *   vars:array{disabled_actions:list<DisabledPaneAction>},
 *   table:array<string,mixed>
 * }
 * @phpstan-type RailTabAvailability array{
 *   is_available:bool,
 *   show_in_actions_queue:bool,
 *   show_in_fix_now:bool,
 *   disabled_reason:''|'not_enabled'|'upgrade_required',
 *   disabled_message:string,
 *   disabled_status:string,
 *   disabled_actions:list<DisabledPaneAction>
 * }
 * @phpstan-type DetailExpansionAction array{
 *   label:string,
 *   href:string,
 *   target?:string
 * }
 * @phpstan-type DetailExpansionSimpleTableRow array{
 *   title:string,
 *   subtitle:string,
 *   context:string,
 *   identifier:string,
 *   action:DetailExpansionAction
 * }
 * @phpstan-type DetailExpansion array{
 *   id:string,
 *   type:string,
 *   status:string,
 *   table:array<string,mixed>
 * }
 */
class ScansResultsViewBuilder {

	use PluginControllerConsumer;

	private const RESULT_AREA_KEYS = [
		'wordpress',
		'plugins',
		'themes',
		'vulnerabilities',
		'abandoned',
		'malware',
		'file_locker',
	];
	private const DIRECT_TABLE_AREA_KEYS = [
		'wordpress',
		'malware',
	];

	private ?ScansResultsRailTabAvailability $cachedRailTabAvailability = null;

	/**
	 * @return VulnerabilitiesPayload
	 */
	protected function buildVulnerabilities() :array {
		return ( new ScansVulnerabilitiesBuilder() )->build();
	}

	/**
	 * @return RailTabAvailability
	 */
	protected function getRailTabAvailability( string $tabKey ) :array {
		return $this->getRailTabAvailabilityBuilder()->build( $tabKey );
	}

	/**
	 * @param list<array<string,mixed>> $items
	 */
	private function countNonGoodItems( array $items ) :int {
		return \count( \array_filter( $items, static fn( array $item ) :bool => $item[ 'status' ] !== 'good' ) );
	}

	/**
	 * @return array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon:string,
	 *   summary_keys:list<string>
	 * }>
	 */
	protected function getScanTabDefinitions() :array {
		return PluginNavs::actionsQueueScanDefinitions();
	}

	/**
	 * @return array{label:string,icon_class:string,summary_keys:list<string>}
	 */
	protected function getRailTabMeta( string $key ) :array {
		if ( $key === 'summary' ) {
			return [
				'label'        => __( 'Summary', 'wp-simple-firewall' ),
				'icon_class'   => 'bi bi-clipboard2-pulse-fill',
				'summary_keys' => [],
			];
		}

		$definition = $this->getScanTabDefinitions()[ $key ] ?? null;
		if ( \is_array( $definition ) ) {
			return [
				'label'        => $definition[ 'label' ],
				'icon_class'   => PluginNavs::actionsLandingScanRailIconClass( $key ),
				'summary_keys' => $definition[ 'summary_keys' ],
			];
		}

		return [
			'label'        => $key,
			'icon_class'   => '',
			'summary_keys' => [],
		];
	}

	/**
	 * @return QueueAssetPane
	 */
	public function buildActionsQueuePluginsPane( ?array $resultsDisplayOptions = null ) :array {
		return $this->buildActionsQueuePluginThemePane( 'plugin', $resultsDisplayOptions );
	}

	/**
	 * @return QueueAssetPane
	 */
	public function buildActionsQueueThemesPane( ?array $resultsDisplayOptions = null ) :array {
		return $this->buildActionsQueuePluginThemePane( 'theme', $resultsDisplayOptions );
	}

	/**
	 * @return QueueFileLockerPane
	 */
	public function buildActionsQueueFileLockerPane() :array {
		$availability = $this->getRailTabAvailability( 'file_locker' );
		if ( !$availability[ 'is_available' ] ) {
			return $this->buildDisabledAssetPane( $availability );
		}

		return [
			'is_disabled'      => false,
			'disabled_message' => '',
			'disabled_actions' => [],
			'cards'            => $this->buildFileLockerQueueRecords(),
		];
	}

	/**
	 * @return ScanResultAccessDetailPane
	 */
	public function buildActionsQueueDirectTablePane( string $areaKey, ?array $resultsDisplayOptions = null ) :array {
		$areaKey = $this->normalizeDirectTableAreaKey( $areaKey );
		$availability = $this->getRailTabAvailability( $areaKey );
		if ( !$availability[ 'is_available' ] ) {
			return $this->buildDisabledDirectTablePane( $availability );
		}

		return $this->buildEnabledDirectTablePane(
			$this->buildDirectTableForArea( $areaKey, $resultsDisplayOptions )
		);
	}

	/**
	 * @return ScanResultAccessDetailPane
	 */
	public function buildActionsQueueSubjectTablePane(
		string $subjectType,
		string $subjectId,
		?array $resultsDisplayOptions = null
	) :array {
		$areaKey = $this->areaKeyForSubjectType( $subjectType );
		$availability = $this->getRailTabAvailability( $areaKey );
		if ( !$availability[ 'is_available' ] ) {
			return $this->buildDisabledDirectTablePane( $availability );
		}

		return $this->buildEnabledDirectTablePane(
			$this->buildSubjectTableForArea( $areaKey, $subjectId, $resultsDisplayOptions )
		);
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildPluginThemeRailItemsDirect( string $assetType ) :array {
		$issueItems = [];
		foreach ( $this->buildPluginThemeIssueRecords( $assetType ) as $item ) {
			$row = $this->attachExpansionToDetailRow(
				$this->buildDetailRow(
					$item[ 'title' ],
					$item[ 'stat_text' ],
					$item[ 'status' ],
					$item[ 'count_badge' ],
					$item[ 'status' ],
					$item[ 'actions' ]
				),
				$this->buildDetailExpansion(
					$item[ 'expand_target' ],
					DetailExpansionType::SCAN_RESULTS_TABLE,
					$item[ 'status' ],
					$item[ 'table' ]
				)
			);
			$row[ 'section_label' ] = __( 'Needs attention', 'wp-simple-firewall' );
			$issueItems[] = $row;
		}

		return $issueItems;
	}

	/**
	 * @return list<QueueAssetCard>
	 */
	protected function buildPluginThemeIssueRecords( string $assetType, ?array $resultsDisplayOptions = null ) :array {
		return \array_values( \array_map(
			fn( array $card ) :array => $this->normalizeQueueAssetCard( $card ),
			$this->buildActionsQueueAssetCardsBuilder()->buildIssueRecords(
				$assetType,
				$this->queueScanResultsOptions()->normalize( $resultsDisplayOptions )
			)
		) );
	}

	/**
	 * @return QueueAssetPane
	 */
	private function buildActionsQueuePluginThemePane( string $assetType, ?array $resultsDisplayOptions = null ) :array {
		$availability = $this->getRailTabAvailability( $assetType === 'plugin' ? 'plugins' : 'themes' );
		if ( !$availability[ 'is_available' ] ) {
			return $this->buildDisabledAssetPane( $availability );
		}

		return [
			'is_disabled'      => false,
			'disabled_message' => '',
			'disabled_actions' => [],
			'cards'            => $this->buildPluginThemeIssueRecords(
				$assetType,
				$this->queueScanResultsOptions()->normalize( $resultsDisplayOptions )
			),
		];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildVulnerabilitiesRailItems( array $vulnerabilities ) :array {
		$items = [];
		foreach ( $this->normalizedVulnerabilitySections( $vulnerabilities ) as $section ) {
			$sectionLabel = $section[ 'label' ];
			foreach ( $section[ 'items' ] as $item ) {
				$severity = StatusPriority::normalize( $item[ 'severity' ], 'warning' );
				$items[] = $this->buildDetailRow(
					$item[ 'label' ],
					$item[ 'description' ],
					$severity,
					$item[ 'count' ],
					$severity,
					$item[ 'actions' ],
					null,
					null,
					$sectionLabel
				);
			}
		}
		return $items;
	}

	protected function buildVulnerabilitiesRailStatus( array $vulnerabilities ) :string {
		$statuses = [];
		foreach ( $this->normalizedVulnerabilitySections( $vulnerabilities ) as $section ) {
			$statuses[] = StatusPriority::normalize( (string)( $section[ 'status' ] ?? 'good' ), 'good' );
			foreach ( $section[ 'items' ] as $item ) {
				$statuses[] = StatusPriority::normalize( $item[ 'severity' ], 'warning' );
			}
		}
		return StatusPriority::highest( $statuses, 'good' );
	}

	/**
	 * @return list<QueueFileLockerCard>
	 */
	protected function buildFileLockerQueueRecords() :array {
		$records = [];
		foreach ( $this->getProblemFileLocks() as $lock ) {
			$records[] = $this->buildFileLockerQueueRecord( $lock, 'warning' );
		}
		foreach ( $this->getPendingFileLockDisplays() as $pendingLock ) {
			$records[] = $this->buildPendingFileLockerQueueRecord( $pendingLock );
		}
		foreach ( $this->getGoodFileLocks() as $lock ) {
			$records[] = $this->buildFileLockerQueueRecord( $lock, 'good' );
		}
		return $records;
	}

	/**
	 * @return list<PendingFileLockDisplay>
	 */
	protected function getPendingFileLockDisplays() :array {
		return ( new GetPendingFileLockDisplays() )->run();
	}

	protected function getProblemFileLocks() :array {
		return ( new LoadFileLocks() )->withProblems();
	}

	protected function getGoodFileLocks() :array {
		return ( new LoadFileLocks() )->withoutProblems();
	}

	/**
	 * @param array{count?:int,status?:string,sections?:array<string,mixed>} $vulnerabilities
	 * @return array{
	 *   key:string,
	 *   label:string,
	 *   status:string,
	 *   icon_class:string,
	 *   count_items:int,
	 *   items:list<array<string,mixed>>,
	 *   is_loaded:bool,
	 *   is_disabled:bool,
	 *   disabled_message:string,
	 *   disabled_status:string,
	 *   disabled_actions:list<DisabledPaneAction>
	 * }
	 */
	public function buildRailPaneData( string $tabKey, array $vulnerabilities = [], ?string $vulnerabilitySection = null ) :array {
		$tabKey = \strtolower( \trim( $tabKey ) );
		$meta = $this->getRailTabMeta( $tabKey );
		$availability = $this->getRailTabAvailability( $tabKey );
		$items = [];
		$count = 0;
		$status = 'good';
		$isDisabled = false;
		$disabledMessage = '';
		$disabledStatus = $availability[ 'disabled_status' ];
		$disabledActions = [];

		if ( !$availability[ 'is_available' ]
			 && $this->isProtectedScanResultsArea( $tabKey ) ) {
			$isDisabled = true;
			$status = $disabledStatus;
			$disabledMessage = $availability[ 'disabled_message' ];
			$disabledActions = $availability[ 'disabled_actions' ] ?? [];
		}

		if ( !$isDisabled ) {
			switch ( $tabKey ) {
				case 'plugins':
					$items = $this->buildPluginThemeRailItemsDirect( 'plugin' );
					$count = $this->countNonGoodItems( $items );
					$status = $count > 0 ? 'warning' : 'good';
					break;

				case 'themes':
					$items = $this->buildPluginThemeRailItemsDirect( 'theme' );
					$count = $this->countNonGoodItems( $items );
					$status = $count > 0 ? 'warning' : 'good';
					break;

				case 'vulnerabilities':
					$vulnerabilities = empty( $vulnerabilities ) ? $this->buildVulnerabilities() : $vulnerabilities;
					$sectionPayload = $this->vulnerabilityPanePayload( $vulnerabilities, $vulnerabilitySection ?? 'vulnerable' );
					$items = $this->buildVulnerabilitiesRailItems( $sectionPayload );
					$count = $sectionPayload[ 'count' ];
					$status = $sectionPayload[ 'status' ];
					break;

				case 'abandoned':
					$vulnerabilities = empty( $vulnerabilities ) ? $this->buildVulnerabilities() : $vulnerabilities;
					$sectionPayload = $this->vulnerabilityPanePayload( $vulnerabilities, 'abandoned' );
					$items = $this->buildVulnerabilitiesRailItems( $sectionPayload );
					$count = $sectionPayload[ 'count' ];
					$status = $sectionPayload[ 'status' ];
					break;

			}
		}

		return [
			'key'         => $tabKey,
			'label'       => $meta[ 'label' ],
			'icon_class'  => $meta[ 'icon_class' ],
			'count_items' => $count,
			'status'      => $status,
			'items'       => $items,
			'is_loaded'   => true,
			'is_disabled' => $isDisabled,
			'disabled_message' => $disabledMessage,
			'disabled_status'  => $disabledStatus,
			'disabled_actions' => $disabledActions,
			'render_action'    => [],
			'show_count_placeholder' => false,
		];
	}

	/**
	 * @return ScanResultAccessDetailPane
	 */
	private function buildDisabledDirectTablePane( array $availability ) :array {
		return [
			'flags'   => [
				'is_disabled' => true,
			],
			'strings' => [
				'disabled_message' => $availability[ 'disabled_message' ],
			],
			'vars'    => [
				'disabled_actions' => $availability[ 'disabled_actions' ] ?? [],
			],
			'table'   => [],
		];
	}

	/**
	 * @return ScanResultAccessDetailPane
	 */
	private function buildEnabledDirectTablePane( array $table ) :array {
		return [
			'flags'   => [
				'is_disabled' => false,
			],
			'strings' => [
				'disabled_message' => '',
			],
			'vars'    => [
				'disabled_actions' => [],
			],
			'table'   => $table,
		];
	}

	private function normalizeDirectTableAreaKey( string $areaKey ) :string {
		$areaKey = \strtolower( \trim( $areaKey ) );
		if ( !\in_array( $areaKey, self::DIRECT_TABLE_AREA_KEYS, true ) ) {
			throw new \InvalidArgumentException( \sprintf( 'Scan result area "%s" has no direct table.', $areaKey ) );
		}

		return $areaKey;
	}

	private function isProtectedScanResultsArea( string $areaKey ) :bool {
		return \in_array( $areaKey, self::RESULT_AREA_KEYS, true );
	}

	private function areaKeyForSubjectType( string $subjectType ) :string {
		switch ( \strtolower( \trim( $subjectType ) ) ) {
			case InvestigationTableContract::SUBJECT_TYPE_PLUGIN:
				return 'plugins';

			case InvestigationTableContract::SUBJECT_TYPE_THEME:
				return 'themes';

			default:
				throw new \InvalidArgumentException( \sprintf( 'Unsupported scan result subject type "%s".', $subjectType ) );
		}
	}

	private function buildDirectTableForArea( string $areaKey, ?array $resultsDisplayOptions ) :array {
		$tableBuilder = $this->buildScanResultsTableBuilder();

		switch ( $areaKey ) {
			case 'wordpress':
				return $tableBuilder->buildWordpressTable( $resultsDisplayOptions );

			case 'malware':
				return $tableBuilder->buildMalwareTable( $resultsDisplayOptions );

			default:
				throw new \InvalidArgumentException( \sprintf( 'Scan result area "%s" has no direct table.', $areaKey ) );
		}
	}

	private function buildSubjectTableForArea( string $areaKey, string $subjectId, ?array $resultsDisplayOptions ) :array {
		$tableBuilder = $this->buildScanResultsTableBuilder();

		switch ( $areaKey ) {
			case 'plugins':
				return $tableBuilder->buildPluginTable( $subjectId, $resultsDisplayOptions );

			case 'themes':
				return $tableBuilder->buildThemeTable( $subjectId, $resultsDisplayOptions );

			default:
				throw new \InvalidArgumentException( \sprintf( 'Scan result area "%s" has no subject table.', $areaKey ) );
		}
	}

	/**
	 * @phpstan-param RailTabAvailability $availability
	 * @return QueueAssetPane|QueueFileLockerPane
	 */
	private function buildDisabledAssetPane( array $availability ) :array {
		return [
			'is_disabled'      => true,
			'disabled_message' => $availability[ 'disabled_message' ],
			'disabled_actions' => $availability[ 'disabled_actions' ] ?? [],
			'cards'            => [],
		];
	}

	/**
	 * @param array{count?:int,status?:string,sections?:array<string,mixed>} $vulnerabilities
	 * @return array{count:int,status:string,sections:array<string,mixed>}
	 */
	private function vulnerabilityPanePayload( array $vulnerabilities, ?string $sectionKey ) :array {
		if ( $sectionKey === null ) {
			return [
				'count'    => (int)( $vulnerabilities[ 'count' ] ?? 0 ),
				'status'   => (string)( $vulnerabilities[ 'status' ] ?? $this->buildVulnerabilitiesRailStatus( $vulnerabilities ) ),
				'sections' => $this->normalizedVulnerabilitySections( $vulnerabilities ),
			];
		}

		$sections = $this->normalizedVulnerabilitySections( $vulnerabilities );
		$section = $sections[ $sectionKey ] ?? $this->emptyVulnerabilitySection( $sectionKey );

		return [
			'count'    => $section[ 'count' ],
			'status'   => $section[ 'status' ],
			'sections' => [
				$sectionKey => $section,
			],
		];
	}

	/**
	 * @param array{count?:int,status?:string,sections?:array<string,mixed>} $vulnerabilities
	 * @return array<string,array{
	 *   label:string,
	 *   count:int,
	 *   status:string,
	 *   items:list<array<string,mixed>>
	 * }>
	 */
	private function normalizedVulnerabilitySections( array $vulnerabilities ) :array {
		$sections = [];
		foreach ( \is_array( $vulnerabilities[ 'sections' ] ?? null ) ? $vulnerabilities[ 'sections' ] : [] as $key => $section ) {
			$items = \is_array( $section[ 'items' ] ?? null ) ? \array_values( $section[ 'items' ] ) : [];
			$sections[ $key ] = [
				'label'  => (string)( $section[ 'label' ] ?? '' ),
				'count'  => (int)( $section[ 'count' ] ?? \count( $items ) ),
				'status' => (string)( $section[ 'status' ] ?? ( empty( $items ) ? 'good' : 'critical' ) ),
				'items'  => $items,
			];
		}

		return $sections;
	}

	/**
	 * @return array{
	 *   label:string,
	 *   count:int,
	 *   status:string,
	 *   items:list<array<string,mixed>>
	 * }
	 */
	private function emptyVulnerabilitySection( string $sectionKey ) :array {
		return [
			'label'  => $sectionKey === 'abandoned'
				? __( 'Abandoned Assets', 'wp-simple-firewall' )
				: __( 'Known Vulnerabilities', 'wp-simple-firewall' ),
			'count'  => 0,
			'status' => 'good',
			'items'  => [],
		];
	}

	/**
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender> $actionClass
	 */
	protected function buildAjaxRenderActionData( string $actionClass, array $aux = [] ) :array {
		return ActionData::BuildAjaxRender( $actionClass, $aux );
	}

	/**
	 * @return QueueFileLockerCard
	 */
	private function buildFileLockerQueueRecord( object $lock, string $status ) :array {
		$path = (string)$lock->path;
		$rid = (int)$lock->id;

		return $this->normalizeQueueAssetCard( [
			'key'          => (string)$lock->id,
			'panel_id'     => 'actions-queue-filelocker-card-'.$rid,
			'panel_target' => 'actions-queue-filelocker-'.$rid,
			'status'       => $status,
			'icon_class'   => 'bi bi-file-lock2-fill',
			'title'        => \basename( $path ),
			'rail_title'   => $path,
			'stat_text'    => $status === 'good'
				? __( 'File integrity verified.', 'wp-simple-firewall' )
				: $this->describeFileLockerRecord( $lock ),
			'meta_text'          => $path,
			'show_meta_in_tile'  => false,
			'render_action'      => $this->buildAjaxRenderActionData( ScansFileLockerDiff::class, [
				'rid' => $rid,
			] ),
		] );
	}

	/**
	 * @param PendingFileLockDisplay $pendingLock
	 * @return QueueFileLockerCard
	 */
	private function buildPendingFileLockerQueueRecord( array $pendingLock ) :array {
		$fileKey = sanitize_key( $pendingLock[ 'file_key' ] );
		$path = $pendingLock[ 'path' ];

		return $this->normalizeQueueAssetCard( [
			'key'                => 'pending:'.$fileKey,
			'panel_id'           => 'actions-queue-filelocker-card-pending-'.$fileKey,
			'panel_target'       => 'actions-queue-filelocker-pending-'.$fileKey,
			'status'             => 'neutral',
			'icon_class'         => 'bi bi-file-lock2-fill',
			'title'              => $pendingLock[ 'title' ],
			'rail_title'         => $path,
			'stat_text'          => __( 'Initial lock is still being created.', 'wp-simple-firewall' ),
			'meta_text'          => $path,
			'show_meta_in_tile'  => false,
			'body_notice'        => __( 'Shield is still creating the first lock for this file. Check back in about a minute for the full lock details.', 'wp-simple-firewall' ),
			'body_notice_variant' => 'info',
		] );
	}

	protected function buildDetailRow(
		string $title,
		string $description,
		string $status,
		?int $countBadge = null,
		?string $badgeStatus = null,
		array $actions = [],
		?string $statusIcon = null,
		?string $statusLabel = null,
		?string $sectionLabel = null
	) :array {
		return [
			'title'        => $title,
			'description'  => $description,
			'status'       => $status,
			'status_icon'  => $statusIcon,
			'status_label' => $statusLabel,
			'count_badge'  => $countBadge,
			'badge_status' => $badgeStatus,
			'expandable'   => false,
			'expand_target' => '',
			'expand_cta_label' => '',
			'expand_accessible_label' => '',
			'expand_title' => '',
			'expansion'    => [],
			'explanations' => [],
			'show_gear'    => false,
			'actions'      => $actions,
			'attributes'   => [],
			'section_label' => $sectionLabel ?? '',
		];
	}

	/**
	 * @param array{title:string,expandable:bool,expand_target:string,expand_accessible_label:string,expansion:array<string,mixed>} $row
	 * @param DetailExpansion $expansion
	 * @return array<string,mixed>
	 */
	protected function attachExpansionToDetailRow( array $row, array $expansion ) :array {
		if ( $expansion[ 'id' ] === '' ) {
			throw new \LogicException( 'Expandable scan result rows require a non-empty expansion target.' );
		}

		$row[ 'expandable' ] = true;
		$row[ 'expand_target' ] = $expansion[ 'id' ];
		$row[ 'expand_accessible_label' ] = $this->buildExpandAccessibleLabel(
			CommonDisplayStrings::get( 'details_label' ),
			$row[ 'title' ]
		);
		$row[ 'expansion' ] = $expansion;
		return $row;
	}

	/**
	 * @param array<string,mixed> $table
	 * @return DetailExpansion
	 */
	protected function buildDetailExpansion( string $id, string $type, string $status, array $table ) :array {
		return [
			'id'     => $id,
			'type'   => $type,
			'status' => $status,
			'table'  => $table,
		];
	}

	private function buildExpandAccessibleLabel( string $label, string $rowTitle ) :string {
		$label = \trim( $label );
		$rowTitle = \trim( $rowTitle );

		$accessibleLabel = $label !== '' && $rowTitle !== ''
			? \sprintf( __( '%1$s: %2$s', 'wp-simple-firewall' ), $label, $rowTitle )
			: ( $label !== '' ? $label : $rowTitle );
		if ( $accessibleLabel === '' ) {
			throw new \LogicException( 'Expandable scan result rows require a non-empty accessible label.' );
		}

		return $accessibleLabel;
	}

	/**
	 * @param array<string,mixed> $card
	 * @return QueueAssetCard|QueueFileLockerCard
	 */
	private function normalizeQueueAssetCard( array $card ) :array {
		$card = \array_merge( [
			'key'               => '',
			'panel_id'          => '',
			'panel_target'      => '',
			'expand_target'     => '',
			'status'            => 'good',
			'icon_class'        => '',
			'title'             => '',
			'rail_title'        => '',
			'stat_text'         => '',
			'meta_text'         => '',
			'show_meta_in_tile' => true,
			'count_badge'       => null,
			'body_notice'       => '',
			'body_notice_variant' => '',
			'panel_data'        => [],
			'actions'           => [],
			'table'             => [],
			'render_action'     => [],
		], $card );

		$renderAction = \is_array( $card[ 'render_action' ] ?? null )
			? $card[ 'render_action' ]
			: [];
		$panelData = [
			'actions-queue-asset-panel-loaded' => empty( $renderAction ) ? '1' : '0',
			'actions-queue-asset-panel-lazy'   => empty( $renderAction ) ? '0' : '1',
		];
		if ( !empty( $renderAction ) ) {
			$panelData[ 'actions-queue-asset-render-action' ] = OperatorChromeContract::encodeJson( $renderAction );
		}

		$card[ 'panel_data' ] = $panelData;
		$card[ 'actions' ] = \array_values( \array_map(
			fn( array $action ) :array => $this->normalizeQueueAssetAction( $action ),
			\is_array( $card[ 'actions' ] ?? null ) ? $card[ 'actions' ] : []
		) );
		unset( $card[ 'render_action' ] );

		return $card;
	}

	/**
	 * @param array<string,mixed> $action
	 * @return QueueAssetAction
	 */
	private function normalizeQueueAssetAction( array $action ) :array {
		$type = \trim( (string)( $action[ 'type' ] ?? '' ) );
		$iconClass = \trim( (string)( $action[ 'icon' ] ?? '' ) );
		if ( $iconClass === '' ) {
			$iconClass = $type === 'update'
				? 'bi bi-arrow-up-circle-fill'
				: ( $type === 'deactivate' ? 'bi bi-power' : 'bi bi-arrow-right-circle-fill' );
		}
		$isAction = (bool)( $action[ 'is_action' ] ?? false );

		return [
			'type'         => $type,
			'label'        => (string)( $action[ 'label' ] ?? '' ),
			'href'         => $isAction ? '' : (string)( $action[ 'href' ] ?? '' ),
			'is_action'    => $isAction,
			'icon_class'   => $iconClass,
			'tooltip_attr' => \trim( (string)( $action[ 'tooltip' ] ?? '' ) ),
			'attributes'   => \is_array( $action[ 'attributes' ] ?? null ) ? $action[ 'attributes' ] : [],
		];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildActionsForHref(
		string $label,
		string $href,
		string $type = 'navigate',
		string $target = ''
	) :array {
		if ( $label === '' || $href === '' ) {
			return [];
		}

		$attributes = [];
		if ( $target !== '' ) {
			$attributes[ 'target' ] = $target;
		}

		return [
			[
				'type'      => $type,
				'label'     => $label,
				'href'      => $href,
				'is_action' => false,
				'icon'       => $type === 'update'
					? 'bi bi-arrow-up-circle-fill'
					: 'bi bi-arrow-right-circle-fill',
				'attributes' => $attributes,
			],
		];
	}

	private function describeFileLockerRecord( object $lock ) :string {
		return empty( $lock->hash_current )
			? __( 'Locked file is missing.', 'wp-simple-firewall' )
			: __( 'File has been modified since the lock was created.', 'wp-simple-firewall' );
	}

	private function getRailTabAvailabilityBuilder() :ScansResultsRailTabAvailability {
		if ( $this->cachedRailTabAvailability === null ) {
			$this->cachedRailTabAvailability = new ScansResultsRailTabAvailability();
		}

		return $this->cachedRailTabAvailability;
	}

	protected function buildActionsQueueAssetCardsBuilder() :ActionsQueueScanAssetCardsBuilder {
		return new ActionsQueueScanAssetCardsBuilder();
	}

	protected function buildScanResultsTableBuilder() :ActionsQueueScanResultsTableBuilder {
		return new ActionsQueueScanResultsTableBuilder();
	}

	private function queueScanResultsOptions() :ScanResultsDisplayOptions {
		return new ScanResultsDisplayOptions();
	}
}
