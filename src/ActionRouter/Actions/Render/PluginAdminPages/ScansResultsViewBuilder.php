<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Malware,
	Plugins,
	Themes,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansFileLockerDiff;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 * @phpstan-type QueueAssetAction array{
 *   type:string,
 *   label:string,
 *   href:string,
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
 *   count_badge:int,
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
 *   panel_data:QueueAssetPanelData,
 *   actions:list<QueueAssetAction>,
 *   table:array<string,mixed>
 * }
 * @phpstan-type SummaryRow array{
 *   key:string,
 *   label:string,
 *   text:string,
 *   severity:string,
 *   count:int,
 *   action:string,
 *   href:string
 * }
 * @phpstan-type AssessmentRow array{
 *   key:string,
 *   label:string,
 *   status:string,
 *   description:string,
 *   status_icon_class:string,
 *   status_label:string
 * }
 * @phpstan-type DisabledPaneAction array{
 *   type:string,
 *   label:string,
 *   href:string,
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
 * @phpstan-type RailTabAvailability array{
 *   is_available:bool,
 *   show_in_actions_queue:bool,
 *   show_in_fix_now:bool,
 *   disabled_reason:''|'not_enabled'|'upgrade_required',
 *   disabled_message:string,
 *   disabled_status:string,
 *   disabled_actions:list<DisabledPaneAction>
 * }
 * @phpstan-type SectionPayload array{
 *   render_output:string,
 *   render_data:array{
 *     flags?:array<string,mixed>,
 *     vars:array<string,mixed>,
 *     count?:int
 *   }
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

	private ?array $cachedAfsItems = null;
	private ?ScansResultsRailTabAvailability $cachedRailTabAvailability = null;

	public function build() :array {
		$summaryRows = $this->buildSummaryRows();
		$assessmentRows = $this->buildAssessmentRows();
		$wordpressPayload = $this->buildWordpressSectionPayload();
		$pluginsPayload = $this->buildPluginsSectionPayload();
		$themesPayload = $this->buildThemesSectionPayload();
		$malwarePayload = $this->buildMalwareSectionPayload();
		$fileLockerPayload = $this->buildFileLockerSectionPayload();
		$vulnerabilities = $this->buildVulnerabilities();
		$legacyTabs = $this->buildLegacyTabs(
			$summaryRows,
			$wordpressPayload,
			$pluginsPayload,
			$themesPayload,
			$malwarePayload,
			$fileLockerPayload,
			$vulnerabilities
		);
		$railTabs = $this->buildRailTabs(
			$summaryRows,
			$assessmentRows,
			$wordpressPayload,
			$pluginsPayload,
			$themesPayload,
			$malwarePayload,
			$fileLockerPayload,
			$vulnerabilities
		);

		return [
			'vars'    => [
				'tabs'            => $legacyTabs,
				'rail'            => $this->buildRailContract( $railTabs ),
				'rail_tabs'       => $railTabs,
				'summary_rows'    => $summaryRows,
				'assessment_rows' => $assessmentRows,
				'vulnerabilities' => $vulnerabilities,
			],
			'content' => [
				'section' => [
					'wordpress'  => $wordpressPayload[ 'render_output' ],
					'plugins'    => $pluginsPayload[ 'render_output' ],
					'themes'     => $themesPayload[ 'render_output' ],
					'malware'    => $malwarePayload[ 'render_output' ],
					'filelocker' => $fileLockerPayload[ 'render_output' ],
				],
			],
		];
	}

	/**
	 * @return list<SummaryRow>
	 */
	protected function buildSummaryRows() :array {
		$attentionQuery = $this->buildAttentionQuery();
		return \array_values( \array_map(
			static function ( array $row ) :array {
				return [
					'key'      => $row[ 'key' ],
					'label'    => $row[ 'label' ],
					'text'     => $row[ 'description' ],
					'severity' => $row[ 'severity' ],
					'count'    => $row[ 'count' ],
					'action'   => $row[ 'action' ],
					'href'     => $row[ 'href' ],
				];
			},
			$attentionQuery[ 'groups' ][ 'scans' ][ 'items' ]
		) );
	}

	/**
	 * @return list<AssessmentRow>
	 */
	protected function buildAssessmentRows() :array {
		return ( new ActionsQueueLandingAssessmentBuilder() )->buildForZone( 'scans' );
	}

	protected function buildWordpressSectionPayload() :array {
		return $this->actionPayload( Wordpress::class );
	}

	protected function buildPluginsSectionPayload() :array {
		return $this->actionPayload( Plugins::class );
	}

	protected function buildThemesSectionPayload() :array {
		return $this->actionPayload( Themes::class );
	}

	protected function buildMalwareSectionPayload() :array {
		return $this->actionPayload( Malware::class );
	}

	protected function buildFileLockerSectionPayload() :array {
		return $this->actionPayload( FileLocker::class );
	}

	/**
	 * @return VulnerabilitiesPayload
	 */
	protected function buildVulnerabilities() :array {
		return ( new ScansVulnerabilitiesBuilder() )->build();
	}

	protected function isWordpressTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledWpCore();
	}

	protected function isPluginsRailTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledPlugins();
	}

	protected function isThemesRailTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledThemes();
	}

	protected function isVulnerabilitiesRailTabEnabled() :bool {
		$scansCon = self::con()->comps->scans;
		return $scansCon->WPV()->isEnabled() || $scansCon->APC()->isEnabled();
	}

	protected function isAbandonedRailTabEnabled() :bool {
		return self::con()->comps->scans->APC()->isEnabled();
	}

	protected function isMalwareRailTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isEnabledMalwareScanPHP();
	}

	/**
	 * @return RailTabAvailability
	 */
	protected function getRailTabAvailability( string $tabKey ) :array {
		return $this->getRailTabAvailabilityBuilder()->build( $tabKey );
	}

	/**
	 * @param array<int,array{
	 *   key:string,
	 *   label:string,
	 *   count:int|null,
	 *   is_shown:bool,
	 *   icon_class?:string,
	 *   status?:string,
	 *   items?:list<array<string,mixed>>,
	 *   is_loaded?:bool,
	 *   is_disabled?:bool,
	 *   disabled_message?:string,
	 *   disabled_status?:string,
	 *   render_action?:array<string,mixed>,
	 *   show_count_placeholder?:bool
	 * }> $definitions
	 * @return list<array<string,mixed>>
	 */
	protected function buildTabs( array $definitions ) :array {
		$tabs = [];
		foreach ( $definitions as $definition ) {
			if ( !$definition[ 'is_shown' ] ) {
				continue;
			}

			$paneId = 'h-tabs-'.$definition[ 'key' ];
			$baseTab = [
				'key'       => $definition[ 'key' ],
				'pane_id'   => $paneId,
				'nav_id'    => $paneId.'-tab',
				'label'     => $definition[ 'label' ],
				'count'     => $definition[ 'count' ],
				'is_active' => empty( $tabs ),
				'target'    => '#'.$paneId,
				'controls'  => $paneId,
				'icon_class' => '',
				'status'    => 'good',
				'items'     => [],
				'is_loaded' => true,
				'is_disabled' => false,
				'disabled_message' => '',
				'disabled_status' => 'neutral',
				'render_action' => [],
				'show_count_placeholder' => false,
			];
			unset( $definition[ 'key' ], $definition[ 'label' ], $definition[ 'count' ], $definition[ 'is_shown' ] );
			$tabs[] = \array_merge( $baseTab, $definition );
		}
		return $tabs;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildLegacyTabs(
		array $summaryRows,
		array $wordpressPayload,
		array $pluginsPayload,
		array $themesPayload,
		array $malwarePayload,
		array $fileLockerPayload,
		array $vulnerabilities
	) :array {
		$definitions = [];
		$legacyCounts = [
			'wordpress'       => $this->extractSectionCount( $wordpressPayload ),
			'plugins'         => $this->extractSectionCount( $pluginsPayload ),
			'themes'          => $this->extractSectionCount( $themesPayload ),
			'vulnerabilities' => $vulnerabilities[ 'count' ],
			'malware'         => $this->extractSectionCount( $malwarePayload ),
			'file_locker'     => $this->extractSectionCount( $fileLockerPayload ),
		];

		foreach ( $this->getOrderedRailTabKeys() as $key ) {
			$tabMeta = $this->getRailTabMeta( $key );
			$definition = [
				'key'      => $key,
				'label'    => $tabMeta[ 'label' ],
				'count'    => $key === 'summary' ? $this->countSummaryRowIssues( $summaryRows ) : $legacyCounts[ $key ],
				'is_shown' => true,
			];

			if ( $key !== 'summary' ) {
				if ( $key === 'wordpress' ) {
					$definition[ 'is_shown' ] = $this->isWordpressTabEnabled();
				}
				elseif ( \in_array( $key, [ 'plugins', 'themes', 'vulnerabilities' ], true ) ) {
					$definition[ 'is_shown' ] = $legacyCounts[ $key ] > 0;
				}
			}

			$definitions[] = $definition;
		}

		return $this->buildTabs( $definitions );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildRailTabs(
		array $summaryRows,
		array $assessmentRows,
		array $wordpressPayload,
		array $pluginsPayload,
		array $themesPayload,
		array $malwarePayload,
		array $fileLockerPayload,
		array $vulnerabilities
	) :array {
		return $this->buildTabs(
			$this->buildResolvedRailTabDefinitions(
				$summaryRows,
				$assessmentRows,
				$vulnerabilities,
				$fileLockerPayload
			)
		);
	}

	/**
	 * @param list<array<string,mixed>> $definitions
	 * @return array<string,string>
	 */
	protected function buildSummaryRailTargets( array $definitions ) :array {
		$targets = [];
		foreach ( \array_column( $definitions, 'key' ) as $tabKey ) {
			foreach ( $this->getRailTabMeta( $tabKey )[ 'summary_keys' ] as $summaryKey ) {
				$targets[ $summaryKey ] = $tabKey;
			}
		}
		return $targets;
	}

	/**
	 * @return list<string>
	 */
	protected function getOrderedRailTabKeys( bool $includeSummary = true ) :array {
		$keys = \array_keys( $this->getScanTabDefinitions() );
		if ( $includeSummary ) {
			\array_unshift( $keys, 'summary' );
		}
		return $keys;
	}

	/**
	 * @param array{count?:int,status?:string,sections?:array<string,mixed>} $vulnerabilities
	 * @param array<string,mixed> $fileLockerPayload
	 * @return list<array<string,mixed>>
	 */
	private function buildResolvedRailTabDefinitions(
		array $summaryRows,
		array $assessmentRows,
		array $vulnerabilities,
		array $fileLockerPayload = []
	) :array {
		$definitions = [];

		foreach ( $this->getOrderedRailTabKeys( false ) as $tabKey ) {
			$definition = $this->buildResolvedRailTabDefinition(
				$tabKey,
				$vulnerabilities,
				$fileLockerPayload
			);
			if ( $definition !== null ) {
				$definitions[] = $definition;
			}
		}

		$summaryMeta = $this->getRailTabMeta( 'summary' );
		$summaryDefinition = [
			'key'        => 'summary',
			'label'      => $summaryMeta[ 'label' ],
			'count'      => $this->countSummaryRowIssues( $summaryRows ),
			'is_shown'   => true,
			'status'     => StatusPriority::highest( \array_column( $definitions, 'status' ), 'good' ),
			'icon_class' => $summaryMeta[ 'icon_class' ],
			'items'      => $this->buildSummaryRailItems(
				$summaryRows,
				$assessmentRows,
				$this->buildSummaryRailTargets( $definitions )
			),
		];
		\array_unshift( $definitions, $summaryDefinition );

		return $definitions;
	}

	/**
	 * @param array{count?:int,status?:string,sections?:array<string,mixed>} $vulnerabilities
	 * @param array<string,mixed> $fileLockerPayload
	 * @return array<string,mixed>|null
	 */
	private function buildResolvedRailTabDefinition(
		string $tabKey,
		array $vulnerabilities,
		array $fileLockerPayload
	) :?array {
		$tabMeta = $this->getRailTabMeta( $tabKey );
		$definition = [
			'key'        => $tabKey,
			'label'      => $tabMeta[ 'label' ],
			'is_shown'   => true,
			'icon_class' => $tabMeta[ 'icon_class' ],
		];

		switch ( $tabKey ) {
			case 'wordpress':
				if ( !$this->isWordpressTabEnabled() ) {
					return null;
				}
				$pane = $this->buildRailPaneData( 'wordpress' );
				return \array_merge( $definition, [
					'count'  => $pane[ 'count_items' ],
					'status' => $pane[ 'status' ],
					'items'  => $pane[ 'items' ],
				] );

			case 'plugins':
				if ( !$this->isPluginsRailTabEnabled() ) {
					return null;
				}
				$pane = $this->buildRailPaneData( 'plugins' );
				return \array_merge( $definition, [
					'count'  => $pane[ 'count_items' ],
					'status' => $pane[ 'status' ],
					'items'  => $pane[ 'items' ],
				] );

			case 'themes':
				if ( !$this->isThemesRailTabEnabled() ) {
					return null;
				}
				$pane = $this->buildRailPaneData( 'themes' );
				return \array_merge( $definition, [
					'count'  => $pane[ 'count_items' ],
					'status' => $pane[ 'status' ],
					'items'  => $pane[ 'items' ],
				] );

			case 'vulnerabilities':
				if ( !$this->isVulnerabilitiesRailTabEnabled() ) {
					return null;
				}
				$pane = $this->buildRailPaneData( 'vulnerabilities', $vulnerabilities );
				return \array_merge( $definition, [
					'count'  => $pane[ 'count_items' ],
					'status' => $pane[ 'status' ],
					'items'  => $pane[ 'items' ],
				] );

			case 'abandoned':
				if ( !$this->isAbandonedRailTabEnabled() ) {
					return null;
				}
				$pane = $this->buildRailPaneData( 'abandoned', $vulnerabilities, 'abandoned' );
				return \array_merge( $definition, [
					'count'  => $pane[ 'count_items' ],
					'status' => $pane[ 'status' ],
					'items'  => $pane[ 'items' ],
				] );

			case 'malware':
				if ( !$this->isMalwareRailTabEnabled() ) {
					return null;
				}
				$pane = $this->buildRailPaneData( 'malware' );
				return \array_merge( $definition, [
					'count'  => $pane[ 'count_items' ],
					'status' => $pane[ 'status' ],
					'items'  => $pane[ 'items' ],
				] );

			case 'file_locker':
				$items = $this->buildFileLockerRailItems( $fileLockerPayload );
				$count = $this->countNonGoodItems( $items );
				return \array_merge( $definition, [
					'count'  => $count,
					'status' => $count > 0 ? 'warning' : 'good',
					'items'  => $items,
				] );
		}

		return null;
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
		return PluginNavs::actionsLandingScanDefinitions();
	}

	protected function getRailTabKeyForSummaryKey( string $summaryKey ) :string {
		$definition = $this->scanDefinitionForSummaryKey( $summaryKey );
		return $definition[ 'slug' ] ?? '';
	}

	/**
	 * @return array{
	 *   slug:string,
	 *   label:string,
	 *   icon:string,
	 *   summary_keys:list<string>
	 * }|null
	 */
	protected function scanDefinitionForSummaryKey( string $summaryKey ) :?array {
		foreach ( $this->getScanTabDefinitions() as $definition ) {
			if ( \in_array( $summaryKey, $definition[ 'summary_keys' ], true ) ) {
				return $definition;
			}
		}

		return null;
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
	 * @param list<array{
	 *   key:string,
	 *   label:string,
	 *   icon_class:string,
	 *   nav_id:string,
	 *   target:string,
	 *   controls:string,
	 *   status?:string,
	 *   count?:int|null,
	 *   is_active?:bool
	 * }> $tabs
	 * @return array{
	 *   id:string,
	 *   accent_status:string,
	 *   items:list<array{
	 *     key:string,
	 *     label:string,
	 *     icon_class:string,
	 *     status:string,
	 *     count:int|null,
	 *     nav_id:string,
	 *     target:string,
	 *     controls:string,
	 *     is_active:bool
	 *   }>
	 * }
	 */
	protected function buildRailContract( array $tabs ) :array {
		$accentStatuses = \array_column(
			\array_filter(
				$tabs,
				static fn( array $tab ) :bool => $tab[ 'key' ] !== 'summary'
			),
			'status'
		);

		return [
			'id'            => 'ScanResultsRailSidebar',
			'accent_status' => StatusPriority::highest( $accentStatuses, 'good' ),
			'items'         => \array_values( \array_map(
				static function ( array $tab ) :array {
					return [
						'key'                    => $tab[ 'key' ],
						'label'                  => $tab[ 'label' ],
						'icon_class'             => $tab[ 'icon_class' ],
						'status'                 => $tab[ 'status' ],
						'count'                  => $tab[ 'count' ],
						'nav_id'                 => $tab[ 'nav_id' ],
						'target'                 => $tab[ 'target' ],
						'controls'               => $tab[ 'controls' ],
						'is_active'              => $tab[ 'is_active' ],
						'show_count_placeholder' => $tab[ 'show_count_placeholder' ],
					];
				},
				$tabs
			) ),
		];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildSummaryRailItems( array $summaryRows, array $assessmentRows, array $summaryRailTargets = [] ) :array {
		if ( !empty( $summaryRows ) ) {
			$items = \array_values( \array_map( function ( array $item ) use ( $summaryRailTargets ) :array {
				$severity = StatusPriority::normalize( $item[ 'severity' ], 'warning' );
				$itemKey = $item[ 'key' ];
				$railTab = $summaryRailTargets[ $itemKey ] ?? '';
				$row = $this->buildDetailRow(
					$item[ 'label' ],
					$item[ 'text' ],
					$severity,
					$item[ 'count' ],
					$severity
				);
				if ( $railTab !== '' ) {
					$row[ 'attributes' ] = $this->buildRailSwitchRowAttributes( $railTab );
				}
				else {
					$row[ 'actions' ] = $this->buildActionsForHref(
						$item[ 'action' ],
						$item[ 'href' ]
					);
				}
				$row[ 'section_label' ] = __( 'Needs attention', 'wp-simple-firewall' );
				return $row;
			}, $summaryRows ) );

			$goodAssessments = \array_filter(
				$assessmentRows,
				static fn( array $item ) :bool => $item[ 'status' ] === 'good'
			);
			foreach ( $goodAssessments as $item ) {
				$row = $this->buildDetailRow(
					$item[ 'label' ],
					$item[ 'description' ],
					'good',
					null,
					null,
					[],
					$item[ 'status_icon_class' ],
					$item[ 'status_label' ]
				);
				$row[ 'section_label' ] = __( 'All clear', 'wp-simple-firewall' );
				$items[] = $row;
			}

			return $items;
		}

		return \array_values( \array_map( fn( array $item ) :array => $this->buildDetailRow(
			$item[ 'label' ],
			$item[ 'description' ],
			StatusPriority::normalize( $item[ 'status' ], 'good' ),
			null,
			null,
			[],
			$item[ 'status_icon_class' ],
			$item[ 'status_label' ]
		), $assessmentRows ) );
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
		return $this->buildActionsQueueAssetCardsBuilder()->buildIssueRecords(
			$assetType,
			$this->queueScanResultsOptions()->normalize( $resultsDisplayOptions )
		);
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
	 * @return list<array<string,mixed>>
	 */
	protected function buildWordpressRailItems() :array {
		$items = [];
		foreach ( $this->getAfsDisplayItems() as $item ) {
			if ( empty( $item->is_in_core ) ) {
				continue;
			}
			$items[] = $this->buildDetailRow(
				(string)$item->path_fragment,
				$this->describeWordpressResultItem( $item ),
				'critical'
			);
		}
		return $items;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildMalwareRailItems() :array {
		$items = [];
		foreach ( $this->getAfsDisplayItems() as $item ) {
			if ( empty( $item->is_mal ) ) {
				continue;
			}
			$items[] = $this->buildDetailRow(
				(string)$item->path_fragment,
				$this->describeMalwareResultItem( $item ),
				'critical'
			);
		}
		return $items;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildFileLockerRailItems( array $payload ) :array {
		/** @var SectionPayload $payload */
		$flags = $payload[ 'render_data' ][ 'flags' ];
		if ( !$flags[ 'is_enabled' ] || $flags[ 'is_restricted' ] ) {
			return [];
		}

		$items = [];
		foreach ( $this->buildFileLockerQueueRecords() as $lock ) {
			$row = $this->buildDetailRow(
				$lock[ 'rail_title' ],
				$lock[ 'stat_text' ],
				$lock[ 'status' ]
			);
			$row[ 'section_label' ] = $lock[ 'status' ] === 'good'
				? __( 'All clear', 'wp-simple-firewall' )
				: __( 'Needs attention', 'wp-simple-firewall' );
			$items[] = $row;
		}

		return $items;
	}

	/**
	 * @return list<QueueFileLockerCard>
	 */
	protected function buildFileLockerQueueRecords() :array {
		$records = [];
		foreach ( $this->getProblemFileLocks() as $lock ) {
			$records[] = $this->buildFileLockerQueueRecord( $lock, 'warning' );
		}
		foreach ( $this->getGoodFileLocks() as $lock ) {
			$records[] = $this->buildFileLockerQueueRecord( $lock, 'good' );
		}
		return $records;
	}

	/**
	 * @return list<object>
	 */
	protected function getAfsDisplayItems() :array {
		if ( $this->cachedAfsItems === null ) {
			try {
				$this->cachedAfsItems = self::con()->comps->scans->AFS()->getResultsForDisplay()->getAllItems();
			}
			catch ( \Throwable $e ) {
				$this->cachedAfsItems = [];
			}
		}
		return $this->cachedAfsItems;
	}

	protected function getProblemFileLocks() :array {
		return ( new LoadFileLocks() )->withProblems();
	}

	protected function getGoodFileLocks() :array {
		return ( new LoadFileLocks() )->withoutProblems();
	}

	protected function isFileLockerEnabled() :bool {
		return self::con()->comps->file_locker->isEnabled();
	}

	protected function isPremiumActive() :bool {
		return self::con()->isPremiumActive();
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
		$availability = $this->normalizedRailPaneAvailability(
			$tabKey,
			$this->getRailTabAvailability( $tabKey ),
			$vulnerabilitySection
		);
		$items = [];
		$count = 0;
		$status = 'good';
		$isDisabled = false;
		$disabledMessage = '';
		$disabledStatus = $availability[ 'disabled_status' ];
		$disabledActions = [];

		if ( !$availability[ 'is_available' ]
			 && \in_array( $tabKey, [ 'wordpress', 'plugins', 'themes', 'vulnerabilities', 'abandoned', 'malware', 'file_locker' ], true ) ) {
			$isDisabled = true;
			$status = $disabledStatus;
			$disabledMessage = $availability[ 'disabled_message' ];
			$disabledActions = $availability[ 'disabled_actions' ] ?? [];
		}

		if ( !$isDisabled ) {
			switch ( $tabKey ) {
				case 'wordpress':
					$items = $this->buildWordpressRailItems();
					$count = $this->countNonGoodItems( $items );
					$status = $count > 0 ? 'critical' : 'good';
					break;

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
					$sectionPayload = $this->vulnerabilityPanePayload( $vulnerabilities, $vulnerabilitySection );
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

				case 'malware':
					$items = $this->buildMalwareRailItems();
					$count = $this->countNonGoodItems( $items );
					$status = $count > 0 ? 'critical' : 'good';
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
	 * @return array{
	 *   is_available:bool,
	 *   show_in_actions_queue:bool,
	 *   disabled_message:string,
	 *   disabled_status:string
	 * }
	 */
	private function normalizedRailPaneAvailability(
		string $tabKey,
		array $availability,
		?string $vulnerabilitySection
	) :array {
		if ( $tabKey === 'vulnerabilities'
			 && $vulnerabilitySection === null
			 && $this->isVulnerabilitiesRailTabEnabled() ) {
			$availability[ 'is_available' ] = true;
			$availability[ 'disabled_message' ] = '';
			$availability[ 'disabled_status' ] = 'neutral';
		}

		return $availability;
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

	private function actionPayload( string $actionClass ) :array {
		return self::con()->action_router->action( $actionClass )->payload();
	}

	/**
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction> $actionClass
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
			'count_badge'        => null,
			'actions'            => [],
			'table'              => [],
			'render_action' => $this->buildAjaxRenderActionData( ScansFileLockerDiff::class, [
				'rid' => $rid,
			] ),
		] );
	}

	private function extractSectionCount( array $payload ) :int {
		/** @var SectionPayload $payload */
		$renderData = $payload[ 'render_data' ];
		$vars = $renderData[ 'vars' ];

		if ( isset( $vars[ 'count_items' ] ) ) {
			return (int)$vars[ 'count_items' ];
		}
		if ( isset( $renderData[ 'count' ] ) ) {
			return (int)$renderData[ 'count' ];
		}

		return (int)$vars[ 'file_locks' ][ 'count_items' ];
	}

	private function extractPayloadRows( array $payload, string $key ) :array {
		/** @var SectionPayload $payload */
		return \array_values( $payload[ 'render_data' ][ 'vars' ][ $key ] );
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
			'expansion'    => [],
			'explanations' => [],
			'show_gear'    => false,
			'actions'      => $actions,
			'attributes'   => [],
			'section_label' => $sectionLabel ?? '',
		];
	}

	/**
	 * @param DetailExpansion $expansion
	 * @return array<string,mixed>
	 */
	protected function attachExpansionToDetailRow( array $row, array $expansion ) :array {
		$row[ 'expandable' ] = true;
		$row[ 'expand_target' ] = $expansion[ 'id' ];
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

		return [
			'type'         => $type,
			'label'        => (string)( $action[ 'label' ] ?? '' ),
			'href'         => (string)( $action[ 'href' ] ?? '' ),
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
				'type'    => $type,
				'label'   => $label,
				'href'    => $href,
				'icon'    => $type === 'update'
					? 'bi bi-arrow-up-circle-fill'
					: 'bi bi-arrow-right-circle-fill',
				'attributes' => $attributes,
			],
		];
	}

	/**
	 * @return array<string,string>
	 */
	private function buildRailSwitchRowAttributes( string $target ) :array {
		return [
			'data-shield-rail-switch' => $target,
			'role'                    => 'button',
			'tabindex'                => '0',
		];
	}

	private function describeWordpressResultItem( object $item ) :string {
		if ( !empty( $item->is_missing ) ) {
			return __( 'File is missing.', 'wp-simple-firewall' );
		}
		if ( !empty( $item->is_checksumfail ) ) {
			return __( 'File has been modified.', 'wp-simple-firewall' );
		}
		if ( !empty( $item->is_unrecognised ) ) {
			return __( 'File is unrecognised.', 'wp-simple-firewall' );
		}
		if ( !empty( $item->is_unidentified ) ) {
			return __( 'File is unidentified.', 'wp-simple-firewall' );
		}

		$statuses = \method_exists( $item, 'getStatusForHuman' ) ? \array_values( $item->getStatusForHuman() ) : [];
		return empty( $statuses )
			? __( 'WordPress core file needs review.', 'wp-simple-firewall' )
			: \implode( ', ', $statuses );
	}

	private function describeMalwareResultItem( object $item ) :string {
		$statuses = \method_exists( $item, 'getStatusForHuman' ) ? \array_values( $item->getStatusForHuman() ) : [];
		if ( !empty( $statuses ) ) {
			return \implode( ', ', $statuses );
		}
		return __( 'Potential malware detected.', 'wp-simple-firewall' );
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

	/**
	 * @param list<SummaryRow> $summaryRows
	 */
	protected function countSummaryRowIssues( array $summaryRows ) :int {
		return (int)\array_sum( \array_column( $summaryRows, 'count' ) );
	}

	/**
	 * @return AttentionQuery
	 */
	protected function buildAttentionQuery() :array {
		return self::con()->comps->site_query->attention();
	}

	protected function buildActionsQueueAssetCardsBuilder() :ActionsQueueScanAssetCardsBuilder {
		return new ActionsQueueScanAssetCardsBuilder();
	}

	private function queueScanResultsOptions() :ActionsQueueScanResultsOptions {
		return new ActionsQueueScanResultsOptions();
	}
}
