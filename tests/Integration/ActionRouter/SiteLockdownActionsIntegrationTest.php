<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	BlockdownDisableFormSubmit,
	BlockdownFormSubmit
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteLockdown\SiteBlockdownCfg;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class SiteLockdownActionsIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionSnapshot = [];

	public function set_up() {
		parent::set_up();

		$this->enablePremiumCapabilities( [ 'site_blockdown' ] );
		$this->requireDb( 'ips' );
		$this->requireDb( 'ip_rules' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'blockdown_cfg',
		] );
		$this->loginAsSecurityAdmin();
		$this->resetActionRequestState( '203.0.113.80' );
		$this->setBlockdownCfg();
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		\wp_set_current_user( 0 );

		parent::tear_down();
	}

	private function resetActionRequestState( string $ip ) :void {
		$this->resetIpCaches();

		$con = $this->requireController();
		$con->this_req->ip = $ip;
		$con->this_req->request_bypasses_all_restrictions = false;
		$con->this_req->request_subject_to_shield_restrictions = true;
		$con->this_req->is_ip_blocked = false;
		$con->this_req->wp_is_ajax = false;
	}

	private function setBlockdownCfg( array $overrides = [] ) :void {
		$this->requireController()->opts->optSet( 'blockdown_cfg', $this->blockdownCfg( $overrides ) );
	}

	private function blockdownCfg( array $overrides = [] ) :array {
		return \array_merge( [
			'activated_at' => 0,
			'activated_by' => '',
			'disabled_at'  => 0,
			'exclusions'   => [],
			'whitelist_me' => '',
		], $overrides );
	}

	private function currentBlockdownCfg() :SiteBlockdownCfg {
		return ( new SiteBlockdownCfg() )
			->applyFromArray( $this->requireController()->comps->opts_lookup->getBlockdownCfg() );
	}

	public function test_blockdown_form_submit_activates_lockdown_and_fires_event() :void {
		TestDataFactory::insertBypass( '203.0.113.200' );
		$this->resetIpCaches();
		$this->captureShieldEvents();

		$payload = ( new ActionProcessor() )->processAction( BlockdownFormSubmit::SLUG, [
			'form_data' => [
				'confirm'      => [ 'consequences', 'authority', 'access', 'cache' ],
				'whitelist_me' => 'N',
				'exclusions'   => [ 'wp-login.php' ],
			],
		] )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertTrue( (bool)( $payload[ 'page_reload' ] ?? false ) );

		$cfg = $this->currentBlockdownCfg();
		$this->assertTrue( $cfg->isLockdownActive() );
		$this->assertGreaterThan( 0, (int)$cfg->activated_at );
		$this->assertSame( 0, (int)$cfg->disabled_at );
		$this->assertNotSame( '', (string)$cfg->activated_by );
		$this->assertSame( [ 'wp-login.php' ], $cfg->exclusions );
		$this->assertSame( '', (string)$cfg->whitelist_me );

		$events = $this->getCapturedEventsByKey( 'site_blockdown_started' );
		$this->assertCount( 1, $events );
		$this->assertNotSame( '', (string)( $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'user_login' ] ?? '' ) );
	}

	public function test_blockdown_disable_form_submit_deactivates_lockdown_and_fires_event() :void {
		$activatedAt = Services::Request()->ts() - 60;
		$this->setBlockdownCfg( [
			'activated_at' => $activatedAt,
			'activated_by' => 'integration',
			'disabled_at'  => 0,
			'exclusions'   => [ 'wp-login.php' ],
		] );
		$this->captureShieldEvents();

		$payload = ( new ActionProcessor() )->processAction( BlockdownDisableFormSubmit::SLUG )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertTrue( (bool)( $payload[ 'page_reload' ] ?? false ) );

		$cfg = $this->currentBlockdownCfg();
		$this->assertFalse( $cfg->isLockdownActive() );
		$this->assertSame( $activatedAt, (int)$cfg->activated_at );
		$this->assertGreaterThanOrEqual( $activatedAt, (int)$cfg->disabled_at );
		$this->assertSame( [], $cfg->exclusions );
		$this->assertSame( '', (string)$cfg->whitelist_me );

		$events = $this->getCapturedEventsByKey( 'site_blockdown_ended' );
		$this->assertCount( 1, $events );
		$this->assertNotSame( '', (string)( $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'user_login' ] ?? '' ) );
	}
}
