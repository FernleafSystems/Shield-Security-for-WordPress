<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record as ScanRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-type ActionItem array{
 *   key:string,
 *   zone:string,
 *   label:string,
 *   count:int,
 *   severity:string,
 *   text:string,
 *   href:string,
 *   action:string,
 *   target:string
 * }
 * @phpstan-type QueueItem array{
 *   key:string,
 *   zone:string,
 *   label:string,
 *   count:int,
 *   severity:string,
 *   description:string,
 *   href:string,
 *   action:string,
 *   target:string
 * }
 */
class AttentionItemsProvider {

	use PluginControllerConsumer;

	private const KEY_SORT = [
		'malware',
		'vulnerable_assets',
		'wp_files',
		'plugin_files',
		'theme_files',
		'file_locker',
		'abandoned',
		'wp_updates',
		'wp_plugins_updates',
		'wp_themes_updates',
		'wp_plugins_inactive',
		'wp_themes_inactive',
		'system_ssl_certificate',
		'system_php_version',
		'wp_db_password',
		'system_lib_openssl',
	];

	/**
	 * @return list<QueueItem>
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
					'target'      => $item[ 'target' ],
				];
			},
			$this->buildActionItems()
		);
	}

	/**
	 * @return list<ActionItem>
	 */
	public function buildActionItems() :array {
		return $this->sortItems( \array_merge(
			$this->buildScanItems(),
			( new OperationalIssuesProvider() )->buildQueueItems()
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
		$total = \array_sum( \array_map(
			static fn( array $item ) :int => $item[ 'count' ],
			$items
		) );
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
	 * @return list<ActionItem>
	 */
	public function buildScanItems() :array {
		$state = ( new ActionsQueueScanStateBuilder() )->build();

		return $this->sortItems( $state[ 'rows' ] );
	}

	/**
	 * @param list<ActionItem> $items
	 * @return list<ActionItem>
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
}
