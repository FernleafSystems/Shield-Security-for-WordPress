<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ActivityLogsCrudTest extends ShieldIntegrationTestCase {

	public function test_insert_and_retrieve_log_entry() {
		$this->requireDb( 'activity_logs' );

		$id = TestDataFactory::insertActivityLog( 'test_event', '10.10.10.10' );
		$this->assertGreaterThan( 0, $id );

		$dbh = $this->requireController()->db_con->activity_logs;
		$record = $dbh->getQuerySelector()->byId( $id );
		$this->assertNotEmpty( $record );
		$this->assertSame( 'test_event', $record->event_slug );
		$this->assertSame( '10.10.10.10', $record->ip );
	}

	public function test_delete_log_entry() {
		$this->requireDb( 'activity_logs' );

		$id = TestDataFactory::insertActivityLog( 'deletable_event' );
		$dbh = $this->requireController()->db_con->activity_logs;

		$this->assertNotEmpty( $dbh->getQuerySelector()->byId( $id ) );

		$dbh->getQueryDeleter()->deleteById( $id );

		$this->assertEmpty( $dbh->getQuerySelector()->byId( $id ) );
	}

	public function test_select_by_event_slug() {
		$this->requireDb( 'activity_logs' );

		TestDataFactory::insertActivityLog( 'ip_blocked', '10.10.10.11' );
		TestDataFactory::insertActivityLog( 'ip_blocked', '10.10.10.12' );
		TestDataFactory::insertActivityLog( 'user_login', '10.10.10.13' );

		$dbh = $this->requireController()->db_con->activity_logs;
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\Ops\Select $select */
		$select = $dbh->getQuerySelector();
		$results = $select->filterByEvent( 'ip_blocked' )->query();

		$this->assertCount( 2, $results );

		$ips = \array_map( fn( $r ) => $r->ip, $results );
		$this->assertContains( '10.10.10.11', $ips, 'Filter should return the first ip_blocked record' );
		$this->assertContains( '10.10.10.12', $ips, 'Filter should return the second ip_blocked record' );
	}
}
