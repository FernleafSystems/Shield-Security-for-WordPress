<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\RulesManagerTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Rules\RuleRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\{
	ParseRuleBuilderForm,
	RuleFormBuilderVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\IsPhpCli;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\EventFire;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\SecurityRules\BuildSecurityRulesTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules\Support\RuntimeRulesStorageAssertions;

/**
 * Integration coverage for RulesManagerTableAction CRUD/mutation paths.
 */
class RulesManagerTableActionIntegrationTest extends ShieldIntegrationTestCase {

	use RuntimeRulesStorageAssertions;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'rules' );
		$this->enablePremiumCapabilities( [ 'custom_security_rules' ] );
		\delete_transient( 'shield_dt_total_'.\md5( BuildSecurityRulesTableData::class ) );

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

	private function tableRequestData() :array {
		return [
			'start'       => 0,
			'length'      => 10,
			'search'      => [
				'value' => '',
			],
			'searchPanes' => [],
		];
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
		$this->assertContains( 'custom/activate-deactivate', $this->runtimeCustomRuleSlugs() );

		$deactivate = $this->processor()->processAction( RulesManagerTableAction::SLUG, [
			'sub_action' => 'deactivate',
			'rids'       => [ $ruleId ],
		] )->payload();

		$this->assertTrue( $deactivate[ 'success' ] ?? false );
		$this->assertSame( 0, (int)$dbh->getQuerySelector()->byId( $ruleId )->is_active );
		$this->assertNotContains( 'custom/activate-deactivate', $this->runtimeCustomRuleSlugs() );
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

	public function test_deactivate_all_disables_records_and_rebuilds_storage() {
		$dbh = $this->requireController()->db_con->rules;

		$idA = $this->createRule( 'deactivate-all-a' );
		$idB = $this->createRule( 'deactivate-all-b' );
		$dbh->getQueryUpdater()->updateById( $idA, [ 'is_active' => 1 ] );
		$dbh->getQueryUpdater()->updateById( $idB, [ 'is_active' => 1 ] );

		$this->assertNotEmpty( $this->runtimeCustomRuleSlugs() );

		$payload = $this->processor()->processAction( RulesManagerTableAction::SLUG, [
			'sub_action' => 'deactivate_all',
			'rids'       => [ $idA, $idB ],
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( 0, (int)$dbh->getQuerySelector()->byId( $idA )->is_active );
		$this->assertSame( 0, (int)$dbh->getQuerySelector()->byId( $idB )->is_active );
		$this->assertSame( [], $this->runtimeCustomRuleSlugs() );
	}

	public function test_retrieve_table_data_exposes_machine_readable_row_contract() {
		$ruleId = $this->createRule( 'table-contract' );

		$payload = $this->processor()->processAction( RulesManagerTableAction::SLUG, [
			'sub_action' => 'retrieve_table_data',
			'table_data' => $this->tableRequestData(),
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertArrayHasKey( 'datatable_data', $payload );

		$table = $payload[ 'datatable_data' ];
		$this->assertArrayHasKey( 'data', $table );
		$this->assertArrayHasKey( 'recordsTotal', $table );
		$this->assertArrayHasKey( 'recordsFiltered', $table );
		$this->assertGreaterThanOrEqual( 1, (int)$table[ 'recordsTotal' ] );
		$this->assertGreaterThanOrEqual( 1, (int)$table[ 'recordsFiltered' ] );

		$matchingRows = \array_values( \array_filter(
			$table[ 'data' ],
			static fn( array $row ) :bool => (int)( $row[ 'rid' ] ?? 0 ) === $ruleId
		) );
		$this->assertCount( 1, $matchingRows );

		$row = $matchingRows[ 0 ];
		foreach ( [ 'rid', 'active', 'actions', 'details', 'drag', 'version', 'created_since', 'is_viable' ] as $key ) {
			$this->assertArrayHasKey( $key, $row );
		}
		$this->assertSame( $ruleId, (int)$row[ 'rid' ] );
		$this->assertTrue( (bool)$row[ 'is_viable' ] );
		$this->assertNotSame( '', (string)$row[ 'active' ] );
		$this->assertNotSame( '', (string)$row[ 'actions' ] );
		$this->assertNotSame( '', (string)$row[ 'details' ] );
		$this->assertNotSame( '', (string)$row[ 'drag' ] );
		$this->assertNotSame( '', (string)$row[ 'version' ] );
		$this->assertNotSame( '', (string)$row[ 'created_since' ] );
	}

	public function test_invalid_rule_id_fails_without_mutating_records() {
		$ruleId = $this->createRule( 'invalid-id-control' );
		$before = (int)( new RuleRecords() )->byID( $ruleId )->is_active;

		$payload = $this->processor()->processAction( RulesManagerTableAction::SLUG, [
			'sub_action' => 'activate',
			'rids'       => [ 999999 ],
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertNotSame( '', (string)( $payload[ 'message' ] ?? '' ) );
		$this->assertSame( $before, (int)( new RuleRecords() )->byID( $ruleId )->is_active );
	}

	public function test_invalid_sub_action_fails_without_mutating_record() {
		$ruleId = $this->createRule( 'invalid-action-control' );
		$before = (int)( new RuleRecords() )->byID( $ruleId )->is_active;

		$payload = $this->processor()->processAction( RulesManagerTableAction::SLUG, [
			'sub_action' => 'not_supported',
			'rids'       => [ $ruleId ],
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertNotSame( '', (string)( $payload[ 'message' ] ?? '' ) );
		$this->assertSame( $before, (int)( new RuleRecords() )->byID( $ruleId )->is_active );
	}
}
