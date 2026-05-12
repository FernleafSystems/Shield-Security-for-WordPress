<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler as IpRulesHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions\Scopes\V2\ProcessIPs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class CrowdSecProcessIPsTest extends ShieldIntegrationTestCase {

	private function loadCrowdsecRulesForIp( string $ip ) :array {
		$loader = ( new LoadIpRules() )->setIP( $ip );
		$loader->wheres = [
			sprintf( "`ir`.`type`='%s'", IpRulesHandler::T_CROWDSEC )
		];
		return \array_values( $loader->select() );
	}

	public function test_v2_import_skips_manual_override_ips() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$manualBlockIP = '10.0.13.10';
		$manualBypassIP = '10.0.13.11';
		$newCrowdsecIP = '10.0.13.12';
		TestDataFactory::insertManualBlock( $manualBlockIP );
		TestDataFactory::insertBypass( $manualBypassIP );

		$count = ( new class extends ProcessIPs {
			public function exposeProcessNew( array $newDecisions ) :int {
				$this->newDecisions = $newDecisions;
				return $this->processNew();
			}
		} )->exposeProcessNew( [
			$manualBlockIP => [ 'expires_at' => Services::Request()->ts() + WEEK_IN_SECONDS ],
			$manualBypassIP => [ 'expires_at' => Services::Request()->ts() + WEEK_IN_SECONDS ],
			$newCrowdsecIP => [ 'expires_at' => Services::Request()->ts() + WEEK_IN_SECONDS ],
		] );

		$this->assertSame( 1, $count );
		$this->assertCount( 0, $this->loadCrowdsecRulesForIp( $manualBlockIP ) );
		$this->assertCount( 0, $this->loadCrowdsecRulesForIp( $manualBypassIP ) );
		$this->assertCount( 1, $this->loadCrowdsecRulesForIp( $newCrowdsecIP ) );
	}
}
