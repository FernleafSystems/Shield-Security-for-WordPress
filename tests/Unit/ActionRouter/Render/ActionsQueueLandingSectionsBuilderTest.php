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
		$layout = $this->build();
		$sections = $layout[ 'sections' ];

		$this->assertTrue( $layout[ 'has_lanes' ] );
		$this->assertSame( [ 'critical', 'review', 'passing', 'not_covered' ], \array_column( $sections, 'key' ) );
		$this->assertSame( [ true, false, false, false ], \array_column( $sections, 'is_open' ) );
		$this->assertSame( [ true, true, false, true ], \array_column( $sections, 'show_count' ) );
		$this->assertSame( 'bucket-critical-json', $sections[ 0 ][ 'selection_json' ] );
	}

	public function test_lane_boxes_follow_kind_order_with_single_rows_sharing_a_box() :void {
		$critical = $this->build()[ 'sections' ][ 0 ];

		// Malware comes before plugins; the two single-row plugin groups share one box under the kind's heading.
		$this->assertSame(
			[ [ 'shared', 'malware' ], [ 'shared', 'plugins' ] ],
			\array_map( fn( array $box ) :array => [ $box[ 'type' ], $box[ 'kind' ] ], $critical[ 'boxes' ] )
		);
		$this->assertSame( 'Plugins with scan results', $critical[ 'boxes' ][ 1 ][ 'heading' ] );
		$this->assertCount( 2, $critical[ 'boxes' ][ 1 ][ 'entries' ] );
		$this->assertFalse( $critical[ 'boxes' ][ 1 ][ 'collapsible' ] );
		$this->assertSame(
			// The breakdown keeps the order the groups came in.
			[ [ 'label' => 'Plugin A', 'count' => 1 ], [ 'label' => 'PHP Malware', 'count' => 3 ], [ 'label' => 'Plugin B', 'count' => 2 ] ],
			$critical[ 'breakdown' ]
		);
	}

	public function test_review_lane_folds_its_boxes_and_counts_only_items_still_to_do() :void {
		$review = $this->build()[ 'sections' ][ 1 ];

		$this->assertSame( [ 'group', 'group' ], \array_column( $review[ 'boxes' ], 'type' ) );
		$this->assertSame( [ true, true ], \array_column( $review[ 'boxes' ], 'collapsible' ) );
		// The fully ignored group stays in the lane but counts for nothing; the other counts its rows not ignored.
		$this->assertSame( [ [ 'label' => 'WordPress', 'count' => 1 ] ], $review[ 'breakdown' ] );
	}

	public function test_passing_lists_each_check_once_and_never_one_a_lane_needs() :void {
		$passing = $this->build()[ 'sections' ][ 2 ];

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

	public function test_not_covered_gathers_upsells_and_turned_off_protections() :void {
		$notCovered = $this->build()[ 'sections' ][ 3 ];

		$this->assertSame( 'pro', $notCovered[ 'status' ] );
		$this->assertSame( 'Vulnerabilities, File Scan', $notCovered[ 'summary' ] );
		$this->assertSame( [ 'plain' ], \array_column( $notCovered[ 'boxes' ], 'type' ) );
	}

	public function test_without_lanes_only_passing_shows() :void {
		$layout = ( new ActionsQueueLandingSectionsBuilder() )->build(
			[ $this->bucket( 'critical' ) ],
			[ 'critical' => $this->layer( 'critical', [], [ $this->section( [ $this->group( 'firewall', 'Firewall', 'good' ) ] ) ] ) ],
			[],
			[]
		);

		$this->assertFalse( $layout[ 'has_lanes' ] );
		$this->assertSame( [ 'passing' ], \array_column( $layout[ 'sections' ], 'key' ) );
	}

	private function build() :array {
		$buckets = [
			$this->bucket( 'critical' ),
			$this->bucket( 'review' ),
			[ 'is_interactive' => false ] + $this->bucket( 'info' ),
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

	private function row( string $title, bool $isIgnored ) :array {
		return [
			'icon_class'  => '',
			'title'       => $title,
			'inline_meta' => '',
			'summary'     => '',
			'badge_label' => '',
			'is_ignored'  => $isIgnored,
			'actions'     => [],
		];
	}

	private function healthyRow( string $title, string $groupKey, string $zone ) :array {
		return [ 'group_key' => $groupKey, 'zone' => $zone ] + $this->row( $title, false );
	}
}
