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

	public function test_restricted_page_renders_expected_modal_contract() :void {
		$payload = $this->processor()->processAction( PageSecurityAdminRestricted::SLUG )->payload();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertNotSame( '', $html );
		$this->assertHtmlContainsMarker( 'id="SecurityAdminOverlay"', $html, 'Security admin restricted modal root id' );
		$this->assertHtmlContainsMarker( 'modal fade', $html, 'Security admin restricted modal lifecycle classes' );
		$this->assertHtmlContainsMarker( 'data-bs-backdrop="static"', $html, 'Security admin restricted modal static backdrop' );
		$this->assertHtmlContainsMarker( 'data-bs-keyboard="false"', $html, 'Security admin restricted modal keyboard lock' );
		$this->assertHtmlContainsMarker( 'shield-modal-content-raised', $html, 'Security admin restricted modal raised offset class' );
		$this->assertHtmlContainsMarker( 'id="sec_admin_key"', $html, 'Security admin restricted modal key input' );
		$this->assertHtmlNotContainsMarker( 'autofocus', $html, 'Security admin restricted modal input autofocus attribute' );
	}
}
