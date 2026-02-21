<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class ConfigureLandingHeroMeterContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testConfigureLandingTemplateUsesHeroMeterSurface() :void {
		$content = $this->getPluginFileContents(
			'templates/twig/wpadmin/plugin_pages/inner/configure_landing.twig',
			'configure landing template'
		);

		$this->assertStringContainsString( 'progress-metercard progress-metercard-hero progress-metercard-summary', $content );
		$this->assertStringContainsString( 'data-meter_channel="config"', $content );
		$this->assertStringContainsString( '{{ content.hero_meter|raw }}', $content );
		$this->assertStringNotContainsString( '{{ vars.config_meter_percentage }}%', $content );
	}
}
