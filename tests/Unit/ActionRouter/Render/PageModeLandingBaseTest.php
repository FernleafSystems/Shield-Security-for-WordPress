<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageModeLandingBase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\UnitTestControllerFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\UnitTestPluginUrls;

class PageModeLandingBaseTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		UnitTestControllerFactory::install( new UnitTestPluginUrls() );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
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

		$this->assertArrayHasKey( 'inner_page_title', $data[ 'strings' ] ?? [] );
		$this->assertArrayHasKey( 'inner_page_subtitle', $data[ 'strings' ] ?? [] );
		$this->assertSame( 'value', $data[ 'strings' ][ 'extra' ] );
		$this->assertSame( 'icon-gear', $data[ 'imgs' ][ 'inner_page_title_icon' ] );
		$this->assertSame( [ 'main' => 'content' ], $data[ 'content' ] );
		$this->assertSame( [ 'flag' => true ], $data[ 'flags' ] );
		$this->assertSame( [ 'home' => '/home' ], $data[ 'hrefs' ] );
		$this->assertSame( 3, $data[ 'vars' ][ 'count' ] );
		$this->assertSame( 'configure', $data[ 'vars' ][ 'mode_shell' ][ 'mode' ] );
		$this->assertArrayNotHasKey( 'accent_status', $data[ 'vars' ][ 'mode_shell' ] );
		$this->assertSame( 'compact', $data[ 'vars' ][ 'mode_shell' ][ 'header_density' ] );
		$this->assertSame( '/admin/home', $data[ 'vars' ][ 'mode_shell' ][ 'home_href' ] ?? '' );
		$this->assertArrayHasKey( 'home_label', $data[ 'vars' ][ 'mode_shell' ] ?? [] );
		$this->assertTrue( (bool)$data[ 'vars' ][ 'mode_shell' ][ 'is_mode_landing' ] );
		$this->assertFalse( (bool)$data[ 'vars' ][ 'mode_shell' ][ 'is_interactive' ] );
		$this->assertTrue( (bool)$data[ 'vars' ][ 'mode_shell' ][ 'use_operator_chrome' ] );
		$rootStep = $data[ 'vars' ][ 'mode_shell' ][ 'root_step' ] ?? [];
		$this->assertSame( $data[ 'strings' ][ 'inner_page_title' ], $rootStep[ 'breadcrumb_label' ] ?? '' );
		$this->assertSame( $data[ 'strings' ][ 'inner_page_subtitle' ], $rootStep[ 'summary' ] ?? '' );
		$this->assertSame( 'configure', $rootStep[ 'color_key' ] ?? '' );
		$this->assertArrayNotHasKey( 'display_options', $rootStep );
		$this->assertSame(
			$rootStep,
			\json_decode( (string)( $data[ 'vars' ][ 'mode_shell' ][ 'root_step_json' ] ?? '' ), true )
		);
		$this->assertSame( 'zone_one', $data[ 'vars' ][ 'mode_tiles' ][ 0 ][ 'key' ] );
		$this->assertSame( 'zone_one', $data[ 'vars' ][ 'mode_tiles' ][ 0 ][ 'panel_target' ] );
		$this->assertTrue( (bool)$data[ 'vars' ][ 'mode_tiles' ][ 0 ][ 'is_enabled' ] );
		$this->assertFalse( (bool)$data[ 'vars' ][ 'mode_tiles' ][ 0 ][ 'is_disabled' ] );
		$this->assertSame( '', $data[ 'vars' ][ 'mode_panel' ][ 'active_target' ] );
		$this->assertFalse( (bool)$data[ 'vars' ][ 'mode_panel' ][ 'is_open' ] );
		$this->assertArrayHasKey( 'close_label', $data[ 'vars' ][ 'mode_panel' ] ?? [] );
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

		$this->assertArrayHasKey( 'inner_page_title', $data[ 'strings' ] ?? [] );
		$this->assertArrayHasKey( 'inner_page_subtitle', $data[ 'strings' ] ?? [] );
		$this->assertArrayNotHasKey( 'content', $data );
		$this->assertArrayNotHasKey( 'flags', $data );
		$this->assertArrayNotHasKey( 'hrefs', $data );
		$this->assertArrayNotHasKey( 'vars', $data );
	}

}
