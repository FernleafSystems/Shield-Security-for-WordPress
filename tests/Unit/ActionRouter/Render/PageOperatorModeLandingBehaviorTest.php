<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageOperatorModeLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;

class PageOperatorModeLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count ) :string => $count === 1 ? $single : $plural
		);
	}

	public function test_build_actions_hero_single_item_uses_singular_copy() :void {
		$page = new PageOperatorModeLanding();
		$hero = $this->invokeNonPublicMethod( $page, 'buildActionsHero', [
			[
				'has_items'   => true,
				'total_items' => 1,
				'severity'    => 'critical',
				'icon_class'  => 'bi bi-exclamation-triangle-fill',
				'subtext'     => 'Last scan: 2 minutes ago',
			],
		] );

		$this->assertSame( 'critical', $hero[ 'severity' ] ?? '' );
		$this->assertSame( 'critical', $hero[ 'badge_status' ] ?? '' );
		$this->assertSame( 'bi bi-exclamation-triangle-fill', $hero[ 'icon_class' ] ?? '' );
		$this->assertSame( 'Last scan: 2 minutes ago', $hero[ 'meta' ] ?? '' );
		$this->assertSame( '1 item', $hero[ 'badge_text' ] ?? '' );
		$this->assertStringContainsString( '1 issue needs your attention', $hero[ 'subtitle' ] ?? '' );
	}

	public function test_build_actions_hero_uses_normalized_summary_contract() :void {
		$page = new PageOperatorModeLanding();
		$hero = $this->invokeNonPublicMethod( $page, 'buildActionsHero', [
			[
				'has_items'   => true,
				'total_items' => 2,
				'severity'    => 'warning',
				'icon_class'  => 'bi bi-exclamation-triangle-fill',
				'subtext'     => 'Last scan: 4 minutes ago',
			],
		] );

		$this->assertSame( 'warning', $hero[ 'severity' ] ?? '' );
		$this->assertSame( 'warning', $hero[ 'badge_status' ] ?? '' );
		$this->assertSame( 'bi bi-exclamation-triangle-fill', $hero[ 'icon_class' ] ?? '' );
		$this->assertSame( 'Last scan: 4 minutes ago', $hero[ 'meta' ] ?? '' );
		$this->assertSame( '2 items', $hero[ 'badge_text' ] ?? '' );
		$this->assertStringContainsString( '2 issues need your attention', $hero[ 'subtitle' ] ?? '' );
	}

	public function test_build_actions_hero_all_clear_branch_uses_good_defaults() :void {
		$page = new PageOperatorModeLanding();
		$hero = $this->invokeNonPublicMethod( $page, 'buildActionsHero', [
			[
				'has_items'   => false,
				'total_items' => 0,
				'severity'    => 'good',
				'icon_class'  => 'bi bi-shield-check',
				'subtext'     => '',
			],
		] );

		$this->assertSame( 'good', $hero[ 'severity' ] ?? '' );
		$this->assertSame( 'good', $hero[ 'badge_status' ] ?? '' );
		$this->assertSame( 'bi bi-shield-check', $hero[ 'icon_class' ] ?? '' );
		$this->assertSame( 'All clear', $hero[ 'badge_text' ] ?? '' );
		$this->assertSame( 'All clear - no issues require your attention', $hero[ 'subtitle' ] ?? '' );
	}
}
