<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageSecurityAdminRestricted;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\HtmlDomAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class SecurityAdminRestrictedPageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;

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
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', $html );
		$this->assertXPathExists(
			$xpath,
			'//*[@id="SecurityAdminOverlay" and contains(@class,"modal") and @aria-labelledby="SecurityAdminLabel" and @aria-modal="true" and @data-bs-backdrop="static" and @data-bs-keyboard="false"]',
			'Security admin restricted modal contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="IcwpWpsfSecurityAdmin" and contains(@class,"shield-modal-content-raised")]',
			'Security admin restricted modal content shell'
		);
		$this->assertXPathExists(
			$xpath,
			'//h5[@id="SecurityAdminLabel" and normalize-space()!=""]',
			'Security admin restricted modal labelled heading'
		);
		$this->assertXPathExists(
			$xpath,
			'//label[@for="sec_admin_key" and contains(@class,"visually-hidden")]',
			'Security admin restricted modal hidden input label'
		);
		$this->assertXPathExists(
			$xpath,
			'//input[@id="sec_admin_key" and @type="password"]',
			'Security admin restricted modal key input'
		);
		$this->assertHtmlNotContainsMarker( 'autofocus', $html, 'Security admin restricted modal input autofocus attribute' );
	}
}
