<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type AttentionItem from BuildAttentionItems
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 * @phpstan-import-type AssessmentRowsByZone from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type BucketSelection from ActionsQueueDrillDownPresentationBuilder
 * @phpstan-import-type CompactSummaryRow from ActionsQueueCompactSummaryRowBuilder
 * @phpstan-type BucketSource array{
 *   attention_items:list<AttentionItem>,
 *   item_count:int,
 *   healthy_item_count:int
 * }
 * @phpstan-type BucketData array{
 *   key:string,
 *   label:string,
 *   status:string,
 *   state_label:string,
 *   item_count:int,
 *   is_interactive:bool,
 *   summary_text:string,
 *   icon_class:string,
 *   selection:BucketSelection
 * }
 */
class ActionsQueueBucketsBuilder {

	private ?ActionsQueueGroupDefinitions $groupDefinitions = null;
	private ?ActionsQueueDrillDownPresentationBuilder $presentation = null;
	private ?ActionsQueueCompactSummaryRowBuilder $summaryRowBuilder = null;

	/**
	 * @param AttentionQuery $attentionQuery
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 * @return list<BucketData>
	 */
	public function build( array $attentionQuery, array $assessmentRowsByZone ) :array {
		$sources = $this->classify( $attentionQuery, $assessmentRowsByZone );
		$buckets = [];
		$presentation = $this->presentation();

		foreach ( $this->getBucketDefinitions() as $bucketKey => $definition ) {
			$bucketSource = $sources[ $bucketKey ];
			$status = $this->bucketStatus( $bucketSource );
			$summary = $this->buildBucketHeaderSummary( $definition[ 'label' ], $bucketSource );
			$selection = $presentation->buildBucketSelection(
				$bucketKey,
				$definition[ 'label' ],
				$definition[ 'meta' ],
				$status,
				$definition[ 'icon_class' ],
				$bucketSource[ 'item_count' ],
				$summary
			);
			$buckets[] = [
				'key'            => $bucketKey,
				'label'          => $definition[ 'label' ],
				'status'         => $status,
				'state_label'    => $this->buildBucketStateLabel( $status ),
				'item_count'     => $bucketSource[ 'item_count' ],
				'is_interactive' => $bucketSource[ 'item_count' ] > 0 || $bucketSource[ 'healthy_item_count' ] > 0,
				'summary_text'   => $this->buildSummaryText( $bucketSource ),
				'icon_class'     => $definition[ 'icon_class' ],
				'selection'      => $selection,
			];
		}

		return $buckets;
	}

	/**
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 * @return array{
	 *   label:string,
	 *   rows:list<CompactSummaryRow>
	 * }
	 */
	public function buildHealthyDisclosure( array $assessmentRowsByZone ) :array {
		$rows = [];

		foreach ( $assessmentRowsByZone[ 'scans' ] as $row ) {
			if ( $row[ 'status' ] === 'good' ) {
				$rows[] = $this->buildHealthyDisclosureRow( $row );
			}
		}

		foreach ( $assessmentRowsByZone[ 'maintenance' ] as $row ) {
			if ( $row[ 'status' ] === 'good' ) {
				$rows[] = $this->buildHealthyDisclosureRow( $row );
			}
		}

		return [
			'label' => __( 'No action required', 'wp-simple-firewall' ),
			'rows'  => $rows,
		];
	}

	/**
	 * @phpstan-param array{
	 *   item_icon_class:string,
	 *   label:string,
	 *   description:string
	 * } $row
	 * @return CompactSummaryRow
	 */
	private function buildHealthyDisclosureRow( array $row ) :array {
		return $this->summaryRowBuilder()->build(
			(string)$row[ 'item_icon_class' ],
			(string)$row[ 'label' ],
			(string)$row[ 'description' ]
		);
	}

	/**
	 * @param AttentionQuery $attentionQuery
	 * @return array<string,BucketSource>
	 */
	public function classify( array $attentionQuery, array $assessmentRowsByZone = [ 'scans' => [], 'maintenance' => [] ] ) :array {
		$sources = [
			'critical' => [
				'attention_items' => [],
				'item_count' => 0,
				'healthy_item_count' => 0,
			],
			'review' => [
				'attention_items' => [],
				'item_count' => 0,
				'healthy_item_count' => 0,
			],
		];

		foreach ( $attentionQuery[ 'items' ] as $item ) {
			$bucketKey = $this->bucketKeyForStatus( $item[ 'severity' ] );
			if ( !isset( $sources[ $bucketKey ] ) ) {
				continue;
			}
			$sources[ $bucketKey ][ 'attention_items' ][] = $item;
			$sources[ $bucketKey ][ 'item_count' ] += $item[ 'count' ];
		}

		foreach ( [ 'scans', 'maintenance' ] as $zone ) {
			foreach ( $assessmentRowsByZone[ $zone ] ?? [] as $row ) {
				if ( ( $row[ 'status' ] ?? '' ) !== 'good' ) {
					continue;
				}

				$bucketKey = \trim( (string)( $row[ 'drill_bucket' ] ?? '' ) );
				if ( isset( $sources[ $bucketKey ] ) ) {
					$sources[ $bucketKey ][ 'healthy_item_count' ]++;
				}
			}
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
	private function buildSummaryText( array $bucketSource ) :string {
		$summaryParts = $this->buildAttentionSummaryParts( $bucketSource[ 'attention_items' ] );

		if ( empty( $summaryParts ) ) {
			return $bucketSource[ 'healthy_item_count' ] > 0
				? __( 'Everything in this bucket is currently looking good.', 'wp-simple-firewall' )
				: __( 'No items in this bucket.', 'wp-simple-firewall' );
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

			case 'abandoned':
				return \sprintf(
					_n( '%s abandoned asset', '%s abandoned assets', $count, 'wp-simple-firewall' ),
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
	 *   meta:string,
	 *   icon_class:string
	 * }>
	 */
	private function getBucketDefinitions() :array {
		return [
			'critical' => [
				'label' => __( 'Fix now', 'wp-simple-firewall' ),
				'meta' => __( 'Critical queue', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-exclamation-triangle-fill',
			],
			'review' => [
				'label' => __( 'Review next', 'wp-simple-firewall' ),
				'meta' => __( 'Review queue', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-eye-fill',
			],
		];
	}

	/**
	 * @param BucketSource $bucketSource
	 */
	private function bucketStatus( array $bucketSource ) :string {
		if ( $bucketSource[ 'item_count' ] > 0 ) {
			return empty( $bucketSource[ 'attention_items' ] )
				? 'warning'
				: StatusPriority::highest( \array_column( $bucketSource[ 'attention_items' ], 'severity' ), 'warning' );
		}

		return $bucketSource[ 'healthy_item_count' ] > 0 ? 'good' : 'neutral';
	}

	/**
	 * @param BucketSource $bucketSource
	 */
	private function buildBucketHeaderSummary( string $bucketLabel, array $bucketSource ) :string {
		if ( $bucketSource[ 'item_count' ] > 0 ) {
			return $this->presentation()->buildBucketFocusText( $bucketLabel, $bucketSource[ 'item_count' ] );
		}

		return $bucketSource[ 'healthy_item_count' ] > 0
			? __( 'Everything in this bucket is currently looking good.', 'wp-simple-firewall' )
			: __( 'There is nothing to review in this bucket right now.', 'wp-simple-firewall' );
	}

	private function buildBucketStateLabel( string $status ) :string {
		switch ( $status ) {
			case 'critical':
				return __( 'Critical', 'wp-simple-firewall' );
			case 'warning':
				return __( 'Review', 'wp-simple-firewall' );
			case 'good':
				return __( 'Looking good', 'wp-simple-firewall' );
			default:
				return __( 'Unavailable', 'wp-simple-firewall' );
		}
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

	private function presentation() :ActionsQueueDrillDownPresentationBuilder {
		if ( $this->presentation === null ) {
			$this->presentation = new ActionsQueueDrillDownPresentationBuilder();
		}

		return $this->presentation;
	}

	private function summaryRowBuilder() :ActionsQueueCompactSummaryRowBuilder {
		if ( $this->summaryRowBuilder === null ) {
			$this->summaryRowBuilder = new ActionsQueueCompactSummaryRowBuilder();
		}

		return $this->summaryRowBuilder;
	}
}
