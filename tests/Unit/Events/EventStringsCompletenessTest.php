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
		$specPath = static::resolveExistingFilePath( 'plugin-spec/46_events.json' );
		if ( $specPath === null ) {
			return [];
		}
		$events = \json_decode( (string)\file_get_contents( $specPath ), true );
		return \array_map( fn( $key ) => [ $key ], \array_keys( $events ?? [] ) );
	}

	public static function provideEventStringKeys() :array {
		$keys = static::parseEventStringKeysFromSource();
		return \array_map( fn( $key ) => [ $key ], $keys );
	}

	private function getSpecEventKeys() :array {
		$specPath = static::resolveExistingFilePath( 'plugin-spec/46_events.json' );
		if ( $specPath === null ) {
			$this->markTestSkipped( 'Events completeness checks require plugin-spec/46_events.json, which is not available in this environment.' );
		}

		$events = \json_decode( (string)\file_get_contents( $specPath ), true );
		$this->assertSame( \JSON_ERROR_NONE, \json_last_error(), 'Events spec should contain valid JSON: '.\json_last_error_msg() );
		$this->assertIsArray( $events, 'Events spec should decode to an array structure' );
		return \array_keys( $events );
	}

	private function getEventStringKeys() :array {
		return static::parseEventStringKeysFromSource();
	}

	/**
	 * Parse EventStrings source to extract each event entry's structure (name/audit keys).
	 */
	private function getEventStringEntries() :array {
		$sourcePath = static::resolveExistingFilePath( 'src/Events/EventStrings.php' );
		if ( $sourcePath === null ) {
			$this->markTestSkipped( 'Events completeness checks require src/Events/EventStrings.php, which is not available in this environment.' );
		}
		$source = (string)\file_get_contents( $sourcePath );

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
		$path = static::resolveExistingFilePath( 'src/Events/EventStrings.php' );
		if ( $path === null ) {
			return [];
		}
		$source = (string)\file_get_contents( $path );
		\preg_match_all( "/^\t{3}'([a-z0-9_]+)'\s*=>/m", $source, $matches );
		return $matches[1] ?? [];
	}

	private static function resolveExistingFilePath( string $relativePath ) :?string {
		$relativePath = \ltrim( $relativePath, '/' );
		foreach ( static::getCandidateRoots() as $root ) {
			$path = $root.'/'.$relativePath;
			if ( \file_exists( $path ) ) {
				return $path;
			}
		}
		return null;
	}

	private static function getCandidateRoots() :array {
		$roots = [];

		$envPath = \getenv( 'SHIELD_PACKAGE_PATH' );
		if ( $envPath !== false && !empty( $envPath ) ) {
			$roots[] = \rtrim( $envPath, '/\\' );
		}

		$roots[] = \dirname( \dirname( \dirname( __DIR__ ) ) );

		$cwd = \getcwd();
		if ( \is_string( $cwd ) && $cwd !== '' ) {
			$roots[] = \rtrim( $cwd, '/\\' );
		}

		// Remove duplicates while preserving order.
		$roots = \array_values( \array_unique( $roots ) );

		// Keep only existing directories to avoid useless file checks.
		return \array_values(
			\array_filter(
				$roots,
				static fn( string $root ) :bool => \is_dir( $root )
			)
		);
	}

}
