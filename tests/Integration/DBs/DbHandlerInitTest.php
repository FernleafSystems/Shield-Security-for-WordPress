<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Database\DbCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class DbHandlerInitTest extends ShieldIntegrationTestCase {

	/**
	 * Every handler listed in DbCon::MAP can be loaded and reports isReady().
	 * This is the one place where iterating the full list is justified:
	 * we're testing the DB initialisation mechanism, not counting tables.
	 */
	public function test_all_handlers_load_and_are_ready() {
		$con = $this->requireController();
		$failedKeys = [];

		foreach ( \array_keys( DbCon::MAP ) as $dbKey ) {
			try {
				$handler = $con->db_con->load( $dbKey );
				if ( empty( $handler ) || !$handler->isReady() ) {
					$failedKeys[] = $dbKey.' (not ready)';
				}
			}
			catch ( \Exception $e ) {
				$failedKeys[] = $dbKey.' ('.$e->getMessage().')';
			}
		}

		$this->assertEmpty( $failedKeys, 'These DB handlers failed to load or are not ready: '.\implode( ', ', $failedKeys ) );
	}

	/**
	 * Foreign-key tables (ip_rules -> ips, bot_signals -> ips) load their
	 * dependencies automatically.
	 */
	public function test_foreign_key_tables_resolve_dependencies() {
		$con = $this->requireController();

		// ip_rules depends on ips
		$ipRules = $con->db_con->load( 'ip_rules' );
		$this->assertNotEmpty( $ipRules );
		$this->assertTrue( $ipRules->isReady() );

		// bot_signals depends on ips
		$botSignals = $con->db_con->load( 'bot_signals' );
		$this->assertNotEmpty( $botSignals );
		$this->assertTrue( $botSignals->isReady() );

		// The parent ips table must also be ready
		$ips = $con->db_con->load( 'ips' );
		$this->assertNotEmpty( $ips );
		$this->assertTrue( $ips->isReady() );
	}
}
