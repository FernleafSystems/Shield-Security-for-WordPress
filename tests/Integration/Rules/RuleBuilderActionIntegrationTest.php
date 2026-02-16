<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\RuleBuilderAction
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Rules\RuleRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\IsPhpCli;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\EventFire;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Integration coverage for RuleBuilderAction:
 * - create_rule with realistic form payload
 * - reset behavior for saved rules
 * - sanitization + persistence into stored form structures
 */
class RuleBuilderActionIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'rules' );
		$this->enablePremiumCapabilities( [ 'custom_security_rules' ] );

		$userId = self::factory()->user->create( [
			'role' => 'administrator',
		] );
		\wp_set_current_user( $userId );

		$con = $this->requireController();
		$con->this_req->is_security_admin = true;
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

	public function test_create_rule_persists_form_data_and_sanitizes_text_fields() {
		$response = $this->processor()->processAction( RuleBuilderAction::SLUG, [
			'builder_action' => 'create_rule',
			'rule_form'      => $this->buildValidRuleForm(),
		] );

		$payload = $response->payload();
		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertStringContainsString( 'saved', \strtolower( (string)( $payload[ 'message' ] ?? '' ) ) );
		$this->assertGreaterThan( 0, (int)( $payload[ 'edit_rule_id' ] ?? 0 ) );

		$record = ( new RuleRecords() )->byID( (int)$payload[ 'edit_rule_id' ] );
		$this->assertNotNull( $record );
		$this->assertNotEmpty( $record->form, 'Ready form submission should persist saved form, not only draft data' );

		$form = $this->getStoredForm( $record );
		$this->assertNotEmpty( $form, 'Rule action must persist either saved form or draft form data' );
		$this->assertSame( 'My Rule Name', $form[ 'name' ] ?? '' );
		$this->assertSame( 'Desc with unsafe chars', $form[ 'description' ] ?? '' );
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

		$this->assertTrue( $resetPayload[ 'success' ] ?? false );
		$resetRuleId = (int)( $resetPayload[ 'edit_rule_id' ] ?? 0 );
		$this->assertGreaterThan( 0, $resetRuleId );
		$this->assertStringContainsString( 'reset', \strtolower( (string)( $resetPayload[ 'message' ] ?? '' ) ) );

		$reloaded = ( new RuleRecords() )->byID( $resetRuleId );
		$this->assertNotEmpty( $reloaded->form_draft, 'Reset should persist draft content based on saved rule form' );
	}
}
