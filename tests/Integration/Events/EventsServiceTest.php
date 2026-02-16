<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Events\EventsService;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Tests the EventsService: event definitions, existence checks, event
 * firing, audit parameter verification, and default field application.
 */
class EventsServiceTest extends ShieldIntegrationTestCase {

	private function events() :EventsService {
		return $this->requireController()->comps->events;
	}

	// ── getEvents ──────────────────────────────────────────────────

	public function test_get_events_returns_populated_array() {
		$events = $this->events()->getEvents();
		$this->assertIsArray( $events );
		$this->assertNotEmpty( $events, 'Events list should not be empty' );
	}

	// ── eventExists ────────────────────────────────────────────────

	public function test_event_exists_for_known_event() {
		$this->assertTrue( $this->events()->eventExists( 'ip_blocked' ),
			'ip_blocked should be a known event' );
	}

	public function test_event_does_not_exist_for_unknown() {
		$this->assertFalse( $this->events()->eventExists( 'completely_made_up_event_xyz' ) );
	}

	// ── fireEvent ──────────────────────────────────────────────────

	public function test_fire_event_triggers_action() {
		$this->captureShieldEvents();

		$this->events()->fireEvent( 'ip_blocked', [
			'audit_params' => [
				'from' => '192.0.2.99',
				'to'   => 'manual',
			],
		] );

		$captured = $this->getCapturedEventsByKey( 'ip_blocked' );
		$this->assertNotEmpty( $captured, 'fireEvent should trigger shield/event action' );
		$this->assertSame( '192.0.2.99', $captured[ 0 ][ 'meta' ][ 'audit_params' ][ 'from' ] ?? '' );
	}

	public function test_fire_nonexistent_event_silently_fails() {
		$this->captureShieldEvents();

		// Should not throw
		$this->events()->fireEvent( 'nonexistent_event_xyz', [] );

		$captured = $this->getCapturedEventsByKey( 'nonexistent_event_xyz' );
		$this->assertEmpty( $captured, 'Nonexistent event should not fire' );
	}

	public function test_fire_event_strips_extra_audit_params() {
		$this->captureShieldEvents();

		$this->events()->fireEvent( 'ip_blocked', [
			'audit_params' => [
				'from'            => '192.0.2.98',
				'to'              => 'auto',
				'extra_not_valid' => 'should be stripped',
			],
		] );

		$captured = $this->getCapturedEventsByKey( 'ip_blocked' );
		$this->assertNotEmpty( $captured );
		$auditParams = $captured[ 0 ][ 'meta' ][ 'audit_params' ] ?? [];
		$this->assertArrayNotHasKey( 'extra_not_valid', $auditParams,
			'Extra audit params should be silently stripped' );
	}

	public function test_report_sent_requires_all_audit_params() {
		$this->captureShieldEvents();

		$this->events()->fireEvent( 'report_sent', [
			'audit_params' => [
				'medium' => 'email',
			],
		] );

		$captured = $this->getCapturedEventsByKey( 'report_sent' );
		$this->assertEmpty( $captured, 'report_sent should not fire if required audit params are missing' );
	}

	public function test_report_sent_fires_when_required_audit_params_provided() {
		$this->captureShieldEvents();

		$this->events()->fireEvent( 'report_sent', [
			'audit_params' => [
				'type'   => 'Alert',
				'medium' => 'email',
			],
		] );

		$captured = $this->getCapturedEventsByKey( 'report_sent' );
		$this->assertNotEmpty( $captured, 'report_sent should fire when all required audit params are supplied' );
		$this->assertSame( 'Alert', $captured[ 0 ][ 'meta' ][ 'audit_params' ][ 'type' ] ?? '' );
	}

	// ── buildEvents defaults ───────────────────────────────────────

	public function test_report_generated_alert_requires_all_audit_params() {
		$this->captureShieldEvents();

		$this->events()->fireEvent( 'report_generated_alert', [
			'audit_params' => [
				'type' => 'Alert',
			],
		] );

		$captured = $this->getCapturedEventsByKey( 'report_generated_alert' );
		$this->assertEmpty( $captured, 'report_generated_alert should not fire if required audit params are missing' );
	}

	public function test_report_generated_alert_fires_when_required_audit_params_provided() {
		$this->captureShieldEvents();

		$this->events()->fireEvent( 'report_generated_alert', [
			'audit_params' => [
				'type'     => 'Alert',
				'interval' => 'hourly',
			],
		] );

		$captured = $this->getCapturedEventsByKey( 'report_generated_alert' );
		$this->assertNotEmpty( $captured, 'report_generated_alert should fire when all required audit params are supplied' );
		$this->assertSame( 'hourly', $captured[ 0 ][ 'meta' ][ 'audit_params' ][ 'interval' ] ?? '' );
	}

	public function test_build_events_applies_correct_defaults() {
		$events = $this->events()->getEvents();

		$requiredKeys = [ 'level', 'stat', 'audit', 'offense', 'audit_params', 'key' ];
		foreach ( $events as $key => $evt ) {
			foreach ( $requiredKeys as $reqKey ) {
				$this->assertArrayHasKey( $reqKey, $evt, "Event '{$key}' missing required key '{$reqKey}'" );
			}
		}
	}

	public function test_event_def_returns_null_for_unknown() {
		$this->assertNull( $this->events()->getEventDef( 'unknown_event_xyz' ) );
	}

	public function test_event_def_returns_array_for_known() {
		$def = $this->events()->getEventDef( 'ip_blocked' );
		$this->assertIsArray( $def );
		$this->assertSame( 'ip_blocked', $def[ 'key' ] );
	}

	public function test_event_levels_added_via_filter_are_normalised() {
		$callback = function ( array $events ) {
			foreach ( [
				'test_filter_level_alert'   => 'alert',
				'test_filter_level_debug'   => 'debug',
				'test_filter_level_unknown' => 'something_else',
			] as $eventKey => $level ) {
				$events[ $eventKey ] = [
					'level'        => $level,
					'stat'         => false,
					'audit'        => false,
					'offense'      => false,
					'audit_params' => [],
					'key'          => $eventKey,
				];
			}
			return $events;
		};

		add_filter( 'shield/events/definitions', $callback );
		try {
			$eventsService = $this->events();
			\Closure::bind( function () {
				unset( $this->events );
			}, $eventsService, \get_class( $eventsService ) )();

			$events = $eventsService->getEvents();
			$this->assertSame( 'warning', $events[ 'test_filter_level_alert' ][ 'level' ] ?? '' );
			$this->assertSame( 'info', $events[ 'test_filter_level_debug' ][ 'level' ] ?? '' );
			$this->assertSame( 'notice', $events[ 'test_filter_level_unknown' ][ 'level' ] ?? '' );
		}
		finally {
			remove_filter( 'shield/events/definitions', $callback );
		}
	}
}
