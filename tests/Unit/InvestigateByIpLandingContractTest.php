<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class InvestigateByIpLandingContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testInvestigateLandingTemplateRendersInlineByIpAnalysisContract() :void {
		$content = $this->getPluginFileContents(
			'templates/twig/wpadmin/plugin_pages/inner/investigate_landing.twig',
			'investigate landing template'
		);

		$this->assertStringContainsString( '{% if flags.has_by_ip_lookup %}', $content );
		$this->assertStringContainsString( '{{ content.by_ip_analysis|raw }}', $content );
		$this->assertStringContainsString( 'flags.by_ip_is_valid', $content );
		$this->assertStringContainsString( 'strings.by_ip_invalid_text', $content );
	}

	public function testInvestigateLandingPageBuildsByIpAnalysisRenderData() :void {
		$content = $this->getPluginFileContents(
			'src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php',
			'investigate landing page class'
		);

		$this->assertStringContainsString( 'IpAnalyseContainer::class', $content );
		$this->assertStringContainsString( "'by_ip_analysis' => \$analysisContent", $content );
		$this->assertStringContainsString( "'by_ip_is_valid'   => \$isValidIp", $content );
	}
}
