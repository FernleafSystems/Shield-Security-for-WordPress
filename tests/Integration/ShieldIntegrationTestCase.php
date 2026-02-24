<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Database\DbCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRulesCache;
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
		$this->resetScanResultCountMemoization();
		$this->disablePremiumCapabilities();
	}

	public function tear_down() {
		$this->disablePremiumCapabilities();
		$this->truncateShieldTables();
		$this->resetIpCaches();
		if ( static::con() !== null ) {
			$this->resetScanResultCountMemoization();
		}
		parent::tear_down();
	}

	/**
	 * Enable premium mode for integration tests with only the requested capabilities.
	 */
	protected function enablePremiumCapabilities( array $capabilities = [] ) :void {
		$con = $this->requireController();
		$ts = \time();

		$con->comps->license->updateLicenseData( [
			'checksum'         => 'integration-test-license',
			'success'          => true,
			'license'          => 'valid',
			'expires'          => 'lifetime',
			'last_request_at'  => $ts,
			'last_verified_at' => $ts,
			'capabilities'     => \array_values( \array_unique( \array_filter(
				$capabilities,
				fn( $cap ) => \is_string( $cap ) && $cap !== ''
			) ) ),
			'lic_version'      => 1,
		] );

		$con->opts
			->optSet( 'license_activated_at', $ts )
			->optSet( 'license_deactivated_at', 0 );
	}

	protected function disablePremiumCapabilities() :void {
		$con = static::con();
		if ( $con === null ) {
			return;
		}
		$con->comps->license->updateLicenseData( [] );
		$con->opts
			->optSet( 'license_activated_at', 0 )
			->optSet( 'license_deactivated_at', 0 );
	}

	// ── Controller helpers ─────────────────────────────────────────

	protected function requireController() :Controller {
		$con = static::con();
		if ( $con === null ) {
			$this->markTestSkipped( 'Shield Controller is not available.' );
		}
		return $con;
	}

	protected function isControllerConfigReady() :bool {
		$con = static::con();
		if ( !$con instanceof Controller ) {
			return false;
		}

		try {
			$cfg = $con->cfg;
		}
		catch ( \Throwable $e ) {
			return false;
		}

		return \is_object( $cfg );
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

	protected function setSecurityAdminContext( bool $enabled = true ) :void {
		$this->requireController()->this_req->is_security_admin = $enabled;
	}

	protected function createAdministratorUser( array $userData = [] ) :int {
		return self::factory()->user->create( \array_merge(
			[
				'role' => 'administrator',
			],
			$userData
		) );
	}

	protected function loginAsAdministrator( array $userData = [] ) :int {
		$userId = $this->createAdministratorUser( $userData );
		\wp_set_current_user( $userId );
		$this->setSecurityAdminContext( false );
		return $userId;
	}

	protected function loginAsSecurityAdmin( array $userData = [] ) :int {
		$userId = $this->loginAsAdministrator( $userData );
		$this->setSecurityAdminContext( true );
		return $userId;
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

		// FirewallPatternFoundInRequest static request-param cache
		$fpClass = \FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\FirewallPatternFoundInRequest::class;
		if ( \class_exists( $fpClass ) ) {
			$ref4 = new \ReflectionClass( $fpClass );
			if ( $ref4->hasProperty( 'ParamsToAssess' ) ) {
				$p = $ref4->getProperty( 'ParamsToAssess' );
				$p->setAccessible( true );
				$p->setValue( null, null );
			}
		}

		// ExtractSubConditions static dependency caches
		$escClass = \FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\ExtractSubConditions::class;
		if ( \class_exists( $escClass ) ) {
			$ref5 = new \ReflectionClass( $escClass );
			foreach ( [ 'ConditionDeps', 'AllConditions' ] as $prop ) {
				if ( $ref5->hasProperty( $prop ) ) {
					$p = $ref5->getProperty( $prop );
					$p->setAccessible( true );
					$p->setValue( null, [] );
				}
			}
		}

		// Keep setup noise low if bootstrap has already identified controller boot issues.
		if ( $this->isControllerConfigReady() ) {
			IpRulesCache::ResetAll();
		}
	}

	protected function resetScanResultCountMemoization() :void {
		$this->requireController()
			 ->comps
			 ->scans
			 ->resetScanResultsCountMemoization();
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
	 * Disables FK checks to allow truncation of tables referenced by foreign keys.
	 */
	protected function truncateShieldTables() :void {
		$con = static::con();
		if ( $con === null ) {
			return;
		}

		global $wpdb;
		$prefix = $con->getPluginPrefix( '_' );

		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' );
		foreach ( DbCon::MAP as $dbKey => $spec ) {
			$tableName = $wpdb->prefix.$prefix.'_'.$spec[ 'slug' ];
			$wpdb->query( "TRUNCATE TABLE `{$tableName}`" );
		}
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
	}

	protected function compactSnippet( string $value, int $limit = 180 ) :string {
		$single_line = \preg_replace( '/\s+/', ' ', \trim( $value ) );
		if ( !\is_string( $single_line ) ) {
			$single_line = '';
		}
		return \strlen( $single_line ) > $limit ? \substr( $single_line, 0, $limit ).'...' : $single_line;
	}

	protected function assertHtmlContainsMarker( string $marker, string $html, string $label ) :void {
		$this->assertTrue(
			\strpos( $html, $marker ) !== false,
			\sprintf(
				'%s missing marker "%s" (html_len=%d, html_head="%s")',
				$label,
				$marker,
				\strlen( $html ),
				$this->compactSnippet( $html )
			)
		);
	}

	protected function assertHtmlNotContainsMarker( string $marker, string $html, string $label ) :void {
		$this->assertTrue(
			\strpos( $html, $marker ) === false,
			\sprintf(
				'%s unexpectedly contains marker "%s" (html_len=%d, html_head="%s")',
				$label,
				$marker,
				\strlen( $html ),
				$this->compactSnippet( $html )
			)
		);
	}
}
