<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRulesCache;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * Enhanced base test case for Shield security-logic integration tests.
 *
 * Provides helpers for DB readiness checks, static cache resets,
 * event capture, and per-test data cleanup.
 */
abstract class ShieldIntegrationTestCase extends ShieldWordPressTestCase {

	private static ?\FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest $baselineThisRequest = null;

	/**
	 * Events captured via the shield/event hook during a test.
	 *
	 * @var array[]
	 */
	private array $capturedEvents = [];

	/** @var string[] */
	private array $transactionScopedTables = [];

	public function set_up() {
		parent::set_up();
		if ( static::con() !== null ) {
			RuntimeTestState::restoreOptions( [], false );
			RuntimeTestState::resetMfaProviderCache();
			$this->resetThisRequestState();
			RuntimeTestState::resetRequestLoggerState();
		}
		$this->capturedEvents = [];
		$this->transactionScopedTables = [];
		$this->resetIpCaches();
		$this->resetScanResultCountMemoization();
		$this->disablePremiumCapabilities();
	}

	public function tear_down() {
		$this->disablePremiumCapabilities();
		if ( static::con() !== null ) {
			RuntimeTestState::resetRequestLoggerState();
		}
		$this->resetIpCaches();
		if ( static::con() !== null ) {
			$this->resetScanResultCountMemoization();
		}
		$parentFailure = null;
		try {
			parent::tear_down();
		}
		catch ( \Throwable $e ) {
			$parentFailure = $e;
		}

		global $wpdb;
		$cleanupFailures = [];
		foreach ( $this->transactionScopedTables as $table ) {
			$wpdb->last_error = '';
			if ( $wpdb->query( "DROP TEMPORARY TABLE IF EXISTS `{$table}`" ) === false || $wpdb->last_error !== '' ) {
				$cleanupFailures[] = $table.': '.( $wpdb->last_error !== '' ? $wpdb->last_error : 'unknown database error' );
			}
		}
		$this->transactionScopedTables = [];
		if ( $cleanupFailures !== [] ) {
			throw new \RuntimeException(
				( $parentFailure === null ? '' : 'Parent teardown failed: '.$parentFailure->getMessage().'. ' )
				.'Failed to remove transaction-scoped tables: '.\implode( '; ', $cleanupFailures ),
				0,
				$parentFailure
			);
		}
		if ( $parentFailure !== null ) {
			throw $parentFailure;
		}
	}

	/**
	 * Enable premium mode for integration tests with only the requested capabilities.
	 */
	protected function enablePremiumCapabilities( array $capabilities = [] ) :void {
		$this->requireController();
		RuntimeTestState::applyPremiumCapabilities( $capabilities );
	}

	protected function disablePremiumCapabilities() :void {
		if ( static::con() === null ) {
			return;
		}
		RuntimeTestState::disablePremiumCapabilities();
	}

	protected function snapshotSelectedOptions( array $keys ) :array {
		$this->requireController();
		return RuntimeTestState::snapshotOptions( \array_map(
			static fn( $key ) :string => (string)$key,
			$keys
		) );
	}

	protected function restoreSelectedOptions( array $snapshot, bool $store = true ) :void {
		if ( static::con() === null ) {
			return;
		}
		RuntimeTestState::restoreOptions( $snapshot, $store );
	}

	/**
	 * Load an optional handler against a WordPress-rewritten temporary table.
	 * This keeps ordinary fixture setup inside the per-test transaction model.
	 *
	 * @return mixed
	 */
	protected function requireTransactionScopedDb( string $dbKey ) {
		$con = $this->requireController();
		$handler = $con->db_con->loadDbH( $con->db_con::MAP[ $dbKey ][ 'slug' ], true );
		if ( empty( $handler ) ) {
			throw new \RuntimeException( \sprintf( 'DB handler "%s" could not be loaded.', $dbKey ) );
		}

		global $wpdb;
		$wpdb->last_error = '';
		$result = $wpdb->query( $handler->getTableSchema()->buildCreate() );
		if ( $result === false || $wpdb->last_error !== '' ) {
			throw new \RuntimeException( \sprintf(
				'Transaction-scoped table for DB handler "%s" could not be created: %s',
				$dbKey,
				$wpdb->last_error !== '' ? $wpdb->last_error : 'unknown database error'
			) );
		}

		$readyProperty = new \ReflectionProperty(
			\FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler::class,
			'isReady'
		);
		$readyProperty->setAccessible( true );
		$readyProperty->setValue( $handler, true );
		$this->transactionScopedTables[ $handler->getTable() ] = $handler->getTable();

		return $handler;
	}

	// Controller helpers.

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
		try {
			$this->requireController();
			$handler = RuntimeTestState::requireDbHandler( $dbKey );
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
		if ( $userData === [] ) {
			return RuntimeTestState::loginAsSecurityAdmin();
		}

		$userId = $this->loginAsAdministrator( $userData );
		$this->setSecurityAdminContext( true );
		return $userId;
	}

	// Cache resets.

	protected function resetIpCaches() :void {
		// IpRuleStatus static caches
		$ref = new \ReflectionClass( IpRuleStatus::class );

		foreach ( [ 'cache', 'ranges', 'rangeMatchers' ] as $prop ) {
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
		$this->requireController();
		RuntimeTestState::resetScanResultCountMemoization();
	}

	private function resetThisRequestState() :void {
		$con = $this->requireController();
		if ( self::$baselineThisRequest === null ) {
			self::$baselineThisRequest = $this->cloneThisRequest( $con->this_req );
		}
		$con->this_req = $this->cloneThisRequest( self::$baselineThisRequest );
	}

	private function cloneThisRequest(
		\FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest $source
	) :\FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest {
		$copy = clone $source;
		$raw = $source->getRawData();
		foreach ( $raw as $key => $value ) {
			if ( \is_object( $value ) && ( new \ReflectionObject( $value ) )->isCloneable() ) {
				$raw[ $key ] = clone $value;
			}
		}
		$copy->applyFromArray( $raw );
		return $copy;
	}

	// Event capture.

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

	/**
	 * Run a method-scoped database operation which may implicitly commit the
	 * transaction owned by WP_UnitTestCase.
	 *
	 * The exercise must restage every fixture it needs after the first rollback.
	 * The restoration callback owns only the exact persistent state changed by
	 * the exercise.
	 *
	 * @return mixed
	 */
	protected function runWithPersistentDatabaseMutation( callable $exercise, callable $restoration ) {
		global $wp_filter;

		$queryHook = $wp_filter[ 'query' ] ?? null;
		$createHook = [ $this, '_create_temporary_tables' ];
		$dropHook = [ $this, '_drop_temporary_tables' ];
		if ( !$queryHook instanceof \WP_Hook
			 || \has_filter( 'query', $createHook ) === false
			 || \has_filter( 'query', $dropHook ) === false ) {
			throw new \RuntimeException( 'The WordPress database transaction/query-hook contract is unavailable.' );
		}

		$originalQueryHook = clone $queryHook;

		$result = null;
		$exerciseFailure = null;
		$restorationFailure = null;
		try {
			$this->executeRequiredDatabaseStatement( 'SAVEPOINT shield_persistent_mutation_contract' );
			$this->executeRequiredDatabaseStatement( 'ROLLBACK' );
			\remove_filter( 'query', $createHook, (int)\has_filter( 'query', $createHook ) );
			\remove_filter( 'query', $dropHook, (int)\has_filter( 'query', $dropHook ) );
			try {
				$result = $exercise();
			}
			catch ( \Throwable $e ) {
				$exerciseFailure = $e;
			}

			try {
				$this->executeRequiredDatabaseStatement( 'ROLLBACK' );
				$restoration();
				$this->executeRequiredDatabaseStatement( 'COMMIT' );
			}
			catch ( \Throwable $e ) {
				$restorationFailure = $e;
				try {
					$this->executeRequiredDatabaseStatement( 'ROLLBACK' );
				}
				catch ( \Throwable $rollbackFailure ) {
					$restorationFailure = new \RuntimeException(
						$e->getMessage().' Recovery rollback also failed: '.$rollbackFailure->getMessage(),
						0,
						$e
					);
				}
			}

			try {
				$this->executeRequiredDatabaseStatement( 'SET autocommit=0' );
				$this->executeRequiredDatabaseStatement( 'START TRANSACTION' );
			}
			catch ( \Throwable $e ) {
				$restorationFailure = new \RuntimeException(
					$restorationFailure === null
						? 'Failed to restore the WordPress transaction contract: '.$e->getMessage()
						: $restorationFailure->getMessage().' Transaction restart also failed: '.$e->getMessage(),
					0,
					$restorationFailure ?? $e
				);
			}
		}
		finally {
			$wp_filter[ 'query' ] = $originalQueryHook;
		}

		if ( $exerciseFailure !== null && $restorationFailure !== null ) {
			throw new \RuntimeException(
				'Persistent database exercise failed: '.$exerciseFailure->getMessage()
				.'. Restoration also failed: '.$restorationFailure->getMessage(),
				0,
				$exerciseFailure
			);
		}
		if ( $restorationFailure !== null ) {
			throw $restorationFailure;
		}
		if ( $exerciseFailure !== null ) {
			throw $exerciseFailure;
		}

		return $result;
	}

	private function executeRequiredDatabaseStatement( string $sql ) :void {
		global $wpdb;

		$wpdb->last_error = '';
		$result = $wpdb->query( $sql );
		if ( $result === false || $wpdb->last_error !== '' ) {
			throw new \RuntimeException( \sprintf(
				'Database statement failed (%s): %s',
				$sql,
				$wpdb->last_error !== '' ? $wpdb->last_error : 'unknown database error'
			) );
		}
	}

	protected function compactSnippet( string $value, int $limit = 180 ) :string {
		$single_line = \preg_replace( '/\s+/', ' ', \trim( $value ) );
		if ( !\is_string( $single_line ) ) {
			$single_line = '';
		}
		return \strlen( $single_line ) > $limit ? \substr( $single_line, 0, $limit ).'...' : $single_line;
	}

	protected function htmlContainsMarker( string $marker, string $html ) :bool {
		if ( \strpos( $html, $marker ) !== false ) {
			return true;
		}

		$decodedHtml = $this->decodeHtmlEntities( $html );
		if ( \strpos( $decodedHtml, $marker ) !== false ) {
			return true;
		}

		$decodedMarker = $this->decodeHtmlEntities( $marker );
		return $decodedMarker !== $marker
			   && ( \strpos( $html, $decodedMarker ) !== false
					|| \strpos( $decodedHtml, $decodedMarker ) !== false );
	}

	protected function decodeHtmlEntities( string $value ) :string {
		return \html_entity_decode( $value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8' );
	}

	protected function assertHtmlContainsMarker( string $marker, string $html, string $label ) :void {
		$this->assertTrue(
			$this->htmlContainsMarker( $marker, $html ),
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
			!$this->htmlContainsMarker( $marker, $html ),
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
