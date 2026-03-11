<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\Investigation\InvestigationTableContract
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Malware,
	Plugins,
	Themes,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\AttentionItemsProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\{
	RetrieveBase,
	RetrieveItems
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

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
					'wordpress'  => (string)( $wordpressPayload[ 'render_output' ] ?? '' ),
					'plugins'    => (string)( $pluginsPayload[ 'render_output' ] ?? '' ),
					'themes'     => (string)( $themesPayload[ 'render_output' ] ?? '' ),
					'malware'    => (string)( $malwarePayload[ 'render_output' ] ?? '' ),
					'filelocker' => (string)( $fileLockerPayload[ 'render_output' ] ?? '' ),
				],
			],
		];
	}

	protected function buildSummaryRows() :array {
		return ( new AttentionItemsProvider() )->buildScanItems();
	}

	protected function buildAssessmentRows() :array {
		return ( new ActionsQueueLandingAssessmentBuilder() )->build()[ 'scans' ] ?? [];
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

	protected function isMalwareRailTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isEnabledMalwareScanPHP();
	}

	/**
	 * @return array{
	 *   is_available:bool,
	 *   show_in_actions_queue:bool,
	 *   disabled_message:string,
	 *   disabled_status:string
	 * }
	 */
	protected function getRailTabAvailability( string $tabKey ) :array {
		return $this->getRailTabAvailabilityBuilder()->build( $tabKey );
	}

	/**
	 * @param array<int,array{key:string,label:string,count:int,is_shown?:bool}> $definitions
	 * @return list<array<string,mixed>>
	 */
	protected function buildTabs( array $definitions ) :array {
		$tabs = [];
		foreach ( $definitions as $definition ) {
			if ( !( $definition[ 'is_shown' ] ?? true ) ) {
				continue;
			}

			$paneId = 'h-tabs-'.$definition[ 'key' ];
			$baseTab = [
				'key'       => $definition[ 'key' ],
				'pane_id'   => $paneId,
				'nav_id'    => $paneId.'-tab',
				'label'     => $definition[ 'label' ],
				'count'     => \array_key_exists( 'count', $definition )
					? ( $definition[ 'count' ] === null ? null : (int)$definition[ 'count' ] )
					: null,
				'is_active' => empty( $tabs ),
				'target'    => '#'.$paneId,
				'controls'  => $paneId,
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
			'vulnerabilities' => (int)( $vulnerabilities[ 'count' ] ?? 0 ),
			'malware'         => $this->extractSectionCount( $malwarePayload ),
			'file_locker'     => $this->extractSectionCount( $fileLockerPayload ),
		];

		foreach ( $this->getOrderedRailTabKeys() as $key ) {
			$tabMeta = $this->getRailTabMeta( $key );
			$definition = [
				'key'   => $key,
				'label' => $tabMeta[ 'label' ],
				'count' => $key === 'summary' ? \count( $summaryRows ) : ( $legacyCounts[ $key ] ?? 0 ),
			];

			if ( $key !== 'summary' ) {
				if ( $key === 'wordpress' ) {
					$definition[ 'is_shown' ] = $this->isWordpressTabEnabled();
				}
				elseif ( \in_array( $key, [ 'plugins', 'themes', 'vulnerabilities' ], true ) ) {
					$definition[ 'is_shown' ] = ( $legacyCounts[ $key ] ?? 0 ) > 0;
				}
				else {
					$definition[ 'is_shown' ] = true;
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
			foreach ( $this->getRailTabMeta( $tabKey )[ 'summary_keys' ] ?? [] as $summaryKey ) {
				$targets[ $summaryKey ] = $tabKey;
			}
		}
		return $targets;
	}

	/**
	 * @return list<string>
	 */
	protected function getOrderedRailTabKeys( bool $includeSummary = true ) :array {
		$keys = [ 'wordpress', 'plugins', 'themes', 'vulnerabilities', 'malware', 'file_locker' ];
		if ( $includeSummary ) {
			\array_unshift( $keys, 'summary' );
		}
		return $keys;
	}

	/**
	 * Keep the scan tab definitions local to this slice and continue converging here.
	 * If this is revisited, prefer shrinking this class by splitting the tab resolution
	 * into smaller private builders and, when possible, deleting the legacy tabs path
	 * entirely. Avoid introducing a generic shared rail builder or a second parallel
	 * definition path just to make the structure look cleaner.
	 *
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
			'count'      => \count( $summaryRows ),
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
		return \count( \array_filter( $items, static fn( array $item ) :bool => ( $item[ 'status' ] ?? '' ) !== 'good' ) );
	}

	/**
	 * @return array{label:string,icon_class:string,summary_keys:list<string>}
	 */
	protected function getRailTabMeta( string $key ) :array {
		$meta = [
			'summary' => [
				'label'        => __( 'Summary', 'wp-simple-firewall' ),
				'icon_class'   => 'bi bi-clipboard2-pulse-fill',
				'summary_keys' => [],
			],
			'wordpress' => [
				'label'        => __( 'WordPress', 'wp-simple-firewall' ),
				'icon_class'   => 'bi bi-wordpress',
				'summary_keys' => [ 'wp_files' ],
			],
			'plugins' => [
				'label'        => __( 'Plugins', 'wp-simple-firewall' ),
				'icon_class'   => 'bi bi-plug-fill',
				'summary_keys' => [ 'plugin_files' ],
			],
			'themes' => [
				'label'        => __( 'Themes', 'wp-simple-firewall' ),
				'icon_class'   => 'bi bi-palette-fill',
				'summary_keys' => [ 'theme_files' ],
			],
			'vulnerabilities' => [
				'label'        => __( 'Vulnerabilities', 'wp-simple-firewall' ),
				'icon_class'   => 'bi bi-shield-exclamation',
				'summary_keys' => [ 'vulnerable_assets', 'abandoned' ],
			],
			'malware' => [
				'label'        => __( 'Malware', 'wp-simple-firewall' ),
				'icon_class'   => 'bi bi-bug-fill',
				'summary_keys' => [ 'malware' ],
			],
			'file_locker' => [
				'label'        => __( 'File Locker', 'wp-simple-firewall' ),
				'icon_class'   => 'bi bi-file-lock2-fill',
				'summary_keys' => [],
			],
		];
		return $meta[ $key ] ?? [
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
				static fn( array $tab ) :bool => ( $tab[ 'key' ] ?? '' ) !== 'summary'
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
						'status'                 => $tab[ 'status' ] ?? 'good',
						'count'                  => \array_key_exists( 'count', $tab ) ? $tab[ 'count' ] : null,
						'nav_id'                 => $tab[ 'nav_id' ],
						'target'                 => $tab[ 'target' ],
						'controls'               => $tab[ 'controls' ],
						'is_active'              => (bool)( $tab[ 'is_active' ] ?? false ),
						'show_count_placeholder' => (bool)( $tab[ 'show_count_placeholder' ] ?? false ),
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
				$severity = StatusPriority::normalize( (string)( $item[ 'severity' ] ?? 'warning' ), 'warning' );
				$itemKey = (string)( $item[ 'key' ] ?? '' );
				$railTab = $summaryRailTargets[ $itemKey ] ?? '';
				$row = $this->buildDetailRow(
					(string)( $item[ 'label' ] ?? '' ),
					(string)( $item[ 'text' ] ?? '' ),
					$severity,
					(int)( $item[ 'count' ] ?? 0 ),
					$severity
				);
				if ( $railTab !== '' ) {
					$row[ 'attributes' ] = $this->buildRailSwitchRowAttributes( $railTab );
				}
				else {
					$row[ 'actions' ] = $this->buildActionsForHref(
						(string)( $item[ 'action' ] ?? '' ),
						(string)( $item[ 'href' ] ?? '' )
					);
				}
				$row[ 'section_label' ] = __( 'Needs attention', 'wp-simple-firewall' );
				return $row;
			}, $summaryRows ) );

			$goodAssessments = \array_filter(
				$assessmentRows,
				static fn( array $item ) :bool => ( $item[ 'status' ] ?? '' ) === 'good'
			);
			foreach ( $goodAssessments as $item ) {
				$row = $this->buildDetailRow(
					(string)( $item[ 'label' ] ?? '' ),
					(string)( $item[ 'description' ] ?? '' ),
					'good',
					null,
					null,
					[],
					(string)( $item[ 'status_icon_class' ] ?? '' ),
					(string)( $item[ 'status_label' ] ?? '' )
				);
				$row[ 'section_label' ] = __( 'All clear', 'wp-simple-firewall' );
				$items[] = $row;
			}

			return $items;
		}

		return \array_values( \array_map( fn( array $item ) :array => $this->buildDetailRow(
			(string)( $item[ 'label' ] ?? '' ),
			(string)( $item[ 'description' ] ?? '' ),
			StatusPriority::normalize( (string)( $item[ 'status' ] ?? 'good' ), 'good' ),
			null,
			null,
			[],
			(string)( $item[ 'status_icon_class' ] ?? '' ),
			(string)( $item[ 'status_label' ] ?? '' )
		), $assessmentRows ) );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildPluginThemeRailItemsDirect( string $assetType ) :array {
		$results = ( new RetrieveItems() )
			->setScanController( self::con()->comps->scans->AFS() )
			->addWheres( [
				\sprintf(
					"%s.`meta_key`='%s'",
					RetrieveBase::ABBR_RESULTITEMMETA,
					$assetType === 'plugin' ? 'is_in_plugin' : 'is_in_theme'
				),
			] )
			->retrieveForResultsTables();

		$groupedBySlug = [];
		foreach ( $results->getItems() as $item ) {
			$slug = (string)$item->ptg_slug;
			if ( $slug === '' ) {
				continue;
			}
			$groupedBySlug[ $slug ][] = $item;
		}

		$issueItems = [];
		$tableBuilder = new InvestigationFileStatusTableContractBuilder();

		foreach ( $groupedBySlug as $slug => $items ) {
			if ( $assetType === 'plugin' ) {
				$asset = Services::WpPlugins()->getPluginAsVo( $slug, true );
				if ( !$asset instanceof WpPluginVo ) {
					continue;
				}
				$assetName = (string)$asset->Title;
				$subjectType = InvestigationTableContract::SUBJECT_TYPE_PLUGIN;
				$subjectId = (string)$asset->file;
			}
			else {
				$asset = Services::WpThemes()->getThemeAsVo( $slug, true );
				if ( !$asset instanceof WpThemeVo ) {
					continue;
				}
				$assetName = (string)$asset->Name;
				$subjectType = InvestigationTableContract::SUBJECT_TYPE_THEME;
				$subjectId = (string)$asset->stylesheet;
			}

			$fileCount = \count( $items );
			$expandTarget = 'scan-files-'.$assetType.'-'.\sanitize_key( $slug );

			$row = $this->buildDetailRow(
				$assetName,
				\sprintf(
					_n( '%s file needs review', '%s files need review', $fileCount, 'wp-simple-firewall' ),
					$fileCount
				),
				'warning',
				$fileCount,
				'warning',
				$this->buildAssetActions( $asset, $assetType )
			);
			$row[ 'expandable' ] = true;
			$row[ 'expand_target' ] = $expandTarget;
			$row[ 'expansion_table' ] = $tableBuilder->build( $subjectType, $subjectId );
			$row[ 'section_label' ] = __( 'Needs attention', 'wp-simple-firewall' );
			$issueItems[] = $row;
		}

		\usort( $issueItems, static function ( array $a, array $b ) :int {
			$countCmp = ( $b[ 'count_badge' ] ?? 0 ) <=> ( $a[ 'count_badge' ] ?? 0 );
			return $countCmp !== 0
				? $countCmp
				: \strcmp( (string)( $a[ 'title' ] ?? '' ), (string)( $b[ 'title' ] ?? '' ) );
		} );

		return $issueItems;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return list<array<string,mixed>>
	 */
	protected function buildAssetActions( $asset, string $assetType ) :array {
		$actions = [];
		if ( $asset->hasUpdate() ) {
			$actions[] = [
				'type'    => 'update',
				'label'   => __( 'Update', 'wp-simple-firewall' ),
				'href'    => \admin_url( 'update-core.php' ),
				'icon'    => 'bi bi-arrow-up-circle-fill',
				'tooltip' => __( 'Go to updates', 'wp-simple-firewall' ),
			];
		}
		if ( $assetType === 'plugin' ) {
			$actions[] = [
				'type'    => 'deactivate',
				'label'   => __( 'Deactivate', 'wp-simple-firewall' ),
				'href'    => \admin_url( 'plugins.php' ),
				'icon'    => 'bi bi-power',
				'tooltip' => __( 'Go to plugins', 'wp-simple-firewall' ),
			];
		}
		return $actions;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildVulnerabilitiesRailItems( array $vulnerabilities ) :array {
		$items = [];
		foreach ( \is_array( $vulnerabilities[ 'sections' ] ?? null ) ? $vulnerabilities[ 'sections' ] : [] as $section ) {
			$sectionLabel = (string)( $section[ 'label' ] ?? '' );
			foreach ( \is_array( $section[ 'items' ] ?? null ) ? $section[ 'items' ] : [] as $item ) {
				$severity = StatusPriority::normalize( (string)( $item[ 'severity' ] ?? 'warning' ), 'warning' );
				$actions = \is_array( $item[ 'actions' ] ?? null ) ? \array_values( $item[ 'actions' ] ) : [];
				if ( empty( $actions ) ) {
					$cta = \is_array( $item[ 'cta' ] ?? null ) ? $item[ 'cta' ] : [];
					$actions = $this->buildActionsForHref(
						(string)( $cta[ 'label' ] ?? '' ),
						(string)( $cta[ 'href' ] ?? '' ),
						(string)( $cta[ 'type' ] ?? 'navigate' )
					);
				}
				$items[] = $this->buildDetailRow(
					(string)( $item[ 'label' ] ?? '' ),
					(string)( $item[ 'description' ] ?? '' ),
					$severity,
					isset( $item[ 'count' ] ) ? (int)$item[ 'count' ] : null,
					$severity,
					$actions,
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
		foreach ( \is_array( $vulnerabilities[ 'sections' ] ?? null ) ? $vulnerabilities[ 'sections' ] : [] as $section ) {
			foreach ( \is_array( $section[ 'items' ] ?? null ) ? $section[ 'items' ] : [] as $item ) {
				$statuses[] = StatusPriority::normalize( (string)( $item[ 'severity' ] ?? 'warning' ), 'warning' );
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
		$flags = \is_array( $payload[ 'render_data' ][ 'flags' ] ?? null ) ? $payload[ 'render_data' ][ 'flags' ] : [];
		if ( empty( $flags[ 'is_enabled' ] ) || !empty( $flags[ 'is_restricted' ] ) ) {
			return [];
		}

		$items = [];

		foreach ( $this->getProblemFileLocks() as $lock ) {
			$row = $this->buildDetailRow(
				(string)( $lock->path ?? '' ),
				$this->describeFileLockerRecord( $lock ),
				'warning'
			);
			$row[ 'section_label' ] = __( 'Needs attention', 'wp-simple-firewall' );
			$items[] = $row;
		}

		foreach ( $this->getGoodFileLocks() as $lock ) {
			$row = $this->buildDetailRow(
				(string)( $lock->path ?? '' ),
				__( 'File integrity verified.', 'wp-simple-firewall' ),
				'good'
			);
			$row[ 'section_label' ] = __( 'All clear', 'wp-simple-firewall' );
			$items[] = $row;
		}

		return $items;
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
	 *   disabled_status:string
	 * }
	 */
	public function buildRailPaneData( string $tabKey, array $vulnerabilities = [] ) :array {
		$tabKey = \strtolower( \trim( $tabKey ) );
		$meta = $this->getRailTabMeta( $tabKey );
		$availability = $this->getRailTabAvailability( $tabKey );
		$items = [];
		$count = 0;
		$status = 'good';
		$isDisabled = false;
		$disabledMessage = '';
		$disabledStatus = (string)( $availability[ 'disabled_status' ] ?? 'neutral' );

		if ( \in_array( $tabKey, [ 'plugins', 'themes', 'vulnerabilities', 'malware' ], true )
			 && empty( $availability[ 'is_available' ] ) ) {
			$isDisabled = true;
			$status = $disabledStatus;
			$disabledMessage = (string)( $availability[ 'disabled_message' ] ?? '' );
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
					$items = $this->buildVulnerabilitiesRailItems( $vulnerabilities );
					$count = (int)( $vulnerabilities[ 'count' ] ?? 0 );
					$status = $this->buildVulnerabilitiesRailStatus( $vulnerabilities );
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
		];
	}

	private function actionPayload( string $actionClass ) :array {
		return self::con()->action_router->action( $actionClass )->payload();
	}

	/**
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction> $actionClass
	 */
	protected function buildAjaxRenderActionData( string $actionClass ) :array {
		return ActionData::BuildAjaxRender( $actionClass );
	}

	private function extractSectionCount( array $payload ) :int {
		$renderData = \is_array( $payload[ 'render_data' ] ?? null ) ? $payload[ 'render_data' ] : [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];

		if ( isset( $vars[ 'count_items' ] ) ) {
			return (int)$vars[ 'count_items' ];
		}
		if ( isset( $renderData[ 'count' ] ) ) {
			return (int)$renderData[ 'count' ];
		}

		return (int)( $vars[ 'file_locks' ][ 'count_items' ] ?? 0 );
	}

	private function extractPayloadRows( array $payload, string $key ) :array {
		$vars = \is_array( $payload[ 'render_data' ][ 'vars' ] ?? null ) ? $payload[ 'render_data' ][ 'vars' ] : [];
		return \is_array( $vars[ $key ] ?? null ) ? \array_values( $vars[ $key ] ) : [];
	}

	private function buildDetailRow(
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
		$row = [
			'title'        => $title,
			'description'  => $description,
			'status'       => $status,
			'status_icon'  => $statusIcon,
			'status_label' => $statusLabel,
			'count_badge'  => $countBadge,
			'badge_status' => $badgeStatus,
			'expandable'   => false,
			'explanations' => [],
			'show_gear'    => false,
			'actions'      => $actions,
			'attributes'   => [],
		];
		if ( $sectionLabel !== null ) {
			$row[ 'section_label' ] = $sectionLabel;
		}
		return $row;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function buildActionsForHref( string $label, string $href, string $type = 'navigate' ) :array {
		if ( $label === '' || $href === '' ) {
			return [];
		}

		return [
			[
				'type'    => $type,
				'label'   => $label,
				'href'    => $href,
				'icon'    => $type === 'update'
					? 'bi bi-arrow-up-circle-fill'
					: 'bi bi-arrow-right-circle-fill',
				'attributes' => [],
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
}
