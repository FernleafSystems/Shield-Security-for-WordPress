<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

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
}
