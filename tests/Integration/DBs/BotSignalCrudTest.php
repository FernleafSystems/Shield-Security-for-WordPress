<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class BotSignalCrudTest extends ShieldIntegrationTestCase {

	public function test_insert_and_retrieve_signal() {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );

		$now = Services::Request()->ts();
		$id = TestDataFactory::insertBotSignal( '192.0.2.20', [
			'notbot_at' => $now,
			'auth_at'   => $now - 60,
		] );
		$this->assertGreaterThan( 0, $id );

		$dbh = $this->requireController()->db_con->bot_signals;
		$record = $dbh->getQuerySelector()->byId( $id );
		$this->assertNotEmpty( $record );
		$this->assertSame( $now, (int)$record->notbot_at );
		$this->assertSame( $now - 60, (int)$record->auth_at );
	}

	public function test_update_individual_signal_column() {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );

		$id = TestDataFactory::insertBotSignal( '192.0.2.21' );
		$dbh = $this->requireController()->db_con->bot_signals;

		$now = Services::Request()->ts();

		$dbh->getQueryUpdater()->updateById( $id, [ 'bt404_at' => $now ] );

		$record = $dbh->getQuerySelector()->byId( $id );
		$this->assertSame( $now, (int)$record->bt404_at );
	}

	public function test_multiple_signal_columns_update() {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );

		$now = Services::Request()->ts();
		$id = TestDataFactory::insertBotSignal( '192.0.2.22' );
		$dbh = $this->requireController()->db_con->bot_signals;

		$dbh->getQueryUpdater()->updateById( $id, [
			'btfake_at'       => $now,
			'btloginfail_at'  => $now - 30,
			'btxml_at'        => $now - 120,
		] );

		$record = $dbh->getQuerySelector()->byId( $id );
		$this->assertSame( $now, (int)$record->btfake_at );
		$this->assertSame( $now - 30, (int)$record->btloginfail_at );
		$this->assertSame( $now - 120, (int)$record->btxml_at );
	}
}
