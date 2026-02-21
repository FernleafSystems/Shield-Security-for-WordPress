<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class LogoSidebarTemplateTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testLogoSidebarRetainsLogoLinkAndDropsLegacyUtilityLinkRow() :void {
		$content = $this->getPluginFileContents(
			'templates/twig/wpadmin/components/page/logo_sidebar.twig',
			'logo sidebar template'
		);

		$this->assertStringContainsString( '{{ hrefs.plugin_home }}', $content );
		$this->assertStringContainsString( 'id="navbar-bannerlogo"', $content );

		$this->assertStringNotContainsString( 'shieldsecurityhome', $content );
		$this->assertStringNotContainsString( '{{ hrefs.facebook_group }}', $content );
		$this->assertStringNotContainsString( '{{ hrefs.helpdesk }}', $content );
		$this->assertStringNotContainsString( '{{ hrefs.email_signup }}', $content );
	}
}
