<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Configuration;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ActivityLogLevelsConfigTest extends TestCase {

	use PluginPathsTrait;

	private array $config;

	protected function set_up() :void {
		parent::set_up();
		$this->config = $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );
	}

	public function test_event_spec_contains_no_legacy_alert_or_debug_levels() :void {
		$events = $this->config[ 'config_spec' ][ 'events' ] ?? [];
		$this->assertIsArray( $events, 'Events specification should exist in plugin.json config_spec.events' );

		foreach ( $events as $eventKey => $eventDef ) {
			$level = $eventDef[ 'level' ] ?? null;
			if ( $level !== null ) {
				$this->assertNotSame( 'alert', $level, "Event '{$eventKey}' should not use 'alert'" );
				$this->assertNotSame( 'debug', $level, "Event '{$eventKey}' should not use 'debug'" );
			}
		}
	}

	public function test_request_policy_decision_event_is_internal_trace_contract() :void {
		$event = $this->config[ 'config_spec' ][ 'events' ][ 'request_policy_decision' ] ?? [];

		$this->assertSame( false, $event[ 'audit' ] ?? null );
		$this->assertSame( false, $event[ 'stat' ] ?? null );
		$this->assertSame( false, $event[ 'offense' ] ?? null );
		$this->assertSame( 'notice', $event[ 'level' ] ?? '' );
	}

	public function test_request_policy_block_event_contract() :void {
		$event = $this->config[ 'config_spec' ][ 'events' ][ 'request_policy_block' ] ?? [];

		$this->assertSame( [
			'mode',
			'sensitivity',
			'detector',
			'surface',
			'risk_band',
			'risk_reason',
			'block_category',
			'reason',
			'rule',
			'enforced',
		], $event[ 'audit_params' ] ?? [] );
		$this->assertSame( false, $event[ 'offense' ] ?? null );
		$this->assertSame( true, $event[ 'recent' ] ?? null );
		$this->assertSame( true, $event[ 'audit_countable' ] ?? null );
		$this->assertSame( 'warning', $event[ 'level' ] ?? '' );
	}
}
