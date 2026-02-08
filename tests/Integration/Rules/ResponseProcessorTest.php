<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\ResponseProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Tests the ResponseProcessor: verifying that it fires events, handles
 * nonexistent response classes gracefully, and respects immediate_exec_response.
 */
class ResponseProcessorTest extends ShieldIntegrationTestCase {

	private function makeRuleVO( array $overrides = [] ) :RuleVO {
		$defaults = [
			'slug'                    => 'test_rule',
			'name'                    => 'Test Rule',
			'conditions'              => fn() => true,
			'responses'               => [],
			'immediate_exec_response' => true,
		];
		return ( new RuleVO() )->applyFromArray( \array_merge( $defaults, $overrides ) );
	}

	public function test_default_event_fires_for_rule() {
		$this->requireController();
		$this->captureShieldEvents();

		$rule = $this->makeRuleVO( [ 'slug' => 'test_response_rule' ] );

		$proc = new ResponseProcessor( $rule );
		$proc->setThisRequest( self::con()->this_req );
		$proc->run();

		// EventFireDefault fires with 'shield/rules/response/' prefix
		$events = $this->getCapturedEvents();
		$slugsFired = \array_column( $events, 'event' );

		$this->assertContains( 'shield/rules/response/test_response_rule', $slugsFired,
			'EventFireDefault should fire shield/rules/response/ + rule slug' );
	}

	public function test_nonexistent_response_class_still_fires_default_event() {
		$this->requireController();
		$this->captureShieldEvents();

		$rule = $this->makeRuleVO( [
			'slug'      => 'test_bad_response_rule',
			'responses' => [
				[
					'response' => 'NonExistent\\Response\\Class',
					'params'   => [],
				],
			],
		] );

		$proc = new ResponseProcessor( $rule );
		$proc->setThisRequest( self::con()->this_req );
		$proc->run();

		// The bad response class is caught, but the default event should still fire
		$events = $this->getCapturedEvents();
		$slugsFired = \array_column( $events, 'event' );
		$this->assertContains( 'shield/rules/response/test_bad_response_rule', $slugsFired,
			'Default event should still fire even when custom response class fails' );
	}

	public function test_empty_response_definition_still_fires_default_event() {
		$this->requireController();
		$this->captureShieldEvents();

		$rule = $this->makeRuleVO( [
			'slug'      => 'test_empty_response_rule',
			'responses' => [
				[
					// Missing 'response' key
					'params' => [],
				],
			],
		] );

		$proc = new ResponseProcessor( $rule );
		$proc->setThisRequest( self::con()->this_req );
		$proc->run();

		// Despite the bad response definition, the default event should fire
		$events = $this->getCapturedEvents();
		$slugsFired = \array_column( $events, 'event' );
		$this->assertContains( 'shield/rules/response/test_empty_response_rule', $slugsFired,
			'Default event should fire even with empty response definition' );
	}
}
