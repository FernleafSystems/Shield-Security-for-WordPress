<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\{
	ActionsQueueScanStateBuilder,
	MaintenanceIssueStateProvider
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type ActionsQueueScanIssueRow from ActionsQueueScanStateBuilder
 * @phpstan-import-type ActionsQueueScanState from ActionsQueueScanStateBuilder
 * @phpstan-import-type MaintenanceIssueState from MaintenanceIssueStateProvider
 * @phpstan-type AttentionItem array{
 *   key:string,
 *   zone:'scans'|'maintenance',
 *   source:'scan'|'maintenance',
 *   label:string,
 *   description:string,
 *   count:int,
 *   ignored_count:int,
 *   severity:string,
 *   href:string,
 *   action:string,
 *   target:string,
 *   supports_sub_items:bool
 * }
 * @phpstan-type AttentionSummary array{
 *   total:int,
 *   severity:string,
 *   is_all_clear:bool
 * }
 * @phpstan-type AttentionGroup array{
 *   zone:'scans'|'maintenance',
 *   total:int,
 *   severity:string,
 *   items:list<AttentionItem>
 * }
 * @phpstan-type AttentionGroups array{
 *   scans:AttentionGroup,
 *   maintenance:AttentionGroup
 * }
 * @phpstan-type AttentionQuery array{
 *   generated_at:int,
 *   summary:AttentionSummary,
 *   items:list<AttentionItem>,
 *   groups:AttentionGroups
 * }
 */
class BuildAttentionItems {

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
	 * @return AttentionQuery
	 */
	public function build() :array {
		$scanItems = $this->buildScanItems();
		$maintenanceItems = $this->buildMaintenanceItems();
		$items = $this->sortItems( \array_merge( $scanItems, $maintenanceItems ) );

		return [
			'generated_at' => Services::Request()->ts(),
			'summary'      => $this->buildSummary( $items ),
			'items'        => $items,
			'groups'       => [
				'scans'       => $this->buildGroup( 'scans', $scanItems ),
				'maintenance' => $this->buildGroup( 'maintenance', $maintenanceItems ),
			],
		];
	}

	/**
	 * @return list<AttentionItem>
	 */
	protected function buildScanItems() :array {
		/** @var ActionsQueueScanState $scanState */
		$scanState = ( new ActionsQueueScanStateBuilder() )->build();

		return $this->sortItems( \array_map(
			static function ( array $item ) :array {
				return [
					'key'                => $item[ 'key' ],
					'zone'               => 'scans',
					'source'             => 'scan',
					'label'              => $item[ 'label' ],
					'description'        => $item[ 'text' ],
					'count'              => $item[ 'count' ],
					'ignored_count'      => 0,
					'severity'           => $item[ 'severity' ],
					'href'               => $item[ 'href' ],
					'action'             => $item[ 'action' ],
					'target'             => $item[ 'target' ],
					'supports_sub_items' => false,
				];
			},
			$scanState[ 'rows' ]
		) );
	}

	/**
	 * @return list<AttentionItem>
	 */
	protected function buildMaintenanceItems() :array {
		return $this->sortItems( \array_values( \array_filter( \array_map(
			static function ( array $state ) :?array {
				if ( $state[ 'count' ] < 1 ) {
					return null;
				}

				return [
					'key'                => $state[ 'key' ],
					'zone'               => 'maintenance',
					'source'             => 'maintenance',
					'label'              => $state[ 'label' ],
					'description'        => $state[ 'description' ],
					'count'              => $state[ 'count' ],
					'ignored_count'      => $state[ 'ignored_count' ],
					'severity'           => $state[ 'severity' ],
					'href'               => $state[ 'href' ],
					'action'             => $state[ 'action' ],
					'target'             => $state[ 'target' ],
					'supports_sub_items' => $state[ 'supports_sub_items' ],
				];
			},
			\array_values( ( new MaintenanceIssueStateProvider() )->buildStates() )
		) ) ) );
	}

	/**
	 * @param 'scans'|'maintenance' $zone
	 * @param list<AttentionItem> $items
	 * @return AttentionGroup
	 */
	private function buildGroup( string $zone, array $items ) :array {
		return [
			'zone'     => $zone,
			'total'    => (int)\array_sum( \array_column( $items, 'count' ) ),
			'severity' => empty( $items ) ? 'good' : StatusPriority::highest( \array_column( $items, 'severity' ), 'good' ),
			'items'    => $items,
		];
	}

	/**
	 * @param list<AttentionItem> $items
	 * @return AttentionSummary
	 */
	private function buildSummary( array $items ) :array {
		$total = (int)\array_sum( \array_column( $items, 'count' ) );
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
	 * @param list<AttentionItem> $items
	 * @return list<AttentionItem>
	 */
	private function sortItems( array $items ) :array {
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
