<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageModeLandingBase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;

class PageModeLandingBaseTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
	}

	public function test_render_data_contains_shared_and_extended_sections() :void {
		$page = new class extends PageModeLandingBase {
			public const SLUG = 'test_mode_landing';

			protected function getLandingTitle() :string {
				return 'Landing Title';
			}

			protected function getLandingSubtitle() :string {
				return 'Landing Subtitle';
			}

			protected function getLandingIcon() :string {
				return 'gear';
			}

			protected function getLandingMode() :string {
				return 'configure';
			}

			protected function getLandingTiles() :array {
				return [
					[
						'key'          => 'zone_one',
						'panel_target' => 'zone_one',
						'is_enabled'   => true,
						'is_disabled'  => false,
					],
				];
			}

			protected function getLandingPanel() :array {
				return [
					'active_target' => '',
					'is_open'       => false,
				];
			}

			protected function getLandingContent() :array {
				return [ 'main' => 'content' ];
			}

			protected function getLandingFlags() :array {
				return [ 'flag' => true ];
			}

			protected function getLandingHrefs() :array {
				return [ 'home' => '/home' ];
			}

			protected function getLandingStrings() :array {
				return [ 'extra' => 'value' ];
			}

			protected function getLandingVars() :array {
				return [ 'count' => 3 ];
			}

			protected function buildLandingIconClass( string $icon ) :string {
				return 'icon-'.$icon;
			}
		};

		$data = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertSame( 'Landing Title', $data[ 'strings' ][ 'inner_page_title' ] );
		$this->assertSame( 'Landing Subtitle', $data[ 'strings' ][ 'inner_page_subtitle' ] );
		$this->assertSame( 'value', $data[ 'strings' ][ 'extra' ] );
		$this->assertSame( 'icon-gear', $data[ 'imgs' ][ 'inner_page_title_icon' ] );
		$this->assertSame( [ 'main' => 'content' ], $data[ 'content' ] );
		$this->assertSame( [ 'flag' => true ], $data[ 'flags' ] );
		$this->assertSame( [ 'home' => '/home' ], $data[ 'hrefs' ] );
		$this->assertSame( 3, $data[ 'vars' ][ 'count' ] );
		$this->assertSame( 'configure', $data[ 'vars' ][ 'mode_shell' ][ 'mode' ] );
		$this->assertSame( 'good', $data[ 'vars' ][ 'mode_shell' ][ 'accent_status' ] );
		$this->assertSame( 'compact', $data[ 'vars' ][ 'mode_shell' ][ 'header_density' ] );
		$this->assertTrue( (bool)$data[ 'vars' ][ 'mode_shell' ][ 'is_mode_landing' ] );
		$this->assertFalse( (bool)$data[ 'vars' ][ 'mode_shell' ][ 'is_interactive' ] );
		$this->assertSame( 'zone_one', $data[ 'vars' ][ 'mode_tiles' ][ 0 ][ 'key' ] );
		$this->assertSame( 'zone_one', $data[ 'vars' ][ 'mode_tiles' ][ 0 ][ 'panel_target' ] );
		$this->assertTrue( (bool)$data[ 'vars' ][ 'mode_tiles' ][ 0 ][ 'is_enabled' ] );
		$this->assertFalse( (bool)$data[ 'vars' ][ 'mode_tiles' ][ 0 ][ 'is_disabled' ] );
		$this->assertSame( '', $data[ 'vars' ][ 'mode_panel' ][ 'active_target' ] );
		$this->assertFalse( (bool)$data[ 'vars' ][ 'mode_panel' ][ 'is_open' ] );
		$this->assertSame( 'Close', $data[ 'vars' ][ 'mode_panel' ][ 'close_label' ] );
	}

	public function test_empty_optional_sections_are_not_added() :void {
		$page = new class extends PageModeLandingBase {
			public const SLUG = 'test_mode_landing_minimal';

			protected function getLandingTitle() :string {
				return 'Minimal Title';
			}

			protected function getLandingSubtitle() :string {
				return 'Minimal Subtitle';
			}

			protected function getLandingIcon() :string {
				return 'search';
			}

			protected function buildLandingIconClass( string $icon ) :string {
				return 'icon-'.$icon;
			}
		};

		$data = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertSame( 'Minimal Title', $data[ 'strings' ][ 'inner_page_title' ] );
		$this->assertSame( 'Minimal Subtitle', $data[ 'strings' ][ 'inner_page_subtitle' ] );
		$this->assertArrayNotHasKey( 'content', $data );
		$this->assertArrayNotHasKey( 'flags', $data );
		$this->assertArrayNotHasKey( 'hrefs', $data );
		$this->assertArrayNotHasKey( 'vars', $data );
	}

}
