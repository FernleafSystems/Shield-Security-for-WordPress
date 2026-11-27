<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\UserMeta\MetaRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\MfaRecordsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Many users' Shield records and two-factor records can be loaded together, a batch to a query, after which asking
 * for any one of them costs no query. Loading them is looking: a user with no record isn't given one, and gets one
 * only when something is written to it.
 */
class UserRecordsPreloadIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->requireDb( 'user_meta' );
		$this->requireDb( 'mfa' );
	}

	public function test_user_records_load_a_batch_to_a_query_and_are_then_free_to_ask_for() :void {
		// One more than a batch holds.
		$users = $this->createUsers( 51 );
		$metas = $this->requireController()->user_metas;
		$table = $this->requireController()->db_con->user_meta->getTable();
		$metas->for( $users[ 3 ] )->record->last_login_at = 1700000000;
		RuntimeTestState::forgetLoadedUserRecords();

		$this->assertSame( 2, $this->queriesOf( $table, static fn() => $metas->preload( $users ) ) );
		$this->assertSame( 0, $this->queriesOf( $table, static function () use ( $metas, $users ) {
			foreach ( $users as $user ) {
				$metas->for( $user );
			}
		} ) );
		// What was loaded is each user's stored record, with what it holds.
		foreach ( $users as $user ) {
			$this->assertSame( $user->ID, $metas->for( $user )->record->user_id );
			$this->assertGreaterThan( 0, $metas->for( $user )->record->id );
		}
		$this->assertSame( 1700000000, $metas->for( $users[ 3 ] )->record->last_login_at );
	}

	public function test_a_user_without_a_record_is_not_given_one_for_being_looked_at() :void {
		$user = $this->createUsers( 1 )[ 0 ];
		$this->forgetRecordOf( $user->ID );
		$metas = $this->requireController()->user_metas;

		$metas->preload( [ $user ] );
		$this->assertSame( 0, $metas->for( $user )->record->last_login_at );
		$this->assertNull( ( new MetaRecords() )->loadMeta( $user->ID, false ) );
	}

	public function test_a_user_without_a_record_gets_one_when_something_is_written_to_it() :void {
		$user = $this->createUsers( 1 )[ 0 ];
		$this->forgetRecordOf( $user->ID );
		$metas = $this->requireController()->user_metas;
		$metas->preload( [ $user ] );

		$metas->for( $user )->record->last_login_at = 1700000000;

		$stored = ( new MetaRecords() )->loadMeta( $user->ID, false );
		$this->assertNotNull( $stored );
		$this->assertSame( 1700000000, $stored->last_login_at );
		// What's held in memory is now that stored record, so later writes go to it too.
		$metas->for( $user )->record->hard_suspended_at = 1700000001;
		$this->assertSame( 1700000001, ( new MetaRecords() )->loadMeta( $user->ID, false )->hard_suspended_at );
	}

	public function test_two_factor_records_load_together_and_are_then_free_to_ask_for() :void {
		$users = $this->createUsers( 6 );
		$handler = new MfaRecordsHandler();
		$table = $this->requireController()->db_con->mfa->getTable();
		$recordId = TestDataFactory::insertMfaRecord( $users[ 2 ]->ID, 'ga' );
		RuntimeTestState::forgetLoadedUserRecords();

		$this->assertSame( 1, $this->queriesOf( $table, static fn() => $handler->preload( $users ) ) );
		$this->assertSame( 0, $this->queriesOf( $table, static function () use ( $handler, $users ) {
			foreach ( $users as $user ) {
				$handler->loadForUser( $user );
			}
		} ) );
		// The user who has a record gets it, and the others are known to have none.
		$this->assertSame( [ $recordId ], \array_map(
			static fn( $record ) :int => (int)$record->id,
			$handler->loadForUser( $users[ 2 ] )
		) );
		$this->assertSame( [], $handler->loadForUser( $users[ 0 ] ) );
	}

	/**
	 * @return list<\WP_User> users whose records are stored but no longer held in memory
	 */
	private function createUsers( int $count ) :array {
		$users = \array_map(
			static fn( int $id ) :\WP_User => \get_user_by( 'id', $id ),
			self::factory()->user->create_many( $count )
		);
		RuntimeTestState::forgetLoadedUserRecords();
		return $users;
	}

	/**
	 * Leaves the user as one who was on the site before Shield was: no record of them, stored or held in memory.
	 */
	private function forgetRecordOf( int $userId ) :void {
		$this->requireController()->db_con->user_meta->getQueryDeleter()->filterByUser( $userId )->query();
		RuntimeTestState::forgetLoadedUserRecords();
	}

	/**
	 * @return int how many queries the work makes of the table
	 */
	private function queriesOf( string $table, callable $work ) :int {
		$queries = 0;
		$count = static function ( $sql ) use ( $table, &$queries ) {
			$queries += \strpos( (string)$sql, $table ) === false ? 0 : 1;
			return $sql;
		};
		\add_filter( 'query', $count );
		$work();
		\remove_filter( 'query', $count );
		return $queries;
	}
}
