<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class OptionsFormForRenderIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderOptionsForm( array $options ) :string {
		$payload = $this->processActionPayloadWithAdminBypass( OptionsFormFor::SLUG, [
			'options' => $options,
		] );
		return $this->assertRouteRenderOutputHealthy( $payload, 'options form render' );
	}

	private function getOptionDefinition( string $key ) :array {
		return $this->requireController()->cfg->configuration->options[ $key ];
	}

	public function test_option_helper_renders_custom_data_attributes_without_wrapped_quotes() :void {
		$sessionIdleTimeout = $this->getOptionDefinition( 'session_idle_timeout_interval' );
		$fileLocker = $this->getOptionDefinition( 'file_locker' );
		$html = $this->renderOptionsForm( [
			'session_lock',
			'session_idle_timeout_interval',
			'file_locker',
		] );
		$xpath = $this->createDomXPathFromHtml( $html );

		$descriptionExpander = $this->assertXPathExists(
			$xpath,
			'//button[contains(concat(" ", normalize-space(@class), " "), " option-description-expander ") and @data-option_description_key="session_lock"]',
			'Description expander button'
		);
		$this->assertInstanceOf( \DOMElement::class, $descriptionExpander );
		$this->assertSame( 'session_lock', $descriptionExpander->getAttribute( 'data-option_description_key' ) );

		$descriptionPanel = $this->assertXPathExists(
			$xpath,
			'//*[@id="Description-session_lock"]',
			'Description panel target'
		);
		$this->assertInstanceOf( \DOMElement::class, $descriptionPanel );
		$this->assertStringContainsString(
			'option-description-session_lock',
			$descriptionPanel->getAttribute( 'class' )
		);

		$helpButton = $this->assertXPathExists(
			$xpath,
			'//button[contains(concat(" ", normalize-space(@class), " "), " beacon-article ") and @data-beacon_article_id="'.(string)( $sessionIdleTimeout[ 'beacon_id' ] ?? '' ).'"]',
			'Beacon help button'
		);
		$this->assertInstanceOf( \DOMElement::class, $helpButton );
		$this->assertSame( (string)( $sessionIdleTimeout[ 'beacon_id' ] ?? '' ), $helpButton->getAttribute( 'data-beacon_article_id' ) );
		$this->assertSame( 'sidebar', $helpButton->getAttribute( 'data-beacon_article_format' ) );

		$videoButton = $this->assertXPathExists(
			$xpath,
			'//button[contains(concat(" ", normalize-space(@class), " "), " option-video ") and @data-vimeoid="'.(string)( $fileLocker[ 'vimeo_id' ] ?? '' ).'"]',
			'Option video button'
		);
		$this->assertInstanceOf( \DOMElement::class, $videoButton );
		$this->assertSame( (string)( $fileLocker[ 'vimeo_id' ] ?? '' ), $videoButton->getAttribute( 'data-vimeoid' ) );
	}
}
