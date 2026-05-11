<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\AdminNotice;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class AdminNoticeTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'wp_generate_password' )->justReturn( 'generated-id' );
		UnitTestControllerFactory::install();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	/**
	 * @dataProvider providerWpNoticeClassMappings
	 */
	public function testRenderDataIncludesWordPressNoticeClassForType( string $type, string $expectedClass ) :void {
		$component = new AdminNoticeTestDouble( [
			'raw_notice_data' => [
				'id'          => 'unit-notice',
				'type'        => $type,
				'render_data' => [
					'notice_classes' => [ 'existing-class' ],
				],
			],
		] );

		$classes = \explode( ' ', $component->renderDataForTest()[ 'notice_classes' ] );

		$this->assertContains( 'existing-class', $classes );
		$this->assertContains( $type, $classes );
		$this->assertContains( $expectedClass, $classes );
		$this->assertContains( 'notice-unit-notice', $classes );
	}

	public static function providerWpNoticeClassMappings() :array {
		return [
			'error maps to notice-error' => [ 'error', 'notice-error' ],
			'warning maps to notice-warning' => [ 'warning', 'notice-warning' ],
			'updated maps to notice-success' => [ 'updated', 'notice-success' ],
			'success maps to notice-success' => [ 'success', 'notice-success' ],
			'promo maps to notice-info' => [ 'promo', 'notice-info' ],
			'unknown maps to notice-info' => [ 'foo', 'notice-info' ],
		];
	}
}

class AdminNoticeTestDouble extends AdminNotice {

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}
}
