<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\RuleBuilderAction,
	Actions\RulesManagerTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Rules\RuleRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions\IsRequestStatus404,
	Conditions\RequestBypassesAllRestrictions,
	Enum\EnumLogic,
	Processors\ProcessConditions,
	Processors\ResponseProcessor,
	Responses\TriggerIpBlock,
	RuleVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules\Support\RuntimeRulesStorageAssertions;

class Custom404RuleBehaviorIntegrationTest extends ShieldIntegrationTestCase {

	use RuntimeRulesStorageAssertions;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'rules' );
		$this->enablePremiumCapabilities( [ 'custom_security_rules' ] );
		$this->loginAsSecurityAdmin();
		$this->resetRuntimeRequestState();
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function ruleForm() :array {
		return [
			'edit_rule_id'                  => -1,
			'rule_name'                     => 'custom_404_block',
			'rule_description'              => 'custom_404_block description',
			'conditions_logic'              => EnumLogic::LOGIC_AND,
			'condition_1'                   => IsRequestStatus404::Slug(),
			'response_1'                    => TriggerIpBlock::Slug(),
			'checkbox_auto_include_bypass'  => 'Y',
			'checkbox_accept_rules_warning' => 'Y',
		];
	}

	private function createAndActivateCustom404Rule() :int {
		$createPayload = $this->processor()->processAction( RuleBuilderAction::SLUG, [
			'builder_action' => 'create_rule',
			'rule_form'      => $this->ruleForm(),
		] )->payload();

		$this->assertTrue( $createPayload[ 'success' ] ?? false );
		$ruleID = (int)( $createPayload[ 'edit_rule_id' ] ?? 0 );
		$this->assertGreaterThan( 0, $ruleID );
		$this->assertSame( 0, (int)( new RuleRecords() )->byID( $ruleID )->is_active );

		$activatePayload = $this->processor()->processAction( RulesManagerTableAction::SLUG, [
			'sub_action' => 'activate',
			'rids'       => [ $ruleID ],
		] )->payload();

		$this->assertTrue( $activatePayload[ 'success' ] ?? false );
		$this->assertSame( 1, (int)( new RuleRecords() )->byID( $ruleID )->is_active );

		return $ruleID;
	}

	private function loadRuntimeCustom404RuleRaw() :array {
		return $this->loadStoredRuntimeRuleBySlug( 'custom/custom_404_block' );
	}

	private function loadRuntimeCustom404Rule() :RuleVO {
		return ( new RuleVO() )->applyFromArray( $this->loadRuntimeCustom404RuleRaw() );
	}

	private function flattenConditions( array $condition ) :array {
		if ( !\is_array( $condition[ 'conditions' ] ?? null ) ) {
			return [ $condition ];
		}

		$flat = [];
		foreach ( $condition[ 'conditions' ] as $subCondition ) {
			$flat = \array_merge( $flat, $this->flattenConditions( $subCondition ) );
		}
		return $flat;
	}

	private function assertRuntimeRuleContract( array $rule ) :void {
		$this->assertSame( 'custom/custom_404_block', $rule[ 'slug' ] ?? '' );
		$this->assertSame( 'custom_404_block', $rule[ 'name' ] ?? '' );
		$this->assertTrue( (bool)( $rule[ 'immediate_exec_response' ] ?? false ) );

		$conditions = $this->flattenConditions( $rule[ 'conditions' ] ?? [] );
		$conditionClasses = \array_column( $conditions, 'conditions' );
		$this->assertContains( RequestBypassesAllRestrictions::class, $conditionClasses );
		$this->assertContains( IsRequestStatus404::class, $conditionClasses );

		$bypassConditions = \array_values( \array_filter(
			$conditions,
			static fn( array $condition ) :bool => ( $condition[ 'conditions' ] ?? '' ) === RequestBypassesAllRestrictions::class
		) );
		$this->assertCount( 1, $bypassConditions );
		$this->assertSame( EnumLogic::LOGIC_INVERT, $bypassConditions[ 0 ][ 'logic' ] ?? '' );

		$status404Conditions = \array_values( \array_filter(
			$conditions,
			static fn( array $condition ) :bool => ( $condition[ 'conditions' ] ?? '' ) === IsRequestStatus404::class
		) );
		$this->assertCount( 1, $status404Conditions );
		$this->assertIsArray( $status404Conditions[ 0 ][ 'params' ] ?? [] );

		$this->assertArrayHasKey( 'responses', $rule );
		$this->assertCount( 1, $rule[ 'responses' ] );
		$response = $rule[ 'responses' ][ 0 ];
		$this->assertSame( TriggerIpBlock::class, $response[ 'response' ] ?? '' );
		$this->assertIsArray( $response[ 'params' ] ?? [] );
	}

	private function resetRuntimeRequestState() :void {
		$this->resetIpCaches();
		$con = $this->requireController();
		$con->this_req->request_bypasses_all_restrictions = false;
		$con->comps->offense_tracker->setIsBlocked( false );
	}

	private function prepareAnonymous404Request( string $path, bool $bypassed = false ) :void {
		$this->resetRuntimeRequestState();
		\wp_set_current_user( 0 );
		$this->go_to( '/?p='.PHP_INT_MAX );

		$con = $this->requireController();
		$con->this_req->path = $path;
		$con->this_req->request_bypasses_all_restrictions = $bypassed;
	}

	private function prepareAnonymousNon404Request() :void {
		$this->resetRuntimeRequestState();
		\wp_set_current_user( 0 );
		$postID = self::factory()->post->create();
		$path = (string)\wp_parse_url( \get_permalink( $postID ), \PHP_URL_PATH );
		$path = empty( $path ) ? '/' : $path;

		$this->go_to( $path );
		$con = $this->requireController();
		$con->this_req->path = $path;
		$con->this_req->request_bypasses_all_restrictions = false;
	}

	private function ruleMatches( RuleVO $rule ) :bool {
		return ( new ProcessConditions( $rule->conditions ) )
			->setThisRequest( $this->requireController()->this_req )
			->process();
	}

	private function processRuleIfMatched( RuleVO $rule ) :bool {
		$matched = $this->ruleMatches( $rule );
		if ( $matched ) {
			( new ResponseProcessor( $rule ) )
				->setThisRequest( $this->requireController()->this_req )
				->run();
		}
		return $matched;
	}

	public function test_saved_and_activated_custom_404_rule_rebuilds_runtime_contract() {
		$this->createAndActivateCustom404Rule();

		$this->assertRuntimeRuleContract( $this->loadRuntimeCustom404RuleRaw() );
	}

	public function test_custom_404_rule_matches_and_triggers_block_response() {
		$this->createAndActivateCustom404Rule();
		$rule = $this->loadRuntimeCustom404Rule();
		$this->prepareAnonymous404Request( '/definitely/missing/custom-404-block.php' );

		$this->assertTrue( \is_404(), 'Request should be a 404 for this scenario.' );
		$this->assertTrue( $this->processRuleIfMatched( $rule ) );
		$this->assertTrue( $this->requireController()->comps->offense_tracker->isBlocked() );
	}

	public function test_custom_404_rule_does_not_block_non_404_request() {
		$this->createAndActivateCustom404Rule();
		$rule = $this->loadRuntimeCustom404Rule();
		$this->prepareAnonymousNon404Request();

		$this->assertFalse( \is_404(), 'Test fixture should resolve as a non-404 request.' );
		$this->assertFalse( $this->processRuleIfMatched( $rule ) );
		$this->assertFalse( $this->requireController()->comps->offense_tracker->isBlocked() );
	}

	public function test_custom_404_rule_does_not_block_bypassed_404_request() {
		$this->createAndActivateCustom404Rule();
		$rule = $this->loadRuntimeCustom404Rule();
		$this->prepareAnonymous404Request( '/definitely/missing/custom-404-bypass.php', true );

		$this->assertTrue( \is_404(), 'Request should be a 404 for this scenario.' );
		$this->assertFalse( $this->processRuleIfMatched( $rule ) );
		$this->assertFalse( $this->requireController()->comps->offense_tracker->isBlocked() );
	}
}
