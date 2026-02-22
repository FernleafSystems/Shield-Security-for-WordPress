<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageModeLandingBase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class PageModeLandingBaseTest extends BaseUnitTest {

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

		$data = $this->invokeGetRenderData( $page );

		$this->assertSame( 'Landing Title', $data[ 'strings' ][ 'inner_page_title' ] );
		$this->assertSame( 'Landing Subtitle', $data[ 'strings' ][ 'inner_page_subtitle' ] );
		$this->assertSame( 'value', $data[ 'strings' ][ 'extra' ] );
		$this->assertSame( 'icon-gear', $data[ 'imgs' ][ 'inner_page_title_icon' ] );
		$this->assertSame( [ 'main' => 'content' ], $data[ 'content' ] );
		$this->assertSame( [ 'flag' => true ], $data[ 'flags' ] );
		$this->assertSame( [ 'home' => '/home' ], $data[ 'hrefs' ] );
		$this->assertSame( [ 'count' => 3 ], $data[ 'vars' ] );
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

		$data = $this->invokeGetRenderData( $page );

		$this->assertSame( 'Minimal Title', $data[ 'strings' ][ 'inner_page_title' ] );
		$this->assertSame( 'Minimal Subtitle', $data[ 'strings' ][ 'inner_page_subtitle' ] );
		$this->assertArrayNotHasKey( 'content', $data );
		$this->assertArrayNotHasKey( 'flags', $data );
		$this->assertArrayNotHasKey( 'hrefs', $data );
		$this->assertArrayNotHasKey( 'vars', $data );
	}

	private function invokeGetRenderData( PageModeLandingBase $page ) :array {
		$ref = new \ReflectionMethod( $page, 'getRenderData' );
		$ref->setAccessible( true );
		return $ref->invoke( $page );
	}
}
