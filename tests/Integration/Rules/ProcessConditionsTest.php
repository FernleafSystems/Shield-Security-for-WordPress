<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\ConditionsVO;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\ProcessConditions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Tests the recursive condition evaluator: callable, single, and group modes,
 * AND/OR/INVERT logic, short-circuit behaviour, and nesting.
 */
class ProcessConditionsTest extends ShieldIntegrationTestCase {

	private function makeVO( array $def ) :ConditionsVO {
		return ( new ConditionsVO() )->applyFromArray( $def );
	}

	private function processCondition( ConditionsVO $vo ) :bool {
		$proc = new ProcessConditions( $vo );
		$proc->setThisRequest( self::con()->this_req );
		return $proc->process();
	}

	// ── Callable mode ──────────────────────────────────────────────

	public function test_callable_returning_true() {
		$vo = $this->makeVO( [
			'conditions' => fn() => true,
		] );
		$this->assertTrue( $this->processCondition( $vo ) );
	}

	public function test_callable_returning_false() {
		$vo = $this->makeVO( [
			'conditions' => fn() => false,
		] );
		$this->assertFalse( $this->processCondition( $vo ) );
	}

	// ── Single condition mode ──────────────────────────────────────

	public function test_single_condition_with_real_handler() {
		if ( \PHP_SAPI !== 'cli' ) {
			$this->markTestSkipped( 'IsPhpCli condition only returns true in CLI context' );
		}
		$vo = $this->makeVO( [
			'conditions' => \FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\IsPhpCli::class,
			'logic'      => EnumLogic::LOGIC_ASIS,
		] );
		$this->assertTrue( $this->processCondition( $vo ) );
	}

	public function test_single_condition_nonexistent_handler_returns_false() {
		$vo = $this->makeVO( [
			'conditions' => 'NonExistent\\Condition\\Handler\\Class',
			'logic'      => EnumLogic::LOGIC_ASIS,
		] );
		$this->assertFalse( $this->processCondition( $vo ) );
	}

	// ── INVERT logic ───────────────────────────────────────────────

	public function test_invert_logic_flips_true_to_false() {
		if ( \PHP_SAPI !== 'cli' ) {
			$this->markTestSkipped( 'IsPhpCli condition only returns true in CLI context' );
		}
		$vo = $this->makeVO( [
			'conditions' => \FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\IsPhpCli::class,
			'logic'      => EnumLogic::LOGIC_INVERT,
		] );
		// IsPhpCli is true in CLI, INVERT makes it false
		$this->assertFalse( $this->processCondition( $vo ) );
	}

	// ── Empty conditions ───────────────────────────────────────────

	public function test_empty_conditions_throws() {
		$this->expectException( \Exception::class );
		$vo = $this->makeVO( [ 'conditions' => [] ] );
		$this->processCondition( $vo );
	}

	// ── Group AND logic ────────────────────────────────────────────

	public function test_group_and_true_true() {
		$vo = $this->makeVO( [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[ 'conditions' => fn() => true ],
				[ 'conditions' => fn() => true ],
			],
		] );
		$this->assertTrue( $this->processCondition( $vo ) );
	}

	public function test_group_and_true_false() {
		$vo = $this->makeVO( [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[ 'conditions' => fn() => true ],
				[ 'conditions' => fn() => false ],
			],
		] );
		$this->assertFalse( $this->processCondition( $vo ) );
	}

	// ── Group OR logic ─────────────────────────────────────────────

	public function test_group_or_false_true() {
		$vo = $this->makeVO( [
			'logic'      => EnumLogic::LOGIC_OR,
			'conditions' => [
				[ 'conditions' => fn() => false ],
				[ 'conditions' => fn() => true ],
			],
		] );
		$this->assertTrue( $this->processCondition( $vo ) );
	}

	public function test_group_or_false_false() {
		$vo = $this->makeVO( [
			'logic'      => EnumLogic::LOGIC_OR,
			'conditions' => [
				[ 'conditions' => fn() => false ],
				[ 'conditions' => fn() => false ],
			],
		] );
		$this->assertFalse( $this->processCondition( $vo ) );
	}

	// ── Short-circuit ──────────────────────────────────────────────

	public function test_short_circuit_and_stops_on_first_false() {
		$callCount = 0;
		$vo = $this->makeVO( [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[ 'conditions' => fn() => false ],
				[
					'conditions' => function () use ( &$callCount ) {
						$callCount++;
						return true;
					}
				],
			],
		] );
		$this->assertFalse( $this->processCondition( $vo ) );
		$this->assertSame( 0, $callCount, 'Second condition should never be evaluated in AND short-circuit' );
	}

	public function test_short_circuit_or_stops_on_first_true() {
		$callCount = 0;
		$vo = $this->makeVO( [
			'logic'      => EnumLogic::LOGIC_OR,
			'conditions' => [
				[ 'conditions' => fn() => true ],
				[
					'conditions' => function () use ( &$callCount ) {
						$callCount++;
						return false;
					}
				],
			],
		] );
		$this->assertTrue( $this->processCondition( $vo ) );
		$this->assertSame( 0, $callCount, 'Second condition should never be evaluated in OR short-circuit' );
	}

	// ── Nested groups ──────────────────────────────────────────────

	public function test_nested_groups() {
		// (true AND true) OR (false AND true) => true OR false => true
		$vo = $this->makeVO( [
			'logic'      => EnumLogic::LOGIC_OR,
			'conditions' => [
				[
					'logic'      => EnumLogic::LOGIC_AND,
					'conditions' => [
						[ 'conditions' => fn() => true ],
						[ 'conditions' => fn() => true ],
					],
				],
				[
					'logic'      => EnumLogic::LOGIC_AND,
					'conditions' => [
						[ 'conditions' => fn() => false ],
						[ 'conditions' => fn() => true ],
					],
				],
			],
		] );
		$this->assertTrue( $this->processCondition( $vo ) );
	}

	public function test_deeply_nested_groups() {
		// ((true OR false) AND (true AND true)) => (true AND true) => true
		$vo = $this->makeVO( [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'logic'      => EnumLogic::LOGIC_OR,
					'conditions' => [
						[ 'conditions' => fn() => true ],
						[ 'conditions' => fn() => false ],
					],
				],
				[
					'logic'      => EnumLogic::LOGIC_AND,
					'conditions' => [
						[ 'conditions' => fn() => true ],
						[ 'conditions' => fn() => true ],
					],
				],
			],
		] );
		$this->assertTrue( $this->processCondition( $vo ) );
	}
}
