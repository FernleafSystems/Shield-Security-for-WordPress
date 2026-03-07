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

	private function renderOptionsForm( array $options, array $extra = [] ) :string {
		$payload = $this->processActionPayloadWithAdminBypass(
			OptionsFormFor::SLUG,
			\array_merge( [
				'options' => $options,
			], $extra )
		);
		return $this->assertRouteRenderOutputHealthy( $payload, 'options form render' );
	}

	private function getOptionDefinition( string $key ) :array {
		return $this->requireController()->cfg->configuration->options[ $key ];
	}

	public function test_option_helper_renders_safe_custom_attributes_and_explicit_description_target() :void {
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
			'//button[contains(concat(" ", normalize-space(@class), " "), " option-description-expander ") and @aria-controls="Description-session_lock"]',
			'Description expander button'
		);
		$this->assertInstanceOf( \DOMElement::class, $descriptionExpander );
		$this->assertSame( 'Description-session_lock', $descriptionExpander->getAttribute( 'aria-controls' ) );
		$this->assertSame( 'false', $descriptionExpander->getAttribute( 'aria-expanded' ) );

		$descriptionPanel = $this->assertXPathExists(
			$xpath,
			'//*[@id="Description-session_lock" and @aria-labelledby="Label-session_lock" and contains(concat(" ", normalize-space(@class), " "), " hidden ")]',
			'Description panel target'
		);
		$this->assertInstanceOf( \DOMElement::class, $descriptionPanel );
		$this->assertSame( 'true', $descriptionPanel->getAttribute( 'aria-hidden' ) );

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

	public function test_option_description_focus_state_renders_expanded_accessibility_contract() :void {
		$html = $this->renderOptionsForm(
			[
				'session_lock',
				'session_idle_timeout_interval',
			],
			[
				'config_item' => 'session_lock',
			]
		);
		$xpath = $this->createDomXPathFromHtml( $html );

		$descriptionExpander = $this->assertXPathExists(
			$xpath,
			'//button[contains(concat(" ", normalize-space(@class), " "), " option-description-expander ") and @aria-controls="Description-session_lock"]',
			'Focused description expander button'
		);
		$this->assertInstanceOf( \DOMElement::class, $descriptionExpander );
		$this->assertSame( 'true', $descriptionExpander->getAttribute( 'aria-expanded' ) );

		$descriptionPanel = $this->assertXPathExists(
			$xpath,
			'//*[@id="Description-session_lock" and @aria-labelledby="Label-session_lock" and not(contains(concat(" ", normalize-space(@class), " "), " hidden "))]',
			'Focused description panel target'
		);
		$this->assertInstanceOf( \DOMElement::class, $descriptionPanel );
		$this->assertSame( 'false', $descriptionPanel->getAttribute( 'aria-hidden' ) );
	}
}
