<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageSecurityAdminRestricted;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class SecurityAdminRestrictedPageIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator();
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	public function test_restricted_page_exposes_security_admin_contract() :void {
		$payload = $this->processor()->processAction( PageSecurityAdminRestricted::SLUG )->payload();
		$renderData = (array)( $payload[ 'render_data' ] ?? [] );

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertFalse( (bool)( $payload[ 'render_error' ] ?? true ) );
		$this->assertSame( PageSecurityAdminRestricted::TEMPLATE, (string)( $payload[ 'render_template' ] ?? '' ) );
		$this->assertArrayHasKey( 'allow_email_override', (array)( $renderData[ 'flags' ] ?? [] ) );
		$this->assertArrayHasKey( 'inner_page_title_icon', (array)( $renderData[ 'imgs' ] ?? [] ) );
		$this->assertArrayHasKey( 'icon_shield', (array)( $renderData[ 'imgs' ] ?? [] ) );
		$this->assertArrayHasKey( 'disable_security_admin', (array)( $renderData[ 'strings' ] ?? [] ) );
		$this->assertArrayHasKey( 'send_to_email', (array)( $renderData[ 'strings' ] ?? [] ) );
	}
}
