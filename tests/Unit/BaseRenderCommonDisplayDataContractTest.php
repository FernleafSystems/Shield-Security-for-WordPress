<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class BaseRenderCommonDisplayDataContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testLegacySidebarSocialAndIconKeysArePrunedFromCommonDisplayData() :void {
		$content = $this->getPluginFileContents(
			'src/ActionRouter/Actions/Render/BaseRender.php',
			'base render action'
		);

		$this->assertStringContainsString( "'plugin_home'    => \$con->labels->PluginURI,", $content );
		$this->assertStringContainsString( "'facebook'    => \$con->svgs->iconClass( 'facebook' ),", $content );

		$this->assertStringNotContainsString( "'helpdesk'       => \$con->labels->url_helpdesk,", $content );
		$this->assertStringNotContainsString( "'facebook_group' => 'https://clk.shldscrty.com/pluginshieldsecuritygroupfb',", $content );
		$this->assertStringNotContainsString( "'email_signup'   => 'https://clk.shldscrty.com/emailsubscribe',", $content );
		$this->assertStringNotContainsString( "'helpdesk'    => \$con->svgs->iconClass( 'life-preserver' ),", $content );
		$this->assertStringNotContainsString( "'newsletter'  => \$con->svgs->iconClass( 'envelope' ),", $content );
		$this->assertStringNotContainsString( "'home'        => \$con->svgs->iconClass( 'house-door' ),", $content );
	}
}

