<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler as IpRulesHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class IpRulesCrudTest extends ShieldIntegrationTestCase {

	public function test_insert_and_select_manual_block() {
		$dbh = $this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$id = TestDataFactory::insertManualBlock( '192.0.2.10' );
		$this->assertGreaterThan( 0, $id );

		$record = $dbh->getQuerySelector()->byId( $id );
		$this->assertNotEmpty( $record );
		$this->assertSame( IpRulesHandler::T_MANUAL_BLOCK, $record->type );
	}

	public function test_insert_and_select_manual_bypass() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$id = TestDataFactory::insertBypass( '192.0.2.11' );
		$dbh = $this->requireController()->db_con->ip_rules;

		$record = $dbh->getQuerySelector()->byId( $id );
		$this->assertNotEmpty( $record );
		$this->assertSame( IpRulesHandler::T_MANUAL_BYPASS, $record->type );
	}

	public function test_insert_and_select_auto_block() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$id = TestDataFactory::insertAutoBlock( '192.0.2.12' );
		$dbh = $this->requireController()->db_con->ip_rules;

		$record = $dbh->getQuerySelector()->byId( $id );
		$this->assertNotEmpty( $record );
		$this->assertSame( IpRulesHandler::T_AUTO_BLOCK, $record->type );
		$this->assertGreaterThan( 0, $record->blocked_at );
	}

	public function test_insert_and_select_crowdsec() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$id = TestDataFactory::insertCrowdsecBlock( '192.0.2.13' );
		$dbh = $this->requireController()->db_con->ip_rules;

		$record = $dbh->getQuerySelector()->byId( $id );
		$this->assertNotEmpty( $record );
		$this->assertSame( IpRulesHandler::T_CROWDSEC, $record->type );
	}

	public function test_update_offense_count() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$id = TestDataFactory::insertAutoBlock( '192.0.2.14', [ 'offenses' => 3 ] );
		$dbh = $this->requireController()->db_con->ip_rules;

		$record = $dbh->getQuerySelector()->byId( $id );
		$this->assertSame( 3, (int)$record->offenses );

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Update $updater */
		$updater = $dbh->getQueryUpdater();
		$updater->incrementTransgressions( $record, 2 );

		$reloaded = $dbh->getQuerySelector()->byId( $id );
		$this->assertSame( 5, (int)$reloaded->offenses );
	}

	public function test_delete_rule_by_id() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$id = TestDataFactory::insertManualBlock( '192.0.2.15' );
		$dbh = $this->requireController()->db_con->ip_rules;

		$this->assertNotEmpty( $dbh->getQuerySelector()->byId( $id ) );

		$dbh->getQueryDeleter()->deleteById( $id );

		$this->assertEmpty( $dbh->getQuerySelector()->byId( $id ) );
	}
}
