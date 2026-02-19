<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\RulesManagerTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\{
	ParseRuleBuilderForm,
	RuleFormBuilderVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\IsPhpCli;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\EventFire;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Integration coverage for RulesManagerTableAction CRUD/mutation paths.
 */
class RulesManagerTableActionIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'rules' );

		$this->loginAsSecurityAdmin();
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function buildForm( string $name ) :RuleFormBuilderVO {
		$form = ( new ParseRuleBuilderForm( [
			'edit_rule_id'                  => -1,
			'rule_name'                     => $name,
			'rule_description'              => $name.' description',
			'conditions_logic'              => 'AND',
			'condition_1'                   => IsPhpCli::Slug(),
			'response_1'                    => EventFire::Slug(),
			'response_1_param_event'        => 'frontpage_load',
			'checkbox_auto_include_bypass'  => 'Y',
			'checkbox_accept_rules_warning' => 'Y',
		] ) )->parseForm();

		$form->form_builder_version = self::con()->cfg->version();
		return $form;
	}

	private function createRule( string $name ) :int {
		$dbh = self::con()->db_con->rules;
		$dbh->insertFromForm( $this->buildForm( $name ), false );
		$record = $dbh->getQuerySelector()
					  ->setOrderBy( 'id', 'DESC', true )
					  ->setLimit( 1 )
					  ->first();
		return (int)$record->id;
	}

	public function test_activate_and_deactivate_rule() {
		$dbh = $this->requireController()->db_con->rules;
		$ruleId = $this->createRule( 'activate-deactivate' );

		$dbh->getQueryUpdater()->updateById( $ruleId, [ 'is_active' => 0 ] );

		$activate = $this->processor()->processAction( RulesManagerTableAction::SLUG, [
			'sub_action' => 'activate',
			'rids'       => [ $ruleId ],
		] )->payload();

		$this->assertTrue( $activate[ 'success' ] ?? false );
		$this->assertSame( 1, (int)$dbh->getQuerySelector()->byId( $ruleId )->is_active );

		$deactivate = $this->processor()->processAction( RulesManagerTableAction::SLUG, [
			'sub_action' => 'deactivate',
			'rids'       => [ $ruleId ],
		] )->payload();

		$this->assertTrue( $deactivate[ 'success' ] ?? false );
		$this->assertSame( 0, (int)$dbh->getQuerySelector()->byId( $ruleId )->is_active );
	}

	public function test_export_flag_toggles() {
		$dbh = $this->requireController()->db_con->rules;
		$ruleId = $this->createRule( 'export-flag' );

		$setExport = $this->processor()->processAction( RulesManagerTableAction::SLUG, [
			'sub_action' => 'set_to_export',
			'rids'       => [ $ruleId ],
		] )->payload();
		$this->assertTrue( $setExport[ 'success' ] ?? false );
		$this->assertSame( 1, (int)$dbh->getQuerySelector()->byId( $ruleId )->can_export );

		$setNoExport = $this->processor()->processAction( RulesManagerTableAction::SLUG, [
			'sub_action' => 'set_no_export',
			'rids'       => [ $ruleId ],
		] )->payload();
		$this->assertTrue( $setNoExport[ 'success' ] ?? false );
		$this->assertSame( 0, (int)$dbh->getQuerySelector()->byId( $ruleId )->can_export );
	}

	public function test_reorder_updates_exec_order() {
		$dbh = $this->requireController()->db_con->rules;

		$idA = $this->createRule( 'rule-a' );
		$idB = $this->createRule( 'rule-b' );
		$idC = $this->createRule( 'rule-c' );

		$payload = $this->processor()->processAction( RulesManagerTableAction::SLUG, [
			'sub_action' => 'reorder',
			'rids'       => [ $idC, $idA, $idB ],
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( 1, (int)$dbh->getQuerySelector()->byId( $idC )->exec_order );
		$this->assertSame( 2, (int)$dbh->getQuerySelector()->byId( $idA )->exec_order );
		$this->assertSame( 3, (int)$dbh->getQuerySelector()->byId( $idB )->exec_order );
	}

	public function test_delete_removes_record() {
		$dbh = $this->requireController()->db_con->rules;
		$ruleId = $this->createRule( 'delete-me' );
		$this->assertNotEmpty( $dbh->getQuerySelector()->byId( $ruleId ) );

		$payload = $this->processor()->processAction( RulesManagerTableAction::SLUG, [
			'sub_action' => 'delete',
			'rids'       => [ $ruleId ],
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertEmpty( $dbh->getQuerySelector()->byId( $ruleId ) );
	}
}
