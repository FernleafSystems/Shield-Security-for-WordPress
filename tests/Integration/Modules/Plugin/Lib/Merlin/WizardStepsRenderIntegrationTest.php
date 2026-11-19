<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Merlin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps\Integrations;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Wizards;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\ProviderPluginFixture;

/**
 * Each wizard step supplies everything its own template shows. Templates are rendered here with Twig refusing
 * anything that hasn't been supplied, so a step that loses a piece of its text fails to render instead of
 * quietly showing a gap.
 */
class WizardStepsRenderIntegrationTest extends ShieldIntegrationTestCase {

	use ProviderPluginFixture;

	private array $optionSnapshot = [];

	private ?\Closure $refuseWhatIsNotSupplied = null;

	public function set_up() {
		parent::set_up();

		$this->refuseWhatIsNotSupplied = static function ( $twig ) :void {
			$twig->enableStrictVariables();
		};
		\add_action( 'shield/services/pre_render_twig', $this->refuseWhatIsNotSupplied );

		$this->optionSnapshot = $this->snapshotSelectedOptions( [ 'form_spam_providers', 'user_form_providers' ] );
		$this->snapshotProviderPluginFixtureState();
		$this->loginAsSecurityAdmin();

		// The Integrations step lists one integration the plan covers and one it doesn't, so both are drawn.
		$this->enablePremiumCapabilities( [ 'thirdparty_scan_spam' ] );
		$this->installProviderFixture( 'ninjaforms', 'ninja-forms.php', 'Ninja_Forms', 'Ninja Forms' );
		$this->installProviderFixture(
			'easy-digital-downloads',
			'easy-digital-downloads.php',
			'Easy_Digital_Downloads',
			'Easy Digital Downloads'
		);
		$this->requireController()->opts
			 ->optSet( 'form_spam_providers', [] )
			 ->optSet( 'user_form_providers', [ 'wordpress' ] )
			 ->store();
		$this->resetProviderCaches();
	}

	public function tear_down() {
		if ( $this->refuseWhatIsNotSupplied !== null ) {
			\remove_action( 'shield/services/pre_render_twig', $this->refuseWhatIsNotSupplied );
		}
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionSnapshot );
			$this->restoreProviderPluginFixtureState();
		}
		parent::tear_down();
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'providerWizardSteps' )]
	public function test_step_supplies_everything_its_template_shows( string $stepClass ) :void {
		$html = ( new $stepClass() )->render();

		// A step that renders has its heading; one that couldn't be rendered is only the reason why.
		$this->assertStringContainsString( '<h4', $html, $html );
	}

	public static function providerWizardSteps() :array {
		$steps = [];
		foreach ( Wizards::WIZARD_STEPS_WELCOME as $stepClass ) {
			$steps[ $stepClass::SLUG ] = [ $stepClass ];
		}
		return $steps;
	}

	/**
	 * Each integration is a tick box named by its slug and sent as 'Y', which is what the step reads back when the
	 * form is sent. One the plan doesn't cover is shown but can't be ticked, so it's never sent.
	 */
	public function test_integrations_step_offers_a_tick_box_for_each_integration_waiting_to_be_switched_on() :void {
		$html = ( new Integrations() )->render();

		$covered = $this->tickBoxNamed( 'ninjaforms', $html );
		$this->assertSame( 'Y', $covered->getAttribute( 'value' ) );
		$this->assertFalse( $covered->hasAttribute( 'disabled' ) );

		$notCovered = $this->tickBoxNamed( 'easydigitaldownloads', $html );
		$this->assertSame( 'Y', $notCovered->getAttribute( 'value' ) );
		$this->assertTrue( $notCovered->hasAttribute( 'disabled' ) );
	}

	private function tickBoxNamed( string $name, string $html ) :\DOMElement {
		$dom = new \DOMDocument();
		$previous = \libxml_use_internal_errors( true );
		try {
			$dom->loadHTML( '<?xml encoding="utf-8" ?><div>'.$html.'</div>', \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD );
		}
		finally {
			\libxml_clear_errors();
			\libxml_use_internal_errors( $previous );
		}
		$tickBoxes = ( new \DOMXPath( $dom ) )->query( '//input[@type="checkbox"][@name="'.$name.'"]' );
		$this->assertSame( 1, $tickBoxes->length, $html );
		return $tickBoxes->item( 0 );
	}
}
