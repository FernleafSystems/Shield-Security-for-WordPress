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

	public function test_restricted_page_renders_expected_overlay_contract() :void {
		$payload = $this->processor()->processAction( PageSecurityAdminRestricted::SLUG )->payload();
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', $html );
		$this->assertXPathExists(
			$xpath,
			'//*[@id="SecurityAdminOverlay" and contains(concat(" ", normalize-space(@class), " "), " security-admin-overlay ") and @role="dialog" and @tabindex="-1" and @aria-labelledby="SecurityAdminLabel" and @aria-describedby="SecurityAdminDescription" and not(@aria-modal) and not(@aria-hidden) and not(@data-bs-backdrop) and not(@data-bs-keyboard)]',
			'Security admin restricted overlay contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="IcwpWpsfSecurityAdmin" and contains(@class,"shield-modal-content-raised")]',
			'Security admin restricted overlay content shell'
		);
		$this->assertXPathExists(
			$xpath,
			'//h5[@id="SecurityAdminLabel" and normalize-space()!=""]',
			'Security admin restricted overlay labelled heading'
		);
		$this->assertXPathExists(
			$xpath,
			'//p[@id="SecurityAdminDescription" and normalize-space()!=""]',
			'Security admin restricted overlay description'
		);
		$this->assertXPathExists(
			$xpath,
			'//label[@for="sec_admin_key" and contains(@class,"visually-hidden")]',
			'Security admin restricted overlay hidden input label'
		);
		$this->assertXPathExists(
			$xpath,
			'//input[@id="sec_admin_key" and @type="password"]',
			'Security admin restricted overlay key input'
		);
		$this->assertHtmlNotContainsMarker( 'autofocus', $html, 'Security admin restricted modal input autofocus attribute' );
		$this->assertHtmlNotContainsMarker( 'data-bs-backdrop', $html, 'Security admin restricted overlay bootstrap backdrop marker' );
		$this->assertHtmlNotContainsMarker( 'aria-modal', $html, 'Security admin restricted overlay bootstrap aria-modal marker' );
	}
}
