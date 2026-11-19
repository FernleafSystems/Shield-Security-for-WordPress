<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Merlin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionRoutingController;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MerlinAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PluginBadgeMode;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SetupGuideState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * A wizard form is text fields only. It's request input, so a form that holds anything else is refused where it
 * enters the wizard, before any step sees it: no step has to check its own fields.
 */
class WizardFormSubmitIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'admin_access_key',
			'display_plugin_badge',
			SetupGuideState::OPT_KEY,
		] );
		$this->loginAsSecurityAdmin();
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionSnapshot );
		}
		parent::tear_down();
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'providerFormsWithAFieldThatIsNotText' )]
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

	/**
	 * The page shows the reason a form was refused, and a field's name is as much request input as its value, so
	 * the reason never repeats one.
	 */
	public function test_the_reason_a_form_is_refused_repeats_nothing_that_was_sent() :void {
		$sentName = '<b data-sent="name">field</b>';
		$this->requireController()->this_req->wp_is_ajax = false;

		$payload = $this->requireController()->action_router->action(
			MerlinAction::SLUG,
			[
				ActionData::FIELD_FORM => [
					'step_slug' => 'security_badge',
					$sentName   => [ 'not text' ],
				],
			],
			ActionRoutingController::ACTION_REST
		)->payload();

		$this->assertFalse( $payload[ 'success' ] );
		$this->assertNotSame( '', $payload[ 'message' ] );
		$this->assertStringNotContainsString( 'data-sent', $payload[ 'message' ] );
		$this->assertSame( [], ( new SetupGuideState() )->doneSlugs() );
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
