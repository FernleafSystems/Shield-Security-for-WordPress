<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PageAdminPlugin;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;

class PageAdminPluginBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias(
			fn( string $text ) :string => $text
		);
		Functions\when( 'sanitize_key' )->alias(
			fn( string $key ) :string => \strtolower( \trim( $key ) )
		);
	}

	public function test_non_activity_route_does_not_include_investigate_context_inputs() :void {
		$action = new PageAdminPlugin( [
			'user_lookup' => '33',
			'subject'     => 'ip',
		] );

		$data = $this->invokeBuildDelegateActionData(
			$action,
			PluginNavs::NAV_REPORTS,
			PluginNavs::SUBNAV_REPORTS_OVERVIEW
		);

		$this->assertSame(
			[
				Constants::NAV_ID     => PluginNavs::NAV_REPORTS,
				Constants::NAV_SUB_ID => PluginNavs::SUBNAV_REPORTS_OVERVIEW,
			],
			$data
		);
	}

	public function test_activity_overview_route_preserves_provided_investigate_context_inputs() :void {
		$action = new PageAdminPlugin( [
			'user_lookup' => '33',
			'subject'     => 'ip',
		] );

		$data = $this->invokeBuildDelegateActionData(
			$action,
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_OVERVIEW
		);

		$this->assertSame( '33', $data[ 'user_lookup' ] ?? '' );
		$this->assertSame( 'ip', $data[ 'subject' ] ?? '' );
	}

	public function test_activity_legacy_by_user_route_overrides_subject_with_canonical_user_key() :void {
		$action = new PageAdminPlugin( [
			'user_lookup' => 'admin@example.com',
			'subject'     => 'ip',
		] );

		$data = $this->invokeBuildDelegateActionData(
			$action,
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_BY_USER
		);

		$this->assertSame( 'admin@example.com', $data[ 'user_lookup' ] ?? '' );
		$this->assertSame( 'user', $data[ 'subject' ] ?? '' );
	}

	public function test_activity_legacy_by_core_route_sets_canonical_core_subject_without_lookup() :void {
		$action = new PageAdminPlugin();

		$data = $this->invokeBuildDelegateActionData(
			$action,
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_BY_CORE
		);

		$this->assertSame( 'core', $data[ 'subject' ] ?? '' );
	}

	private function invokeBuildDelegateActionData( PageAdminPlugin $action, string $nav, string $subNav ) :array {
		return $this->invokeNonPublicMethod( $action, 'buildDelegateActionData', [ $nav, $subNav ] );
	}
}
