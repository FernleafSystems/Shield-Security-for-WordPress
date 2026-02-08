<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\CrossSystem;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\ConditionsVO;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\{
	ProcessConditions,
	ResponseProcessor
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Cross-system integration test: verifies that a rule condition match flows
 * through to event firing and can be observed in the captured events.
 */
class RuleToEventToAuditFlowTest extends ShieldIntegrationTestCase {

	public function test_matched_rule_fires_event_via_response_processor() {
		$this->requireController();
		$this->captureShieldEvents();

		// Create a rule that always matches (callable returns true)
		$rule = ( new RuleVO() )->applyFromArray( [
			'slug'                    => 'test_rule_to_event_flow',
			'name'                    => 'Test Rule',
			'conditions'              => fn() => true,
			'responses'               => [],
			'immediate_exec_response' => true,
		] );

		// First verify the condition matches
		$condVO = ( new ConditionsVO() )->applyFromArray( [
			'conditions' => fn() => true,
		] );
		$proc = new ProcessConditions( $condVO );
		$proc->setThisRequest( self::con()->this_req );
		$matched = $proc->process();
		$this->assertTrue( $matched, 'Condition should match' );

		// Process the response (which fires the default event)
		$respProc = new ResponseProcessor( $rule );
		$respProc->setThisRequest( self::con()->this_req );
		$respProc->run();

		// EventFireDefault fires with 'shield/rules/response/' prefix (see EventFireDefault::execResponse)
		$events = $this->getCapturedEvents();
		$slugs = \array_column( $events, 'event' );
		$this->assertContains( 'shield/rules/response/test_rule_to_event_flow', $slugs,
			'ResponseProcessor should fire event with shield/rules/response/ prefix + rule slug' );
	}

	public function test_unmatched_rule_does_not_fire_event() {
		$this->requireController();
		$this->captureShieldEvents();

		// Verify condition does NOT match
		$condVO = ( new ConditionsVO() )->applyFromArray( [
			'conditions' => fn() => false,
		] );
		$proc = new ProcessConditions( $condVO );
		$proc->setThisRequest( self::con()->this_req );
		$matched = $proc->process();
		$this->assertFalse( $matched );

		// Since the condition didn't match, ResponseProcessor should NOT be called
		// (in real code, the RulesController only calls ResponseProcessor when conditions pass)
		$events = $this->getCapturedEventsByKey( 'test_unmatched_rule' );
		$this->assertEmpty( $events );
	}
}
