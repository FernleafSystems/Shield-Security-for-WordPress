<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Smoke test: every event defined in the spec must have a corresponding
 * name + audit string entry in EventStrings, and vice-versa.
 *
 * This prevents "Empty" labels appearing in the Activity Log search pane
 * when an event slug exists in the database but has no display name.
 */
class EventStringsCompletenessTest extends TestCase {

	use PluginPathsTrait;

	/**
	 * @dataProvider provideSpecEventKeys
	 */
	public function testSpecEventHasStrings( string $eventKey ) :void {
		$stringKeys = $this->getEventStringKeys();
		$this->assertContains(
			$eventKey,
			$stringKeys,
			\sprintf(
				"Event '%s' is defined in the spec (46_events.json) but has no entry in EventStrings::theStrings(). "
				."Activity Log will show an 'Empty' label for this event.",
				$eventKey
			)
		);
	}

	/**
	 * @dataProvider provideEventStringKeys
	 */
	public function testEventStringHasSpecEntry( string $eventKey ) :void {
		$specKeys = $this->getSpecEventKeys();
		$this->assertContains(
			$eventKey,
			$specKeys,
			\sprintf(
				"Event '%s' has strings in EventStrings but is not defined in the spec (46_events.json). "
				."This is dead code that should be removed.",
				$eventKey
			)
		);
	}

	public function testSpecEventHasNameString() :void {
		$stringEntries = $this->getEventStringEntries();
		foreach ( $this->getSpecEventKeys() as $eventKey ) {
			$this->assertArrayHasKey(
				$eventKey,
				$stringEntries,
				\sprintf( "Event '%s' missing from EventStrings.", $eventKey )
			);
			$this->assertArrayHasKey(
				'name',
				$stringEntries[ $eventKey ] ?? [],
				\sprintf( "Event '%s' entry in EventStrings is missing the 'name' key.", $eventKey )
			);
			$this->assertArrayHasKey(
				'audit',
				$stringEntries[ $eventKey ] ?? [],
				\sprintf( "Event '%s' entry in EventStrings is missing the 'audit' key.", $eventKey )
			);
		}
	}

	public static function provideSpecEventKeys() :array {
		$specPath = static::resolvePluginRoot().'/plugin-spec/46_events.json';
		$events = \json_decode( (string)\file_get_contents( $specPath ), true );
		return \array_map( fn( $key ) => [ $key ], \array_keys( $events ?? [] ) );
	}

	public static function provideEventStringKeys() :array {
		$keys = static::parseEventStringKeysFromSource();
		return \array_map( fn( $key ) => [ $key ], $keys );
	}

	private function getSpecEventKeys() :array {
		$events = $this->decodePluginJsonFile( 'plugin-spec/46_events.json', 'Events spec' );
		return \array_keys( $events );
	}

	private function getEventStringKeys() :array {
		return static::parseEventStringKeysFromSource();
	}

	/**
	 * Parse EventStrings source to extract each event entry's structure (name/audit keys).
	 */
	private function getEventStringEntries() :array {
		$source = $this->getPluginFileContents( 'src/Events/EventStrings.php', 'EventStrings source' );

		// Extract the body of theStrings() return array
		$startPos = \strpos( $source, 'return [' );
		if ( $startPos === false ) {
			$this->fail( 'Could not find "return [" in EventStrings source.' );
		}

		$entries = [];
		$currentKey = null;
		$hasName = false;
		$hasAudit = false;

		foreach ( \explode( "\n", \substr( $source, $startPos ) ) as $line ) {
			// Match top-level event key: 3 tabs + 'key' => [
			if ( \preg_match( "/^\t{3}'([a-z0-9_]+)'\s*=>/", $line, $m ) ) {
				// Save previous entry
				if ( $currentKey !== null ) {
					$entries[ $currentKey ] = [];
					if ( $hasName ) {
						$entries[ $currentKey ]['name'] = true;
					}
					if ( $hasAudit ) {
						$entries[ $currentKey ]['audit'] = true;
					}
				}
				$currentKey = $m[1];
				$hasName = false;
				$hasAudit = false;
			}
			elseif ( $currentKey !== null ) {
				if ( \preg_match( "/^\t{4}'name'/", $line ) ) {
					$hasName = true;
				}
				if ( \preg_match( "/^\t{4}'audit'/", $line ) ) {
					$hasAudit = true;
				}
			}
		}
		// Save last entry
		if ( $currentKey !== null ) {
			$entries[ $currentKey ] = [];
			if ( $hasName ) {
				$entries[ $currentKey ]['name'] = true;
			}
			if ( $hasAudit ) {
				$entries[ $currentKey ]['audit'] = true;
			}
		}

		return $entries;
	}

	/**
	 * Extract top-level array keys from EventStrings::theStrings() by parsing source.
	 */
	private static function parseEventStringKeysFromSource() :array {
		$path = static::resolvePluginRoot().'/src/Events/EventStrings.php';
		$source = (string)\file_get_contents( $path );
		\preg_match_all( "/^\t{3}'([a-z0-9_]+)'\s*=>/m", $source, $matches );
		return $matches[1] ?? [];
	}

	private static function resolvePluginRoot() :string {
		$envPath = \getenv( 'SHIELD_PACKAGE_PATH' );
		if ( $envPath !== false && !empty( $envPath ) ) {
			return $envPath;
		}
		return \dirname( \dirname( \dirname( __DIR__ ) ) );
	}
}
