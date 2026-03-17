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

		$selection = $builder->buildBucketSelection( 'critical', 'Fix now', 'critical', 2, $context );

		$this->assertSame( 'critical', $selection[ 'key' ] );
		$this->assertSame( 'Fix now', $selection[ 'label' ] );
		$this->assertSame( 'critical', $selection[ 'status' ] );
		$this->assertSame( 2, $selection[ 'item_count' ] );
		$this->assertSame( 'Fix now - 2 items', $selection[ 'strip_text' ] );
		$this->assertSame( '2 items', $selection[ 'strip_badge' ] );
		$this->assertSame( $context, $selection[ 'context' ] );
		$this->assertSame(
			'{"path":["Triage buckets","Fix now"],"focus":"Fix now contains 2 items that still need attention.","next_step":"Choose a group to review the matching results."}',
			$selection[ 'context_json' ]
		);
		$selectionForJson = $selection;
		unset( $selectionForJson[ 'selection_json' ] );
		$this->assertSame( $selectionForJson, \json_decode( $selection[ 'selection_json' ], true ) );
		$this->assertSame( 'Fix now contains 2 items that still need attention.', $builder->buildBucketFocusText( 'Fix now', 2 ) );
	}

	public function test_build_group_selection_includes_detail_shell() :void {
		$builder = new ActionsQueueDrillDownPresentationBuilder();
		$context = [
			'path'      => [ 'Triage buckets', 'Review next', 'Maintenance Items' ],
			'focus'     => '1 maintenance item needs review.',
			'next_step' => 'Review the maintenance items and address them in the next appropriate maintenance window.',
		];

		$selection = $builder->buildGroupSelection( 'maintenance', 'Maintenance Items', 'warning', 1, 'maintenance', $context );

		$this->assertSame( 'maintenance', $selection[ 'key' ] );
		$this->assertSame( 'Maintenance Items', $selection[ 'label' ] );
		$this->assertSame( 'warning', $selection[ 'status' ] );
		$this->assertSame( 1, $selection[ 'item_count' ] );
		$this->assertSame( 'maintenance', $selection[ 'detail_shell' ] );
		$this->assertSame( 'Maintenance Items - 1 item', $selection[ 'strip_text' ] );
		$this->assertSame( '1 item', $selection[ 'strip_badge' ] );
		$this->assertSame( $context, $selection[ 'context' ] );
		$this->assertSame(
			'{"path":["Triage buckets","Review next","Maintenance Items"],"focus":"1 maintenance item needs review.","next_step":"Review the maintenance items and address them in the next appropriate maintenance window."}',
			$selection[ 'context_json' ]
		);
		$selectionForJson = $selection;
		unset( $selectionForJson[ 'selection_json' ] );
		$this->assertSame( $selectionForJson, \json_decode( $selection[ 'selection_json' ], true ) );
	}
}
