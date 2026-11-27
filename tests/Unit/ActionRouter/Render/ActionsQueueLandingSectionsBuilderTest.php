<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueLandingSectionsBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueLandingSectionsBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
	}

	public function test_lanes_take_groups_needing_action_and_open_only_the_first() :void {
		$sections = $this->build();

		$this->assertSame( [ 'critical', 'review', 'passing', 'not_covered' ], \array_column( $sections, 'key' ) );
		$this->assertSame( [ true, false, false, false ], \array_column( $sections, 'is_open' ) );
		$this->assertSame( [ true, false, false, false ], \array_column( $sections, 'is_selected_tab' ) );
		$this->assertSame( [ true, true, false, true ], \array_column( $sections, 'show_count' ) );
		$this->assertSame( 'bucket-critical-json', $sections[ 0 ][ 'selection_json' ] );
	}

	public function test_lane_boxes_follow_kind_order_with_single_rows_sharing_a_box() :void {
		$critical = $this->build()[ 0 ];

		// Malware comes before plugins; the two single-row plugin groups share one box under the kind's heading.
		$this->assertSame(
			[ [ 'shared', 'malware' ], [ 'shared', 'plugins' ] ],
			\array_map( fn( array $box ) :array => [ $box[ 'type' ], $box[ 'kind' ] ], $critical[ 'boxes' ] )
		);
		$this->assertSame( 'Plugins with scan results', $critical[ 'boxes' ][ 1 ][ 'heading' ] );
		$this->assertCount( 2, $critical[ 'boxes' ][ 1 ][ 'entries' ] );
		$this->assertFalse( $critical[ 'boxes' ][ 1 ][ 'collapsible' ] );
		$this->assertSame(
			// The folded summary follows the order of the boxes, not the order the groups came in.
			[ [ 'label' => 'PHP Malware', 'count' => 3 ], [ 'label' => 'Plugin A', 'count' => 1 ], [ 'label' => 'Plugin B', 'count' => 2 ] ],
			$critical[ 'breakdown' ]
		);
	}

	public function test_review_lane_folds_its_boxes_and_counts_only_items_still_to_do() :void {
		$review = $this->build()[ 1 ];

		$this->assertSame( [ 'group', 'group' ], \array_column( $review[ 'boxes' ], 'type' ) );
		$this->assertSame( [ true, true ], \array_column( $review[ 'boxes' ], 'collapsible' ) );
		// The fully ignored group stays in the lane but counts for nothing; the other counts its rows not ignored.
		$this->assertSame( [ [ 'label' => 'WordPress', 'count' => 1 ] ], $review[ 'breakdown' ] );
	}

	public function test_plugin_and_theme_housekeeping_closes_the_lane_in_a_fixed_order() :void {
		$group = fn( string $key, string $label, string $status, array $ignored = [] ) :array => $this->group(
			$key, $label, $status, [ 'detail_shell' => 'maintenance', 'card_type' => 'category', 'ignored_rows' => $ignored ]
		);
		$review = ( new ActionsQueueLandingSectionsBuilder() )->build(
			[ $this->bucket( 'review' ) ],
			[
				'review' => $this->layer( 'review', [
					$this->section( [
						$group( 'wp_themes_updates', 'Themes With Updates', 'warning' ),
						$group( 'wp_plugins_inactive', 'Inactive Plugins', 'warning' ),
						$group( 'maintenance_system', 'System', 'warning' ),
						$group( 'wp_plugins_updates', 'Plugins With Updates', 'warning' ),
					] ),
				], [
					// Everything ignored arrives after the rest, but keeps its place among them.
					$this->section( [ $group( 'wp_themes_inactive', 'Inactive Themes', 'good', [ $this->row( 'Twenty', true ) ] ) ] ),
				] ),
			],
			[],
			[]
		)[ 0 ];

		$this->assertSame(
			[ 'maintenance_system', 'wp_plugins_updates', 'wp_plugins_inactive', 'wp_themes_updates', 'wp_themes_inactive' ],
			\array_column( $review[ 'boxes' ], 'kind' )
		);
		// Only the plugin and theme housekeeping lists its items two to a line.
		$this->assertSame( [ false, true, true, true, true ], \array_column( $review[ 'boxes' ], 'two_columns' ) );
		// The folded summary reads in the same order, leaving out the group with everything ignored.
		$this->assertSame(
			[ 'System', 'Plugins With Updates', 'Inactive Plugins', 'Themes With Updates' ],
			\array_column( $review[ 'breakdown' ], 'label' )
		);
	}

	public function test_passing_lists_each_check_once_and_never_one_a_lane_needs() :void {
		$passing = $this->build()[ 2 ];

		// "Plugin A" passes in the review lane but needs action in critical, so it isn't passing. Its summary
		// row is dropped for the same reason; "Firewall" shows once, as a group.
		$this->assertSame( 2, $passing[ 'count' ] );
		$this->assertSame( '2 checks passing', $passing[ 'label' ] );
		$this->assertSame( 'Firewall, Server Config', $passing[ 'summary' ] );
		$this->assertSame( [ 'Shield protections', 'System' ], \array_column( $passing[ 'boxes' ], 'heading' ) );
		$this->assertSame( 'Firewall', $passing[ 'boxes' ][ 0 ][ 'entries' ][ 0 ][ 'group' ][ 'label' ] );
		$this->assertSame( [], $passing[ 'boxes' ][ 0 ][ 'rows' ] );
		$this->assertSame( [ 'Server Config' ], \array_column( $passing[ 'boxes' ][ 1 ][ 'rows' ], 'title' ) );
	}

	public function test_not_covered_lists_what_is_switched_off_apart_from_and_ahead_of_what_needs_an_upgrade() :void {
		$notCovered = $this->build()[ 3 ];
		$labelsByBox = \array_map(
			fn( array $box ) :array => \array_map( fn( array $entry ) :string => $entry[ 'group' ][ 'label' ], $box[ 'entries' ] ),
			$notCovered[ 'boxes' ]
		);

		// The upsell arrived first, from an earlier lane, but what can be turned on here leads.
		$this->assertSame( [ [ 'File Scan' ], [ 'Vulnerabilities' ] ], $labelsByBox );
		$this->assertSame( 'File Scan, Vulnerabilities', $notCovered[ 'summary' ] );
		$this->assertSame( 2, $notCovered[ 'count' ] );
		$this->assertNotSame( $notCovered[ 'boxes' ][ 0 ][ 'heading' ], $notCovered[ 'boxes' ][ 1 ][ 'heading' ] );
		// Holding something that isn't a matter of upgrading, the section doesn't present itself as Pro's.
		$this->assertSame( 'neutral', $notCovered[ 'status' ] );
	}

	public function test_not_covered_is_pros_section_when_it_holds_only_what_needs_an_upgrade() :void {
		$sections = ( new ActionsQueueLandingSectionsBuilder() )->build(
			[ $this->bucket( 'critical' ) ],
			[
				'critical' => $this->layer( 'critical', [
					$this->section( [ $this->group( 'vulnerabilities', 'Vulnerabilities', 'neutral', [ 'is_pro_upsell' => true ] ) ] ),
				], [] ),
			],
			[],
			[]
		);
		$notCovered = $sections[ 1 ];

		$this->assertSame( 'not_covered', $notCovered[ 'key' ] );
		$this->assertSame( 'pro', $notCovered[ 'status' ] );
		$this->assertCount( 1, $notCovered[ 'boxes' ] );
		$this->assertCount( 1, $notCovered[ 'boxes' ][ 0 ][ 'entries' ] );
	}

	public function test_a_lane_with_nothing_to_do_stays_folded_in_green_with_a_zero_and_says_so() :void {
		$sections = ( new ActionsQueueLandingSectionsBuilder() )->build(
			// Fix now has only a passing group; Review next has nothing at all, so no groups were built for it.
			[ $this->bucket( 'critical' ), [ 'is_interactive' => false ] + $this->bucket( 'review' ) ],
			[ 'critical' => $this->layer( 'critical', [], [ $this->section( [ $this->group( 'firewall', 'Firewall', 'good' ) ] ) ] ) ],
			[],
			[]
		);

		$this->assertSame( [ 'critical', 'review', 'passing' ], \array_column( $sections, 'key' ) );
		foreach ( [ $sections[ 0 ], $sections[ 1 ] ] as $lane ) {
			$this->assertSame( 'good', $lane[ 'status' ] );
			$this->assertSame( 0, $lane[ 'count' ] );
			$this->assertTrue( $lane[ 'show_count' ] );
			$this->assertFalse( $lane[ 'is_open' ] );
			$this->assertNotSame( '', $lane[ 'empty_text' ] );
			$this->assertSame( [], $lane[ 'boxes' ] );
		}
		$this->assertNotSame( $sections[ 0 ][ 'empty_text' ], $sections[ 1 ][ 'empty_text' ] );
		// With nothing to do anywhere, the first section is the tab showing.
		$this->assertSame( [ true, false, false ], \array_column( $sections, 'is_selected_tab' ) );
	}

	public function test_a_lane_holding_only_ignored_items_has_nothing_to_do_and_still_lists_them() :void {
		$sections = ( new ActionsQueueLandingSectionsBuilder() )->build(
			[ $this->bucket( 'critical' ), $this->bucket( 'review' ) ],
			[
				'critical' => $this->layer( 'critical', [ $this->section( [ $this->group( 'malware', 'PHP Malware', 'critical', [ 'item_count' => 1 ] ) ] ) ], [] ),
				'review'   => $this->layer( 'review', [], [
					$this->section( [
						$this->group( 'wp_themes_inactive', 'Inactive Themes', 'good', [
							'detail_shell' => 'maintenance',
							'card_type'    => 'category',
							'ignored_rows' => [ $this->row( 'Twenty', true ) ],
						] ),
					] ),
				] ),
			],
			[],
			[]
		);
		$review = $sections[ 1 ];

		$this->assertSame( 'good', $review[ 'status' ] );
		$this->assertSame( 0, $review[ 'count' ] );
		$this->assertFalse( $review[ 'is_open' ] );
		$this->assertNotSame( '', $review[ 'empty_text' ] );
		// What was ignored is still there to be restored, and the lane says it's leaving something out.
		$this->assertSame( [ 'wp_themes_inactive' ], \array_column( $review[ 'boxes' ], 'kind' ) );
		$this->assertSame( 1, $review[ 'ignored_count' ] );
		$this->assertSame( 0, $sections[ 0 ][ 'ignored_count' ] );
		$this->assertSame( [ true, false ], \array_column( $sections, 'is_open' ) );
	}

	public function test_scan_findings_that_are_all_ignored_stay_in_their_lane_and_are_never_passing() :void {
		$sections = ( new ActionsQueueLandingSectionsBuilder() )->build(
			[ $this->bucket( 'critical' ) ],
			[
				'critical' => $this->layer( 'critical', [], [
					$this->section( [
						$this->group( 'abandoned', 'Abandoned Plugins', 'good', [
							'card_type'    => 'category',
							'ignored_rows' => [ $this->row( 'Old Plugin', true ), $this->row( 'Older Plugin', true ) ],
						] ),
						// Shield left this one out by itself: nobody ignored anything, so the check is passing.
						$this->group( 'hidden_plugins', 'Cloaked Plugins', 'good', [
							'card_type'    => 'category',
							'ignored_rows' => [ $this->row( 'Shield loader', true, false ) ],
						] ),
					] ),
				] ),
			],
			[
				$this->healthyRow( 'Abandoned Assets', 'abandoned', 'scans' ),
			],
			[]
		);

		$this->assertSame( [ 'critical', 'passing' ], \array_column( $sections, 'key' ) );
		[ $critical, $passing ] = $sections;
		$this->assertSame( 'good', $critical[ 'status' ] );
		$this->assertSame( 0, $critical[ 'count' ] );
		$this->assertSame( [ 'abandoned' ], \array_column( $critical[ 'boxes' ], 'kind' ) );
		$this->assertSame( 2, $critical[ 'ignored_count' ] );
		// Neither the group nor its summary row is listed as passing.
		$this->assertSame( 1, $passing[ 'count' ] );
		$this->assertSame(
			[ 'hidden_plugins' ],
			\array_map( fn( array $entry ) :string => $entry[ 'group' ][ 'key' ], $passing[ 'boxes' ][ 0 ][ 'entries' ] )
		);
		$this->assertSame( [], $passing[ 'boxes' ][ 0 ][ 'rows' ] );
	}

	public function test_a_lane_of_ignored_items_does_not_take_the_place_of_the_lane_to_open() :void {
		$ignored = $this->group( 'wp_themes_inactive', 'Inactive Themes', 'good', [
			'detail_shell' => 'maintenance',
			'card_type'    => 'category',
			'ignored_rows' => [ $this->row( 'Twenty', true ) ],
		] );
		$sections = ( new ActionsQueueLandingSectionsBuilder() )->build(
			[ $this->bucket( 'critical' ), $this->bucket( 'review' ) ],
			[
				'critical' => $this->layer( 'critical', [], [ $this->section( [ $ignored ] ) ] ),
				'review'   => $this->layer( 'review', [ $this->section( [ $this->group( 'malware', 'PHP Malware', 'warning', [ 'item_count' => 1 ] ) ] ) ], [] ),
			],
			[],
			[]
		);

		$this->assertSame( [ false, true ], \array_column( $sections, 'is_open' ) );
		$this->assertSame( [ false, true ], \array_column( $sections, 'is_selected_tab' ) );
	}

	public function test_the_first_lane_with_something_to_do_opens_after_an_empty_one() :void {
		$sections = ( new ActionsQueueLandingSectionsBuilder() )->build(
			[ [ 'is_interactive' => false ] + $this->bucket( 'critical' ), $this->bucket( 'review' ) ],
			[ 'review' => $this->layer( 'review', [ $this->section( [ $this->group( 'malware', 'PHP Malware', 'warning', [ 'item_count' => 1 ] ) ] ) ], [] ) ],
			[],
			[]
		);

		$this->assertSame( [ 'critical', 'review' ], \array_column( $sections, 'key' ) );
		$this->assertSame( [ false, true ], \array_column( $sections, 'is_open' ) );
		$this->assertSame( [ false, true ], \array_column( $sections, 'is_selected_tab' ) );
		$this->assertSame( [ 'good', 'warning' ], \array_column( $sections, 'status' ) );
		$this->assertSame( '', $sections[ 1 ][ 'empty_text' ] );
	}

	private function build() :array {
		$buckets = [
			$this->bucket( 'critical' ),
			$this->bucket( 'review' ),
		];
		$groups = [
			'critical' => $this->layer( 'critical', [
				$this->section( [
					$this->group( 'plugins:a', 'Plugin A', 'critical', [ 'item_count' => 1 ] ),
					$this->group( 'malware', 'PHP Malware', 'critical', [ 'item_count' => 3 ] ),
					$this->group( 'plugins:b', 'Plugin B', 'warning', [ 'item_count' => 2 ] ),
					$this->group( 'vulnerabilities', 'Vulnerabilities', 'neutral', [ 'is_pro_upsell' => true ] ),
				] ),
			], [
				$this->section( [ $this->group( 'firewall', 'Firewall', 'good' ) ] ),
			] ),
			'review'   => $this->layer( 'review', [
				$this->section( [
					$this->group( 'maintenance_wordpress', 'WordPress', 'warning', [
						'detail_shell'     => 'maintenance',
						'card_type'        => 'category',
						'maintenance_rows' => [ $this->row( 'Version', false ), $this->row( 'Admin', true ) ],
					] ),
					$this->group( 'wp_themes_inactive', 'Inactive Themes', 'good', [
						'detail_shell' => 'maintenance',
						'ignored_rows' => [ $this->row( 'Twenty', true ) ],
					] ),
					$this->group( 'afs', 'File Scan', 'neutral', [ 'enable_dialog_json' => '{}' ] ),
				] ),
			], [
				$this->section( [
					$this->group( 'plugins:a', 'Plugin A', 'good' ),
					$this->group( 'maintenance_system:healthy', 'System', 'good', [ 'detail_shell' => 'maintenance' ] ),
				] ),
			] ),
		];
		$healthyRows = [
			$this->healthyRow( 'Firewall', 'firewall', 'scans' ),
			$this->healthyRow( 'Plugin A', 'plugins:a', 'scans' ),
			$this->healthyRow( 'Server Config', 'system_server', 'maintenance' ),
		];

		return ( new ActionsQueueLandingSectionsBuilder() )->build( $buckets, $groups, $healthyRows, [ 'maintenance_system', 'maintenance_wordpress' ] );
	}

	private function bucket( string $key ) :array {
		return [
			'key'            => $key,
			'label'          => \ucfirst( $key ),
			'status'         => 'warning',
			'state_label'    => '',
			'item_count'     => 1,
			'display_count'  => 1,
			'is_interactive' => true,
			'summary_text'   => '',
			'icon_class'     => 'bi bi-'.$key,
			'selection'      => $this->selection( $key ),
		];
	}

	private function selection( string $key ) :array {
		return [
			'key'            => $key,
			'label'          => \ucfirst( $key ),
			'status'         => 'warning',
			'icon_class'     => '',
			'item_count'     => 1,
			'header'         => [],
			'selection_json' => 'bucket-'.$key.'-json',
		];
	}

	private function layer( string $key, array $active, array $healthy ) :array {
		return [
			'bucket_selection' => $this->selection( $key ),
			'active_sections'  => $active,
			'healthy_sections' => $healthy,
			'header'           => [],
		];
	}

	private function section( array $groups ) :array {
		return [
			'heading_label' => 'Heading',
			'heading_icon'  => 'bi bi-heading',
			'groups'        => $groups,
		];
	}

	private function group( string $key, string $label, string $status, array $overrides = [] ) :array {
		return \array_merge( [
			'key'                => $key,
			'label'              => $label,
			'item_count'         => 0,
			'status'             => $status,
			'status_label'       => '',
			'icon_class'         => '',
			'detail_shell'       => 'direct_table',
			'card_type'          => 'expandable',
			'is_pro_upsell'      => false,
			'enable_dialog_json' => '',
			'maintenance_rows'   => [],
			'ignored_rows'       => [],
		], $overrides );
	}

	/**
	 * An item someone ignored comes with its way back; one Shield leaves out by itself has none.
	 */
	private function row( string $title, bool $isIgnored, bool $canBeRestored = true ) :array {
		return [
			'icon_class'  => '',
			'title'       => $title,
			'inline_meta' => '',
			'summary'     => '',
			'badge_label' => '',
			'is_ignored'  => $isIgnored,
			'actions'     => $isIgnored && $canBeRestored ? [ [ 'kind' => 'unignore' ] ] : [],
		];
	}

	private function healthyRow( string $title, string $groupKey, string $zone ) :array {
		return [ 'group_key' => $groupKey, 'zone' => $zone ] + $this->row( $title, false );
	}
}
