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

	public function test_log_level_db_option_schema_is_canonical() :void {
		$options = $this->config[ 'config_spec' ][ 'options' ] ?? [];
		$logLevels = null;
		foreach ( $options as $option ) {
			if ( ( $option[ 'key' ] ?? '' ) === 'log_level_db' ) {
				$logLevels = $option;
				break;
			}
		}

		$this->assertIsArray( $logLevels, 'log_level_db option definition should exist' );
		$this->assertSame( [ 'warning', 'notice' ], $logLevels[ 'default' ] ?? [] );

		$values = \array_map(
			static fn( array $value ) :string => (string)( $value[ 'value_key' ] ?? '' ),
			$logLevels[ 'value_options' ] ?? []
		);
		$this->assertSame( [ 'disabled', 'warning', 'notice', 'info' ], $values );
	}

	public function test_event_spec_contains_no_legacy_alert_or_debug_levels() :void {
		$events = $this->decodePluginJsonFile( 'plugin-spec/46_events.json', 'Events specification' );
		foreach ( $events as $eventKey => $eventDef ) {
			$level = $eventDef[ 'level' ] ?? null;
			if ( $level !== null ) {
				$this->assertNotSame( 'alert', $level, "Event '{$eventKey}' should not use 'alert'" );
				$this->assertNotSame( 'debug', $level, "Event '{$eventKey}' should not use 'debug'" );
			}
		}
	}
}
