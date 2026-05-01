<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Database\CleanIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class CleanIpRulesTest extends ShieldIntegrationTestCase {

	public function test_manual_block_removes_matching_crowdsec_rule() {
		$dbh = $this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.12.10';
		$now = Services::Request()->ts();
		$manualID = TestDataFactory::insertManualBlock( $ip );
		$crowdsecID = TestDataFactory::insertCrowdsecBlock( $ip, [
			'blocked_at'   => $now - HOUR_IN_SECONDS,
			'unblocked_at' => $now,
		] );

		( new CleanIpRules() )->manualOverrides_Crowdsec();

		$this->assertNotEmpty( $dbh->getQuerySelector()->byId( $manualID ) );
		$this->assertEmpty( $dbh->getQuerySelector()->byId( $crowdsecID ) );
	}

	public function test_manual_bypass_removes_matching_crowdsec_rule() {
		$dbh = $this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.12.11';
		$now = Services::Request()->ts();
		$bypassID = TestDataFactory::insertBypass( $ip );
		$crowdsecID = TestDataFactory::insertCrowdsecBlock( $ip, [
			'blocked_at'   => $now - HOUR_IN_SECONDS,
			'unblocked_at' => $now,
		] );

		( new CleanIpRules() )->manualOverrides_Crowdsec();

		$this->assertNotEmpty( $dbh->getQuerySelector()->byId( $bypassID ) );
		$this->assertEmpty( $dbh->getQuerySelector()->byId( $crowdsecID ) );
	}
}
