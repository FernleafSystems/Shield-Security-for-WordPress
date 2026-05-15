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

	public function test_request_policy_decision_event_is_audit_only_contract() :void {
		$event = $this->config[ 'config_spec' ][ 'events' ][ 'request_policy_decision' ] ?? [];

		$this->assertSame( [
			'mode',
			'detector',
			'decision',
			'reason',
			'surface',
			'risk_band',
			'rule',
		], $event[ 'audit_params' ] ?? [] );
		$this->assertSame( false, $event[ 'offense' ] ?? null );
		$this->assertSame( 'notice', $event[ 'level' ] ?? '' );
	}
}
