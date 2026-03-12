<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueuePayload;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class NeedsAttentionQueueSummaryContractTest extends BaseUnitTest {

	public function test_extracts_summary_from_render_payload_contract() :void {
		$summary = NeedsAttentionQueuePayload::summary( [
			'render_data' => [
				'vars' => [
					'summary' => [
						'has_items'   => true,
						'total_items' => 4,
						'severity'    => 'critical',
						'icon_class'  => 'bi bi-exclamation-triangle-fill',
						'subtext'     => 'Last scan: 2 minutes ago',
					],
				],
			],
		] );

		$this->assertSame( true, $summary[ 'has_items' ] );
		$this->assertSame( 4, $summary[ 'total_items' ] );
		$this->assertSame( 'critical', $summary[ 'severity' ] );
		$this->assertSame( 'bi bi-exclamation-triangle-fill', $summary[ 'icon_class' ] );
		$this->assertSame( 'Last scan: 2 minutes ago', $summary[ 'subtext' ] );
	}

	public function test_prefers_render_data_summary_contract_over_top_level_vars() :void {
		$summary = NeedsAttentionQueuePayload::summary( [
			'vars'        => [
				'summary' => [
					'has_items'   => false,
					'total_items' => 0,
					'severity'    => 'good',
					'icon_class'  => 'wrong-path',
					'subtext'     => 'wrong-path',
				],
			],
			'render_data' => [
				'vars' => [
					'summary' => [
						'has_items'   => true,
						'total_items' => 3,
						'severity'    => 'warning',
						'icon_class'  => 'from-render-data',
						'subtext'     => 'from-render-data',
					],
				],
			],
		] );

		$this->assertSame( true, $summary[ 'has_items' ] );
		$this->assertSame( 3, $summary[ 'total_items' ] );
		$this->assertSame( 'warning', $summary[ 'severity' ] );
		$this->assertSame( 'from-render-data', $summary[ 'icon_class' ] );
		$this->assertSame( 'from-render-data', $summary[ 'subtext' ] );
	}

	public function test_safe_summary_uses_defaults_when_payload_contract_is_missing() :void {
		$summary = NeedsAttentionQueuePayload::summary(
			[],
			[
				'has_items'   => false,
				'total_items' => 0,
				'severity'    => 'good',
				'icon_class'  => 'bi bi-shield-check',
				'subtext'     => '',
			]
		);

		$this->assertSame( false, $summary[ 'has_items' ] );
		$this->assertSame( 0, $summary[ 'total_items' ] );
		$this->assertSame( 'good', $summary[ 'severity' ] );
		$this->assertSame( 'bi bi-shield-check', $summary[ 'icon_class' ] );
		$this->assertSame( '', $summary[ 'subtext' ] );
	}

	public function test_safe_zone_group_extractor_normalizes_zone_groups() :void {
		$groups = NeedsAttentionQueuePayload::zoneGroups( [
			'render_data' => [
				'vars' => [
					'zone_groups' => [
						[
							'slug'         => 'scans',
							'label'        => 'Scans',
							'icon_class'   => 'bi bi-shield-exclamation',
							'severity'     => 'critical',
							'total_issues' => 4,
							'items'        => [
								[ 'key' => 'malware' ],
							],
						],
						[
							'slug' => 'maintenance',
						],
						'not-an-array',
					],
				],
			],
		] );

		$this->assertSame(
			[
				[
					'slug'         => 'scans',
					'label'        => 'Scans',
					'icon_class'   => 'bi bi-shield-exclamation',
					'severity'     => 'critical',
					'total_issues' => 4,
					'items'        => [
						[
							'key'         => 'malware',
							'zone'        => '',
							'label'       => '',
							'count'       => 0,
							'severity'    => 'good',
							'description' => '',
							'href'        => '',
							'action'      => '',
							'target'      => '',
						],
					],
				],
				[
					'slug'         => 'maintenance',
					'label'        => '',
					'icon_class'   => '',
					'severity'     => 'good',
					'total_issues' => 0,
					'items'        => [],
				],
			],
			$groups
		);
	}

	public function test_zone_group_returns_matching_group_or_normalized_empty_group() :void {
		$payload = [
			'render_data' => [
				'vars' => [
					'zone_groups' => [
						[
							'slug'         => 'scans',
							'label'        => 'Scans',
							'icon_class'   => 'bi bi-shield-exclamation',
							'severity'     => 'critical',
							'total_issues' => 2,
							'items'        => [],
						],
					],
				],
			],
		];

		$this->assertSame( 'scans', NeedsAttentionQueuePayload::zoneGroup( $payload, 'scans' )[ 'slug' ] );
		$this->assertSame(
			[
				'slug'         => 'maintenance',
				'label'        => '',
				'icon_class'   => '',
				'severity'     => 'good',
				'total_issues' => 0,
				'items'        => [],
			],
			NeedsAttentionQueuePayload::zoneGroup( $payload, 'maintenance' )
		);
	}

	public function test_counts_from_items_uses_item_level_severity_totals() :void {
		$counts = NeedsAttentionQueuePayload::countsFromItems( [
			$this->buildQueueItem( 'critical', 2 ),
			$this->buildQueueItem( 'warning', 1 ),
			$this->buildQueueItem( 'warning', 3 ),
			$this->buildQueueItem( 'good', 99 ),
		] );

		$this->assertSame( 2, $counts[ 'critical' ] );
		$this->assertSame( 4, $counts[ 'warning' ] );
	}

	public function test_counts_from_zone_groups_aggregates_item_level_counts() :void {
		$counts = NeedsAttentionQueuePayload::countsFromZoneGroups( [
			[
					'slug'         => 'scans',
					'label'        => 'Scans',
					'icon_class'   => 'bi bi-scans',
					'severity'     => 'critical',
					'total_issues' => 3,
					'items'        => [
						$this->buildQueueItem( 'critical', 1 ),
						$this->buildQueueItem( 'warning', 2 ),
					],
				],
			[
					'slug'         => 'maintenance',
					'label'        => 'Maintenance',
					'icon_class'   => 'bi bi-maintenance',
					'severity'     => 'warning',
					'total_issues' => 2,
					'items'        => [
						$this->buildQueueItem( 'critical', 1 ),
						$this->buildQueueItem( 'warning', 1 ),
					],
				],
			] );

		$this->assertSame( 2, $counts[ 'critical' ] );
		$this->assertSame( 3, $counts[ 'warning' ] );
	}

	public function test_strings_returns_defaults_for_missing_payload_keys() :void {
		$strings = NeedsAttentionQueuePayload::strings(
			[],
			[
				'all_clear_title'      => 'All security zones are clear',
				'all_clear_subtitle'   => 'Nothing requires your action.',
				'all_clear_icon_class' => 'bi bi-shield-check',
			]
		);

		$this->assertSame( 'All security zones are clear', $strings[ 'all_clear_title' ] );
		$this->assertSame( 'Nothing requires your action.', $strings[ 'all_clear_subtitle' ] );
		$this->assertSame( 'bi bi-shield-check', $strings[ 'all_clear_icon_class' ] );
	}

	/**
	 * @return array{
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
	private function buildQueueItem( string $severity, int $count ) :array {
		return [
			'key'         => $severity.'-'.$count,
			'zone'        => 'scans',
			'label'       => 'Item',
			'count'       => $count,
			'severity'    => $severity,
			'description' => '',
			'href'        => '',
			'action'      => '',
			'target'      => '',
		];
	}
}
