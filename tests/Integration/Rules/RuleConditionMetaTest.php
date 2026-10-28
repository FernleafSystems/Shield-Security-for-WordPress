<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions\MatchRequestPath,
	Enum\EnumMatchTypes,
	Responses\EventFire,
	RuleVO,
	RulesController
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Each matched rule keeps its own condition meta, including when its conditions are
 * answered from the condition cache because an earlier rule evaluated them first.
 */
class RuleConditionMetaTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$con = $this->requireController();
		$con->rules = new RulesController();
		$con->rules->setThisRequest( $con->this_req )->execute();
		$con->this_req->path = '/private-area';
	}

	public function test_rules_sharing_a_cached_condition_each_keep_its_meta() :void {
		$first = $this->pathRule( 'test_meta_first' );
		$second = $this->pathRule( 'test_meta_second' );

		$this->captureShieldEvents();
		$this->processRule( $first );
		$this->processRule( $second );

		$this->assertTrue( $first->result );
		$this->assertTrue( $second->result );
		$this->assertSame( '/private-area', $first->condition_meta[ 'path' ] ?? null );
		$this->assertSame( '/private-area', $second->condition_meta[ 'path' ] ?? null );

		$events = $this->getCapturedEventsByKey( 'debug_log' );
		$this->assertCount( 2, $events );
		foreach ( $events as $event ) {
			$this->assertSame( '/private-area', $event[ 'meta' ][ 'audit_params' ][ 'message' ] ?? null );
		}
	}

	public function test_meta_from_one_rule_does_not_leak_into_the_next() :void {
		$pathRule = $this->pathRule( 'test_meta_path' );
		$plainRule = ( new RuleVO() )->applyFromArray( [
			'slug'                    => 'test_meta_plain',
			'name'                    => 'Test Meta Plain',
			'conditions'              => [
				'conditions' => fn() => true,
			],
			'responses'               => [],
			'immediate_exec_response' => true,
		] );

		$this->processRule( $pathRule );
		$this->processRule( $plainRule );

		$this->assertTrue( $plainRule->result );
		$this->assertSame( [], $plainRule->condition_meta );
	}

	private function pathRule( string $slug ) :RuleVO {
		return ( new RuleVO() )->applyFromArray( [
			'slug'                    => $slug,
			'name'                    => $slug,
			'conditions'              => [
				'conditions' => MatchRequestPath::class,
				'params'     => [
					'match_type' => EnumMatchTypes::MATCH_TYPE_EQUALS,
					'match_path' => '/private-area',
				],
			],
			'responses'               => [
				[
					'response' => EventFire::class,
					'params'   => [
						'event'            => 'debug_log',
						'audit_params_map' => [
							'message' => 'path',
						],
					],
				],
			],
			'immediate_exec_response' => true,
		] );
	}

	private function processRule( RuleVO $rule ) :void {
		$method = new \ReflectionMethod( RulesController::class, 'processRule' );
		$method->setAccessible( true );
		$method->invoke( $this->requireController()->rules, $rule );
	}
}
