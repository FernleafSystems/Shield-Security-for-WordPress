<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class FixtureLifecycleRollbackIsolationIntegrationTest extends ShieldIntegrationTestCase {

	private static array $teardownSql = [];
	private static ?string $fixtureSuffix = null;

	public function tear_down() {
		$queries = [];
		$capture = static function ( string $query ) use ( &$queries ) :string {
			$queries[] = $query;
			return $query;
		};

		\add_filter( 'query', $capture, \PHP_INT_MAX );
		try {
			parent::tear_down();
		}
		finally {
			\remove_filter( 'query', $capture, \PHP_INT_MAX );
			self::$teardownSql = \array_merge( self::$teardownSql, $queries );
		}
	}

	public function test_producer_persists_an_ordinary_option() :string {
		$key = $this->fixtureKey( 'ordinary_option' );
		\update_option( $key, 'ordinary fixture state', false );

		$this->assertSame( 'ordinary fixture state', \get_option( $key ) );
		return $key;
	}

	/**
	 * @depends test_producer_persists_an_ordinary_option
	 */
	public function test_consumer_observes_ordinary_option_rollback( string $key ) :void {
		$this->assertNull(
			$this->storedOptionValue( $key ),
			'An ordinary WordPress option must be rolled back after its producer test.'
		);
	}

	public function test_producer_persists_a_shield_option() :string {
		$key = 'shield_'.$this->fixtureKey( 'option' );
		\update_option( $key, 'Shield fixture state', false );

		$this->assertSame( 'Shield fixture state', \get_option( $key ) );
		return $key;
	}

	/**
	 * @depends test_producer_persists_a_shield_option
	 */
	public function test_consumer_observes_shield_option_rollback( string $key ) :void {
		$this->assertNull(
			$this->storedOptionValue( $key ),
			'A Shield-prefixed option must be rolled back after its producer test.'
		);
	}

	public function test_producer_persists_a_user_and_usermeta() :int {
		$userId = self::factory()->user->create( [
			'user_login' => $this->fixtureKey( 'user' ),
			'user_pass'  => 'fixture-password',
		] );
		\add_user_meta( $userId, $this->fixtureKey( 'usermeta' ), 'core fixture state' );

		$this->assertInstanceOf( \WP_User::class, \get_user_by( 'id', $userId ) );
		$this->assertSame( 'core fixture state', \get_user_meta( $userId, $this->fixtureKey( 'usermeta' ), true ) );
		return $userId;
	}

	/**
	 * @depends test_producer_persists_a_user_and_usermeta
	 */
	public function test_consumer_observes_user_and_usermeta_rollback( int $userId ) :void {
		$this->assertFalse(
			\get_user_by( 'id', $userId ),
			'A core user must be rolled back after its producer test.'
		);
		$this->assertSame( [], \get_user_meta( $userId, $this->fixtureKey( 'usermeta' ) ) );
	}

	public function test_producer_persists_a_shield_handler_row() :int {
		$dbh = $this->requireDb( 'malware' );
		$id = TestDataFactory::insertMalwareRecord( $this->fixtureKey( 'handler_row' ).'.php' );

		$this->assertNotEmpty( $dbh->getQuerySelector()->byId( $id ) );
		return $id;
	}

	/**
	 * @depends test_producer_persists_a_shield_handler_row
	 */
	public function test_consumer_observes_shield_handler_row_rollback( int $id ) :void {
		$this->assertEmpty( $this->requireDb( 'malware' )->getQuerySelector()->byId( $id ) );
	}

	/**
	 * @return array{
	 *     handler:Handler,
	 *     table:string,
	 *     physical_baseline:bool,
	 *     service_baseline:bool,
	 *     events_ready_baseline:bool
	 * }
	 * @group database-compat
	 */
	public function test_transaction_scoped_optional_table_producer_tracks_runtime_ownership() :array {
		global $wpdb;

		$table = $this->schemaForDbKey( 'file_locker' )->table;
		$physicalBaseline = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== null;
		Services::WpDb()->clearResultShowTables();
		$serviceBaseline = Services::WpDb()->tableExists( $table );
		$this->assertSame( $physicalBaseline, $serviceBaseline );

		$firstHandler = $this->requireTransactionScopedDb( 'file_locker' );
		$secondHandler = $this->requireTransactionScopedDb( 'file_locker' );
		$this->assertNotSame( $firstHandler, $secondHandler, 'Repeated fixture setup should reload the owned handler.' );

		$readyCache = Handler::GetTableReadyCache();
		$readyCache->setReady( $secondHandler->getTableSchema() );
		$eventsSchema = $this->schemaForDbKey( 'events' );
		$eventsReadyBaseline = $readyCache->isReady( $eventsSchema );
		if ( !$eventsReadyBaseline ) {
			$readyCache->setReady( $eventsSchema );
		}
		Services::WpDb()->tableExists( $table );

		return [
			'handler'               => $secondHandler,
			'table'                 => $table,
			'physical_baseline'     => $physicalBaseline,
			'service_baseline'      => $serviceBaseline,
			'events_ready_baseline' => $eventsReadyBaseline,
		];
	}

	/**
	 * @param array{
	 *     handler:Handler,
	 *     table:string,
	 *     physical_baseline:bool,
	 *     service_baseline:bool,
	 *     events_ready_baseline:bool
	 * } $fixture
	 * @depends test_transaction_scoped_optional_table_producer_tracks_runtime_ownership
	 * @group database-compat
	 */
	public function test_transaction_scoped_optional_table_consumer_observes_complete_cleanup( array $fixture ) :void {
		global $wpdb;

		$readyCache = Handler::GetTableReadyCache();
		$eventsSchema = $this->schemaForDbKey( 'events' );
		try {
			$this->assertFalse(
				$readyCache->isReady( $this->schemaForDbKey( 'file_locker' ) ),
				'The removed temporary table must not retain a ready-cache entry.'
			);
			$this->assertTrue(
				$readyCache->isReady( $eventsSchema ),
				'Targeted fixture cleanup must preserve unrelated ready-cache entries.'
			);

			$previousSuppressErrors = $wpdb->suppress_errors( true );
			$previousLastError = $wpdb->last_error;
			try {
				$wpdb->last_error = '';
				$dropResult = $wpdb->query( "DROP TEMPORARY TABLE `{$fixture['table']}`" );
				$dropError = $wpdb->last_error;
			}
			finally {
				$wpdb->last_error = $previousLastError;
				$wpdb->suppress_errors( $previousSuppressErrors );
			}
			$this->assertFalse(
				$dropResult,
				'A transaction-scoped temporary-table shadow must not survive fixture teardown.'
			);
			$this->assertNotSame(
				'',
				$dropError,
				'The absence probe must fail with a database error rather than silently succeeding.'
			);

			$currentHandler = $this->requireController()->db_con->load( 'file_locker' );
			$this->assertNotSame(
				$fixture[ 'handler' ],
				$currentHandler,
				'The helper-loaded handler must not survive its fixture teardown.'
			);

			$rawExists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $fixture[ 'table' ] ) ) !== null;
			$serviceExists = Services::WpDb()->tableExists( $fixture[ 'table' ] );
			$this->assertSame( $fixture[ 'physical_baseline' ], $rawExists );
			$this->assertSame( $fixture[ 'service_baseline' ], $serviceExists );
			$this->assertSame( $rawExists, $serviceExists );
		}
		finally {
			if ( !$fixture[ 'events_ready_baseline' ] ) {
				$readyCache->setReady( $eventsSchema, false );
			}
		}
	}

	/**
	 * @group database-transaction-exception
	 */
	public function test_transaction_scoped_cleanup_continues_after_thrown_drop_and_composes_parent_failure() :void {
		global $wpdb;

		$firstHandler = $this->requireTransactionScopedDb( 'events' );
		$secondSchema = $this->schemaForDbKey( 'file_locker' );
		$secondPhysicalBaseline = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $secondSchema->table )
		) !== null;
		Services::WpDb()->clearResultShowTables();
		$secondServiceBaseline = Services::WpDb()->tableExists( $secondSchema->table );
		$this->assertSame( $secondPhysicalBaseline, $secondServiceBaseline );
		$secondHandler = $this->requireTransactionScopedDb( 'file_locker' );

		$readyCache = Handler::GetTableReadyCache();
		$firstSchema = $firstHandler->getTableSchema();
		$firstReadyBaseline = $readyCache->isReady( $firstSchema );
		$secondReadyBaseline = $readyCache->isReady( $secondSchema );
		$readyCache->setReady( $firstSchema );
		$readyCache->setReady( $secondSchema );
		Services::WpDb()->tableExists( $secondSchema->table );

		$dropQueries = [];
		$dropFault = static function ( string $query ) use ( &$dropQueries, $firstSchema ) :string {
			if ( \stripos( $query, 'DROP TEMPORARY TABLE IF EXISTS' ) !== false ) {
				$dropQueries[] = $query;
				if ( \count( $dropQueries ) === 1 ) {
					throw new \RuntimeException( 'Forced DROP exception for '.$firstSchema->table );
				}
			}
			return $query;
		};
		$parentFailure = new \RuntimeException( 'Forced parent teardown failure.' );

		\add_filter( 'query', $dropFault, \PHP_INT_MAX - 1 );
		try {
			$cleanupFailure = null;
			try {
				$this->cleanupTransactionScopedTables( $parentFailure );
			}
			catch ( \RuntimeException $e ) {
				$cleanupFailure = $e;
			}

			$this->assertNotNull( $cleanupFailure );
			$this->assertSame( $parentFailure, $cleanupFailure->getPrevious() );
			$this->assertStringContainsString( 'Forced parent teardown failure.', $cleanupFailure->getMessage() );
			$this->assertStringContainsString( $firstSchema->table.' drop: Forced DROP exception', $cleanupFailure->getMessage() );
			$this->assertCount( 2, $dropQueries, 'A thrown first DROP must not prevent the later tracked DROP.' );
			$this->assertStringContainsString( $firstSchema->table, $dropQueries[ 0 ] );
			$this->assertStringContainsString( $secondSchema->table, $dropQueries[ 1 ] );
			$this->assertFalse( $readyCache->isReady( $firstSchema ) );
			$this->assertFalse( $readyCache->isReady( $secondSchema ) );
			$this->assertNotSame( $firstHandler, $this->requireController()->db_con->load( 'events' ) );
			$this->assertNotSame( $secondHandler, $this->requireController()->db_con->load( 'file_locker' ) );

			$secondRawExists = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $secondSchema->table )
			) !== null;
			$this->assertSame( $secondPhysicalBaseline, $secondRawExists );
			$this->assertSame( $secondServiceBaseline, Services::WpDb()->tableExists( $secondSchema->table ) );
		}
		finally {
			\remove_filter( 'query', $dropFault, \PHP_INT_MAX - 1 );
			$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS `{$firstSchema->table}`" );
			$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS `{$secondSchema->table}`" );
			Services::WpDb()->clearResultShowTables();
			$this->requireController()->db_con->reset();
			$readyCache->setReady( $firstSchema, $firstReadyBaseline );
			$readyCache->setReady( $secondSchema, $secondReadyBaseline );
		}
	}

	/**
	 * @depends test_consumer_observes_ordinary_option_rollback
	 * @depends test_consumer_observes_shield_option_rollback
	 * @depends test_consumer_observes_user_and_usermeta_rollback
	 * @depends test_consumer_observes_shield_handler_row_rollback
	 */
	public function test_teardown_avoids_global_fixture_cleanup_sql() :void {
		$sql = \implode( "\n", self::$teardownSql );

		$this->assertDoesNotMatchRegularExpression( '/\bTRUNCATE\s+TABLE\b/i', $sql );
		$this->assertDoesNotMatchRegularExpression(
			"/DELETE\\s+FROM\\s+`?[^\\s`]+`?\\s+WHERE\\s+`?option_name`?\\s+LIKE\\s+['\"](?:icwp|shield)_[^'\"]*%['\"]/i",
			$sql
		);
	}

	private function fixtureKey( string $type ) :string {
		if ( self::$fixtureSuffix === null ) {
			self::$fixtureSuffix = \substr( \sha1( __CLASS__.\microtime( true ).\getmypid() ), 0, 12 );
		}
		return 'fixture_lifecycle_'.$type.'_'.self::$fixtureSuffix;
	}

	private function storedOptionValue( string $key ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare(
			"SELECT `option_value` FROM `{$wpdb->options}` WHERE `option_name`=%s",
			$key
		) );
	}

	private function schemaForDbKey( string $dbKey ) :TableSchema {
		$con = $this->requireController();
		$dbSpec = $con->db_con->getHandlers()[ $dbKey ];
		$dbDef = $dbSpec[ 'def' ];
		$dbDef[ 'table_prefix' ] = $con->getPluginPrefix( '_' );
		$handlerClass = $dbSpec[ 'handler_class' ];
		return ( new $handlerClass( $dbDef ) )->getTableSchema();
	}
}
