<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Database\DbCon;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler as IpRulesHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

/**
 * Enhanced base test case for Shield security-logic integration tests.
 *
 * Provides helpers for DB readiness checks, static cache resets,
 * event capture, and per-test data cleanup.
 */
abstract class ShieldIntegrationTestCase extends ShieldWordPressTestCase {

	/**
	 * Events captured via the shield/event hook during a test.
	 *
	 * @var array[]
	 */
	private array $capturedEvents = [];

	public function set_up() {
		parent::set_up();
		$this->capturedEvents = [];
		$this->resetIpCaches();
	}

	public function tear_down() {
		$this->truncateShieldTables();
		$this->resetIpCaches();
		parent::tear_down();
	}

	// ── Controller helpers ─────────────────────────────────────────

	protected function requireController() :Controller {
		$con = static::con();
		if ( $con === null ) {
			$this->markTestSkipped( 'Shield Controller is not available.' );
		}
		return $con;
	}

	/**
	 * Load a DB handler by its key in DbCon::MAP and assert it is ready.
	 * Returns the handler or skips the test.
	 *
	 * @return \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler|mixed
	 */
	protected function requireDb( string $dbKey ) {
		$con = $this->requireController();
		try {
			$handler = $con->db_con->load( $dbKey );
		}
		catch ( \Exception $e ) {
			$this->markTestSkipped( "DB handler '{$dbKey}' could not be loaded: ".$e->getMessage() );
		}
		if ( empty( $handler ) || !$handler->isReady() ) {
			$this->markTestSkipped( "DB handler '{$dbKey}' is not ready." );
		}
		return $handler;
	}

	// ── Cache resets ───────────────────────────────────────────────

	protected function resetIpCaches() :void {
		// IpRuleStatus static caches
		$ref = new \ReflectionClass( IpRuleStatus::class );

		foreach ( [ 'cache', 'ranges', 'bypass' ] as $prop ) {
			if ( $ref->hasProperty( $prop ) ) {
				$p = $ref->getProperty( $prop );
				$p->setAccessible( true );
				$p->setValue( null, $prop === 'cache' ? [] : null );
			}
		}

		// IPRecords static IP cache
		$ipRecordsClass = \FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords::class;
		if ( \class_exists( $ipRecordsClass ) ) {
			$ref2 = new \ReflectionClass( $ipRecordsClass );
			if ( $ref2->hasProperty( 'ips' ) ) {
				$p = $ref2->getProperty( 'ips' );
				$p->setAccessible( true );
				$p->setValue( null, [] );
			}
		}

		// ProcessConditions static condition cache
		$pcClass = \FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\ProcessConditions::class;
		if ( \class_exists( $pcClass ) ) {
			$ref3 = new \ReflectionClass( $pcClass );
			if ( $ref3->hasProperty( 'ConditionsCache' ) ) {
				$p = $ref3->getProperty( 'ConditionsCache' );
				$p->setAccessible( true );
				$p->setValue( null, null );
			}
		}
	}

	// ── Event capture ──────────────────────────────────────────────

	/**
	 * Begin capturing shield/event firings. Call early in a test method.
	 */
	protected function captureShieldEvents() :void {
		$this->capturedEvents = [];
		add_action( 'shield/event', function ( $event, $meta = [], $def = [] ) {
			$this->capturedEvents[] = [
				'event' => (string)$event,
				'meta'  => \is_array( $meta ) ? $meta : [],
				'def'   => \is_array( $def ) ? $def : [],
			];
		}, 5, 3 );
	}

	/**
	 * @return array[]
	 */
	protected function getCapturedEvents() :array {
		return $this->capturedEvents;
	}

	/**
	 * Return only captured events whose key matches $eventKey.
	 *
	 * @return array[]
	 */
	protected function getCapturedEventsByKey( string $eventKey ) :array {
		return \array_values( \array_filter(
			$this->capturedEvents,
			fn( array $e ) => $e[ 'event' ] === $eventKey
		) );
	}

	// ── Table cleanup ──────────────────────────────────────────────

	/**
	 * Truncate all Shield custom tables so every test starts clean.
	 */
	protected function truncateShieldTables() :void {
		$con = static::con();
		if ( $con === null ) {
			return;
		}

		global $wpdb;
		$prefix = $con->getPluginPrefix( '_' );

		foreach ( DbCon::MAP as $dbKey => $spec ) {
			$tableName = $wpdb->prefix.$prefix.'_'.$spec[ 'slug' ];
			$wpdb->query( "TRUNCATE TABLE `{$tableName}`" );
		}
	}
}
