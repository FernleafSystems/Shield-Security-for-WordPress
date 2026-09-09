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

	public function test_plugin_file_edited_preserves_plugin_and_file_audit_params() {
		$this->captureShieldEvents();

		$this->events()->fireEvent( 'plugin_file_edited', [
			'audit_params' => [
				'plugin' => 'example-plugin/example-plugin.php',
				'file'   => 'example-plugin/includes/edit.php',
			],
		] );

		$captured = $this->getCapturedEventsByKey( 'plugin_file_edited' );
		$this->assertCount( 1, $captured );
		$auditParams = $captured[ 0 ][ 'meta' ][ 'audit_params' ] ?? [];
		$this->assertSame( 'example-plugin/example-plugin.php', $auditParams[ 'plugin' ] ?? '' );
		$this->assertSame( 'example-plugin/includes/edit.php', $auditParams[ 'file' ] ?? '' );
	}

	public function test_theme_file_edited_preserves_theme_and_file_audit_params() {
		$this->captureShieldEvents();

		$this->events()->fireEvent( 'theme_file_edited', [
			'audit_params' => [
				'theme' => 'example-theme',
				'file'  => 'example-theme/functions.php',
			],
		] );

		$captured = $this->getCapturedEventsByKey( 'theme_file_edited' );
		$this->assertCount( 1, $captured );
		$auditParams = $captured[ 0 ][ 'meta' ][ 'audit_params' ] ?? [];
		$this->assertSame( 'example-theme', $auditParams[ 'theme' ] ?? '' );
		$this->assertSame( 'example-theme/functions.php', $auditParams[ 'file' ] ?? '' );
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

	public function test_non_array_event_definitions_fall_back_to_built_ins() :void {
		$callback = static fn() => (object)[ 'invalid' => true ];
		\add_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );

		try {
			$events = ( new EventsService() )->getEvents();
			$this->assertArrayHasKey( 'ip_blocked', $events );
			$this->assertIsArray( $events[ 'ip_blocked' ] );
		}
		finally {
			\remove_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );
		}
	}

	public function test_malformed_filtered_event_members_use_built_in_fallback_or_are_removed() :void {
		$callback = static function ( array $events ) :array {
			$events[ 'ip_blocked' ] = 'malformed-built-in';
			$events[ 'malformed_new_event' ] = new \stdClass();
			return $events;
		};
		\add_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );

		try {
			$events = ( new EventsService() )->getEvents();
			$this->assertIsArray( $events[ 'ip_blocked' ] );
			$this->assertSame( 'ip_blocked', $events[ 'ip_blocked' ][ 'key' ] ?? '' );
			$this->assertArrayNotHasKey( 'malformed_new_event', $events );
		}
		finally {
			\remove_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );
		}
	}

	public function test_malformed_nested_event_fields_use_built_in_fallback_or_are_removed() :void {
		$validDefinition = [];
		$callback = static function ( array $events ) use ( &$validDefinition ) :array {
			$validDefinition = $events[ 'ip_blocked' ];
			$validDefinition[ 'key' ] = 'valid_new_event';

			$events[ 'ip_blocked' ][ 'level' ] = new \stdClass();
			$events[ 'report_sent' ][ 'audit_params' ] = 'malformed-audit-params';
			$events[ 'malformed_new_level' ] = $validDefinition;
			$events[ 'malformed_new_level' ][ 'level' ] = new \stdClass();
			$events[ 'malformed_new_audit_params' ] = $validDefinition;
			$events[ 'malformed_new_audit_params' ][ 'audit_params' ] = 'malformed-audit-params';
			$events[ 'valid_new_event' ] = $validDefinition;
			return $events;
		};
		\add_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );

		try {
			$events = ( new EventsService() )->getEvents();
			$this->assertSame( 'warning', $events[ 'ip_blocked' ][ 'level' ] ?? '' );
			$this->assertSame( [ 'type', 'medium' ], $events[ 'report_sent' ][ 'audit_params' ] ?? [] );
			$this->assertArrayNotHasKey( 'malformed_new_level', $events );
			$this->assertArrayNotHasKey( 'malformed_new_audit_params', $events );
			$this->assertSame( $validDefinition, $events[ 'valid_new_event' ] ?? [] );
		}
		finally {
			\remove_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );
		}
	}

	public function test_malformed_audit_param_members_use_built_in_fallback_or_are_removed() :void {
		$callback = static function ( array $events ) :array {
			$events[ 'report_sent' ][ 'audit_params' ][] = new \stdClass();
			$events[ 'malformed_new_audit_param' ] = $events[ 'report_sent' ];
			$events[ 'malformed_new_audit_param' ][ 'key' ] = 'malformed_new_audit_param';
			return $events;
		};
		\add_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );

		try {
			$this->captureShieldEvents();
			$eventsService = new EventsService();
			$eventsService->fireEvent( 'report_sent', [
				'audit_params' => [
					'type'   => 'Alert',
					'medium' => 'email',
				],
			] );

			$this->assertCount( 1, $this->getCapturedEventsByKey( 'report_sent' ) );
			$this->assertArrayNotHasKey( 'malformed_new_audit_param', $eventsService->getEvents() );
		}
		finally {
			\remove_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );
		}
	}

	public function test_missing_event_key_uses_built_in_fallback_or_is_removed() :void {
		$callback = static function ( array $events ) :array {
			unset( $events[ 'ip_blocked' ][ 'key' ] );
			$events[ 'missing_key_event' ] = $events[ 'report_sent' ];
			unset( $events[ 'missing_key_event' ][ 'key' ] );
			return $events;
		};
		\add_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );

		try {
			$eventsService = new EventsService();
			$events = $eventsService->getEvents();
			$this->assertSame( 'ip_blocked', $events[ 'ip_blocked' ][ 'key' ] ?? '' );
			$this->assertArrayNotHasKey( 'missing_key_event', $events );
			$this->assertCount( \count( $events ), $eventsService->getEventNames() );
		}
		finally {
			\remove_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );
		}
	}

	public function test_invalid_filtered_event_keys_use_built_in_fallback_or_are_removed() :void {
		$callback = static function ( array $events ) :array {
			$events[ 'ip_blocked' ][ 'key' ] = 'mismatched_key';
			$events[ 123 ] = $events[ 'report_sent' ];
			return $events;
		};
		\add_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );

		try {
			$events = ( new EventsService() )->getEvents();
			$this->assertSame( 'ip_blocked', $events[ 'ip_blocked' ][ 'key' ] ?? '' );
			$this->assertArrayNotHasKey( 123, $events );
			foreach ( \array_keys( $events ) as $eventKey ) {
				$this->assertIsString( $eventKey );
			}
		}
		finally {
			\remove_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );
		}
	}

	public function test_malformed_filtered_custom_event_strings_are_removed() :void {
		$validDefinition = [];
		$callback = static function ( array $events ) use ( &$validDefinition ) :array {
			$validDefinition = $events[ 'report_sent' ];
			$validDefinition[ 'key' ] = 'custom_valid_filtered';
			$validDefinition[ 'strings' ] = [
				'name'  => 'Valid filtered custom event',
				'audit' => [ 'Valid audit string' ],
			];
			$events[ 'custom_valid_filtered' ] = $validDefinition;

			$events[ 'custom_invalid_strings' ] = $validDefinition;
			$events[ 'custom_invalid_strings' ][ 'key' ] = 'custom_invalid_strings';
			$events[ 'custom_invalid_strings' ][ 'strings' ] = 'not-an-array';

			$events[ 'custom_invalid_name' ] = $validDefinition;
			$events[ 'custom_invalid_name' ][ 'key' ] = 'custom_invalid_name';
			$events[ 'custom_invalid_name' ][ 'strings' ][ 'name' ] = new \stdClass();

			$events[ 'custom_invalid_audit' ] = $validDefinition;
			$events[ 'custom_invalid_audit' ][ 'key' ] = 'custom_invalid_audit';
			$events[ 'custom_invalid_audit' ][ 'strings' ][ 'audit' ][] = new \stdClass();
			return $events;
		};
		\add_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );

		try {
			$eventsService = new EventsService();
			$events = $eventsService->getEvents();
			$this->assertSame( $validDefinition, $events[ 'custom_valid_filtered' ] ?? [] );
			$this->assertArrayNotHasKey( 'custom_invalid_strings', $events );
			$this->assertArrayNotHasKey( 'custom_invalid_name', $events );
			$this->assertArrayNotHasKey( 'custom_invalid_audit', $events );
			$this->assertSame(
				$validDefinition[ 'strings' ],
				$eventsService->getEventStrings( 'custom_valid_filtered' )
			);
		}
		finally {
			\remove_filter( 'shield/events/definitions', $callback, \PHP_INT_MAX );
		}
	}

	public function test_custom_event_audit_strings_are_filtered() :void {
		$this->enablePremiumCapabilities();
		$callback = static function ( array $events ) :array {
			$events[ 'custom_filtered_audit' ] = [
				'strings' => [
					'name'  => 'Filtered Audit Event',
					'audit' => [ 'First valid string', '', 123, 'Second valid string' ],
				],
			];
			return $events;
		};

		add_filter( 'shield/events/custom_definitions', $callback );
		try {
			$this->assertSame(
				[ 'First valid string', 'Second valid string' ],
				\array_values( $this->events()->getEventAuditStrings( 'custom_filtered_audit' ) )
			);
		}
		finally {
			remove_filter( 'shield/events/custom_definitions', $callback );
		}
	}

	public function test_malformed_custom_event_definition_rejects_the_custom_set() :void {
		$this->enablePremiumCapabilities();
		$callback = static fn() :array => [
			'custom_valid_definition' => [
				'strings' => [
					'name'  => 'Valid custom event',
					'audit' => [ 'Valid audit string' ],
				],
			],
			'custom_invalid_definition' => new \stdClass(),
		];
		\add_filter( 'shield/events/custom_definitions', $callback, \PHP_INT_MAX );

		try {
			$events = ( new EventsService() )->getEvents();
			$this->assertArrayNotHasKey( 'custom_valid_definition', $events );
			$this->assertArrayNotHasKey( 'custom_invalid_definition', $events );
		}
		finally {
			\remove_filter( 'shield/events/custom_definitions', $callback, \PHP_INT_MAX );
		}
	}

	public function test_falsy_custom_event_definition_is_ignored() :void {
		$this->enablePremiumCapabilities();
		$callback = static fn() :array => [
			'custom_valid_definition' => [
				'strings' => [
					'name'  => 'Valid custom event',
					'audit' => [ 'Valid audit string' ],
				],
			],
			'custom_invalid_definition' => false,
		];
		\add_filter( 'shield/events/custom_definitions', $callback, \PHP_INT_MAX );

		try {
			$events = ( new EventsService() )->getEvents();
			$this->assertArrayHasKey( 'custom_valid_definition', $events );
			$this->assertArrayNotHasKey( 'custom_invalid_definition', $events );
		}
		finally {
			\remove_filter( 'shield/events/custom_definitions', $callback, \PHP_INT_MAX );
		}
	}

	public function test_custom_event_with_no_valid_audit_strings_is_retained() :void {
		$this->enablePremiumCapabilities();
		$callback = static fn() :array => [
			'custom_invalid_audit_strings' => [
				'strings' => [
					'name'  => 'Invalid audit strings',
					'audit' => [ '', 123, [] ],
				],
			],
		];
		\add_filter( 'shield/events/custom_definitions', $callback, \PHP_INT_MAX );

		try {
			$eventsService = new EventsService();
			$this->assertArrayHasKey( 'custom_invalid_audit_strings', $eventsService->getEvents() );
			$this->assertSame( [], $eventsService->getEventAuditStrings( 'custom_invalid_audit_strings' ) );
		}
		finally {
			\remove_filter( 'shield/events/custom_definitions', $callback, \PHP_INT_MAX );
		}
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
