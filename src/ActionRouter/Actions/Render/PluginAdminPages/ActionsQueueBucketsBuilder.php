<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type AttentionItem from BuildAttentionItems
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
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
 * @phpstan-type LayerContext array{
 *   path:list<string>,
 *   focus:string,
 *   next_step:string
 * }
 * @phpstan-type BucketSource array{
 *   attention_items:list<AttentionItem>,
 *   maintenance_rows:list<AssessmentRow>,
 *   item_count:int
 * }
 * @phpstan-type BucketData array{
 *   key:string,
 *   label:string,
 *   status:string,
 *   item_count:int,
 *   summary_text:string,
 *   icon_class:string,
 *   strip_text:string,
 *   strip_badge:string,
 *   context:LayerContext
 * }
 */
class ActionsQueueBucketsBuilder {

	private ?ActionsQueueGroupDefinitions $groupDefinitions = null;

	/**
	 * @param AttentionQuery $attentionQuery
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 * @return list<BucketData>
	 */
	public function build( array $attentionQuery, array $assessmentRowsByZone ) :array {
		$sources = $this->classify( $attentionQuery, $assessmentRowsByZone );
		$buckets = [];

		foreach ( $this->getBucketDefinitions() as $bucketKey => $definition ) {
			$bucketSource = $sources[ $bucketKey ];
			$buckets[] = [
				'key'          => $bucketKey,
				'label'        => $definition[ 'label' ],
				'status'       => $definition[ 'status' ],
				'item_count'   => $bucketSource[ 'item_count' ],
				'summary_text' => $this->buildSummaryText( $bucketKey, $bucketSource ),
				'icon_class'   => $definition[ 'icon_class' ],
				'strip_text'   => $this->buildStripText( $definition[ 'label' ], $bucketSource[ 'item_count' ] ),
				'strip_badge'  => $this->buildItemBadge( $bucketSource[ 'item_count' ] ),
				'context'      => [
					'path'      => [
						__( 'Triage buckets', 'wp-simple-firewall' ),
						$definition[ 'label' ],
					],
					'focus'     => $this->buildBucketFocusText( $definition[ 'label' ], $bucketSource[ 'item_count' ] ),
					'next_step' => empty( $bucketSource[ 'attention_items' ] ) && empty( $bucketSource[ 'maintenance_rows' ] )
						? __( 'Everything in this bucket has already been cleared.', 'wp-simple-firewall' )
						: __( 'Choose a group to review the matching results.', 'wp-simple-firewall' ),
				],
			];
		}

		return $buckets;
	}

	/**
	 * @param AttentionQuery $attentionQuery
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 * @return array<string,BucketSource>
	 */
	public function classify( array $attentionQuery, array $assessmentRowsByZone ) :array {
		$sources = [
			'critical' => [
				'attention_items' => [],
				'maintenance_rows' => [],
				'item_count' => 0,
			],
			'review' => [
				'attention_items' => [],
				'maintenance_rows' => [],
				'item_count' => 0,
			],
			'later' => [
				'attention_items' => [],
				'maintenance_rows' => [],
				'item_count' => 0,
			],
		];

		foreach ( $attentionQuery[ 'items' ] as $item ) {
			$bucketKey = $this->bucketKeyForStatus( $item[ 'severity' ] );
			$sources[ $bucketKey ][ 'attention_items' ][] = $item;
			$sources[ $bucketKey ][ 'item_count' ] += $item[ 'count' ];
		}

		foreach ( $assessmentRowsByZone[ 'maintenance' ] as $row ) {
			$bucketKey = $this->bucketKeyForStatus( $row[ 'status' ] );
			if ( $bucketKey !== 'later' ) {
				continue;
			}

			$sources[ 'later' ][ 'maintenance_rows' ][] = $row;
			++$sources[ 'later' ][ 'item_count' ];
		}

		return $sources;
	}

	private function bucketKeyForStatus( string $status ) :string {
		$status = StatusPriority::normalize( $status, 'good' );

		if ( $status === 'critical' ) {
			return 'critical';
		}
		if ( $status === 'warning' ) {
			return 'review';
		}

		return 'later';
	}

	/**
	 * @param BucketSource $bucketSource
	 */
	private function buildSummaryText( string $bucketKey, array $bucketSource ) :string {
		$summaryParts = $bucketKey === 'later'
			? $this->buildLaterSummaryParts( $bucketSource )
			: $this->buildAttentionSummaryParts( $bucketSource[ 'attention_items' ] );

		if ( empty( $summaryParts ) ) {
			return __( 'No items in this bucket.', 'wp-simple-firewall' );
		}

		return \implode( ', ', \array_slice( $summaryParts, 0, 2 ) );
	}

	/**
	 * @param list<AttentionItem> $items
	 * @return list<string>
	 */
	private function buildAttentionSummaryParts( array $items ) :array {
		$counts = \array_fill_keys( \array_keys( $this->getGroupDefinitions() ), 0 );

		foreach ( $items as $item ) {
			$groupKey = $this->groupDefinitions()->groupKeyForSummaryKey( $item[ 'key' ] );
			$counts[ $groupKey ] += $item[ 'count' ];
		}

		return $this->buildOrderedSummaryParts( $counts, false );
	}

	/**
	 * @param BucketSource $bucketSource
	 * @return list<string>
	 */
	private function buildLaterSummaryParts( array $bucketSource ) :array {
		$counts = [
			'maintenance' => \count( $bucketSource[ 'maintenance_rows' ] ),
		];

		return $this->buildOrderedSummaryParts( $counts, true );
	}

	/**
	 * @param array<string,int> $counts
	 * @return list<string>
	 */
	private function buildOrderedSummaryParts( array $counts, bool $useChecksLabel ) :array {
		\arsort( $counts );
		$parts = [];

		foreach ( $counts as $groupKey => $count ) {
			if ( $count < 1 ) {
				continue;
			}
			$parts[] = $this->summaryLabelForGroup( $groupKey, $count, $useChecksLabel );
		}

		return $parts;
	}

	private function summaryLabelForGroup( string $groupKey, int $count, bool $useChecksLabel ) :string {
		switch ( $groupKey ) {
			case 'vulnerabilities':
				return \sprintf(
					_n( '%s vulnerability', '%s vulnerabilities', $count, 'wp-simple-firewall' ),
					$count
				);

			case 'wordpress':
				return \sprintf(
					_n( '%s WordPress file issue', '%s WordPress file issues', $count, 'wp-simple-firewall' ),
					$count
				);

			case 'plugins':
				return \sprintf(
					_n( '%s plugin file issue', '%s plugin file issues', $count, 'wp-simple-firewall' ),
					$count
				);

			case 'themes':
				return \sprintf(
					_n( '%s theme file issue', '%s theme file issues', $count, 'wp-simple-firewall' ),
					$count
				);

			case 'malware':
				return \sprintf(
					_n( '%s malware detection', '%s malware detections', $count, 'wp-simple-firewall' ),
					$count
				);

			case 'file_locker':
				return \sprintf(
					_n( '%s file change', '%s file changes', $count, 'wp-simple-firewall' ),
					$count
				);

			case 'maintenance':
				return \sprintf(
					$useChecksLabel
						? _n( '%s maintenance check', '%s maintenance checks', $count, 'wp-simple-firewall' )
						: _n( '%s maintenance item', '%s maintenance items', $count, 'wp-simple-firewall' ),
					$count
				);

			default:
				return \sprintf(
					_n( '%s item', '%s items', $count, 'wp-simple-firewall' ),
					$count
				);
		}
	}

	/**
	 * @return array<string,array{
	 *   label:string,
	 *   status:string,
	 *   icon_class:string
	 * }>
	 */
	private function getBucketDefinitions() :array {
		return [
			'critical' => [
				'label' => __( 'Critical', 'wp-simple-firewall' ),
				'status' => 'critical',
				'icon_class' => 'bi bi-exclamation-triangle-fill',
			],
			'review' => [
				'label' => __( 'Review', 'wp-simple-firewall' ),
				'status' => 'warning',
				'icon_class' => 'bi bi-eye-fill',
			],
			'later' => [
				'label' => __( 'Later', 'wp-simple-firewall' ),
				'status' => 'good',
				'icon_class' => 'bi bi-clock-fill',
			],
		];
	}

	/**
	 * @return array<string,array{
	 *   key:string,
	 *   label:string,
	 *   icon_class:string,
	 *   summary_keys:list<string>,
	 *   render_action_class:string,
	 *   render_action_data:array<string,string>
	 * }>
	 */
	private function getGroupDefinitions() :array {
		return $this->groupDefinitions()->all();
	}

	private function groupDefinitions() :ActionsQueueGroupDefinitions {
		if ( $this->groupDefinitions === null ) {
			$this->groupDefinitions = new ActionsQueueGroupDefinitions();
		}

		return $this->groupDefinitions;
	}

	private function buildStripText( string $label, int $itemCount ) :string {
		return \sprintf(
			_n( '%1$s - %2$s item', '%1$s - %2$s items', $itemCount, 'wp-simple-firewall' ),
			$label,
			$itemCount
		);
	}

	private function buildItemBadge( int $itemCount ) :string {
		return \sprintf(
			_n( '%s item', '%s items', $itemCount, 'wp-simple-firewall' ),
			$itemCount
		);
	}

	private function buildBucketFocusText( string $bucketLabel, int $itemCount ) :string {
		return \sprintf(
			_n(
				'%1$s contains %2$s item that still needs attention.',
				'%1$s contains %2$s items that still need attention.',
				$itemCount,
				'wp-simple-firewall'
			),
			$bucketLabel,
			$itemCount
		);
	}
}
