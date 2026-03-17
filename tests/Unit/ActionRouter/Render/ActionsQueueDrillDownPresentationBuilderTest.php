<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueDrillDownPresentationBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueDrillDownPresentationBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
	}

	public function test_build_bucket_selection_reuses_shared_copy_contract() :void {
		$builder = new ActionsQueueDrillDownPresentationBuilder();
		$context = [
			'path'      => [ 'Triage buckets', 'Fix now' ],
			'focus'     => 'Fix now contains 2 items that still need attention.',
			'next_step' => 'Choose a group to review the matching results.',
		];

		$this->assertSame(
			[
				'key'         => 'critical',
				'label'       => 'Fix now',
				'status'      => 'critical',
				'item_count'  => 2,
				'strip_text'  => 'Fix now - 2 items',
				'strip_badge' => '2 items',
				'context'     => $context,
			],
			$builder->buildBucketSelection( 'critical', 'Fix now', 'critical', 2, $context )
		);
		$this->assertSame( 'Fix now contains 2 items that still need attention.', $builder->buildBucketFocusText( 'Fix now', 2 ) );
	}

	public function test_build_group_selection_includes_detail_shell() :void {
		$builder = new ActionsQueueDrillDownPresentationBuilder();
		$context = [
			'path'      => [ 'Triage buckets', 'Review next', 'Maintenance Items' ],
			'focus'     => '1 maintenance item needs review.',
			'next_step' => 'Review the maintenance items and address them in the next appropriate maintenance window.',
		];

		$this->assertSame(
			[
				'key'          => 'maintenance',
				'label'        => 'Maintenance Items',
				'status'       => 'warning',
				'item_count'   => 1,
				'detail_shell' => 'maintenance',
				'strip_text'   => 'Maintenance Items - 1 item',
				'strip_badge'  => '1 item',
				'context'      => $context,
			],
			$builder->buildGroupSelection( 'maintenance', 'Maintenance Items', 'warning', 1, 'maintenance', $context )
		);
	}
}
