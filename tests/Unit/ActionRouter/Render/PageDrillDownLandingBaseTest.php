<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageDrillDownLandingBase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\UnitTestControllerFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\UnitTestPluginUrls;

class PageDrillDownLandingBaseTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? ( \preg_replace( '/[^a-z0-9_]/', '', \strtolower( \trim( $text ) ) ) ?? '' ) : ''
		);
		UnitTestControllerFactory::install( new UnitTestPluginUrls() );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
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
						'key'    => 'Layer One',
						'body'   => '<div>Layer 1</div>',
						'header' => [
							'compact_back_label' => '  Back to Overview  ',
							'title'              => ' Overview ',
							'badge'              => ' 3 items ',
							'badge_status'       => 'critical',
						],
					],
					[
						'key'    => 'bucket_detail',
						'body'   => '<div>Layer 2</div>',
						'header' => [
							'compact_back_label' => ' Back to Bucket Detail ',
							'active_back_label'  => ' Back to Queue ',
							'title'              => ' Bucket Detail ',
							'meta'               => ' Warning ',
							'summary'            => ' Narrow the queue. ',
							'icon_class'         => ' bi bi-eye ',
							'badge'              => ' Review ',
							'badge_status'       => 'warning',
						],
					],
					[
						'key'    => 'Final Detail',
						'body'   => '<div>Layer 3</div>',
						'header' => [
							'active_back_label' => 'Back to Bucket Detail',
							'title'             => 'Item Detail',
							'badge'             => 'Ready',
							'badge_status'      => 'unknown-status',
						],
					],
					[
						'key'    => 'fourth_layer',
						'body'   => '<div>Layer 4</div>',
						'header' => [
							'title' => 'Dropped Layer',
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
		$drillShell = $vars[ 'drill_shell' ] ?? [];
		$layers = $drillShell[ 'layers' ] ?? [];

		$this->assertSame( 'actions', $vars[ 'mode_shell' ][ 'mode' ] ?? '' );
		$this->assertNotSame( '', $vars[ 'mode_shell' ][ 'root_step' ][ 'title' ] ?? '' );
		$this->assertNotSame( '', $vars[ 'mode_shell' ][ 'root_step' ][ 'summary' ] ?? '' );
		$this->assertSame( 'actions_drill_shell', $drillShell[ 'id' ] ?? '' );
		$this->assertSame( 'actions', $drillShell[ 'mode' ] ?? '' );
		$this->assertSame( 1, $drillShell[ 'active_index' ] ?? -1 );
		$this->assertCount( 3, $layers );
		$this->assertSame(
			[ 'layerone', 'bucket_detail', 'finaldetail' ],
			\array_column( $layers, 'key' )
		);
		$this->assertSame( 'neutral', $layers[ 2 ][ 'header' ][ 'badge_status' ] ?? '' );
		$this->assertSame( 'warning', $layers[ 1 ][ 'header' ][ 'badge_status' ] ?? '' );
		$this->assertSame( 'warning', $layers[ 1 ][ 'header' ][ 'color_key' ] ?? '' );
		$this->assertSame( [], $layers[ 1 ][ 'header' ][ 'actions' ] ?? null );
		$layerIDs = [];
		foreach ( $layers as $layerIndex => $layer ) {
			$this->assertSame(
				\sprintf( 'actions_drill_shell_layer_%d_%s', $layerIndex, $layer[ 'key' ] ?? '' ),
				(string)( $layer[ 'id' ] ?? '' )
			);
			$this->assertNotContains( $layer[ 'id' ] ?? '', $layerIDs );
			$layerIDs[] = $layer[ 'id' ] ?? '';
			$this->assertSame( ( $layer[ 'id' ] ?? '' ).'_title', $layer[ 'title_id' ] ?? '' );
			$this->assertNotSame( '', $layer[ 'header' ][ 'title' ] ?? '' );
			$this->assertSame(
				$layer[ 'header' ] ?? [],
				\json_decode( (string)( $layer[ 'header_json' ] ?? '' ), true )
			);
			$this->assertArrayNotHasKey( 'index', $layer );
			$this->assertArrayNotHasKey( 'is_active', $layer );
		}
		$this->assertArrayNotHasKey( 'drill_context_card', $vars );
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
						'key'    => 'overview',
						'body'   => '<div>Overview</div>',
						'header' => [
							'title' => 'Overview',
						],
					],
					[
						'key'    => 'details',
						'body'   => '<div>Details</div>',
						'header' => [
							'title' => 'Details',
						],
					],
				];
			}

			protected function getActiveLayerIndex() :int {
				return 9;
			}
		};

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );

		$this->assertSame( 0, $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 );
		$this->assertArrayNotHasKey( 'drill_context_card', $vars );
	}

	public function test_layers_without_non_empty_titles_are_rejected_at_producer_boundary() :void {
		$page = new class extends PageDrillDownLandingBase {
			public const SLUG = 'test_drill_down_landing_required_titles';

			protected function getLandingTitle() :string {
				return 'Contract Title';
			}

			protected function getLandingSubtitle() :string {
				return 'Contract Subtitle';
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
						'key'    => 'valid_one',
						'body'   => '<div>Valid 1</div>',
						'header' => [
							'title' => 'Valid One',
						],
					],
					[
						'key'  => 'missing_title',
						'body' => '<div>Missing title</div>',
					],
					[
						'key'    => 'whitespace_title',
						'body'   => '<div>Whitespace title</div>',
						'header' => [
							'title' => '   ',
						],
					],
					[
						'key'    => 'valid_two',
						'body'   => '<div>Valid 2</div>',
						'header' => [
							'title' => 'Valid Two',
						],
					],
				];
			}

			protected function getActiveLayerIndex() :int {
				return 2;
			}
		};

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$drillShell = $vars[ 'drill_shell' ] ?? [];
		$layers = $drillShell[ 'layers' ] ?? [];

		$this->assertSame( [ 'valid_one', 'valid_two' ], \array_column( $layers, 'key' ) );
		$this->assertSame( 0, $drillShell[ 'active_index' ] ?? -1 );

		$ids = [];
		$titleIDs = [];
		foreach ( $layers as $layerIndex => $layer ) {
			$this->assertNotSame( '', $layer[ 'header' ][ 'title' ] ?? '' );
			$this->assertSame(
				\sprintf( 'actions_drill_shell_layer_%d_%s', $layerIndex, $layer[ 'key' ] ?? '' ),
				(string)( $layer[ 'id' ] ?? '' )
			);
			$ids[] = $layer[ 'id' ] ?? '';
			$titleIDs[] = $layer[ 'title_id' ] ?? '';
		}

		$this->assertSame( $ids, \array_values( \array_unique( $ids ) ) );
		$this->assertSame( $titleIDs, \array_values( \array_unique( $titleIDs ) ) );
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
		$this->assertArrayNotHasKey( 'drill_context_card', $vars );
	}
}
