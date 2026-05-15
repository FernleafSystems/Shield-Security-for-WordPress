<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\IpRuleAddSubmit
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler as IpRulesHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class IpRuleAddSubmitActionIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->loginAsSecurityAdmin();
	}

	public function tear_down() {
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_ip_rule_add_submit_creates_manual_block_through_add_rule_path() :void {
		$ip = '10.0.30.10';
		$this->applyRequestForIp( '10.0.30.1' );
		$this->captureShieldEvents();

		$payload = $this->submitRuleAdd( $ip, IpRulesHandler::T_MANUAL_BLOCK );

		$this->assertSuccessfulActionPayload( $payload );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( 'ip_block_manual' ) );

		$this->resetIpCaches();
		$status = new IpRuleStatus( $ip );
		$this->assertTrue( $status->hasManualBlock() );
		$this->assertSame( IpRulesHandler::T_MANUAL_BLOCK, $status->getBlockType() );
	}

	public function test_ip_rule_add_submit_creates_manual_bypass_through_add_rule_path() :void {
		$ip = '10.0.30.11';
		$this->applyRequestForIp( '10.0.30.1' );
		$this->captureShieldEvents();

		$payload = $this->submitRuleAdd( $ip, IpRulesHandler::T_MANUAL_BYPASS );

		$this->assertSuccessfulActionPayload( $payload );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( 'ip_bypass_add' ) );

		$this->resetIpCaches();
		$status = new IpRuleStatus( $ip );
		$this->assertTrue( $status->isBypass() );
		$this->assertFalse( $status->isBlocked() );
	}

	public function test_ip_rule_add_submit_rejects_manual_block_for_current_ip() :void {
		$ip = '10.0.30.12';
		$this->applyRequestForIp( $ip );

		$payload = $this->submitRuleAdd( $ip, IpRulesHandler::T_MANUAL_BLOCK );

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertFalse( $payload[ 'success' ] );
		$this->resetIpCaches();
		$this->assertFalse( ( new IpRuleStatus( $ip ) )->hasRules() );
	}

	private function assertSuccessfulActionPayload( array $payload ) :void {
		$this->assertArrayHasKey( 'success', $payload );
		$this->assertArrayHasKey( 'page_reload', $payload );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertFalse( $payload[ 'page_reload' ] );
	}

	private function submitRuleAdd( string $ip, string $type ) :array {
		return ( new ActionProcessor() )->processAction( IpRuleAddSubmit::SLUG, [
			'form_data' => [
				'ip'      => $ip,
				'type'    => $type,
				'confirm' => 'Y',
				'label'   => 'contract-test',
			],
		] )->payload();
	}

	private function applyRequestForIp( string $ip ) :void {
		$this->resetIpCaches();
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-admin/admin-ajax.php',
			'REMOTE_ADDR'    => $ip,
		], [], [], [
			'is_security_admin' => true,
		] );
	}
}
