<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Components\ComponentLoader;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Tests the ComponentLoader lazy-loading mechanism: verifies that all
 * mapped components can be instantiated, and that singleton behaviour holds.
 */
class ComponentLoaderTest extends ShieldIntegrationTestCase {

	/**
	 * Read the component map dynamically via reflection to avoid hardcoding keys.
	 */
	private function getComponentMap() :array {
		$con = $this->requireController();
		$ref = new \ReflectionMethod( $con->comps, 'getConsMap' );
		$ref->setAccessible( true );
		return $ref->invoke( $con->comps );
	}

	/**
	 * Every key in the component map should produce a non-null object when accessed.
	 * Some components may throw due to missing dependencies, so we collect failures
	 * rather than failing on the first one.
	 */
	public function test_all_mapped_components_load() {
		$con = $this->requireController();
		$comps = $con->comps;
		$map = $this->getComponentMap();

		$this->assertNotEmpty( $map, 'Component map should not be empty' );

		$failures = [];
		foreach ( \array_keys( $map ) as $key ) {
			try {
				$component = $comps->{$key};
				if ( $component === null ) {
					$failures[] = $key.' (returned null)';
				}
			}
			catch ( \Throwable $e ) {
				$failures[] = $key.' ('.$e->getMessage().')';
			}
		}

		$this->assertEmpty( $failures, 'These components failed to load: '.\implode( ', ', $failures ) );
	}

	public function test_same_key_returns_same_instance() {
		$con = $this->requireController();
		$comps = $con->comps;

		$first = $comps->events;
		$second = $comps->events;

		$this->assertSame( $first, $second, 'Accessing the same component key twice should return the same instance' );
	}

	public function test_events_service_is_correct_type() {
		$con = $this->requireController();
		$events = $con->comps->events;

		$this->assertInstanceOf(
			\FernleafSystems\Wordpress\Plugin\Shield\Events\EventsService::class,
			$events
		);
	}
}
