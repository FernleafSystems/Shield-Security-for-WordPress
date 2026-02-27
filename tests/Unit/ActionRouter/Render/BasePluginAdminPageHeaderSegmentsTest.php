<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\BasePluginAdminPage;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;

class BasePluginAdminPageHeaderSegmentsTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	public function test_duplicate_terminal_leaf_is_suppressed() :void {
		$segments = $this->buildSegments(
			[
				[
					'text'  => 'Shield Security',
					'href'  => '/dashboard/overview',
					'title' => 'Navigation: Mode Selector',
				],
				[
					'text'  => 'Investigate',
					'href'  => '/activity/overview',
					'title' => 'Navigation: Investigate',
				],
			],
			'investigate',
			'No inner page title set'
		);

		$this->assertCount( 2, $segments );
		$this->assertSame( [ 'Shield Security', 'Investigate' ], \array_column( $segments, 'text' ) );
	}

	public function test_distinct_leaf_is_appended_as_non_link_segment() :void {
		$segments = $this->buildSegments(
			[
				[
					'text'  => 'Shield Security',
					'href'  => '/dashboard/overview',
					'title' => 'Navigation: Mode Selector',
				],
				[
					'text'  => 'Investigate',
					'href'  => '/activity/overview',
					'title' => 'Navigation: Investigate',
				],
			],
			'View Activity Logs',
			'No inner page title set'
		);

		$this->assertCount( 3, $segments );
		$this->assertSame( 'View Activity Logs', $segments[ 2 ][ 'text' ] ?? '' );
		$this->assertSame( '', $segments[ 2 ][ 'href' ] ?? '' );
		$this->assertSame( '', $segments[ 2 ][ 'title' ] ?? '' );
	}

	public function test_breadcrumb_link_metadata_is_preserved() :void {
		$segments = $this->buildSegments(
			[
				[
					'text'  => 'Shield Security',
					'href'  => '/dashboard/overview',
					'title' => 'Navigation: Mode Selector',
				],
				[
					'text'  => 'Actions Queue',
					'href'  => '/scans/overview',
					'title' => 'Navigation: Actions Queue',
				],
			],
			'Scan Results',
			'No inner page title set'
		);

		$this->assertSame( '/dashboard/overview', $segments[ 0 ][ 'href' ] ?? '' );
		$this->assertSame( 'Navigation: Mode Selector', $segments[ 0 ][ 'title' ] ?? '' );
		$this->assertSame( '/scans/overview', $segments[ 1 ][ 'href' ] ?? '' );
		$this->assertSame( 'Navigation: Actions Queue', $segments[ 1 ][ 'title' ] ?? '' );
	}

	public function test_empty_breadcrumbs_with_leaf_yields_single_non_link_segment() :void {
		$segments = $this->buildSegments( [], 'Investigate', 'No inner page title set' );
		$this->assertCount( 1, $segments );
		$this->assertSame( 'Investigate', $segments[ 0 ][ 'text' ] ?? '' );
		$this->assertSame( '', $segments[ 0 ][ 'href' ] ?? '' );
		$this->assertSame( '', $segments[ 0 ][ 'title' ] ?? '' );
	}

	public function test_empty_leaf_uses_fallback_title() :void {
		$segments = $this->buildSegments( [], '', 'No inner page title set' );
		$this->assertCount( 1, $segments );
		$this->assertSame( 'No inner page title set', $segments[ 0 ][ 'text' ] ?? '' );
	}

	private function buildSegments( array $breadcrumbs, string $innerPageTitle, string $fallbackTitle ) :array {
		$page = new class extends BasePluginAdminPage {
			public const SLUG = 'test_plugin_admin_page_header_segments';
		};

		return $this->invokeNonPublicMethod( $page, 'buildInnerPageHeaderSegments', [
			$breadcrumbs,
			$innerPageTitle,
			$fallbackTitle,
		] );
	}
}

