<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ActivityLogsCrudTest extends ShieldIntegrationTestCase {

	public function test_insert_and_retrieve_log_entry() {
		$this->requireDb( 'activity_logs' );
		$this->requireDb( 'req_logs' );
		$this->requireDb( 'ips' );

		$id = TestDataFactory::insertActivityLog( 'test_event', '10.10.10.10' );
		$this->assertGreaterThan( 0, $id );

		$dbh = $this->requireController()->db_con->activity_logs;
		$record = $dbh->getQuerySelector()->byId( $id );
		$this->assertNotEmpty( $record );
		$this->assertSame( 'test_event', $record->event_slug );
		$this->assertGreaterThan( 0, $record->req_ref,
			'Activity log should be linked to a request log record' );
	}

	public function test_delete_log_entry() {
		$this->requireDb( 'activity_logs' );
		$this->requireDb( 'req_logs' );
		$this->requireDb( 'ips' );

		$id = TestDataFactory::insertActivityLog( 'deletable_event' );
		$dbh = $this->requireController()->db_con->activity_logs;

		$this->assertNotEmpty( $dbh->getQuerySelector()->byId( $id ) );

		$dbh->getQueryDeleter()->deleteById( $id );

		$this->assertEmpty( $dbh->getQuerySelector()->byId( $id ) );
	}

	public function test_select_by_event_slug() {
		$this->requireDb( 'activity_logs' );
		$this->requireDb( 'req_logs' );
		$this->requireDb( 'ips' );

		TestDataFactory::insertActivityLog( 'ip_blocked', '10.10.10.11' );
		TestDataFactory::insertActivityLog( 'ip_blocked', '10.10.10.12' );
		TestDataFactory::insertActivityLog( 'user_login', '10.10.10.13' );

		$dbh = $this->requireController()->db_con->activity_logs;
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\Ops\Select $select */
		$select = $dbh->getQuerySelector();
		$results = $select->filterByEvent( 'ip_blocked' )->queryWithResult();

		$this->assertCount( 2, $results );

		foreach ( $results as $r ) {
			$this->assertSame( 'ip_blocked', $r->event_slug,
				'All filtered results should have the ip_blocked event slug' );
		}
	}
}
