<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\OffenseTracker;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

/**
 * Unit tests for OffenseTracker – exercises the in-memory tracking logic
 * without needing WordPress or the DB.
 *
 * OffenseTracker extends EventsListener whose constructor calls add_action()
 * and self::con(). Since shield_security_get_plugin() is already defined before
 * Patchwork loads, we bypass the constructor entirely using reflection.
 */
class OffenseTrackerTest extends BaseUnitTest {

	/**
	 * Create an OffenseTracker instance without invoking the constructor.
	 * This avoids the EventsListener constructor which calls add_action/self::con().
	 */
	private function makeTracker() :OffenseTracker {
		$ref = new \ReflectionClass( OffenseTracker::class );
		/** @var OffenseTracker $tracker */
		$tracker = $ref->newInstanceWithoutConstructor();
		return $tracker;
	}

	public function test_capture_offense_event_increments_count() {
		$tracker = $this->makeTracker();

		$ref = new \ReflectionMethod( $tracker, 'captureEvent' );
		$ref->setAccessible( true );
		$ref->invoke( $tracker, 'test_offense', [], [ 'offense' => true ] );

		$this->assertSame( 1, $tracker->getOffenseCount() );
		$this->assertTrue( $tracker->hasVisitorOffended() );
	}

	public function test_non_offense_event_does_not_increment() {
		$tracker = $this->makeTracker();

		$ref = new \ReflectionMethod( $tracker, 'captureEvent' );
		$ref->setAccessible( true );
		$ref->invoke( $tracker, 'normal_event', [], [ 'offense' => false ] );

		$this->assertSame( 0, $tracker->getOffenseCount() );
		$this->assertFalse( $tracker->hasVisitorOffended() );
	}

	public function test_suppress_offense_prevents_counting() {
		$tracker = $this->makeTracker();

		$ref = new \ReflectionMethod( $tracker, 'captureEvent' );
		$ref->setAccessible( true );
		$ref->invoke( $tracker, 'suppressed', [ 'suppress_offense' => true ], [ 'offense' => true ] );

		$this->assertSame( 0, $tracker->getOffenseCount() );
	}

	public function test_block_meta_sets_blocked() {
		$tracker = $this->makeTracker();

		$ref = new \ReflectionMethod( $tracker, 'captureEvent' );
		$ref->setAccessible( true );
		$ref->invoke( $tracker, 'block_event', [ 'block' => true ], [ 'offense' => true ] );

		$this->assertTrue( $tracker->isBlocked() );
		$this->assertTrue( $tracker->hasVisitorOffended() );
	}

	public function test_set_offense_count_never_decreases() {
		$tracker = $this->makeTracker();
		$tracker->setOffenseCount( 5 );
		$this->assertSame( 5, $tracker->getOffenseCount() );

		$tracker->setOffenseCount( 3 );
		$this->assertSame( 5, $tracker->getOffenseCount(), 'Offense count should never decrease' );

		$tracker->setOffenseCount( 7 );
		$this->assertSame( 7, $tracker->getOffenseCount() );
	}

	public function test_increment_count_adds_correctly() {
		$tracker = $this->makeTracker();
		$tracker->incrementCount( 2 );
		$this->assertSame( 2, $tracker->getOffenseCount() );

		$tracker->incrementCount( 3 );
		$this->assertSame( 5, $tracker->getOffenseCount() );
	}

	public function test_has_visitor_offended_true_when_blocked() {
		$tracker = $this->makeTracker();
		$tracker->setIsBlocked( true );
		$this->assertTrue( $tracker->hasVisitorOffended() );
		$this->assertSame( 0, $tracker->getOffenseCount(), 'Blocked but no offense count' );
	}

	public function test_custom_offense_count_in_meta() {
		$tracker = $this->makeTracker();

		$ref = new \ReflectionMethod( $tracker, 'captureEvent' );
		$ref->setAccessible( true );
		$ref->invoke( $tracker, 'multi_offense', [ 'offense_count' => 3 ], [ 'offense' => true ] );

		$this->assertSame( 3, $tracker->getOffenseCount() );
	}
}
