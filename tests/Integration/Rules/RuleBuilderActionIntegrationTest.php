<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\Components\Rules\RuleBuilder as RuleBuilderRender,
	Actions\RuleBuilderAction
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Rules\RuleRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\IsPhpCli;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\ParseRuleBuilderForm;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\EventFire;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules\Support\RuntimeRulesStorageAssertions;

/**
 * Integration coverage for RuleBuilderAction:
 * - create_rule with realistic form payload
 * - reset behavior for saved rules
 * - sanitization + persistence into stored form structures
 */
class RuleBuilderActionIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;
	use RuntimeRulesStorageAssertions;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'rules' );
		$this->enablePremiumCapabilities( [ 'custom_security_rules' ] );

		$this->loginAsSecurityAdmin();
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function buildValidRuleForm( array $overrides = [] ) :array {
		return \array_merge( [
			'edit_rule_id'                    => -1,
			'rule_name'                       => 'My <Rule> Name!!!',
			'rule_description'                => 'Desc with <unsafe> chars!!!',
			'conditions_logic'                => 'AND',
			'condition_1'                     => IsPhpCli::Slug(),
			'response_1'                      => EventFire::Slug(),
			'response_1_param_event'          => 'frontpage_load',
			'checkbox_auto_include_bypass'    => 'Y',
			'checkbox_accept_rules_warning'   => 'Y',
		], $overrides );
	}

	private function getStoredForm( $record ) :array {
		$form = !empty( $record->form ) ? $record->form : $record->form_draft;
		return \is_array( $form ) ? $form : [];
	}

	private function assertRuleBuilderPayloadContract( array $payload ) :void {
		$this->assertArrayHasKey( 'success', $payload );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertArrayHasKey( 'edit_rule_id', $payload );
		$this->assertIsBool( $payload[ 'success' ] );
		$this->assertIsString( $payload[ 'message' ] );
		$this->assertIsNumeric( $payload[ 'edit_rule_id' ] );
	}

	/**
	 * @dataProvider readinessMatrixProvider
	 */
	public function test_rule_builder_form_readiness_matrix(
		array $overrides,
		array $unsetFields,
		bool $expectedReady,
		?string $expectedAutoInclude = null
	) :void {
		$form = $this->buildValidRuleForm( $overrides );
		foreach ( $unsetFields as $field ) {
			unset( $form[ $field ] );
		}

		$parsed = ( new ParseRuleBuilderForm( $form ) )->parseForm();

		$this->assertSame( $expectedReady, $parsed->ready_to_create );
		if ( $expectedAutoInclude !== null ) {
			$this->assertSame(
				$expectedAutoInclude,
				$parsed->checks[ 'checkbox_auto_include_bypass' ][ 'value' ] ?? null
			);
		}
	}

	public static function readinessMatrixProvider() :array {
		return [
			'normal policy accepted'                => [ [], [], true ],
			'missing policy defaults to accepted Y' => [ [], [ 'checkbox_auto_include_bypass' ], true, 'Y' ],
			'normal policy warning rejected'        => [ [ 'checkbox_accept_rules_warning' => 'N' ], [], false ],
			'normal policy warning absent'          => [ [], [ 'checkbox_accept_rules_warning' ], false ],
			'opt-out accepted with both warnings'   => [ [
				'checkbox_auto_include_bypass'        => 'N',
				'checkbox_has_bypass_all_inverted'    => 'Y',
				'checkbox_accept_rules_warning'       => 'Y',
			], [], true ],
			'opt-out lockout warning rejected'      => [ [
				'checkbox_auto_include_bypass'        => 'N',
				'checkbox_has_bypass_all_inverted'    => 'N',
			], [], false ],
			'opt-out lockout warning absent'        => [ [
				'checkbox_auto_include_bypass' => 'N',
			], [ 'checkbox_has_bypass_all_inverted' ], false ],
			'opt-out advanced warning rejected'     => [ [
				'checkbox_auto_include_bypass'        => 'N',
				'checkbox_has_bypass_all_inverted'    => 'Y',
				'checkbox_accept_rules_warning'       => 'N',
			], [], false ],
			'opt-out advanced warning absent'       => [ [
				'checkbox_auto_include_bypass'     => 'N',
				'checkbox_has_bypass_all_inverted' => 'Y',
			], [ 'checkbox_accept_rules_warning' ], false ],
			'unsupported policy rejected'           => [ [
				'checkbox_auto_include_bypass'        => 'unsupported',
				'checkbox_has_bypass_all_inverted'    => 'Y',
				'checkbox_accept_rules_warning'       => 'Y',
			], [], false ],
			'base description gate rejected'        => [ [ 'rule_description' => '' ], [], false ],
		];
	}

	public function test_create_rule_persists_form_data_and_sanitizes_text_fields() {
		$response = $this->processor()->processAction( RuleBuilderAction::SLUG, [
			'builder_action' => 'create_rule',
			'rule_form'      => $this->buildValidRuleForm(),
		] );

		$payload = $response->payload();
		$this->assertRuleBuilderPayloadContract( $payload );
		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertGreaterThan( 0, (int)( $payload[ 'edit_rule_id' ] ?? 0 ) );

		$record = ( new RuleRecords() )->byID( (int)$payload[ 'edit_rule_id' ] );
		$this->assertNotNull( $record );
		$this->assertNotEmpty( $record->form, 'Ready form submission should persist saved form, not only draft data' );
		$this->assertSame( 'my-rule-name', $record->slug );
		$this->assertSame( 0, (int)$record->is_active, 'New saved custom rules should remain inactive until manager activation.' );

		$form = $this->getStoredForm( $record );
		$this->assertNotEmpty( $form, 'Rule action must persist either saved form or draft form data' );
		$this->assertSame( 'My Rule Name', $form[ 'name' ] ?? '' );
		$this->assertSame( 'Desc with unsafe chars', $form[ 'description' ] ?? '' );
		$this->assertSame( 'Y', $form[ 'checks' ][ 'checkbox_auto_include_bypass' ][ 'value' ] ?? '' );
		$this->assertSame( 'Y', $form[ 'checks' ][ 'checkbox_accept_rules_warning' ][ 'value' ] ?? '' );
		$this->assertSame( IsPhpCli::Slug(), $form[ 'conditions' ][ 'condition_1' ][ 'value' ] ?? '' );
		$this->assertSame( EventFire::Slug(), $form[ 'responses' ][ 'response_1' ][ 'value' ] ?? '' );
		$this->assertSame(
			'frontpage_load',
			$form[ 'responses' ][ 'response_1' ][ 'params' ][ 'event' ][ 'value' ] ?? ''
		);
	}

	public function test_create_rule_without_capability_persists_only_draft_and_no_active_runtime_rule() {
		$this->disablePremiumCapabilities();

		$response = $this->processor()->processAction( RuleBuilderAction::SLUG, [
			'builder_action' => 'create_rule',
			'rule_form'      => $this->buildValidRuleForm( [
				'rule_name' => 'No Capability Runtime Rule',
			] ),
		] );

		$payload = $response->payload();
		$this->assertRuleBuilderPayloadContract( $payload );
		$this->assertTrue( $payload[ 'success' ] ?? false );

		$record = ( new RuleRecords() )->byID( (int)$payload[ 'edit_rule_id' ] );
		$this->assertEmpty( $record->form );
		$this->assertNotEmpty( $record->form_draft );
		$this->assertSame( 0, (int)$record->is_active );
		$this->assertSame( [], ( new RuleRecords() )->getActiveCustom() );

		$this->enablePremiumCapabilities( [ 'custom_security_rules' ] );
		$this->assertSame( [], $this->runtimeCustomRuleSlugs() );
	}

	public function test_create_rule_missing_required_check_persists_draft_without_runtime_rule() {
		$response = $this->processor()->processAction( RuleBuilderAction::SLUG, [
			'builder_action' => 'create_rule',
			'rule_form'      => $this->buildValidRuleForm( [
				'rule_name'                     => 'Missing Required Check',
				'checkbox_accept_rules_warning' => 'N',
			] ),
		] );

		$payload = $response->payload();
		$this->assertRuleBuilderPayloadContract( $payload );
		$this->assertTrue( $payload[ 'success' ] ?? false );

		$record = ( new RuleRecords() )->byID( (int)$payload[ 'edit_rule_id' ] );
		$this->assertEmpty( $record->form );
		$this->assertNotEmpty( $record->form_draft );
		$this->assertSame( 0, (int)$record->is_active );

		$draft = $record->form_draft;
		$this->assertFalse( (bool)( $draft[ 'ready_to_create' ] ?? true ) );
		$this->assertSame( 'N', $draft[ 'checks' ][ 'checkbox_accept_rules_warning' ][ 'value' ] ?? '' );
		$this->assertSame( [], ( new RuleRecords() )->getActiveCustom() );
		$this->assertSame( [], $this->runtimeCustomRuleSlugs() );
	}

	public function test_create_rule_with_accepted_opt_out_persists_saved_form_without_coercion() :void {
		$response = $this->processor()->processAction( RuleBuilderAction::SLUG, [
			'builder_action' => 'create_rule',
			'rule_form'      => $this->buildValidRuleForm( [
				'rule_name'                           => 'Accepted Opt Out Rule',
				'checkbox_auto_include_bypass'        => 'N',
				'checkbox_has_bypass_all_inverted'    => 'Y',
				'checkbox_accept_rules_warning'       => 'Y',
			] ),
		] );

		$payload = $response->payload();
		$this->assertRuleBuilderPayloadContract( $payload );
		$this->assertTrue( $payload[ 'success' ] ?? false );

		$record = ( new RuleRecords() )->byID( (int)$payload[ 'edit_rule_id' ] );
		$this->assertNotEmpty( $record->form );
		$this->assertEmpty( $record->form_draft );
		$this->assertSame( 0, (int)$record->is_apply_default );
		$this->assertSame( 0, (int)$record->is_active );

		$form = $record->form;
		$this->assertTrue( (bool)( $form[ 'ready_to_create' ] ?? false ) );
		$this->assertSame( 'N', $form[ 'checks' ][ 'checkbox_auto_include_bypass' ][ 'value' ] ?? null );
		$this->assertSame( 'Y', $form[ 'checks' ][ 'checkbox_has_bypass_all_inverted' ][ 'value' ] ?? null );
		$this->assertSame( 'Y', $form[ 'checks' ][ 'checkbox_accept_rules_warning' ][ 'value' ] ?? null );
		$this->assertSame( [], ( new RuleRecords() )->getActiveCustom() );
		$this->assertSame( [], $this->runtimeCustomRuleSlugs() );

		$renderPayload = $this->processActionPayloadWithAdminBypass( RuleBuilderRender::SLUG, [
			'edit_rule_id' => (int)$record->id,
		] );
		$this->assertRouteRenderOutputHealthy( $renderPayload, 'saved opt-out rule' );
		$renderData = (array)( $renderPayload[ 'render_data' ] ?? [] );
		$this->assertSame( (int)$record->id, (int)( $renderData[ 'vars' ][ 'edit_rule_id' ] ?? 0 ) );
		$this->assertSame( 'N', $renderData[ 'vars' ][ 'form_data' ][ 'checks' ][ 'checkbox_auto_include_bypass' ][ 'value' ] ?? null );
		$this->assertSame( 'Y', $renderData[ 'vars' ][ 'form_data' ][ 'checks' ][ 'checkbox_has_bypass_all_inverted' ][ 'value' ] ?? null );
		$this->assertSame( 'Y', $renderData[ 'vars' ][ 'form_data' ][ 'checks' ][ 'checkbox_accept_rules_warning' ][ 'value' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'allow_submit' ] ?? null );
	}

	public function test_existing_rule_update_and_render_use_opt_out_draft_readiness() :void {
		$createResponse = $this->processor()->processAction( RuleBuilderAction::SLUG, [
			'builder_action' => 'create_rule',
			'rule_form'      => $this->buildValidRuleForm( [
				'rule_name' => 'Opt Out Update Rule',
			] ),
		] );
		$ruleID = (int)( $createResponse->payload()[ 'edit_rule_id' ] ?? 0 );
		$this->assertGreaterThan( 0, $ruleID );

		$validOptOut = $this->buildValidRuleForm( [
			'edit_rule_id'                        => $ruleID,
			'rule_name'                           => 'Opt Out Update Rule',
			'checkbox_auto_include_bypass'        => 'N',
			'checkbox_has_bypass_all_inverted'    => 'Y',
			'checkbox_accept_rules_warning'       => 'Y',
		] );
		$updateResponse = $this->processor()->processAction( RuleBuilderAction::SLUG, [
			'builder_action' => 'update',
			'rule_form'      => $validOptOut,
		] );
		$this->assertTrue( $updateResponse->payload()[ 'success' ] ?? false );
		$this->assertSame( $ruleID, (int)( $updateResponse->payload()[ 'edit_rule_id' ] ?? 0 ) );

		$record = ( new RuleRecords() )->byID( $ruleID );
		$this->assertNotEmpty( $record->form );
		$this->assertNotEmpty( $record->form_draft );
		$this->assertSame( 'Y', $record->form[ 'checks' ][ 'checkbox_auto_include_bypass' ][ 'value' ] ?? null );
		$this->assertTrue( (bool)( $record->form_draft[ 'ready_to_create' ] ?? false ) );
		$this->assertSame( 'N', $record->form_draft[ 'checks' ][ 'checkbox_auto_include_bypass' ][ 'value' ] ?? null );
		$this->assertSame( 'Y', $record->form_draft[ 'checks' ][ 'checkbox_has_bypass_all_inverted' ][ 'value' ] ?? null );
		$this->assertSame( 'Y', $record->form_draft[ 'checks' ][ 'checkbox_accept_rules_warning' ][ 'value' ] ?? null );

		$renderPayload = $this->processActionPayloadWithAdminBypass( RuleBuilderRender::SLUG, [
			'edit_rule_id' => $ruleID,
		] );
		$this->assertRouteRenderOutputHealthy( $renderPayload, 'valid opt-out draft' );
		$renderData = (array)( $renderPayload[ 'render_data' ] ?? [] );
		$this->assertSame( $ruleID, (int)( $renderData[ 'vars' ][ 'edit_rule_id' ] ?? 0 ) );
		$this->assertSame( 'N', $renderData[ 'vars' ][ 'form_data' ][ 'checks' ][ 'checkbox_auto_include_bypass' ][ 'value' ] ?? null );
		$this->assertSame( 'Y', $renderData[ 'vars' ][ 'form_data' ][ 'checks' ][ 'checkbox_has_bypass_all_inverted' ][ 'value' ] ?? null );
		$this->assertSame( 'Y', $renderData[ 'vars' ][ 'form_data' ][ 'checks' ][ 'checkbox_accept_rules_warning' ][ 'value' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'allow_submit' ] ?? null );

		$invalidOptOut = $validOptOut;
		$invalidOptOut[ 'checkbox_has_bypass_all_inverted' ] = 'N';
		$invalidResponse = $this->processor()->processAction( RuleBuilderAction::SLUG, [
			'builder_action' => 'update',
			'rule_form'      => $invalidOptOut,
		] );
		$this->assertTrue( $invalidResponse->payload()[ 'success' ] ?? false );
		$this->assertSame( $ruleID, (int)( $invalidResponse->payload()[ 'edit_rule_id' ] ?? 0 ) );

		$record = ( new RuleRecords() )->byID( $ruleID );
		$this->assertNotEmpty( $record->form );
		$this->assertFalse( (bool)( $record->form_draft[ 'ready_to_create' ] ?? true ) );
		$this->assertSame( 'N', $record->form_draft[ 'checks' ][ 'checkbox_auto_include_bypass' ][ 'value' ] ?? null );
		$this->assertSame( 'N', $record->form_draft[ 'checks' ][ 'checkbox_has_bypass_all_inverted' ][ 'value' ] ?? null );
		$this->assertSame( 'Y', $record->form_draft[ 'checks' ][ 'checkbox_accept_rules_warning' ][ 'value' ] ?? null );

		$renderPayload = $this->processActionPayloadWithAdminBypass( RuleBuilderRender::SLUG, [
			'edit_rule_id' => $ruleID,
		] );
		$this->assertRouteRenderOutputHealthy( $renderPayload, 'invalid opt-out draft' );
		$renderData = (array)( $renderPayload[ 'render_data' ] ?? [] );
		$this->assertSame( $ruleID, (int)( $renderData[ 'vars' ][ 'edit_rule_id' ] ?? 0 ) );
		$this->assertSame( 'N', $renderData[ 'vars' ][ 'form_data' ][ 'checks' ][ 'checkbox_auto_include_bypass' ][ 'value' ] ?? null );
		$this->assertSame( 'N', $renderData[ 'vars' ][ 'form_data' ][ 'checks' ][ 'checkbox_has_bypass_all_inverted' ][ 'value' ] ?? null );
		$this->assertSame( 'Y', $renderData[ 'vars' ][ 'form_data' ][ 'checks' ][ 'checkbox_accept_rules_warning' ][ 'value' ] ?? null );
		$this->assertSame( false, $renderData[ 'flags' ][ 'allow_submit' ] ?? null );
	}

	public function test_reset_action_on_saved_rule_creates_draft_from_saved_form() {
		$dbh = $this->requireController()->db_con->rules;

		$createResponse = $this->processor()->processAction( RuleBuilderAction::SLUG, [
			'builder_action' => 'create_rule',
			'rule_form'      => $this->buildValidRuleForm(),
		] );
		$createPayload = $createResponse->payload();
		$ruleId = (int)( $createPayload[ 'edit_rule_id' ] ?? 0 );
		$this->assertGreaterThan( 0, $ruleId );

		// Ensure there's a saved form to reset from.
		$record = ( new RuleRecords() )->byID( $ruleId );
		$formToStore = $this->getStoredForm( $record );
		$this->assertNotEmpty( $formToStore );
		$dbh->getQueryUpdater()->updateById( $ruleId, [
			'form' => \base64_encode( \wp_json_encode( $formToStore ) ),
		] );

		$resetResponse = $this->processor()->processAction( RuleBuilderAction::SLUG, [
			'builder_action' => 'reset',
			'rule_form'      => [
				'edit_rule_id' => $ruleId,
			],
		] );
		$resetPayload = $resetResponse->payload();

		$this->assertRuleBuilderPayloadContract( $resetPayload );
		$this->assertTrue( $resetPayload[ 'success' ] ?? false );
		$resetRuleId = (int)( $resetPayload[ 'edit_rule_id' ] ?? 0 );
		$this->assertGreaterThan( 0, $resetRuleId );

		$reloaded = ( new RuleRecords() )->byID( $resetRuleId );
		$this->assertNotEmpty( $reloaded->form_draft, 'Reset should persist draft content based on saved rule form' );
	}
}
