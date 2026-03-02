<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record as ScanRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Handler as MeterHandler,
	Meter\MeterOverallConfig
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

class AttentionItemsProvider {

	use PluginControllerConsumer;

	private const KEY_SORT = [
		'malware',
		'vulnerable_assets',
		'wp_files',
		'plugin_files',
		'theme_files',
		'abandoned',
		'wp_updates',
		'wp_plugins_updates',
		'wp_themes_updates',
		'wp_plugins_inactive',
		'wp_themes_inactive',
		'meter_warning',
		'score_generic',
	];
	private const MAINTENANCE_COMPONENT_SLUGS = [
		'wp_updates',
		'wp_plugins_updates',
		'wp_themes_updates',
		'wp_plugins_inactive',
		'wp_themes_inactive',
	];

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function buildQueueItems() :array {
		return \array_map(
			function ( array $item ) :array {
				return [
					'key'         => $item[ 'key' ],
					'zone'        => $item[ 'zone' ],
					'label'       => $item[ 'label' ],
					'count'       => $item[ 'count' ],
					'severity'    => $item[ 'severity' ],
					'description' => $item[ 'text' ],
					'href'        => $item[ 'href' ],
					'action'      => $item[ 'action' ],
				];
			},
			$this->buildActionItems()
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function buildActionItems() :array {
		return $this->sortItems( \array_merge(
			$this->buildScanItems(),
			$this->buildMaintenanceItems()
		) );
	}

	/**
	 * @return array{
	 *     total: int,
	 *     severity: string,
	 *     is_all_clear: bool
	 * }
	 */
	public function buildActionSummary() :array {
		$items = $this->buildActionItems();
		$total = \count( $items );
		if ( $total === 0 ) {
			return [
				'total'        => 0,
				'severity'     => 'good',
				'is_all_clear' => true,
			];
		}

		$severity = StatusPriority::normalize( $items[ 0 ][ 'severity' ], 'warning' );
		if ( $severity === 'info' ) {
			$severity = 'warning';
		}

		return [
			'total'        => $total,
			'severity'     => $severity,
			'is_all_clear' => false,
		];
	}

	/**
	 * @return array{
	 *     items: array<int, array<string, mixed>>,
	 *     total: int,
	 *     hidden: int
	 * }
	 * @deprecated        21.3.0 Legacy dashboard widget rows compatibility path.
	 *                    Prefer buildActionItems() and buildActionSummary().
	 *
	 */
	public function buildWidgetRows(
		int $maxRows = 0,
		array $summaryMeter = [],
		string $traffic = 'good',
		string $defaultHref = ''
	) :array {
		$items = $this->buildActionItems();
		$summaryItems = $this->buildSummaryItems( $summaryMeter, $traffic, $defaultHref );
		if ( !empty( $summaryItems ) ) {
			$items = $this->sortItems( \array_merge( $items, $summaryItems ) );
		}
		$total = \count( $items );

		if ( $maxRows > 0 ) {
			$items = \array_slice( $items, 0, $maxRows );
		}

		return [
			'items'  => $items,
			'total'  => $total,
			'hidden' => \max( 0, $total - \count( $items ) ),
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function buildSummaryItems( array $summaryMeter, string $traffic, string $defaultHref ) :array {
		if ( empty( $summaryMeter ) ) {
			return [];
		}

		$items = [];
		$warningItem = $this->buildSummaryWarningItem( $summaryMeter, $traffic, $defaultHref );
		if ( !empty( $warningItem ) ) {
			$items[] = $warningItem;
		}

		if ( empty( $items ) && $traffic !== 'good' ) {
			$items[] = $this->buildScoreFallbackItem( $traffic, $defaultHref );
		}

		return $items;
	}

	private function buildSummaryWarningItem( array $summaryMeter, string $traffic, string $defaultHref ) :array {
		$warning = $summaryMeter[ 'warning' ] ?? null;
		if ( !\is_array( $warning ) ) {
			return [];
		}

		$text = $warning[ 'text' ] ?? '';
		if ( !\is_string( $text ) || $text === '' ) {
			return [];
		}

		$href = $warning[ 'href' ] ?? $defaultHref;
		if ( !\is_string( $href ) || $href === '' ) {
			$href = $defaultHref;
		}

		return [
			'key'      => 'meter_warning',
			'zone'     => 'summary',
			'label'    => __( 'Security Meter', 'wp-simple-firewall' ),
			'text'     => $text,
			'count'    => 1,
			'severity' => $traffic === 'critical' ? 'critical' : 'warning',
			'href'     => $href,
			'action'   => __( 'View', 'wp-simple-firewall' ),
		];
	}

	private function buildScoreFallbackItem( string $traffic, string $href ) :array {
		return [
			'key'      => 'score_generic',
			'zone'     => 'summary',
			'label'    => __( 'Security Score', 'wp-simple-firewall' ),
			'text'     => __( 'Security score needs review.', 'wp-simple-firewall' ),
			'count'    => 1,
			'severity' => $traffic === 'critical' ? 'critical' : 'warning',
			'href'     => $href,
			'action'   => __( 'View', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @param string[] $scanSlugs
	 */
	public function getLatestCompletedScanTimestamp( array $scanSlugs ) :int {
		$latest = 0;
		foreach ( $scanSlugs as $scanSlug ) {
			try {
				$record = self::con()
					->db_con
					->scans
					->getQuerySelector()
					->filterByScan( (string)$scanSlug )
					->filterByFinished()
					->setOrderBy( 'id', 'DESC', true )
					->first();
				if ( $record instanceof ScanRecord && (int)$record->finished_at > $latest ) {
					$latest = (int)$record->finished_at;
				}
			}
			catch ( \Exception $e ) {
			}
		}
		return $latest;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function buildScanItems() :array {
		$scansCon = self::con()->comps->scans;
		$counter = $scansCon->getScanResultsCount();
		$scansResultsLink = self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS );

		$items = \array_values( \array_filter( [
			$this->buildItem(
				'wp_files',
				'scans',
				__( 'WP Files', 'wp-simple-firewall' ),
				$counter->countWPFiles(),
				'critical',
				sprintf(
					_n( '%s WordPress core file needs review.', '%s WordPress core files need review.', $counter->countWPFiles(), 'wp-simple-firewall' ),
					$counter->countWPFiles()
				),
				$scansResultsLink,
				__( 'Repair', 'wp-simple-firewall' )
			),
			$this->buildItem(
				'plugin_files',
				'scans',
				__( 'Plugin Files', 'wp-simple-firewall' ),
				$counter->countPluginFiles(),
				'warning',
				sprintf(
					_n( '%s plugin file needs review.', '%s plugin files need review.', $counter->countPluginFiles(), 'wp-simple-firewall' ),
					$counter->countPluginFiles()
				),
				$scansResultsLink,
				__( 'Repair', 'wp-simple-firewall' )
			),
			$this->buildItem(
				'theme_files',
				'scans',
				__( 'Theme Files', 'wp-simple-firewall' ),
				$counter->countThemeFiles(),
				'warning',
				sprintf(
					_n( '%s theme file needs review.', '%s theme files need review.', $counter->countThemeFiles(), 'wp-simple-firewall' ),
					$counter->countThemeFiles()
				),
				$scansResultsLink,
				__( 'Repair', 'wp-simple-firewall' )
			),
			$scansCon->AFS()->isEnabledMalwareScanPHP()
				? $this->buildItem(
				'malware',
				'scans',
				__( 'Malware', 'wp-simple-firewall' ),
				$counter->countMalware(),
				'critical',
				sprintf(
					_n( '%s malware issue detected.', '%s malware issues detected.', $counter->countMalware(), 'wp-simple-firewall' ),
					$counter->countMalware()
				),
				$scansResultsLink,
				__( 'Review', 'wp-simple-firewall' )
			)
				: null,
			$scansCon->WPV()->isEnabled()
				? $this->buildItem(
				'vulnerable_assets',
				'scans',
				__( 'Vulnerable Assets', 'wp-simple-firewall' ),
				$counter->countVulnerableAssets(),
				'critical',
				sprintf(
					_n( '%s vulnerable asset detected.', '%s vulnerable assets detected.', $counter->countVulnerableAssets(), 'wp-simple-firewall' ),
					$counter->countVulnerableAssets()
				),
				$scansResultsLink,
				__( 'Update', 'wp-simple-firewall' )
			)
				: null,
			$scansCon->APC()->isEnabled()
				? $this->buildItem(
				'abandoned',
				'scans',
				__( 'Abandoned Assets', 'wp-simple-firewall' ),
				$counter->countAbandoned(),
				'warning',
				sprintf(
					_n( '%s abandoned asset detected.', '%s abandoned assets detected.', $counter->countAbandoned(), 'wp-simple-firewall' ),
					$counter->countAbandoned()
				),
				$scansResultsLink,
				__( 'Update', 'wp-simple-firewall' )
			)
				: null,
		] ) );

		return $this->sortItems( $items );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function buildMaintenanceItems() :array {
		try {
			$overallConfigMeter = ( new MeterHandler() )->getMeter( MeterOverallConfig::class, false );
		}
		catch ( \Exception $e ) {
			return [];
		}

		$components = $overallConfigMeter[ 'components' ] ?? [];
		if ( !\is_array( $components ) ) {
			return [];
		}

		$items = [];
		foreach ( $components as $component ) {
			if ( !\is_array( $component ) ) {
				continue;
			}

			$normalized = $this->normalizeMaintenanceComponent( $component );
			if ( $normalized === null || !\in_array( $normalized[ 'slug' ], self::MAINTENANCE_COMPONENT_SLUGS, true ) ) {
				continue;
			}

			if ( $normalized[ 'is_protected' ] ) {
				continue;
			}

			$item = $this->buildItem(
				$normalized[ 'slug' ],
				'scans',
				$normalized[ 'title' ],
				1,
				'warning',
				$normalized[ 'description' ],
				$normalized[ 'href' ],
				$normalized[ 'action' ]
			);
			if ( !empty( $item ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * @param array<string, mixed> $component
	 * @return array{
	 *   slug:string,
	 *   is_protected:bool,
	 *   title:string,
	 *   description:string,
	 *   href:string,
	 *   action:string
	 * }|null
	 */
	private function normalizeMaintenanceComponent( array $component ) :?array {
		$slug = $component[ 'slug' ] ?? '';
		$title = !empty( $component[ 'title_unprotected' ] ) ? $component[ 'title_unprotected' ] : ( $component[ 'title' ] ?? '' );
		$description = !empty( $component[ 'desc_unprotected' ] ) ? $component[ 'desc_unprotected' ] : ( $component[ 'description' ] ?? '' );
		$href = $component[ 'href_full' ] ?? self::con()->plugin_urls->adminHome();
		$action = $component[ 'fix' ] ?? __( 'Fix', 'wp-simple-firewall' );

		if ( !\is_string( $slug ) || $slug === '' ) {
			return null;
		}
		if ( !\is_string( $title ) || $title === '' ) {
			return null;
		}
		if ( !\is_string( $description ) || $description === '' ) {
			return null;
		}
		if ( !\is_string( $href ) || $href === '' ) {
			return null;
		}
		if ( !\is_string( $action ) || $action === '' ) {
			return null;
		}

		return [
			'slug'         => $slug,
			'is_protected' => !empty( $component[ 'is_protected' ] ),
			'title'        => $title,
			'description'  => $description,
			'href'         => $href,
			'action'       => $action,
		];
	}

	/**
	 * @param array<int, array<string, mixed>> $items
	 * @return array<int, array<string, mixed>>
	 */
	public function sortItems( array $items ) :array {
		\usort( $items, function ( array $a, array $b ) :int {
			$rankA = StatusPriority::rank( $a[ 'severity' ] );
			$rankB = StatusPriority::rank( $b[ 'severity' ] );
			if ( $rankA !== $rankB ) {
				return $rankB <=> $rankA;
			}

			$countCmp = $b[ 'count' ] <=> $a[ 'count' ];
			if ( $countCmp !== 0 ) {
				return $countCmp;
			}

			$keyA = \array_search( $a[ 'key' ], self::KEY_SORT, true );
			$keyB = \array_search( $b[ 'key' ], self::KEY_SORT, true );
			$keyA = $keyA === false ? \PHP_INT_MAX : $keyA;
			$keyB = $keyB === false ? \PHP_INT_MAX : $keyB;
			if ( $keyA !== $keyB ) {
				return $keyA <=> $keyB;
			}

			return \strcmp( $a[ 'key' ], $b[ 'key' ] );
		} );

		return $items;
	}

	private function buildItem(
		string $key,
		string $zone,
		string $label,
		int $count,
		string $severity,
		string $text,
		string $href,
		string $action
	) :?array {
		if ( $count <= 0 ) {
			return null;
		}

		return [
			'key'      => $key,
			'zone'     => $zone,
			'label'    => $label,
			'text'     => $text,
			'count'    => $count,
			'severity' => $severity,
			'href'     => $href,
			'action'   => $action,
		];
	}
}
