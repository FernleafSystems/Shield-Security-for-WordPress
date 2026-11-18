<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Merlin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PluginBadgeMode;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * A wizard form is text fields only. It's request input, so a form that holds anything else is refused where it
 * enters the wizard, before any step sees it: no step has to check its own fields.
 */
class WizardFormSubmitIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [ 'admin_access_key', 'display_plugin_badge' ] );
		$this->loginAsSecurityAdmin();
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionSnapshot );
		}
		parent::tear_down();
	}

	/**
	 * @dataProvider providerFormsWithAFieldThatIsNotText
	 */
	public function test_a_form_with_a_field_that_is_not_text_is_refused( array $form ) :void {
		$this->expectException( ActionException::class );
		$this->requireController()->comps->wizards->processFormSubmit( $form );
	}

	public static function providerFormsWithAFieldThatIsNotText() :array {
		return [
			'the step'             => [ [ 'step_slug' => [ 'integrations' ] ] ],
			'the protection level' => [ [ 'step_slug' => 'apply_security_profile', 'security_profile' => [ 'light' ] ] ],
			'the PIN'              => [ [ 'step_slug' => 'security_admin', 'SecAdminPIN' => [ 'pin' ], 'SecAdminPINConfirm' => [ 'pin' ] ] ],
			'the badge'            => [ [ 'step_slug' => 'security_badge', 'SecurityPluginBadge' => [ 'Y' ] ] ],
			'an integration'       => [ [ 'step_slug' => 'integrations', 'ninjaforms' => [ 'Y' ] ] ],
			'a field no step uses' => [ [ 'step_slug' => 'security_badge', 'SecurityPluginBadge' => 'Y', 'extra' => 1 ] ],
		];
	}

	public function test_a_refused_form_changes_nothing() :void {
		$con = $this->requireController();
		$con->opts->optSet( 'display_plugin_badge', PluginBadgeMode::DISABLED )->store();

		try {
			// On its own the badge field would switch the badge on.
			$con->comps->wizards->processFormSubmit( [
				'step_slug'           => 'security_badge',
				'SecurityPluginBadge' => 'Y',
				'extra'               => [ 'not text' ],
			] );
			$this->fail( 'The form was not refused.' );
		}
		catch ( ActionException $e ) {
			$this->assertSame( PluginBadgeMode::DISABLED, $con->opts->optGet( 'display_plugin_badge' ) );
		}
	}
}
