<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\General;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class IpAnalyseGeneralVerdictBarTest extends BaseUnitTest {

	public function test_human_verdict_bar_moves_right_from_threshold() :void {
		$method = new \ReflectionMethod( General::class, 'buildVerdictBar' );

		$this->assertSame(
			[
				'pivot_percent'      => 45,
				'fill_left_percent'  => 45,
				'fill_width_percent' => 37,
				'fill_class'         => 'success',
			],
			$method->invoke( new General( [
				'ip' => '198.51.100.20',
			] ), 82, 45, false )
		);
	}

	public function test_bot_verdict_bar_moves_left_from_threshold() :void {
		$method = new \ReflectionMethod( General::class, 'buildVerdictBar' );

		$this->assertSame(
			[
				'pivot_percent'      => 45,
				'fill_left_percent'  => 12,
				'fill_width_percent' => 33,
				'fill_class'         => 'danger',
			],
			$method->invoke( new General( [
				'ip' => '198.51.100.20',
			] ), 12, 45, true )
		);
	}
}
