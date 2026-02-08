<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class MfaRecordsCrudTest extends ShieldIntegrationTestCase {

	public function test_insert_and_retrieve_mfa_record() {
		$this->requireDb( 'mfa' );

		$userId = self::factory()->user->create( [ 'role' => 'administrator' ] );

		$id = TestDataFactory::insertMfaRecord( $userId, 'email', [ 'secret' => 'abc123' ] );
		$this->assertGreaterThan( 0, $id );

		$dbh = $this->requireController()->db_con->mfa;
		$record = $dbh->getQuerySelector()->byId( $id );
		$this->assertNotEmpty( $record );
		$this->assertSame( $userId, (int)$record->user_id );
		$this->assertSame( 'email', $record->slug );
		$this->assertIsArray( $record->data );
		$this->assertSame( 'abc123', $record->data[ 'secret' ] ?? null );
	}

	public function test_select_by_user_id() {
		$this->requireDb( 'mfa' );

		$userId = self::factory()->user->create( [ 'role' => 'administrator' ] );

		TestDataFactory::insertMfaRecord( $userId, 'email' );
		TestDataFactory::insertMfaRecord( $userId, 'google_auth' );

		$dbh = $this->requireController()->db_con->mfa;
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops\Select $select */
		$select = $dbh->getQuerySelector();
		$records = $select->filterByUserID( $userId )->query();

		$this->assertCount( 2, $records );
	}

	public function test_delete_all_mfa_for_user() {
		$this->requireDb( 'mfa' );

		$userId = self::factory()->user->create( [ 'role' => 'editor' ] );

		TestDataFactory::insertMfaRecord( $userId, 'email' );
		TestDataFactory::insertMfaRecord( $userId, 'backup_codes' );

		$dbh = $this->requireController()->db_con->mfa;
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops\Delete $deleter */
		$deleter = $dbh->getQueryDeleter();
		$deleter->filterByUserID( $userId )->query();

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops\Select $select */
		$select = $dbh->getQuerySelector();
		$remaining = $select->filterByUserID( $userId )->query();

		$this->assertEmpty( $remaining );
	}
}
