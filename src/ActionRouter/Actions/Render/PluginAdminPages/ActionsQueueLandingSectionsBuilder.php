<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * Lays out the Actions Queue landing: a section for each lane that needs action, then one for what's passing
 * and one for what the plan doesn't cover or is turned off. Each section holds boxes, so the template only
 * draws them.
 *
 * @phpstan-import-type BucketData from ActionsQueueBucketsBuilder
 * @phpstan-import-type BucketSelection from ActionsQueueDrillDownPresentationBuilder
 * @phpstan-import-type GroupData from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupsLayerData from ActionsQueueGroupsBuilder
 * @phpstan-import-type HealthyRow from ActionsQueueBucketsBuilder
 * @phpstan-type LandingEntry array{
 *   group:GroupData,
 *   bucket_selection:BucketSelection
 * }
 * @phpstan-type LandingBox array{
 *   type:'shared'|'group'|'passing'|'plain',
 *   kind:string,
 *   heading:string,
 *   icon_class:string,
 *   collapsible:bool,
 *   entries:list<LandingEntry>,
 *   rows:list<HealthyRow>
 * }
 * @phpstan-type LandingSection array{
 *   key:string,
 *   status:string,
 *   label:string,
 *   tab_label:string,
 *   icon_class:string,
 *   count:int,
 *   show_count:bool,
 *   summary:string,
 *   breakdown:list<array{label:string,count:int}>,
 *   selection_json:string,
 *   is_open:bool,
 *   boxes:list<LandingBox>
 * }
 */
class ActionsQueueLandingSectionsBuilder {

	/**
	 * Each kind of problem gets a box of its own, most serious first. Anything else follows in the order it came.
	 */
	private const KIND_ORDER = [ 'wordpress', 'malware', 'file_locker', 'vulnerabilities', 'plugins', 'themes' ];

	/**
	 * @param list<BucketData>              $buckets
	 * @param array<string,GroupsLayerData> $bucketGroups the groups of each lane that can be opened
	 * @param list<HealthyRow>              $healthyRows
	 * @param list<string>                  $aggregateGroupKeys groups that gather several maintenance items
	 * @return array{sections:list<LandingSection>,has_lanes:bool}
	 */
	public function build( array $buckets, array $bucketGroups, array $healthyRows, array $aggregateGroupKeys ) :array {
		$lanes = [];
		$passingCandidates = [];
		$notCoveredCandidates = [];
		$activeKeys = [];

		foreach ( $buckets as $bucket ) {
			if ( !$bucket[ 'is_interactive' ] ) {
				continue;
			}
			$layer = $bucketGroups[ $bucket[ 'key' ] ];
			$laneEntries = [];
			$breakdown = [];

			$tagged = \array_merge(
				\array_map( fn( array $s ) :array => [ $s, false ], $layer[ 'active_sections' ] ),
				\array_map( fn( array $s ) :array => [ $s, true ], $layer[ 'healthy_sections' ] )
			);
			foreach ( $tagged as [ $groupSection, $isHealthySection ] ) {
				foreach ( $groupSection[ 'groups' ] as $group ) {
					$entry = [
						'group'            => $group,
						'bucket_selection' => $layer[ 'bucket_selection' ],
						'heading'          => $groupSection[ 'heading_label' ],
						'icon_class'       => $groupSection[ 'heading_icon' ],
					];
					if ( $group[ 'is_pro_upsell' ] || $group[ 'enable_dialog_json' ] !== '' ) {
						$notCoveredCandidates[] = $entry;
					}
					elseif ( $group[ 'detail_shell' ] === 'maintenance' && !empty( $group[ 'ignored_rows' ] ) ) {
						// Ignored items stay in their lane, in a faded group of their own, so each can be restored.
						// They count for nothing.
						$laneEntries[] = $entry;
						$activeKeys[] = $group[ 'key' ];
					}
					elseif ( $group[ 'status' ] === 'good' || $isHealthySection ) {
						$passingCandidates[] = $entry;
					}
					else {
						$laneEntries[] = $entry;
						$activeKeys[] = $group[ 'key' ];
						$breakdown[] = [
							'label' => $group[ 'label' ],
							'count' => $this->countStillToDo( $group ),
						];
					}
				}
			}

			if ( !empty( $laneEntries ) ) {
				// Only the lane that needs attention next starts open; the rest show a one-line breakdown.
				$lanes[] = [
					'key'            => $bucket[ 'key' ],
					'status'         => $bucket[ 'status' ],
					'label'          => $bucket[ 'label' ],
					'tab_label'      => $bucket[ 'label' ],
					'icon_class'     => $bucket[ 'icon_class' ],
					'count'          => $bucket[ 'display_count' ],
					'show_count'     => true,
					'summary'        => $bucket[ 'summary_text' ],
					'breakdown'      => $breakdown,
					'selection_json' => $bucket[ 'selection' ][ 'selection_json' ],
					'is_open'        => empty( $lanes ),
					// Review next is the longest lane, so its boxes start folded to a heading and a count.
					'boxes'          => $this->buildLaneBoxes( $laneEntries, $bucket[ 'key' ] === 'review' ),
				];
			}
		}

		// Each lane lists every group it doesn't flag as passing, so the same passing group can arrive from
		// several lanes, and a group one lane flags can arrive as passing from another. List each group once,
		// and only as passing when no lane needs it. A group gathering several maintenance items ("System",
		// "WordPress") would only repeat items already listed one by one, so passing lists the items themselves.
		$seen = \array_fill_keys( $activeKeys, true );
		$passing = [];
		foreach ( $passingCandidates as $entry ) {
			$key = $entry[ 'group' ][ 'key' ];
			if ( !isset( $seen[ $key ] ) && !\in_array( \explode( ':', $key )[ 0 ], $aggregateGroupKeys, true ) ) {
				$passing[] = $entry;
				$seen[ $key ] = true;
			}
		}
		$notCovered = [];
		foreach ( $notCoveredCandidates as $entry ) {
			$key = $entry[ 'group' ][ 'key' ];
			if ( !isset( $seen[ $key ] ) ) {
				$notCovered[] = $entry;
				$seen[ $key ] = true;
			}
		}

		// A passing check can show both as a group and as a summary row; list it once, and never when it needs
		// action.
		$passingLabels = \array_map( fn( array $e ) :string => \mb_strtolower( $e[ 'group' ][ 'label' ] ), $passing );
		$healthyRows = \array_values( \array_filter(
			$healthyRows,
			fn( array $row ) :bool => !\in_array( \mb_strtolower( $row[ 'title' ] ), $passingLabels, true )
									  && !isset( $seen[ $row[ 'group_key' ] ] )
		) );

		$sections = $lanes;
		$passingSection = $this->buildPassingSection( $passing, $healthyRows );
		if ( $passingSection !== null ) {
			$sections[] = $passingSection;
		}
		if ( !empty( $notCovered ) ) {
			$sections[] = [
				'key'            => 'not_covered',
				'status'         => 'pro',
				'label'          => __( 'Not covered', 'wp-simple-firewall' ),
				'tab_label'      => __( 'Not covered', 'wp-simple-firewall' ),
				'icon_class'     => 'bi bi-lock-fill',
				'count'          => \count( $notCovered ),
				'show_count'     => true,
				'summary'        => \implode( ', ', \array_map( fn( array $e ) :string => $e[ 'group' ][ 'label' ], $notCovered ) ),
				'breakdown'      => [],
				'selection_json' => '',
				'is_open'        => false,
				'boxes'          => [ $this->box( 'plain', '', '', '', false, $this->entries( $notCovered ) ) ],
			];
		}
		return [
			'sections'  => $sections,
			'has_lanes' => !empty( $lanes ),
		];
	}

	/**
	 * How much of a group still needs doing: for a group that lists its items, those not ignored.
	 * @param GroupData $group
	 */
	private function countStillToDo( array $group ) :int {
		if ( $group[ 'card_type' ] !== 'category' ) {
			return $group[ 'item_count' ];
		}
		return empty( $group[ 'maintenance_rows' ] )
			? 1
			: \count( \array_filter( $group[ 'maintenance_rows' ], fn( array $row ) :bool => !$row[ 'is_ignored' ] ) );
	}

	/**
	 * Groups shown as single rows share a box for their kind, under that kind's heading; a group that lists its
	 * items brings its own heading and box.
	 * @param list<array{group:GroupData,bucket_selection:BucketSelection,heading:string,icon_class:string}> $laneEntries
	 * @return list<LandingBox>
	 */
	private function buildLaneBoxes( array $laneEntries, bool $collapsible ) :array {
		$byKind = [];
		foreach ( $laneEntries as $entry ) {
			$byKind[ \explode( ':', $entry[ 'group' ][ 'key' ] )[ 0 ] ][] = $entry;
		}
		$kinds = \array_merge(
			\array_values( \array_intersect( self::KIND_ORDER, \array_keys( $byKind ) ) ),
			\array_values( \array_diff( \array_keys( $byKind ), self::KIND_ORDER ) )
		);

		$kindHeadings = $this->kindHeadings();
		$boxes = [];
		foreach ( $kinds as $kind ) {
			$single = [];
			$listed = [];
			foreach ( $byKind[ $kind ] as $entry ) {
				if ( $this->listsItsItems( $entry[ 'group' ] ) ) {
					$listed[] = $entry;
				}
				else {
					$single[] = $entry;
				}
			}
			if ( !empty( $single ) ) {
				$first = $single[ 0 ];
				$heading = $kindHeadings[ $kind ] ?? $first[ 'heading' ];
				$boxes[] = $this->box(
					'shared',
					(string)$kind,
					$heading,
					$first[ 'group' ][ 'icon_class' ] !== '' ? $first[ 'group' ][ 'icon_class' ] : $first[ 'icon_class' ],
					$collapsible && $heading !== '',
					$this->entries( $single )
				);
			}
			foreach ( $listed as $entry ) {
				$boxes[] = $this->box( 'group', (string)$kind, '', '', $collapsible, $this->entries( [ $entry ] ) );
			}
		}
		return $boxes;
	}

	/**
	 * @param list<array{group:GroupData,bucket_selection:BucketSelection,heading:string,icon_class:string}> $passing
	 * @param list<HealthyRow> $healthyRows
	 * @return LandingSection|null
	 */
	private function buildPassingSection( array $passing, array $healthyRows ) :?array {
		$count = \count( $passing ) + \count( $healthyRows );
		if ( $count === 0 ) {
			return null;
		}

		// Shield's own protections first, then WordPress and the server.
		$boxes = \array_values( \array_filter( [
			$this->box(
				'passing', '', __( 'Shield protections', 'wp-simple-firewall' ), 'bi bi-shield-check', false,
				$this->entries( \array_filter( $passing, fn( array $e ) :bool => $e[ 'group' ][ 'detail_shell' ] !== 'maintenance' ) ),
				\array_values( \array_filter( $healthyRows, fn( array $r ) :bool => $r[ 'zone' ] === 'scans' ) )
			),
			$this->box(
				'passing', '', __( 'System', 'wp-simple-firewall' ), 'bi bi-hdd-stack', false,
				$this->entries( \array_filter( $passing, fn( array $e ) :bool => $e[ 'group' ][ 'detail_shell' ] === 'maintenance' ) ),
				\array_values( \array_filter( $healthyRows, fn( array $r ) :bool => $r[ 'zone' ] === 'maintenance' ) )
			),
		], fn( array $box ) :bool => !empty( $box[ 'entries' ] ) || !empty( $box[ 'rows' ] ) ) );

		$names = \array_values( \array_filter( \array_merge(
			\array_map( fn( array $e ) :string => $e[ 'group' ][ 'label' ], $passing ),
			\array_map( fn( array $r ) :string => $r[ 'title' ], $healthyRows )
		), fn( string $name ) :bool => $name !== '' ) );

		return [
			'key'            => 'passing',
			'status'         => 'good',
			/* translators: %s: number of checks */
			'label'          => \sprintf( __( '%s checks passing', 'wp-simple-firewall' ), $count ),
			'tab_label'      => __( 'Passing', 'wp-simple-firewall' ),
			'icon_class'     => 'bi bi-check-circle-fill',
			'count'          => $count,
			'show_count'     => false,
			'summary'        => \count( $names ) > 3
				/* translators: %1$s: the first few check names, %2$s: how many more */
				? \sprintf( __( '%1$s and %2$s more', 'wp-simple-firewall' ), \implode( ', ', \array_slice( $names, 0, 3 ) ), \count( $names ) - 3 )
				: \implode( ', ', $names ),
			'breakdown'      => [],
			'selection_json' => '',
			'is_open'        => false,
			'boxes'          => $boxes,
		];
	}

	/**
	 * @param GroupData $group
	 */
	private function listsItsItems( array $group ) :bool {
		return $group[ 'card_type' ] === 'category' || !empty( $group[ 'maintenance_rows' ] ) || !empty( $group[ 'ignored_rows' ] );
	}

	/**
	 * @param iterable<array{group:GroupData,bucket_selection:BucketSelection}> $entries
	 * @return list<LandingEntry>
	 */
	private function entries( iterable $entries ) :array {
		$list = [];
		foreach ( $entries as $entry ) {
			$list[] = [
				'group'            => $entry[ 'group' ],
				'bucket_selection' => $entry[ 'bucket_selection' ],
			];
		}
		return $list;
	}

	/**
	 * @param 'shared'|'group'|'passing'|'plain' $type
	 * @param list<LandingEntry> $entries
	 * @param list<HealthyRow>   $rows
	 * @return LandingBox
	 */
	private function box( string $type, string $kind, string $heading, string $iconClass, bool $collapsible, array $entries, array $rows = [] ) :array {
		return [
			'type'        => $type,
			'kind'        => $kind,
			'heading'     => $heading,
			'icon_class'  => $iconClass,
			'collapsible' => $collapsible,
			'entries'     => $entries,
			'rows'        => $rows,
		];
	}

	/**
	 * @return array<string,string>
	 */
	private function kindHeadings() :array {
		return [
			'wordpress'   => __( 'WordPress core files', 'wp-simple-firewall' ),
			'malware'     => __( 'PHP malware', 'wp-simple-firewall' ),
			'file_locker' => __( 'Critical File Locker', 'wp-simple-firewall' ),
			'plugins'     => __( 'Plugins with scan results', 'wp-simple-firewall' ),
			'themes'      => __( 'Themes with scan results', 'wp-simple-firewall' ),
		];
	}
}
