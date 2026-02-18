<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record as ScanRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class AttentionItemsProvider {

	use PluginControllerConsumer;

	private const SEVERITY_SORT = [
		'critical' => 0,
		'warning'  => 1,
	];

	private const KEY_SORT = [
		'malware',
		'vulnerable_assets',
		'wp_files',
		'plugin_files',
		'theme_files',
		'abandoned',
		'meter_warning',
		'score_generic',
	];

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function buildQueueItems() :array {
		return \array_map(
			function ( array $item ) :array {
				return [
					'zone'        => $item[ 'zone' ],
					'label'       => $item[ 'label' ],
					'count'       => $item[ 'count' ],
					'severity'    => $item[ 'severity' ],
					'description' => $item[ 'text' ],
					'href'        => $item[ 'href' ],
				];
			},
			$this->buildScanItems()
		);
	}

	/**
	 * @return array{
	 *     items: array<int, array<string, mixed>>,
	 *     total: int,
	 *     hidden: int
	 * }
	 */
	public function buildWidgetRows( int $maxRows = 0 ) :array {
		$items = $this->buildScanItems();
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
	 * @param array<int, array<string, mixed>> $items
	 * @return array<int, array<string, mixed>>
	 */
	public function sortItems( array $items ) :array {
		\usort( $items, function ( array $a, array $b ) :int {
			$rankA = self::SEVERITY_SORT[ (string)( $a[ 'severity' ] ?? '' ) ] ?? \PHP_INT_MAX;
			$rankB = self::SEVERITY_SORT[ (string)( $b[ 'severity' ] ?? '' ) ] ?? \PHP_INT_MAX;
			if ( $rankA !== $rankB ) {
				return $rankA <=> $rankB;
			}

			$countCmp = (int)( $b[ 'count' ] ?? 0 ) <=> (int)( $a[ 'count' ] ?? 0 );
			if ( $countCmp !== 0 ) {
				return $countCmp;
			}

			$keyA = \array_search( (string)( $a[ 'key' ] ?? '' ), self::KEY_SORT, true );
			$keyB = \array_search( (string)( $b[ 'key' ] ?? '' ), self::KEY_SORT, true );
			$keyA = $keyA === false ? \PHP_INT_MAX : $keyA;
			$keyB = $keyB === false ? \PHP_INT_MAX : $keyB;
			if ( $keyA !== $keyB ) {
				return $keyA <=> $keyB;
			}

			return \strcmp( (string)( $a[ 'key' ] ?? '' ), (string)( $b[ 'key' ] ?? '' ) );
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
