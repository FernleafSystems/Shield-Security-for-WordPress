<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageDrillDownLandingBase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;

class PageDrillDownLandingBaseTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? ( \preg_replace( '/[^a-z0-9_]/', '', \strtolower( \trim( $text ) ) ) ?? '' ) : ''
		);
	}

	public function test_render_data_contains_mode_shell_and_normalized_drill_shell() :void {
		$page = new class extends PageDrillDownLandingBase {
			public const SLUG = 'test_drill_down_landing';

			protected function getLandingTitle() :string {
				return 'Drill Title';
			}

			protected function getLandingSubtitle() :string {
				return 'Drill Subtitle';
			}

			protected function getLandingIcon() :string {
				return 'shield-shaded';
			}

			protected function getLandingMode() :string {
				return 'actions';
			}

			protected function getLayers() :array {
				return [
					[
						'key'          => 'Layer One',
						'label'        => 'Overview',
						'badge'        => '3 items',
						'badge_status' => 'critical',
						'body'         => '<div>Layer 1</div>',
						'context'      => [
							'path'      => [ ' Start ', '', 'Queue ' ],
							'focus'     => '  Focus on the queue.  ',
							'next_step' => '  Pick a bucket.  ',
						],
					],
					[
						'key'          => 'bucket_detail',
						'label'        => 'Bucket Detail',
						'badge'        => 'Review',
						'badge_status' => 'warning',
						'body'         => '<div>Layer 2</div>',
						'context'      => [
							'path'      => [ 'Start', 'Queue', ' Bucket ' ],
							'focus'     => '  Narrow the queue.  ',
							'next_step' => '  Open the item.  ',
						],
					],
					[
						'key'          => 'Final Detail',
						'label'        => 'Item Detail',
						'badge'        => 'Ready',
						'badge_status' => 'unknown-status',
						'body'         => '<div>Layer 3</div>',
						'context'      => [
							'path'      => [ 'Start', 'Queue', 'Bucket', ' Item ' ],
							'focus'     => '  Review the item.  ',
							'next_step' => '  Take the action.  ',
						],
					],
					[
						'key'          => 'fourth_layer',
						'label'        => 'Dropped Layer',
						'badge'        => 'Skip',
						'badge_status' => 'good',
						'body'         => '<div>Layer 4</div>',
						'context'      => [
							'path'      => [ 'Dropped' ],
							'focus'     => 'Should not render.',
							'next_step' => 'Should not render.',
						],
					],
				];
			}

			protected function getActiveLayerIndex() :int {
				return 1;
			}

			protected function buildLandingIconClass( string $icon ) :string {
				return 'icon-'.$icon;
			}
		};

		$data = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$vars = $data[ 'vars' ] ?? [];

		$this->assertSame( 'actions', $vars[ 'mode_shell' ][ 'mode' ] ?? '' );
		$this->assertSame( 'actions_drill_shell', $vars[ 'drill_shell' ][ 'id' ] ?? '' );
		$this->assertSame( 'actions', $vars[ 'drill_shell' ][ 'mode' ] ?? '' );
		$this->assertSame( 1, $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 );
		$this->assertCount( 3, $vars[ 'drill_shell' ][ 'layers' ] ?? [] );
		$this->assertSame(
			[ 'layerone', 'bucket_detail', 'finaldetail' ],
			\array_column( $vars[ 'drill_shell' ][ 'layers' ] ?? [], 'key' )
		);
		$this->assertSame( 'neutral', $vars[ 'drill_shell' ][ 'layers' ][ 2 ][ 'badge_status' ] ?? '' );
		$this->assertSame(
			[
				'path'      => [ 'Start', 'Queue', 'Bucket' ],
				'focus'     => 'Narrow the queue.',
				'next_step' => 'Open the item.',
			],
			$vars[ 'drill_context_card' ][ 'initial_context' ] ?? []
		);
		$this->assertSame(
			[
				'header_label'       => 'Where you are',
				'context_aria_label' => 'Workflow context',
				'focus_label'        => 'Focus',
				'next_step_label'    => 'Next step',
			],
			$vars[ 'drill_context_card' ][ 'strings' ] ?? []
		);
		$this->assertArrayNotHasKey( 'index', $vars[ 'drill_shell' ][ 'layers' ][ 0 ] ?? [] );
		$this->assertArrayNotHasKey( 'is_active', $vars[ 'drill_shell' ][ 'layers' ][ 0 ] ?? [] );
	}

	public function test_out_of_range_active_index_clamps_to_zero() :void {
		$page = new class extends PageDrillDownLandingBase {
			public const SLUG = 'test_drill_down_landing_clamp';

			protected function getLandingTitle() :string {
				return 'Clamp Title';
			}

			protected function getLandingSubtitle() :string {
				return 'Clamp Subtitle';
			}

			protected function getLandingIcon() :string {
				return 'shield-shaded';
			}

			protected function getLandingMode() :string {
				return 'actions';
			}

			protected function getLayers() :array {
				return [
					[
						'key'   => 'overview',
						'label' => 'Overview',
						'body'  => '<div>Overview</div>',
					],
					[
						'key'   => 'details',
						'label' => 'Details',
						'body'  => '<div>Details</div>',
					],
				];
			}

			protected function getActiveLayerIndex() :int {
				return 9;
			}
		};

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );

		$this->assertSame( 0, $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 );
		$this->assertSame(
			[
				'path'      => [],
				'focus'     => '',
				'next_step' => '',
			],
			$vars[ 'drill_context_card' ][ 'initial_context' ] ?? []
		);
	}

	public function test_empty_layers_produce_empty_drill_contract() :void {
		$page = new class extends PageDrillDownLandingBase {
			public const SLUG = 'test_drill_down_landing_empty';

			protected function getLandingTitle() :string {
				return 'Empty Title';
			}

			protected function getLandingSubtitle() :string {
				return 'Empty Subtitle';
			}

			protected function getLandingIcon() :string {
				return 'shield-shaded';
			}

			protected function getLandingMode() :string {
				return 'actions';
			}

			protected function getLayers() :array {
				return [];
			}

			protected function getActiveLayerIndex() :int {
				return 2;
			}
		};

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );

		$this->assertSame( [], $vars[ 'drill_shell' ][ 'layers' ] ?? [ 'unexpected' ] );
		$this->assertSame( 0, $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 );
		$this->assertSame(
			[
				'path'      => [],
				'focus'     => '',
				'next_step' => '',
			],
			$vars[ 'drill_context_card' ][ 'initial_context' ] ?? []
		);
	}
}
