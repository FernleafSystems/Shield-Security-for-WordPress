<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class ProgressMetersTemplateChannelContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function test_progress_meters_template_propagates_meter_channel_to_all_cards() :void {
		$content = $this->getPluginFileContents(
			'templates/twig/wpadmin/components/progress_meter/progress_meters.twig',
			'progress meters template'
		);

		$needle = 'data-meter_channel="{{ vars.meter_channel|default(\'\') }}"';
		$this->assertSame( 2, \substr_count( $content, $needle ) );
	}
}
