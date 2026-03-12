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

	private function renderOptionsFormAsWhitelabelled( array $options, array $extra = [] ) :string {
		$con = $this->requireController();
		$wasWhitelabelled = (bool)$con->labels->is_whitelabelled;
		$con->labels->is_whitelabelled = true;

		try {
			return $this->renderOptionsForm( $options, $extra );
		}
		finally {
			$con->labels->is_whitelabelled = $wasWhitelabelled;
		}
	}

	public function test_option_and_section_helpers_render_shared_icon_action_contract() :void {
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
		$this->assertStringContainsString( 'shield-config-icon-action', $descriptionExpander->getAttribute( 'class' ) );

		$descriptionPanel = $this->assertXPathExists(
			$xpath,
			'//*[@id="Description-session_lock" and @aria-labelledby="Label-session_lock" and contains(concat(" ", normalize-space(@class), " "), " hidden ")]',
			'Description panel target'
		);
		$this->assertInstanceOf( \DOMElement::class, $descriptionPanel );
		$this->assertSame( 'true', $descriptionPanel->getAttribute( 'aria-hidden' ) );

		$helpButton = $this->assertXPathExists(
			$xpath,
			'//div[contains(concat(" ", normalize-space(@class), " "), " shield-option-row ") and .//*[@id="Label-session_idle_timeout_interval"]]//button[contains(concat(" ", normalize-space(@class), " "), " beacon-article ")]',
			'Option beacon help button'
		);
		$this->assertInstanceOf( \DOMElement::class, $helpButton );
		$this->assertSame( (string)( $sessionIdleTimeout[ 'beacon_id' ] ?? '' ), $helpButton->getAttribute( 'data-beacon_article_id' ) );
		$this->assertSame( 'sidebar', $helpButton->getAttribute( 'data-beacon_article_format' ) );
		$this->assertStringContainsString( 'shield-config-icon-action', $helpButton->getAttribute( 'class' ) );

		$sectionHelpButton = $this->assertXPathExists(
			$xpath,
			'//section[.//*[@id="Label-session_idle_timeout_interval"]]//div[contains(concat(" ", normalize-space(@class), " "), " shield-options-panel-header-actions ")]//button[contains(concat(" ", normalize-space(@class), " "), " beacon-article ")]',
			'Section beacon help button'
		);
		$this->assertInstanceOf( \DOMElement::class, $sectionHelpButton );
		$this->assertSame( (string)( $sessionIdleTimeout[ 'beacon_id' ] ?? '' ), $sectionHelpButton->getAttribute( 'data-beacon_article_id' ) );
		$this->assertSame( 'modal', $sectionHelpButton->getAttribute( 'data-beacon_article_format' ) );
		$this->assertStringContainsString( 'shield-config-icon-action', $sectionHelpButton->getAttribute( 'class' ) );

		$videoButton = $this->assertXPathExists(
			$xpath,
			'//button[contains(concat(" ", normalize-space(@class), " "), " option-video ") and @data-vimeoid="'.(string)( $fileLocker[ 'vimeo_id' ] ?? '' ).'"]',
			'Option video button'
		);
		$this->assertInstanceOf( \DOMElement::class, $videoButton );
		$this->assertSame( (string)( $fileLocker[ 'vimeo_id' ] ?? '' ), $videoButton->getAttribute( 'data-vimeoid' ) );
		$this->assertStringContainsString( 'shield-config-icon-action', $videoButton->getAttribute( 'class' ) );
	}

	public function test_section_summary_helper_renders_collapse_contract_when_help_beacon_is_unavailable() :void {
		$html = $this->renderOptionsFormAsWhitelabelled( [
			'session_idle_timeout_interval',
		] );
		$xpath = $this->createDomXPathFromHtml( $html );

		$sectionSummaryToggle = $this->assertXPathExists(
			$xpath,
			'//section[.//*[@id="Label-session_idle_timeout_interval"]]//div[contains(concat(" ", normalize-space(@class), " "), " shield-options-panel-header-actions ")]//button[contains(concat(" ", normalize-space(@class), " "), " section_title_info ") and contains(concat(" ", normalize-space(@class), " "), " shield-config-icon-action ")]',
			'Section summary collapse toggle'
		);
		$this->assertInstanceOf( \DOMElement::class, $sectionSummaryToggle );
		$this->assertSame( 'collapse', $sectionSummaryToggle->getAttribute( 'data-bs-toggle' ) );
		$this->assertSame( '#collapse-section_user_session_management', $sectionSummaryToggle->getAttribute( 'data-bs-target' ) );
		$this->assertSame( 'collapse-section_user_session_management', $sectionSummaryToggle->getAttribute( 'aria-controls' ) );
		$this->assertSame( 'false', $sectionSummaryToggle->getAttribute( 'aria-expanded' ) );

		$sectionSummaryPanel = $this->assertXPathExists(
			$xpath,
			'//section[.//*[@id="Label-session_idle_timeout_interval"]]//*[@id="collapse-section_user_session_management" and contains(concat(" ", normalize-space(@class), " "), " collapse ") and contains(concat(" ", normalize-space(@class), " "), " shield-options-summary-collapse ")]',
			'Section summary collapse panel'
		);
		$this->assertInstanceOf( \DOMElement::class, $sectionSummaryPanel );

		$sectionHeaderBeaconButtons = $xpath->query(
			'//section[.//*[@id="Label-session_idle_timeout_interval"]]//div[contains(concat(" ", normalize-space(@class), " "), " shield-options-panel-header-actions ")]//button[contains(concat(" ", normalize-space(@class), " "), " beacon-article ")]'
		);
		$this->assertNotFalse( $sectionHeaderBeaconButtons, 'Section header beacon query failed' );
		$this->assertSame( 0, $sectionHeaderBeaconButtons->length, 'Section header beacon should not render in whitelabel summary mode' );
	}

	public function test_password_and_multiple_select_controls_render_dedicated_wrappers() :void {
		$html = $this->renderOptionsForm( [
			'admin_access_key',
			'admin_access_restrict_plugins',
		] );
		$xpath = $this->createDomXPathFromHtml( $html );

		$passwordWrapper = $this->assertXPathExists(
			$xpath,
			'//div[contains(concat(" ", normalize-space(@class), " "), " shield-option-row ") and .//*[@id="Label-admin_access_key"]]//div[contains(concat(" ", normalize-space(@class), " "), " shield-option-stacked-control ")]',
			'Password stacked control wrapper'
		);
		$this->assertInstanceOf( \DOMElement::class, $passwordWrapper );
		$this->assertXPathExists(
			$xpath,
			'//div[contains(concat(" ", normalize-space(@class), " "), " shield-option-stacked-control ")]//input[@id="Opt-admin_access_key" and @type="password"]',
			'Primary password input'
		);
		$this->assertXPathExists(
			$xpath,
			'//div[contains(concat(" ", normalize-space(@class), " "), " shield-option-stacked-control ")]//input[@id="Opt-admin_access_key_confirm" and @type="password"]',
			'Password confirmation input'
		);

		$multipleSelectWrapper = $this->assertXPathExists(
			$xpath,
			'//div[contains(concat(" ", normalize-space(@class), " "), " shield-option-row ") and .//*[@id="Label-admin_access_restrict_plugins"]]//div[contains(concat(" ", normalize-space(@class), " "), " shield-option-multiple-list ")]',
			'Multiple select list wrapper'
		);
		$this->assertInstanceOf( \DOMElement::class, $multipleSelectWrapper );
		$multipleSelectInputs = $xpath->query(
			'//div[contains(concat(" ", normalize-space(@class), " "), " shield-option-multiple-list ")]//input[@type="checkbox" and starts-with(@id, "Opt-admin_access_restrict_plugins_")]'
		);
		$this->assertNotFalse( $multipleSelectInputs, 'Multiple select checkboxes query failed' );
		$this->assertGreaterThanOrEqual( 2, $multipleSelectInputs->length, 'Multiple select should render at least two checkbox inputs' );

		$this->assertStringNotContainsString( 'shield-option-control-col--password', $html );
		$this->assertStringNotContainsString( 'shield-option-control-col--multiple-select', $html );
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
