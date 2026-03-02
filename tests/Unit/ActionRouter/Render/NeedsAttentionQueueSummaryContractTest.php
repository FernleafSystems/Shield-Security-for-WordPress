<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller
};

class NeedsAttentionQueueSummaryContractTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_summary_contract_with_items_uses_highest_severity_and_alert_icon() :void {
		$queue = new NeedsAttentionQueue();
		$summary = $this->invokeNonPublicMethod( $queue, 'buildQueueSummaryContract', [
			true,
			4,
			[
				[ 'severity' => 'warning' ],
				[ 'severity' => 'critical' ],
			],
			'Last scan: 2 minutes ago',
		] );

		$this->assertSame( true, $summary[ 'has_items' ] );
		$this->assertSame( 4, $summary[ 'total_items' ] );
		$this->assertSame( 'critical', $summary[ 'severity' ] );
		$this->assertSame( 'bi bi-exclamation-triangle-fill', $summary[ 'icon_class' ] );
		$this->assertSame( 'Last scan: 2 minutes ago', $summary[ 'subtext' ] );
	}

	public function test_summary_contract_without_items_is_all_clear() :void {
		$queue = new NeedsAttentionQueue();
		$summary = $this->invokeNonPublicMethod( $queue, 'buildQueueSummaryContract', [
			false,
			0,
			[],
			'',
		] );

		$this->assertSame( false, $summary[ 'has_items' ] );
		$this->assertSame( 0, $summary[ 'total_items' ] );
		$this->assertSame( 'good', $summary[ 'severity' ] );
		$this->assertSame( 'bi bi-shield-check', $summary[ 'icon_class' ] );
		$this->assertSame( '', $summary[ 'subtext' ] );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		PluginControllerInstaller::install( $controller );
	}
}
